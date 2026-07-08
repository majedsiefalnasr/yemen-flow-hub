<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\EngineRequestAnchorSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\MerchantSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Document scan state coverage per spec § Document scan states.
 *
 * document_scan_enforced is a plain Laravel config value
 * (config/workflow.php), not a SystemSetting DB row — DemoSystemSettingsSeeder
 * writes a SystemSetting row for it (matching the spec's literal wording),
 * but the download-enforcement check in
 * EngineRequestDocumentIntegrityService::assertDownloadAllowed() reads
 * config('workflow.document_scan_enforced') directly, so these tests set
 * that config value at runtime instead of relying on the seeded row.
 */
class DemoSeedDocumentScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
            BankSeeder::class,
            UserSeeder::class,
            MerchantSeeder::class,
            EngineRequestAnchorSeeder::class,
        ]);
    }

    public function test_clean_document_is_downloadable_with_enforcement_on(): void
    {
        config(['workflow.document_scan_enforced' => true]);

        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION)->firstOrFail();
        $document = EngineRequestDocument::query()->where('request_id', $request->id)->where('status', 'active')->firstOrFail();
        $entry = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();

        $this->assertSame('clean', $document->scan_status->value);

        $this->actingAs($entry)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$document->id}/download")
            ->assertOk();
    }

    public function test_pending_document_is_blocked_with_enforcement_on(): void
    {
        config(['workflow.document_scan_enforced' => true]);

        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SCAN_PENDING)->firstOrFail();
        $document = EngineRequestDocument::query()->where('request_id', $request->id)->firstOrFail();
        $reviewer = User::query()->where('email', 'reviewer@ybrd.com.ye')->firstOrFail();

        $this->assertSame('pending', $document->scan_status->value);

        $this->actingAs($reviewer)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$document->id}/download")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'DOCUMENT_SCAN_BLOCKED');
    }

    public function test_failed_document_is_blocked_with_enforcement_on(): void
    {
        config(['workflow.document_scan_enforced' => true]);

        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SCAN_FAILED)->firstOrFail();
        $document = EngineRequestDocument::query()->where('request_id', $request->id)->firstOrFail();
        $reviewer = User::query()->where('email', 'reviewer@ybrd.com.ye')->firstOrFail();

        $this->assertSame('failed', $document->scan_status->value);

        $this->actingAs($reviewer)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$document->id}/download")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'DOCUMENT_SCAN_BLOCKED');
    }

    public function test_infected_document_is_blocked_with_enforcement_on(): void
    {
        config(['workflow.document_scan_enforced' => true]);

        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SCAN_INFECTED)->firstOrFail();
        $document = EngineRequestDocument::query()->where('request_id', $request->id)->firstOrFail();
        $reviewer = User::query()->where('email', 'reviewer@ybrd.com.ye')->firstOrFail();

        $this->assertSame('infected', $document->scan_status->value);

        $this->actingAs($reviewer)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$document->id}/download")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'DOCUMENT_SCAN_BLOCKED');
    }

    public function test_superseded_document_does_not_satisfy_active_evidence(): void
    {
        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_DOCUMENT_REPLACED)->firstOrFail();

        $active = EngineRequestDocument::query()->where('request_id', $request->id)->where('status', 'active')->firstOrFail();
        $superseded = EngineRequestDocument::query()->where('request_id', $request->id)->where('status', 'superseded')->firstOrFail();

        $this->assertSame($active->id, $superseded->superseded_by);
        $this->assertNotSame($active->version, $superseded->version);
    }
}
