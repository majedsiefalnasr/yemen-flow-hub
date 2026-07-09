<?php

namespace Tests\Feature\Customs;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Customs\FxConfirmationAuthorizationService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards API-001: FxConfirmationAuthorizationService::requestInScope() was
 * changed from a per-row `whereKey()->forUser()->exists()` query to an
 * in-memory bank-scope evaluation. These tests pin the scoping semantics so
 * the optimization cannot silently widen or narrow visibility.
 */
class FxConfirmationRequestScopeTest extends TestCase
{
    use RefreshDatabase;

    private FxConfirmationAuthorizationService $service;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->service = app(FxConfirmationAuthorizationService::class);

        $def = WorkflowDefinition::create(['code' => 'FX_SCOPE_WF', 'name' => 'FX Scope WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
    }

    private function bank(string $code): Bank
    {
        $org = Organization::where('code', 'commercial_banks')->firstOrFail();

        return Bank::create(['name' => "Bank {$code}", 'code' => $code, 'is_active' => true, 'organization_id' => $org->id]);
    }

    private function user(string $orgCode, ?int $bankId, string $roleCode): User
    {
        $org = Organization::where('code', $orgCode)->firstOrFail();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@scope.test',
            'password' => bcrypt('pass'),
            'bank_id' => $bankId,
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        return $user->fresh(['roles']);
    }

    private function requestForBank(int $bankId, User $creator): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'ENG-2026-'.uniqid(),
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bankId,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_banking_sector_user_is_in_scope_only_for_their_own_bank(): void
    {
        $bankA = $this->bank('BSA');
        $bankB = $this->bank('BSB');
        $user = $this->user('commercial_banks', $bankA->id, 'intake');

        $own = $this->requestForBank($bankA->id, $user);
        $other = $this->requestForBank($bankB->id, $user);

        $this->assertTrue($this->service->requestInScope($user, $own));
        $this->assertFalse($this->service->requestInScope($user, $other));
    }

    public function test_national_committee_user_is_in_scope_for_any_bank(): void
    {
        $bankA = $this->bank('NCA');
        $ncUser = $this->user('national_committee', null, 'support');
        $bankUser = $this->user('commercial_banks', $bankA->id, 'intake');

        $request = $this->requestForBank($bankA->id, $bankUser);

        $this->assertTrue($this->service->requestInScope($ncUser, $request));
    }

    public function test_banking_sector_user_without_a_bank_is_in_scope_for_nothing(): void
    {
        $bankA = $this->bank('NBA');
        $noBankUser = $this->user('commercial_banks', null, 'intake');
        $creator = $this->user('commercial_banks', $bankA->id, 'intake');

        $request = $this->requestForBank($bankA->id, $creator);

        $this->assertFalse($this->service->requestInScope($noBankUser, $request));
    }

    public function test_system_admin_is_in_scope_for_any_bank(): void
    {
        $bankA = $this->bank('SAA');
        $admin = $this->user('system_administration', null, 'system_admin');
        $creator = $this->user('commercial_banks', $bankA->id, 'intake');

        $request = $this->requestForBank($bankA->id, $creator);

        $this->assertTrue($this->service->requestInScope($admin, $request));
    }

    public function test_scope_result_matches_the_for_user_query_it_replaces(): void
    {
        $bankA = $this->bank('MATCHA');
        $bankB = $this->bank('MATCHB');
        $user = $this->user('commercial_banks', $bankA->id, 'intake');

        foreach ([$bankA->id, $bankB->id] as $bankId) {
            $request = $this->requestForBank($bankId, $user);

            // The query the in-memory path replaces.
            $queryResult = EngineRequest::query()->whereKey($request->id)->forUser($user)->exists();

            $this->assertSame(
                $queryResult,
                $this->service->requestInScope($user, $request),
                "in-memory scope must match forUser() for bank {$bankId}",
            );
        }
    }
}
