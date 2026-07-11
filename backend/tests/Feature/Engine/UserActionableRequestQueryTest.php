<?php

declare(strict_types=1);

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Models\User;
use App\Services\Workflow\StagePermissionResolver;
use App\Services\Workflow\UserActionableRequestQuery;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase D0.1: the extracted UserActionableRequestQuery is the single source of a
 * user's actionable (EXECUTE-stage) work. This proves:
 *
 *  - the service's actionable count and preview IDs equal the /my-queue endpoint's
 *    record set (the five-surface parity invariant, verified by IDs), and
 *  - extraction preserved the pre-existing /my-queue behaviour for each EXECUTE
 *    role on the published V2 (Director→FINAL, Support→SUPPORT, SWIFT→FX,
 *    Reviewer→INTERNAL).
 */
class UserActionableRequestQueryTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the demo governance/users/workflow, then publish the corrected V2. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
    }

    private function userByEmail(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /** @return list<int> record IDs the /my-queue endpoint returns for this user */
    private function myQueueIds(User $user): array
    {
        $ids = [];
        $page = 1;
        do {
            $body = $this->actingAs($user)
                ->getJson("/api/v1/engine-requests/my-queue?per_page=100&page={$page}")
                ->assertOk()
                ->json();
            foreach ($body['data'] as $row) {
                $ids[] = (int) $row['id'];
            }
            $last = (int) ($body['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $last);

        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, string> role label => seeded email, for the four
     *                               single-EXECUTE-stage workflow roles under V2
     */
    public static function executeRoleProvider(): array
    {
        return [
            'director (FINAL)' => ['director@cby.gov.ye'],
            'support (SUPPORT)' => ['support1@cby.gov.ye'],
            'swift (FX)' => ['swift@ybrd.com.ye'],
            'reviewer (INTERNAL)' => ['reviewer@ybrd.com.ye'],
        ];
    }

    #[DataProvider('executeRoleProvider')]
    public function test_actionable_ids_equal_my_queue_ids_for_execute_roles(string $email): void
    {
        $user = $this->userByEmail($email);

        $service = app(UserActionableRequestQuery::class);
        $request = Request::create('/api/v1/dashboard/work', 'GET');

        $actionableIds = $service->actionablePreview($user, $request, 100)
            ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $actionableCount = $service->actionableCount($user, $request);

        $myQueueIds = $this->myQueueIds($user);

        $this->assertSame($myQueueIds, $actionableIds, "Actionable IDs must equal /my-queue IDs for {$email}.");
        $this->assertSame(count($myQueueIds), $actionableCount, "Actionable count must equal /my-queue total for {$email}.");
    }

    public function test_actionable_stage_ids_are_the_execute_scoped_stages(): void
    {
        $director = $this->userByEmail('director@cby.gov.ye');

        $service = app(UserActionableRequestQuery::class);
        $resolver = app(StagePermissionResolver::class);
        $director->loadMissing('roles');

        $this->assertEqualsCanonicalizing(
            array_map('intval', $resolver->accessibleStageIds($director, StageAccessLevel::EXECUTE)),
            array_map('intval', $service->actionableStageIds($director)),
        );
    }
}
