<?php

namespace Tests\Feature\Requests;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
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

    public function test_committee_director_can_upload_fx_confirmation(): void
    {
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        $this->grantViewToUser($request, $director);

        $response = $this->actingAs($director)->postJson(
            "/api/v1/engine-requests/{$request->id}/fx-confirmation-signed",
            []
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_executive_member_cannot_upload_fx_confirmation(): void
    {
        $executive = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->firstOrFail();
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        $this->grantViewToUser($request, $executive);

        $response = $this->actingAs($executive)->postJson(
            "/api/v1/engine-requests/{$request->id}/fx-confirmation-signed",
            []
        );

        $response->assertForbidden();
    }

    public function test_cby_admin_can_update_admin_settings(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/app_name', [
            'value' => 'Test',
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_non_admin_cannot_update_admin_settings(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $response = $this->actingAs($entry)->putJson('/api/admin/settings/app_name', [
            'value' => 'Test',
        ]);

        $response->assertForbidden();
    }
}
