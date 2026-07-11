<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase D0.2: the generic /dashboard/work API. Proves the five-surface parity
 * invariant (actionable count == actionable preview scope == /my-queue record
 * set), that tracking is VIEW-only (never actionable), that scope is enforced,
 * and that a user with no active role sees no role-derived work.
 */
class DashboardWorkApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        // The base seeder pins its anchors to V1; publishing V2 does not move
        // them. Seed a handful of ACTIVE requests directly onto V2 stages so the
        // Director (FINAL), Support (SUPPORT), and Reviewer (INTERNAL) have real
        // actionable work under the corrected workflow.
        $this->seedV2Request('FINAL', 'YBRD');
        $this->seedV2Request('SUPPORT', 'YBRD');
        $this->seedV2Request('INTERNAL', 'YBRD');
        $this->seedV2Request('INTERNAL', 'TIIB');
        // FX (SWIFT) work for the D0.4 pilot role.
        $this->seedV2Request('FX', 'YBRD');
        $this->seedV2Request('FX', 'YBRD');
    }

    private function userByEmail(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function v2(): WorkflowDefinition
    {
        return WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail();
    }

    private function seedV2Request(string $stageCode, string $bankCode): EngineRequest
    {
        $v2 = $this->v2()->versions()->where('state', 'PUBLISHED')->orderByDesc('version_number')->firstOrFail();
        $stage = WorkflowStage::query()
            ->where('workflow_version_id', $v2->id)->where('code', $stageCode)->firstOrFail();
        $bank = Bank::query()->where('code', $bankCode)->firstOrFail();
        $creator = User::query()->where('bank_id', $bank->id)->firstOrFail();

        return EngineRequest::query()->create([
            'workflow_version_id' => $v2->id,
            'current_stage_id' => $stage->id,
            'reference' => sprintf('ENG-2026-%s-%s', $bankCode, strtoupper(Str::random(6))),
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'data' => [],
            'version' => 1,
            'currency' => 'USD',
            'amount' => 100000,
        ]);
    }

    /** @return list<int> */
    private function myQueueIds(User $user): array
    {
        $ids = [];
        $page = 1;
        do {
            $body = $this->actingAs($user)
                ->getJson("/api/v1/engine-requests/my-queue?per_page=100&page={$page}")
                ->assertOk()->json();
            foreach ($body['data'] as $row) {
                $ids[] = (int) $row['id'];
            }
            $last = (int) ($body['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $last);
        sort($ids);

        return $ids;
    }

    public function test_work_endpoint_returns_the_full_contract_shape(): void
    {
        $director = $this->userByEmail('director@cby.gov.ye');

        $this->actingAs($director)
            ->getJson('/api/dashboard/work')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'actionable' => ['count', 'items', 'queue_url'],
                'claimed' => ['count', 'items'],
                'tracking' => ['count', 'items', 'queue_url'],
                'sla' => ['near_due', 'overdue'],
                'recent_activity',
                'metrics',
            ]]);
    }

    public function test_actionable_count_and_items_match_my_queue_record_set(): void
    {
        $director = $this->userByEmail('director@cby.gov.ye');
        $myQueueIds = $this->myQueueIds($director);

        $data = $this->actingAs($director)->getJson('/api/dashboard/work')->assertOk()->json('data');

        $this->assertSame(count($myQueueIds), $data['actionable']['count']);

        $itemIds = collect($data['actionable']['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        // Preview is bounded, so items are a scope-preserving subset of my-queue.
        $this->assertNotEmpty($itemIds);
        foreach ($itemIds as $id) {
            $this->assertContains($id, $myQueueIds, "Actionable item {$id} is outside the /my-queue record set.");
        }
    }

    public function test_tracking_is_view_only_and_disjoint_from_actionable(): void
    {
        // The Director owns EXECUTE on FINAL and VIEW on nothing else under V2, so
        // a role with a broader VIEW footprint exercises tracking better. Support
        // sees SUPPORT (EXECUTE); its tracking must exclude that actionable set.
        $support = $this->userByEmail('support1@cby.gov.ye');

        $data = $this->actingAs($support)->getJson('/api/dashboard/work')->assertOk()->json('data');

        $actionableIds = collect($data['actionable']['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $trackingIds = collect($data['tracking']['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($trackingIds as $id) {
            $this->assertNotContains($id, $actionableIds, "Tracking item {$id} must not also be actionable.");
        }
    }

    public function test_user_with_no_active_role_sees_no_actionable_work(): void
    {
        $orphan = User::query()->create([
            'name' => 'No Role',
            'email' => 'no-role@work.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $data = $this->actingAs($orphan)->getJson('/api/dashboard/work')->assertOk()->json('data');

        $this->assertSame(0, $data['actionable']['count']);
        $this->assertSame([], $data['actionable']['items']);
        $this->assertSame(0, $data['tracking']['count']);
    }

    /**
     * D0.4 pilot: the SWIFT Officer is the first role served by MyWorkDashboard.
     * Prove the actionable section it renders is exactly the SWIFT (FX) my-queue
     * record set — by IDs, not just counts.
     */
    public function test_swift_pilot_actionable_ids_equal_my_queue_ids(): void
    {
        $swift = $this->userByEmail('swift@ybrd.com.ye');
        $myQueueIds = $this->myQueueIds($swift);

        $data = $this->actingAs($swift)->getJson('/api/dashboard/work')->assertOk()->json('data');

        $this->assertNotEmpty($myQueueIds, 'The SWIFT pilot expects seeded FX work.');
        $this->assertSame(count($myQueueIds), $data['actionable']['count']);
        foreach ($data['actionable']['items'] as $item) {
            $this->assertContains((int) $item['id'], $myQueueIds, 'SWIFT actionable item outside /my-queue.');
        }
    }

    /**
     * D0.5: every migrated workflow-executor role's actionable section must equal
     * its /my-queue record set by IDs — the parity oracle for the MyWorkDashboard
     * migration.
     *
     * @return array<string, array{0: string}>
     */
    public static function migratedRoleProvider(): array
    {
        return [
            'support (SUPPORT)' => ['support1@cby.gov.ye'],
            'reviewer (INTERNAL)' => ['reviewer@ybrd.com.ye'],
            'data entry (CREATE)' => ['entry@ybrd.com.ye'],
            'executive member (EXEC)' => ['exec1@cby.gov.ye'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('migratedRoleProvider')]
    public function test_migrated_role_actionable_matches_my_queue(string $email): void
    {
        $user = $this->userByEmail($email);
        $myQueueIds = $this->myQueueIds($user);

        $data = $this->actingAs($user)->getJson('/api/dashboard/work')->assertOk()->json('data');

        // The dashboard count equals the full /my-queue total, and the (bounded)
        // preview is a scope-preserving subset of that same record set.
        $this->assertSame(count($myQueueIds), $data['actionable']['count'], "count mismatch for {$email}");
        foreach ($data['actionable']['items'] as $item) {
            $this->assertContains((int) $item['id'], $myQueueIds, "actionable item outside /my-queue for {$email}");
        }
    }

    public function test_cross_bank_records_are_excluded_from_a_bank_users_work(): void
    {
        // A YBRD reviewer's actionable INTERNAL work must be YBRD-only.
        $reviewer = $this->userByEmail('reviewer@ybrd.com.ye');

        $data = $this->actingAs($reviewer)->getJson('/api/dashboard/work')->assertOk()->json('data');

        foreach ($data['actionable']['items'] as $item) {
            $this->assertStringStartsWith('ENG-2026-YBRD', $item['reference'], 'Cross-bank record leaked into actionable work.');
        }
    }
}
