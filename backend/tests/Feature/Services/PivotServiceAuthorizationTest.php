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
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->app->make(SystemSettingsService::class)
            ->saveSection($admin, 'general', ['app_name' => 'Test']);

        $this->assertTrue(true);
    }

    public function test_non_admin_cannot_save_system_settings(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $this->expectException(AuthorizationException::class);
        $this->app->make(SystemSettingsService::class)
            ->saveSection($entry, 'general', ['app_name' => 'Test']);
    }

    public function test_cby_admin_can_force_release_claim_held_by_another_user(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();

        $claimService = $this->app->make(EngineClaimService::class);
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        $claimService->claim($request, $support);

        $claimService->release($request->fresh(), $admin);

        $this->assertNull($request->fresh()->claimed_by);
    }
}
