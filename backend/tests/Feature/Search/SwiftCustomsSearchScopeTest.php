<?php

namespace Tests\Feature\Search;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class SwiftCustomsSearchScopeTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->stage = $this->stage();
    }

    public function test_swift_officer_searches_customs_declarations_across_banks(): void
    {
        $bankA = $this->bank('A');
        $bankB = $this->bank('B');
        $this->declaration($bankA, 'FX-WP0-A');
        $this->declaration($bankB, 'FX-WP0-B');
        $swift = $this->user(UserRole::SWIFT_OFFICER, null, 'swift@example.test');

        $response = $this->actingAs($swift)
            ->getJson('/api/search?q=FX-WP0')
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['FX-WP0-A', 'FX-WP0-B'],
            collect($response->json('data.customs'))->pluck('declaration_number')->all(),
        );
    }

    public function test_bank_roles_remain_bank_scoped_for_customs_search(): void
    {
        $bankA = $this->bank('OWN');
        $bankB = $this->bank('OTHER');
        $this->declaration($bankA, 'FX-WP0-OWN');
        $this->declaration($bankB, 'FX-WP0-OTHER');
        $bankAdmin = $this->user(UserRole::BANK_ADMIN, $bankA, 'bank-admin@example.test');

        $response = $this->actingAs($bankAdmin)
            ->getJson('/api/search?q=FX-WP0')
            ->assertOk();

        $this->assertSame(
            ['FX-WP0-OWN'],
            collect($response->json('data.customs'))->pluck('declaration_number')->all(),
        );
    }

    private function declaration(Bank $bank, string $number): CustomsDeclaration
    {
        $creator = $this->user(UserRole::DATA_ENTRY, $bank, 'creator-'.$number.'@example.test');
        $request = EngineRequest::query()->create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REQ-'.$number,
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'status' => 'ACTIVE',
        ]);

        return CustomsDeclaration::query()->create([
            'engine_request_id' => $request->id,
            'declaration_number' => $number,
            'issued_by' => $creator->id,
            'issued_at' => now(),
            'pdf_path' => 'customs/'.$number.'.pdf',
        ]);
    }

    private function user(UserRole $role, ?Bank $bank, string $email): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]), $role);
    }

    private function bank(string $suffix): Bank
    {
        return Bank::query()->create([
            'name' => 'Bank '.$suffix,
            'code' => 'B'.$suffix,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
    }

    private function stage(): WorkflowStage
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'wp0-search', 'name' => 'WP0 Search']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'DRAFT',
        ]);

        return WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'fx',
            'name' => 'FX',
        ]);
    }
}
