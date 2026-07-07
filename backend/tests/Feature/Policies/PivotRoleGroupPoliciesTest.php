<?php

namespace Tests\Feature\Policies;

use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class PivotRoleGroupPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, BankSeeder::class, UserSeeder::class]);
    }

    public function test_fx_stage_viewer_can_download_customs_declaration_via_pivot(): void
    {
        $director = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeEngineRequest(null);
        $declaration = $this->makeEngineDeclaration($request);
        $this->grantFxView($request, $director);

        $this->assertTrue($director->can('download', $declaration));
    }

    public function test_data_entry_cannot_download_customs_declaration_for_other_bank(): void
    {
        $entry = $this->firstUserWithRole(UserRole::DATA_ENTRY);
        $otherBank = Bank::query()->where('id', '!=', $entry->bank_id)->firstOrFail();
        $otherBankRequest = $this->makeEngineRequest($otherBank->id);
        $declaration = $this->makeEngineDeclaration($otherBankRequest);
        $this->grantFxView($otherBankRequest, $entry);

        $this->assertFalse($entry->can('download', $declaration));
    }

    public function test_cby_admin_can_create_bank_via_pivot(): void
    {
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);

        $this->assertTrue($admin->can('create', Bank::class));
    }

    public function test_bank_admin_cannot_create_bank(): void
    {
        $bankAdmin = $this->firstUserWithRole(UserRole::BANK_ADMIN);

        $this->assertFalse($bankAdmin->can('create', Bank::class));
    }

    public function test_cby_admin_can_view_any_user(): void
    {
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);

        $this->assertTrue($admin->can('viewAny', User::class));
    }

    public function test_bank_admin_can_manage_own_bank_data_entry_user(): void
    {
        $bankAdmin = $this->firstUserWithRole(UserRole::BANK_ADMIN);
        $entry = User::query()
            ->withUserRole(UserRole::DATA_ENTRY)
            ->where('bank_id', $bankAdmin->bank_id)
            ->firstOrFail();

        $this->assertTrue($bankAdmin->can('update', $entry));
    }

    // ── Helpers (mirrors Tests\Unit\Policies\CustomsDeclarationPolicyTest) ──

    private function grantFxView(EngineRequest $engineRequest, User $user): WorkflowStage
    {
        $fxStage = WorkflowStage::create([
            'workflow_version_id' => $engineRequest->workflow_version_id,
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
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'FX View',
            'version' => 1,
        ]);

        return $fxStage;
    }

    private function makeEngineRequest(?int $bankId): EngineRequest
    {
        $request = EngineWorkflowFactory::seedRequestOnClaimStage();
        DB::table('engine_requests')
            ->where('id', $request->id)
            ->update(['bank_id' => $bankId]);

        return $request->fresh();
    }

    private function makeEngineDeclaration(EngineRequest $engineRequest): CustomsDeclaration
    {
        $id = DB::table('customs_declarations')->insertGetId([
            'engine_request_id' => $engineRequest->id,
            'declaration_number' => 'FX-TEST-'.uniqid(),
            'issued_by' => $engineRequest->created_by,
            'issued_at' => now()->toDateTimeString(),
            'pdf_path' => 'fx-confirmation/test.pdf',
            'metadata' => json_encode([]),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return CustomsDeclaration::findOrFail($id);
    }
}
