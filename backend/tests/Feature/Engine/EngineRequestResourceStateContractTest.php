<?php

declare(strict_types=1);

namespace Tests\Feature\Engine;

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
 * Phase D Step 2/3: the canonical M6 state contract additions on
 * EngineRequestResource — `runtime_status`, `current_stage.semantic_role`,
 * and request-level `final_outcome`. No schema change; both new fields read
 * from the already-cast, already-loaded `currentStage` relation.
 */
class EngineRequestResourceStateContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
    }

    private function seedV2Request(string $stageCode): EngineRequest
    {
        $v2 = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail()
            ->versions()->where('state', 'PUBLISHED')->orderByDesc('version_number')->firstOrFail();
        $stage = WorkflowStage::query()
            ->where('workflow_version_id', $v2->id)->where('code', $stageCode)->firstOrFail();
        $bank = Bank::query()->where('code', 'YBRD')->firstOrFail();
        $creator = User::query()->where('bank_id', $bank->id)->firstOrFail();

        return EngineRequest::query()->create([
            'workflow_version_id' => $v2->id,
            'current_stage_id' => $stage->id,
            'reference' => sprintf('ENG-2026-YBRD-%s', strtoupper(Str::random(6))),
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'data' => [],
            'version' => 1,
            'currency' => 'USD',
            'amount' => 100000,
        ]);
    }

    public function test_active_non_final_request_exposes_runtime_status_and_semantic_role_but_no_outcome(): void
    {
        $request = $this->seedV2Request('SUPPORT');
        $admin = User::query()->where('email', 'admin@cby.gov.ye')->firstOrFail();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame('ACTIVE', $data['runtime_status']);
        $this->assertSame($data['status'], $data['runtime_status']);
        // The freshly published V2 has semantic_role unset on every stage (WP-4
        // metadata was never backfilled for this designer-published version) —
        // the field must still be present (compatibility contract) and null,
        // never absent or a crash.
        $this->assertArrayHasKey('semantic_role', $data['current_stage']);
        $this->assertNull($data['current_stage']['semantic_role']);
        $this->assertArrayNotHasKey('final_outcome', $data);
    }

    public function test_closed_completed_stage_request_exposes_final_outcome(): void
    {
        $request = $this->seedV2Request('CLOSED_COMPLETED');
        $admin = User::query()->where('email', 'admin@cby.gov.ye')->firstOrFail();

        $data = $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk()->json('data');

        $this->assertTrue($data['current_stage']['is_final']);
        $this->assertArrayHasKey('final_outcome', $data);
        $this->assertSame('COMPLETED', $data['final_outcome']);
    }

    public function test_closed_rejected_stage_request_exposes_rejected_outcome(): void
    {
        $request = $this->seedV2Request('CLOSED_REJECTED');
        $admin = User::query()->where('email', 'admin@cby.gov.ye')->firstOrFail();

        $data = $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk()->json('data');

        $this->assertTrue($data['current_stage']['is_final']);
        $this->assertSame('REJECTED', $data['final_outcome']);
    }
}
