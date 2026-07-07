<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

/**
 * The `show` payload must expose `can_execute` so the detail UI can gate its
 * action panel and edit mode. The flag is assignment-based: it reflects EXECUTE
 * on the current stage, and does NOT bypass for system admins (admins widen
 * visibility, never execute authority).
 */
class EngineRequestCanExecuteTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_reports_can_execute_true_for_stage_executor(): void
    {
        ['request' => $request, 'executor' => $executor] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($executor)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.can_execute', true);
    }

    public function test_show_reports_can_execute_false_for_view_only_user(): void
    {
        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();

        // A user who may VIEW the stage but holds no EXECUTE row: a genuine
        // viewer, which the old UI wrongly treated as an actor.
        $viewer = User::factory()->create();
        StagePermission::create([
            'stage_id' => $request->current_stage_id,
            'user_id' => $viewer->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Viewer',
            'version' => 1,
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.can_execute', false);
    }

    public function test_show_reports_can_execute_false_for_unassigned_system_admin(): void
    {
        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();

        // System admin sees every request (policy view === true) but is not
        // assigned to execute this stage, so must not be offered stage actions.
        $admin = User::factory()->create([]);
        $admin->roles()->attach(Role::query()->where('code', 'system_admin')->firstOrFail()->id);

        $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.can_execute', false);
    }
}
