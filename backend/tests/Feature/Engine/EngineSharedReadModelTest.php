<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Support\EngineRequestReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngineSharedReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_for_scopes_bank_users_to_their_bank_and_cby_roles_to_all_banks(): void
    {
        $workflow = $this->workflowWithStages(['CREATE']);
        $bankA = $this->bank('A');
        $bankB = $this->bank('B');
        $creator = User::factory()->create(['bank_id' => $bankA->id]);
        $bankUser = User::factory()->create([
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $bankA->id,
        ]);
        $cbyUser = User::factory()->create([
            'role' => UserRole::SUPPORT_COMMITTEE->value,
            'bank_id' => null,
        ]);

        $bankARequest = $this->engineRequest($workflow, 'CREATE', $bankA, $creator);
        $bankBRequest = $this->engineRequest($workflow, 'CREATE', $bankB, $creator);

        $this->assertSame(
            [$bankARequest->id],
            EngineRequestReadModel::queryFor($bankUser)->pluck('engine_requests.id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$bankARequest->id, $bankBRequest->id],
            EngineRequestReadModel::queryFor($cbyUser)->pluck('engine_requests.id')->all(),
        );
    }

    public function test_bucket_mapping_filters_engine_requests_by_stage_and_status(): void
    {
        $workflow = $this->workflowWithStages([
            'CREATE',
            'INTERNAL',
            'SUPPORT',
            'EXEC',
            'FX',
            'FX_CONFIRM',
            'FINAL',
            'CLOSED',
        ]);
        $bank = $this->bank('A');
        $creator = User::factory()->create(['bank_id' => $bank->id]);

        $requestsByStage = [];
        foreach (array_keys($workflow['stages']) as $stageCode) {
            $requestsByStage[$stageCode] = $this->engineRequest($workflow, $stageCode, $bank, $creator);
        }

        $this->assertSame(
            [$requestsByStage['INTERNAL']->id],
            $this->bucketedIds($creator, 'pending_bank_review'),
        );
        $this->assertSame(
            [$requestsByStage['SUPPORT']->id],
            $this->bucketedIds($creator, 'support_queue'),
        );
        $this->assertSame(
            [$requestsByStage['FX_CONFIRM']->id],
            $this->bucketedIds($creator, 'fx_confirmation_pending'),
        );
        $this->assertSame(
            [$requestsByStage['CLOSED']->id],
            $this->bucketedIds($creator, 'completed'),
        );
    }

    public function test_resource_collection_exposes_legacy_reference_number_and_engine_reference(): void
    {
        $workflow = $this->workflowWithStages(['CREATE']);
        $bank = $this->bank('A');
        $creator = User::factory()->create(['bank_id' => $bank->id]);
        $request = $this->engineRequest($workflow, 'CREATE', $bank, $creator, [
            'reference' => 'ENG-2026-000001',
        ]);

        $items = EngineRequestReadModel::resourceCollection(
            EngineRequestReadModel::queryFor($creator)->get(),
        );

        $this->assertCount(1, $items);
        $this->assertSame('ENG-2026-000001', $items[0]['reference']);
        $this->assertSame('ENG-2026-000001', $items[0]['reference_number']);
        $this->assertSame('ENG-2026-000001', EngineRequestReadModel::reference($request));
        $this->assertSame('ENG-99', EngineRequestReadModel::reference(null, 99));
        $this->assertNull(EngineRequestReadModel::reference(null));
    }

    /**
     * @return array{version: WorkflowVersion, stages: array<string, WorkflowStage>}
     */
    private function workflowWithStages(array $stageCodes): array
    {
        $definition = WorkflowDefinition::query()->create([
            'code' => 'READ_MODEL_'.Str::random(8),
            'name' => 'Read Model Workflow',
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

        $stages = [];
        foreach ($stageCodes as $index => $stageCode) {
            $stages[$stageCode] = WorkflowStage::query()->create([
                'workflow_version_id' => $version->id,
                'code' => $stageCode,
                'name' => Str::headline(Str::lower($stageCode)),
                'sort_order' => $index + 1,
                'is_initial' => $index === 0,
                'is_final' => $stageCode === 'CLOSED',
                'version' => 1,
            ]);
        }

        return ['version' => $version, 'stages' => $stages];
    }

    private function bank(string $suffix): Bank
    {
        return Bank::query()->create([
            'name' => "Read Model Bank {$suffix}",
            'code' => "RMB{$suffix}",
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{version: WorkflowVersion, stages: array<string, WorkflowStage>}  $workflow
     */
    private function engineRequest(
        array $workflow,
        string $stageCode,
        Bank $bank,
        User $creator,
        array $overrides = [],
    ): EngineRequest {
        $merchant = Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => "Merchant {$stageCode}",
            'tax_number' => 'TX-'.Str::random(10),
            'created_by' => $creator->id,
        ]);

        return EngineRequest::query()->create([
            'workflow_version_id' => $workflow['version']->id,
            'current_stage_id' => $workflow['stages'][$stageCode]->id,
            'reference' => 'ENG-'.$stageCode.'-'.Str::random(8),
            'status' => $stageCode === 'CLOSED' ? 'CLOSED' : 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'merchant_id' => $merchant->id,
            'data' => ['stage' => $stageCode],
            'version' => 1,
            ...$overrides,
        ]);
    }

    private function bucketedIds(User $user, string $bucket): array
    {
        return EngineRequestReadModel::queryFor($user)
            ->where(EngineRequestReadModel::bucket($bucket))
            ->pluck('engine_requests.id')
            ->all();
    }
}
