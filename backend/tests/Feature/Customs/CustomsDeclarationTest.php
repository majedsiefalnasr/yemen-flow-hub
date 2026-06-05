<?php

namespace Tests\Feature\Customs;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\Permission;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Customs\CustomsService;
use App\Services\Workflow\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CustomsDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $director;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCustomsPermission();
        $this->bank = Bank::query()->create(['name' => 'بنك اليمن', 'code' => 'YCB', 'is_active' => true]);
        $this->director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
    }

    public function test_director_generates_customs_declaration_and_completes_request(): void
    {
        Storage::fake('local');
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_APPROVED);

        $this->uploadSignedFx($request);

        $response = $this->actingAs($this->director)
            ->postJson("/api/customs/{$request->id}/generate")
            ->assertCreated()
            ->assertJsonPath('data.request.reference_number', $request->reference_number)
            ->assertJsonPath('data.request.bank_name', 'بنك اليمن')
            ->assertJsonPath('data.issuer.id', $this->director->id);

        $declarationId = $response->json('data.id');
        $declarationNumber = $response->json('data.declaration_number');
        $freshRequest = $request->fresh();

        $this->assertSame(RequestStatus::COMPLETED, $freshRequest->status);
        $this->assertSame($declarationId, $freshRequest->customs_declaration_id);
        $this->assertNotNull($freshRequest->customs_issued_at);

        $this->assertDatabaseHas('customs_declarations', [
            'id' => $declarationId,
            'request_id' => $request->id,
            'issued_by' => $this->director->id,
        ]);
        Storage::disk('local')->assertExists("private/customs/{$request->id}/{$declarationNumber}.pdf");

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'issue_customs',
            'from_status' => RequestStatus::FX_CONFIRMATION_PENDING->value,
            'to_status' => RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
        ]);
        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'complete',
            'from_status' => RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            'to_status' => RequestStatus::COMPLETED->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $request->id,
            'user_id' => $this->director->id,
        ]);
    }

    public function test_generated_declaration_metadata_can_be_retrieved(): void
    {
        Storage::fake('local');
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_APPROVED);

        $this->uploadSignedFx($request);

        $declarationId = $this->actingAs($this->director)
            ->postJson("/api/customs/{$request->id}/generate")
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->director)
            ->getJson("/api/customs/{$declarationId}")
            ->assertOk()
            ->assertJsonPath('data.issuer.name', $this->director->name)
            ->assertJsonPath('data.request.reference_number', $request->reference_number)
            ->assertJsonPath('data.request.bank_name', 'بنك اليمن');
    }

    public function test_transaction_rolls_back_declaration_when_completion_fails(): void
    {
        Storage::fake('local');
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_APPROVED);
        $this->uploadSignedFx($request);

        $workflow = Mockery::mock(WorkflowService::class);
        $workflow->shouldReceive('transition')
            ->once()
            ->with(Mockery::type(ImportRequest::class), 'issue_customs', $this->director)
            ->andReturnUsing(fn (ImportRequest $request) => $request);
        $workflow->shouldReceive('transition')
            ->once()
            ->with(Mockery::type(ImportRequest::class), 'complete', $this->director)
            ->andThrow(new RuntimeException('completion failed'));

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldNotReceive('log');

        $service = new CustomsService($workflow, $audit);

        try {
            $service->generate($request, $this->director);
            $this->fail('Expected completion failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('completion failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('customs_declarations', 1);
        $this->assertDatabaseHas('customs_declarations', [
            'request_id' => $request->id,
            'declaration_number' => "PENDING-FX-{$request->id}",
            'pdf_path' => '',
        ]);
        $this->assertNull($request->fresh()->customs_declaration_id);
        $this->assertSame(RequestStatus::FX_CONFIRMATION_PENDING, $request->fresh()->status);
        Storage::disk('local')->assertMissing("private/customs/{$request->id}/CD-".now()->format('Y').'-000001.pdf');
    }

    public function test_signed_fx_upload_transitions_to_pending(): void
    {
        Storage::fake('local');
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_APPROVED);

        $this->uploadSignedFx($request);

        $this->assertSame(RequestStatus::FX_CONFIRMATION_PENDING, $request->fresh()->status);
        $this->assertDatabaseHas('customs_declarations', [
            'request_id' => $request->id,
            'declaration_number' => "PENDING-FX-{$request->id}",
            'signed_fx_doc_uploaded_by' => $this->director->id,
        ]);
    }

    public function test_customs_declaration_is_immutable(): void
    {
        $request = $this->makeRequest(RequestStatus::COMPLETED);
        $declaration = CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => 'CD-'.now()->format('Y').'-000001',
            'issued_by' => $this->director->id,
            'issued_at' => now(),
            'pdf_path' => "customs/{$request->id}/CD-".now()->format('Y').'-000001.pdf',
        ]);

        $this->expectException(\LogicException::class);

        $declaration->update(['pdf_path' => 'customs/replaced.pdf']);
    }

    private function seedCustomsPermission(): void
    {
        $permission = Permission::query()->create([
            'slug' => 'customs.issue',
            'name_ar' => 'إصدار وثيقة تأكيد المصارفة الخارجية',
            'name_en' => 'Issue external FX confirmation document',
            'group' => 'customs',
        ]);

        DB::table('role_permissions')->insert([
            'permission_id' => $permission->id,
            'role' => UserRole::COMMITTEE_DIRECTOR->value,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "customs{$counter}@example.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::COMMITTEE_DIRECTOR,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function uploadSignedFx(ImportRequest $request): void
    {
        $this->actingAs($this->director)
            ->post("/api/requests/{$request->id}/fx-confirmation-upload", [
                'signed_document' => UploadedFile::fake()->create('signed-fx.pdf', 64, 'application/pdf'),
            ])
            ->assertOk();
    }
}
