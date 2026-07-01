<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $bankUserA;

    private User $bankUserB;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    private Bank $bankA;

    private Bank $bankB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $this->bankA = Bank::create(['name' => 'Search Bank A', 'code' => 'SBA', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $this->bankB = Bank::create(['name' => 'Search Bank B', 'code' => 'SBB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->bankUserA = User::create([
            'name' => 'User A',
            'email' => 'usera@search.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bankA->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->bankUserA->teams()->attach($entryTeam);
        $this->bankUserA->roles()->attach($entryRole);

        $this->bankUserB = User::create([
            'name' => 'User B',
            'email' => 'userb@search.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bankB->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->bankUserB->teams()->attach($entryTeam);
        $this->bankUserB->roles()->attach($entryRole);

        $def = WorkflowDefinition::create(['code' => 'SEARCH_WF', 'name' => 'Search WF', 'is_active' => true]);
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

        StagePermission::create([
            'stage_id' => $this->stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Entry View',
            'version' => 1,
        ]);
    }

    private function makeRequest(User $creator, Bank $bank, string $reference, string $invoiceNumber = 'INV-001'): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'invoice_number' => $invoiceNumber,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_search_by_reference_number_returns_matching_request(): void
    {
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-000001', 'INV-SEARCH-001');
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-000002', 'INV-SEARCH-002');

        $response = $this->actingAs($this->bankUserA)
            ->getJson('/api/v1/engine-requests?search=ENG-2026-000001');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertEquals('ENG-2026-000001', $response->json('data.0.reference'));
    }

    public function test_search_by_invoice_number_returns_matching_request(): void
    {
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-000010', 'INV-FINDME-777');
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-000011', 'INV-OTHER-888');

        $response = $this->actingAs($this->bankUserA)
            ->getJson('/api/v1/engine-requests?search=INV-FINDME-777');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_search_returns_no_results_for_nonexistent_term(): void
    {
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-000020', 'INV-PRESENT');

        $response = $this->actingAs($this->bankUserA)
            ->getJson('/api/v1/engine-requests?search=NOPE-NOT-HERE');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_bank_user_only_sees_their_own_bank_requests(): void
    {
        // Bank A has 2 requests; Bank B has 1
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-SCOPE-01', 'INV-A-1');
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-SCOPE-02', 'INV-A-2');
        $this->makeRequest($this->bankUserB, $this->bankB, 'ENG-2026-SCOPE-03', 'INV-B-1');

        $responseA = $this->actingAs($this->bankUserA)->getJson('/api/v1/engine-requests');
        $responseA->assertOk()->assertJsonPath('meta.total', 2);

        $responseB = $this->actingAs($this->bankUserB)->getJson('/api/v1/engine-requests');
        $responseB->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_search_across_all_bank_requests_is_scoped(): void
    {
        $this->makeRequest($this->bankUserA, $this->bankA, 'ENG-2026-999001', 'INV-SCOPE-SHARED');
        $this->makeRequest($this->bankUserB, $this->bankB, 'ENG-2026-999002', 'INV-SCOPE-SHARED');

        // Bank A user sees only their own match, not Bank B's
        $response = $this->actingAs($this->bankUserA)
            ->getJson('/api/v1/engine-requests?search=INV-SCOPE-SHARED');

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertEquals('ENG-2026-999001', $response->json('data.0.reference'));
    }
}
