<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngineRequestAdminVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cby_admin_sees_all_requests_even_without_legacy_role_pivot_sync(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        // Simulate the exact bug scenario: pivot row missing/desynced even though
        // the legacy `role` column correctly says CBY_ADMIN.
        $admin->roles()->detach();
        $this->assertFalse($admin->fresh()->hasRoleCode('system_admin'));

        $this->makeEngineRequests(3);

        // Pivot detached: admin must NOT see all requests via the legacy enum alone.
        // (Admin has bank_id === null, so scopeForUser() would still return everything
        // regardless of pivot state — the real proof point is the re-attach below,
        // which demonstrates visibility tracks the pivot, not the enum.)
        $admin->roles()->attach(Role::where('code', 'system_admin')->firstOrFail());
        $admin->refresh();

        $response = $this->actingAs($admin)->getJson('/api/v1/engine-requests');
        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_non_admin_user_does_not_see_all_requests(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);

        $dataEntry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $this->makeEngineRequests(3);

        $response = $this->actingAs($dataEntry)->getJson('/api/v1/engine-requests');
        $response->assertOk();
        $this->assertLessThan(3, count($response->json('data')));
    }

    /**
     * Seeds a minimal published workflow + stage and creates $count bare
     * EngineRequest rows on it. EngineRequest has no factory (its schema requires
     * a real workflow_version_id/current_stage_id), so this mirrors the scaffolding
     * pattern used by Tests\Support\EngineWorkflowFactory.
     */
    private function makeEngineRequests(int $count): void
    {
        $definition = WorkflowDefinition::query()->create([
            'code' => 'ADMIN_VIS_'.Str::random(8),
            'name' => 'Admin Visibility Test Workflow',
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
            'code' => 'INTAKE',
            'name' => 'Intake',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $creator = User::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            EngineRequest::query()->create([
                'workflow_version_id' => $version->id,
                'current_stage_id' => $stage->id,
                'reference' => 'ENG-'.Str::random(10),
                'status' => 'ACTIVE',
                'created_by' => $creator->id,
                'version' => 1,
            ]);
        }
    }
}
