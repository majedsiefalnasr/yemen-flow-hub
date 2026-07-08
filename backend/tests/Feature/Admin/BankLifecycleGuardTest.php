<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class BankLifecycleGuardTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    public function test_unused_bank_can_be_deactivated_and_deleted(): void
    {
        $admin = $this->admin();
        $bank = $this->bank(['code' => 'UNUSED']);

        $this->actingAs($admin)
            ->postJson("/api/v1/banks/{$bank->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');

        $this->actingAs($admin)
            ->deleteJson("/api/v1/banks/{$bank->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('banks', ['id' => $bank->id]);
    }

    public function test_bank_with_user_is_blocked_from_lifecycle_removal(): void
    {
        // WP-9 (fd136b1f) intentionally split bank suspend vs delete semantics:
        // deactivate/suspend is reversible and no longer blocked by usage, only
        // hard delete is (BankController::isUsed() gates destroy(), not
        // deactivate()). Assert current, documented behavior for both actions.
        $admin = $this->admin();
        $bank = $this->bank(['code' => 'USEDUSER']);
        $this->bankUser($bank);

        $this->actingAs($admin)
            ->postJson("/api/v1/banks/{$bank->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');

        $this->actingAs($admin)
            ->deleteJson("/api/v1/banks/{$bank->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BANK_IN_USE');
    }

    public function test_bank_with_soft_deleted_merchant_is_blocked(): void
    {
        $admin = $this->admin();
        $bank = $this->bank(['code' => 'USEDMERCH']);
        Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Archived importer',
            'commercial_register' => 'CR-ARCHIVED',
            'tax_number' => 'TAX-ARCHIVED',
        ])->delete();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/banks/{$bank->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BANK_IN_USE');
    }

    public function test_bank_with_engine_request_is_blocked(): void
    {
        $admin = $this->admin();
        $bank = $this->bank(['code' => 'USEDREQ']);
        $creator = $this->bankUser($bank);
        $stage = $this->stage();

        EngineRequest::query()->create([
            'workflow_version_id' => $stage->workflow_version_id,
            'current_stage_id' => $stage->id,
            'reference' => 'REQ-USED-BANK',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/banks/{$bank->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BANK_IN_USE');
    }

    private function admin(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'wp0-admin@example.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    private function bank(array $attributes = []): Bank
    {
        $organization = \App\Models\Organization::query()->where('code', 'commercial_banks')->firstOrFail();

        return Bank::query()->create(array_merge([
            'name' => 'WP0 Test Bank',
            'code' => 'WP0BANK',
            'organization_id' => $organization->id,
            'status' => 'ACTIVE',
            'is_active' => true,
        ], $attributes));
    }

    private function bankUser(Bank $bank): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Bank User',
            'email' => 'bank-user-'.$bank->id.'@example.test',
            'password' => Hash::make('password'),
            'bank_id' => $bank->id,
            'is_active' => true,
        ]), UserRole::DATA_ENTRY);
    }

    private function stage(): WorkflowStage
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'wp0-bank-flow', 'name' => 'WP0 Bank Flow']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'DRAFT',
        ]);

        return WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'intake',
            'name' => 'Intake',
        ]);
    }
}
