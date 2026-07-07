<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use App\Services\Workflow\EngineClaimService;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class PivotServiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_cby_admin_can_save_system_settings(): void
    {
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);

        $this->app->make(SystemSettingsService::class)
            ->saveSection($admin, 'general', ['app_name' => 'Test']);

        $this->assertTrue(true);
    }

    public function test_non_admin_cannot_save_system_settings(): void
    {
        $entry = $this->firstUserWithRole(UserRole::DATA_ENTRY);

        $this->expectException(AuthorizationException::class);
        $this->app->make(SystemSettingsService::class)
            ->saveSection($entry, 'general', ['app_name' => 'Test']);
    }

    public function test_cby_admin_can_force_release_claim_held_by_another_user(): void
    {
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $support = $this->firstUserWithRole(UserRole::SUPPORT_COMMITTEE);

        $claimService = $this->app->make(EngineClaimService::class);
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        $claimService->claim($request, $support);

        $claimService->release($request->fresh(), $admin);

        $this->assertNull($request->fresh()->claimed_by);
    }
}
