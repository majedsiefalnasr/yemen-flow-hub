<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineReferenceNumberTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $overrides = []): EngineRequest
    {
        $def = WorkflowDefinition::create([
            'code' => 'REF_WF_'.uniqid(),
            'name' => 'Ref Test Workflow',
            'is_active' => true,
        ]);

        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $uid = uniqid();
        $bank = Bank::create(['name' => 'Ref Bank '.$uid, 'code' => 'RFB'.$uid, 'is_active' => true]);
        $merchant = Merchant::create(['bank_id' => $bank->id, 'name' => 'Ref Merchant', 'tax_number' => 'TX'.uniqid(), 'status' => 'ACTIVE']);
        $creator = User::factory()->create();

        return EngineRequest::create(array_merge([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'ENG-'.date('Y').'-'.str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'merchant_id' => $merchant->id,
            'data' => [],
            'version' => 1,
        ], $overrides));
    }

    public function test_engine_request_has_reference_number_on_creation(): void
    {
        $request = $this->makeRequest();

        $this->assertNotNull($request->reference);
        $this->assertNotEmpty($request->reference);
    }

    public function test_reference_number_matches_expected_format(): void
    {
        $request = $this->makeRequest(['reference' => 'ENG-'.date('Y').'-000001']);

        // Format: ENG-{YEAR}-{6-digit-sequence}
        $this->assertMatchesRegularExpression('/^ENG-\d{4}-\d{6}$/', $request->reference);
    }

    public function test_reference_numbers_are_unique_across_requests(): void
    {
        $a = $this->makeRequest(['reference' => 'ENG-'.date('Y').'-000001']);
        $b = $this->makeRequest(['reference' => 'ENG-'.date('Y').'-000002']);

        $this->assertNotEquals($a->reference, $b->reference);
    }

    public function test_reference_number_is_stored_in_database(): void
    {
        $request = $this->makeRequest(['reference' => 'ENG-'.date('Y').'-999001']);

        $this->assertDatabaseHas('engine_requests', [
            'id' => $request->id,
            'reference' => 'ENG-'.date('Y').'-999001',
        ]);
    }

    public function test_reference_unique_constraint_is_enforced_at_db_level(): void
    {
        $this->makeRequest(['reference' => 'ENG-'.date('Y').'-000001']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeRequest(['reference' => 'ENG-'.date('Y').'-000001']);
    }
}
