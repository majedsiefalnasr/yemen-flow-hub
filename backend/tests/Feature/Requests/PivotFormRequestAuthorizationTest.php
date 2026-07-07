<?php

namespace Tests\Feature\Requests;

use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class PivotFormRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function grantViewToUser(EngineRequest $request, User $user): void
    {
        StagePermission::create([
            'stage_id' => $request->current_stage_id,
            'user_id' => $user->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'View',
            'version' => 1,
        ]);
    }

    public function test_fx_stage_executor_can_upload_fx_confirmation(): void
    {
        $executor = User::query()->where('email', 'fxconfirm@cby.gov.ye')->firstOrFail();
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        $this->grantFxExecute($request, $executor);

        $response = $this->actingAs($executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/fx-confirmation-signed",
            []
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_executive_member_without_fx_execute_cannot_upload_fx_confirmation(): void
    {
        $executive = $this->firstUserWithRole(UserRole::EXECUTIVE_MEMBER);
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        $this->grantViewToUser($request, $executive);

        $response = $this->actingAs($executive)->postJson(
            "/api/v1/engine-requests/{$request->id}/fx-confirmation-signed",
            []
        );

        $response->assertForbidden();
    }

    private function grantFxExecute(EngineRequest $request, User $user): void
    {
        $fxStage = WorkflowStage::create([
            'workflow_version_id' => $request->workflow_version_id,
            'code' => 'FX_CONFIRM',
            'name' => 'FX Confirmation',
            'sort_order' => 99,
            'is_initial' => false,
            'is_final' => false,
            'semantic_role' => StageSemanticRole::FX_CONFIRMATION,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $fxStage->id,
            'user_id' => $user->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'FX Execute',
            'version' => 1,
        ]);
    }

    public function test_cby_admin_can_update_admin_settings(): void
    {
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/app_name', [
            'value' => 'Test',
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_non_admin_cannot_update_admin_settings(): void
    {
        $entry = $this->firstUserWithRole(UserRole::DATA_ENTRY);

        $response = $this->actingAs($entry)->putJson('/api/admin/settings/app_name', [
            'value' => 'Test',
        ]);

        $response->assertForbidden();
    }
}
