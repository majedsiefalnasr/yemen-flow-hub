<?php

namespace Tests\Feature\V1;

use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, UserSeeder::class]);
    }

    public function test_user_can_list_own_report_presets(): void
    {
        $user = User::factory()->create([
            'user_preferences' => [
                'report_presets' => [
                    ['id' => 'p1', 'name' => 'Q1', 'filter' => ['from' => '2026-01-01'], 'createdAt' => '2026-01-01'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/report-presets')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'p1');
    }

    public function test_user_can_save_and_delete_preset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/report-presets', [
                'id' => 'abc',
                'name' => 'My filter',
                'filter' => ['bank' => 1],
                'createdAt' => '2026-07-07',
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', 'abc');

        $this->actingAs($user)
            ->deleteJson('/api/v1/report-presets/abc')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_save_preset_validation_returns_rich_error_envelope(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/report-presets', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'fields', 'request_id'],
            ]);
    }
}
