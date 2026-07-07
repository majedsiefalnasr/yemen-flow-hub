<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StageIsBoundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private WorkflowVersion $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'bound-check', 'name' => 'Bound Check']);
        $this->draft = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();
    }

    public function test_stage_is_bound_checks_engine_requests_not_import_requests(): void
    {
        $stage = $this->draft->stages()->create(['code' => 'review', 'name' => 'Review'])->refresh();
        $bank = Bank::query()->firstOrFail();

        DB::table('engine_requests')->insert([
            'workflow_version_id' => $this->draft->id,
            'current_stage_id' => $stage->id,
            'reference' => 'YFH-TEST-BOUND-001',
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'bank_id' => $bank->id,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_STAGE_BOUND');

        $this->assertDatabaseHas('workflow_stages', ['id' => $stage->id]);
    }
}
