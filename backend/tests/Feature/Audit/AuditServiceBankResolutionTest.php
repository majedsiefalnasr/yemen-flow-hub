<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards SEC-002: AuditService::log() must derive bank_id from the subject
 * at write time so future audit rows are bank-scopable without a follow-up
 * backfill. Resolution rule: if the subject itself carries a bank_id
 * attribute, use it; if the subject IS a Bank, use its own id; otherwise
 * null (a CBY-only entity like Organization/Role/settings has no bank, and
 * must not borrow the acting user's bank — CBY staff are not bank-scoped).
 */
class AuditServiceBankResolutionTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;

    private Organization $bankOrg;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->auditService = app(AuditService::class);
        $this->bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'Resolve Bank', 'code' => 'RSV', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);
    }

    public function test_resolves_bank_id_from_a_bank_subject_itself(): void
    {
        $log = $this->auditService->log(AuditAction::BANK_UPDATED, null, $this->bank);

        $this->assertSame($this->bank->id, $log->bank_id);
    }

    public function test_resolves_bank_id_from_a_user_subject(): void
    {
        $user = User::create([
            'name' => 'Subject User',
            'email' => 'subject@resolve.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bank->id,
            'organization_id' => $this->bankOrg->id,
            'is_active' => true,
        ]);

        $log = $this->auditService->log(AuditAction::USER_UPDATED, null, $user);

        $this->assertSame($this->bank->id, $log->bank_id);
    }

    public function test_resolves_bank_id_from_a_merchant_subject(): void
    {
        $merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Resolve Merchant',
            'tax_number' => '555666777',
            'status' => 'ACTIVE',
        ]);

        $log = $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, null, $merchant);

        $this->assertSame($this->bank->id, $log->bank_id);
    }

    public function test_resolves_bank_id_from_an_engine_request_subject(): void
    {
        $def = WorkflowDefinition::create(['code' => 'RESOLVE_WF', 'name' => 'Resolve WF', 'is_active' => true]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);
        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
        $creator = User::create([
            'name' => 'Request Creator',
            'email' => 'creator@resolve.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bank->id,
            'organization_id' => $this->bankOrg->id,
            'is_active' => true,
        ]);
        $engineRequest = EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'RESOLVE-1',
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
        ]);

        $log = $this->auditService->log(AuditAction::REQUEST_CREATED, null, $engineRequest);

        $this->assertSame($this->bank->id, $log->bank_id);
    }

    public function test_null_for_subjects_with_no_bank_concept(): void
    {
        // Organization is a CBY-side governance entity, no bank_id column.
        $log = $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, null, $this->bankOrg);

        $this->assertNull($log->bank_id);
    }

    public function test_null_when_no_subject_is_given_regardless_of_actor_bank(): void
    {
        // A CBY staff actor (or no actor) with a null subject (settings change,
        // report export) must not borrow the actor's bank -- there is none to
        // borrow correctly, and guessing would misattribute the row.
        $bankUser = User::create([
            'name' => 'Acting Bank User',
            'email' => 'actor@resolve.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bank->id,
            'organization_id' => $this->bankOrg->id,
            'is_active' => true,
        ]);

        $log = $this->auditService->log(AuditAction::SETTINGS_UPDATED, $bankUser, null);

        $this->assertNull($log->bank_id);
    }
}
