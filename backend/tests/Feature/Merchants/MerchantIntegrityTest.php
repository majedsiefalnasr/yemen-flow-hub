<?php

namespace Tests\Feature\Merchants;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $bankAdmin;

    private User $cbyadmin;

    /** @var array{version: WorkflowVersion, stages: array<string, WorkflowStage>} */
    private array $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ScreenPermissionSeeder::class);

        $this->bank = Bank::query()->create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true]);
        $this->otherBank = Bank::query()->create(['name' => 'Bank B', 'code' => 'BKB', 'is_active' => true]);
        $this->bankAdmin = User::query()->create([
            'name' => 'Bank Admin',
            'email' => 'ba@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_ADMIN->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $bankAdminRole = Role::query()->where('code', 'bank_admin')->firstOrFail();
        $this->bankAdmin->roles()->attach($bankAdminRole->id);

        $this->cbyadmin = User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'cby@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => null,
            'is_active' => true,
        ]);
        // cbyadmin needs merchants:MANAGE to reach MerchantController::update()'s
        // authorization gate at all before the immutability guard inside it can
        // fire (test_bank_change_blocked_after_first_request below). No
        // non-bank-user governance role is granted merchants:MANAGE by
        // ScreenPermissionSeeder (system_admin is deliberately denied it), so
        // this attaches bank_admin's governance Role for its screen_permissions
        // grant only -- isBankUser() still reads false from the legacy
        // CBY_ADMIN enum above, so this does not turn cbyadmin into a
        // bank-scoped actor for policy purposes.
        $this->cbyadmin->roles()->attach($bankAdminRole->id);

        $this->workflow = $this->makeWorkflow();
    }

    private function makeMerchant(array $overrides = []): Merchant
    {
        return Merchant::query()->create(array_merge([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => 'TX-'.uniqid(),
            'status' => 'ACTIVE',
            'version' => 1,
            'created_by' => $this->bankAdmin->id,
        ], $overrides));
    }

    private function makeWorkflow(): array
    {
        $definition = WorkflowDefinition::query()->create([
            'code' => 'MERCHANT_TEST_'.Str::random(6),
            'name' => 'Merchant Test Workflow',
            'is_active' => true,
            'version' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'CREATE',
            'name' => 'Create',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        return ['version' => $version, 'stages' => ['CREATE' => $stage]];
    }

    private function createEngineRequest(Merchant $merchant, string $status = 'ACTIVE'): void
    {
        EngineRequest::query()->create([
            'workflow_version_id' => $this->workflow['version']->id,
            'current_stage_id' => $this->workflow['stages']['CREATE']->id,
            'reference' => 'ENG-'.Str::random(8),
            'status' => $status,
            'bank_id' => $merchant->bank_id,
            'merchant_id' => $merchant->id,
            'created_by' => $this->bankAdmin->id,
            'data' => [],
            'version' => 1,
        ]);
    }

    // ─── Tax Number Uniqueness (AC1) ─────────────────────────────────────────

    public function test_duplicate_tax_number_on_create_returns_business_error(): void
    {
        $this->makeMerchant(['tax_number' => 'TX-DUPE-001']);

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Duplicate',
            'tax_number' => 'TX-DUPE-001',
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_TAX_NUMBER_EXISTS');
    }

    public function test_duplicate_tax_number_on_update_returns_business_error(): void
    {
        $this->makeMerchant(['tax_number' => 'TX-TAKEN-001']);
        $merchant = $this->makeMerchant(['tax_number' => 'TX-MINE-001']);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'tax_number' => 'TX-TAKEN-001',
            'version' => 1,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_TAX_NUMBER_EXISTS');
    }

    public function test_same_tax_number_on_own_record_succeeds(): void
    {
        $merchant = $this->makeMerchant(['tax_number' => 'TX-SELF-001']);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'tax_number' => 'TX-SELF-001',
            'name' => 'Updated Name',
            'version' => 1,
        ])->assertOk();
    }

    // ─── Active Request Suspend Guard (AC2) ──────────────────────────────────

    public function test_cannot_suspend_merchant_with_active_requests(): void
    {
        $merchant = $this->makeMerchant();
        $this->createEngineRequest($merchant, 'ACTIVE');

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'status' => 'SUSPENDED',
            'version' => 1,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_HAS_ACTIVE_REQUESTS');
    }

    public function test_can_suspend_merchant_with_only_terminal_requests(): void
    {
        $merchant = $this->makeMerchant();
        $this->createEngineRequest($merchant, 'CLOSED');
        $this->createEngineRequest($merchant, 'REJECTED');

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'status' => 'SUSPENDED',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');
    }

    public function test_can_suspend_merchant_with_no_requests(): void
    {
        $merchant = $this->makeMerchant();

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'status' => 'SUSPENDED',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');
    }

    // ─── Bank Immutability Guard (AC3) ───────────────────────────────────────

    public function test_bank_change_blocked_after_first_request(): void
    {
        $merchant = $this->makeMerchant();
        $this->createEngineRequest($merchant, 'CLOSED');

        $this->actingAs($this->cbyadmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'bank_id' => $this->otherBank->id,
            'version' => 1,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_BANK_IMMUTABLE');
    }

    // ─── Out of Scope Guard (AC4) ───────────────────────────────────────────

    public function test_bank_admin_cannot_view_other_bank_merchant(): void
    {
        $merchant = $this->makeMerchant([
            'bank_id' => $this->otherBank->id,
            'created_by' => $this->cbyadmin->id,
        ]);

        $this->actingAs($this->bankAdmin)
            ->getJson("/api/v1/merchants/{$merchant->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MERCHANT_OUT_OF_SCOPE');
    }

    // ─── Audit on Integrity Blocks ──────────────────────────────────────────

    public function test_integrity_block_does_not_create_spurious_audit(): void
    {
        $this->makeMerchant(['tax_number' => 'TX-AUDIT-DUP']);

        $beforeCount = DB::table('audit_logs')->count();

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Duplicate',
            'tax_number' => 'TX-AUDIT-DUP',
        ])->assertConflict();

        $this->assertSame($beforeCount, DB::table('audit_logs')->count());
    }
}
