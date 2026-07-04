<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
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

    public function test_committee_director_can_download_customs_declaration_via_pivot(): void
    {
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();
        $request = $this->makeEngineRequest(null);
        $declaration = $this->makeEngineDeclaration($request);

        $this->assertTrue($director->can('download', $declaration));
    }

    public function test_data_entry_cannot_download_customs_declaration_for_other_bank(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();
        $otherBank = Bank::query()->where('id', '!=', $entry->bank_id)->firstOrFail();
        $otherBankRequest = $this->makeEngineRequest($otherBank->id);
        $declaration = $this->makeEngineDeclaration($otherBankRequest);

        $this->assertFalse($entry->can('download', $declaration));
    }

    public function test_cby_admin_can_create_bank_via_pivot(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->can('create', \App\Models\Bank::class));
    }

    public function test_bank_admin_cannot_create_bank(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $this->assertFalse($bankAdmin->can('create', \App\Models\Bank::class));
    }

    public function test_cby_admin_can_view_any_user(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->can('viewAny', User::class));
    }

    public function test_bank_admin_can_manage_own_bank_data_entry_user(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();
        $entry = User::query()
            ->where('role', UserRole::DATA_ENTRY->value)
            ->where('bank_id', $bankAdmin->bank_id)
            ->firstOrFail();

        $this->assertTrue($bankAdmin->can('update', $entry));
    }

    // ── Helpers (mirrors Tests\Unit\Policies\CustomsDeclarationPolicyTest) ──

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
