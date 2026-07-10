<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListEndpointUnionParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stageOne;

    private WorkflowStage $stageTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'List Union Bank', 'code' => 'LUB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->user = User::create([
            'name' => 'List Union User', 'email' => 'list-union@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->user->teams()->attach($team);
        $this->user->roles()->attach($role);

        $def = WorkflowDefinition::create(['code' => 'LIST_UNION_WF', 'name' => 'List Union WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->stageOne = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'L_STAGE_ONE', 'name' => 'Stage One',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
        ]);
        $this->stageTwo = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'L_STAGE_TWO', 'name' => 'Stage Two',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1,
        ]);

        foreach ([$this->stageOne, $this->stageTwo] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => StageAccessLevel::VIEW, 'display_label' => 'View', 'version' => 1,
            ]);
        }
    }

    private function makeRequest(WorkflowStage $stage, string $reference, \DateTimeInterface $createdAt): EngineRequest
    {
        // created_at/updated_at are not in EngineRequest::$fillable, so passing
        // them to create() is silently discarded and Eloquent's own timestamp
        // behavior stamps every row with the same "now" value -- forceFill()
        // after create() actually persists the intended fixture timestamp.
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
        ]);
        $request->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $request;
    }

    public function test_list_across_two_accessible_stages_orders_by_created_at_desc(): void
    {
        $oldest = $this->makeRequest($this->stageOne, 'ENG-L-OLD', now()->subDays(5));
        $newest = $this->makeRequest($this->stageTwo, 'ENG-L-NEW', now());
        $middle = $this->makeRequest($this->stageOne, 'ENG-L-MID', now()->subDays(2));

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();

        $this->assertSame([$newest->reference, $middle->reference, $oldest->reference], $refs);
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_list_pagination_meta_is_correct_across_two_stages(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageOne, "ENG-L-PAGE-{$i}", now()->subDays($i));
        }
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageTwo, "ENG-L-PAGE-T{$i}", now()->subDays($i + 10));
        }

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests?per_page=4');

        $response->assertOk();
        $this->assertSame(10, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertCount(4, $response->json('data'));
    }

    public function test_list_date_range_filter_still_applies_across_stage_branches(): void
    {
        $inRange = $this->makeRequest($this->stageOne, 'ENG-L-INRANGE', now()->subDays(1));
        $this->makeRequest($this->stageTwo, 'ENG-L-OUTOFRANGE', now()->subDays(30));

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests?created_from='.now()->subDays(3)->toDateString());

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();
        $this->assertSame([$inRange->reference], $refs);
    }
}
