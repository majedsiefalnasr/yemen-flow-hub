<?php

namespace Tests\Feature\Jobs;

use App\Enums\DocumentScanStatus;
use App\Enums\StageAccessLevel;
use App\Jobs\ScanEngineRequestDocument;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards QUEUE-001 (fail-closed document scan): when the scan job's retries are
 * exhausted, a still-pending document must be marked Failed — never left
 * silently trusted — and a document already resolved by a prior attempt must not
 * be clobbered.
 */
class ScanEngineRequestDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $this->bank = Bank::create(['name' => 'Scan Bank', 'code' => 'SCN', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->owner = User::create([
            'name' => 'Scan Owner',
            'email' => 'scan-owner@doc.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->owner->teams()->attach($entryTeam);
        $this->owner->roles()->attach($entryRole);

        $def = WorkflowDefinition::create(['code' => 'SCAN_WF', 'name' => 'Scan WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
        StagePermission::create([
            'stage_id' => $this->stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Owner Exec',
            'version' => 1,
        ]);
    }

    /**
     * Upload a real document but keep the scan job from running synchronously, so
     * the document is left in Pending for the job to act on explicitly.
     */
    private function pendingDocument(): EngineRequestDocument
    {
        Queue::fake();
        Config::set('workflow.document_scan_enforced', true);

        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'ENG-SCAN-'.uniqid(),
            'status' => 'ACTIVE',
            'created_by' => $this->owner->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
        ]);

        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        $docId = $this->actingAs($this->owner)
            ->postJson("/api/v1/engine-requests/{$request->id}/documents", ['file' => $file])
            ->assertCreated()
            ->json('data.id');

        $document = EngineRequestDocument::query()->findOrFail($docId);
        $this->assertSame(DocumentScanStatus::Pending, $document->scan_status);

        return $document;
    }

    public function test_failed_marks_a_pending_document_as_failed_not_clean(): void
    {
        $document = $this->pendingDocument();

        (new ScanEngineRequestDocument($document->id))->failed(new RuntimeException('scanner down'));

        $this->assertSame(
            DocumentScanStatus::Failed,
            $document->fresh()->scan_status,
            'A scan that exhausts retries must fail closed (Failed), never remain trusted.',
        );
        $this->assertFalse(
            $document->fresh()->scan_status->isDownloadable(true),
            'A Failed scan must not be downloadable under enforcement.',
        );
    }

    public function test_failed_does_not_clobber_a_document_already_resolved(): void
    {
        $document = $this->pendingDocument();
        $document->forceFill(['scan_status' => DocumentScanStatus::Clean])->save();

        (new ScanEngineRequestDocument($document->id))->failed(new RuntimeException('late failure'));

        $this->assertSame(
            DocumentScanStatus::Clean,
            $document->fresh()->scan_status,
            'failed() must leave an already-resolved (Clean/Infected) document untouched.',
        );
    }

    public function test_failed_is_safe_when_the_document_no_longer_exists(): void
    {
        // A deleted document id must not throw from failed().
        (new ScanEngineRequestDocument(999999))->failed(new RuntimeException('missing'));

        $this->assertTrue(true);
    }

    public function test_job_declares_bounded_retries_and_timeout(): void
    {
        $job = new ScanEngineRequestDocument(1);

        $this->assertGreaterThan(1, $job->tries, 'Scan must retry before failing closed.');
        $this->assertGreaterThan(0, $job->timeout, 'Scan must have a timeout so a hung worker is killed.');
        $this->assertNotEmpty($job->backoff(), 'Scan must back off between retries.');
    }
}
