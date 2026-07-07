<?php

namespace Tests\Feature\Operations;

use App\Enums\DocumentStatus;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveSupersededDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private EngineRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();
        $bank = Bank::create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@superseded.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->owner->teams()->attach($entryTeam);
        $this->owner->roles()->attach($entryRole);

        $def = WorkflowDefinition::create(['code' => 'SUPER_WF', 'name' => 'Super WF', 'is_active' => true]);
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
        StagePermission::create([
            'stage_id' => $stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Owner Exec',
            'version' => 1,
        ]);

        $this->request = EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'ENG-SUPER-1',
            'status' => 'ACTIVE',
            'created_by' => $this->owner->id,
            'bank_id' => $bank->id,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_archive_superseded_deletes_physical_file_but_keeps_row(): void
    {
        $path = 'engine-requests/'.$this->request->id.'/old.pdf';
        Storage::disk('private')->put($path, 'pdf');

        $doc = EngineRequestDocument::create([
            'request_id' => $this->request->id,
            'uploaded_by' => $this->owner->id,
            'stage_id' => $this->request->current_stage_id,
            'original_name' => 'old.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => DocumentStatus::Superseded,
        ]);
        $doc->forceFill(['created_at' => now()->subDays(91)])->save();

        $this->artisan('documents:archive-superseded')->assertSuccessful();

        $doc->refresh();
        $this->assertDatabaseHas('engine_request_documents', ['id' => $doc->id]);
        $this->assertNull($doc->path);
        Storage::disk('private')->assertMissing($path);
    }

    public function test_skips_recent_superseded_documents(): void
    {
        $path = 'engine-requests/'.$this->request->id.'/recent.pdf';
        Storage::disk('private')->put($path, 'pdf');

        $doc = EngineRequestDocument::create([
            'request_id' => $this->request->id,
            'uploaded_by' => $this->owner->id,
            'stage_id' => $this->request->current_stage_id,
            'original_name' => 'recent.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => DocumentStatus::Superseded,
        ]);

        $this->artisan('documents:archive-superseded')->assertSuccessful();

        $doc->refresh();
        $this->assertSame($path, $doc->path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_idempotent_on_already_archived_superseded_document(): void
    {
        $doc = EngineRequestDocument::create([
            'request_id' => $this->request->id,
            'uploaded_by' => $this->owner->id,
            'stage_id' => $this->request->current_stage_id,
            'original_name' => 'archived.pdf',
            'path' => null,
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => DocumentStatus::Superseded,
        ]);
        $doc->forceFill(['created_at' => now()->subDays(91)])->save();

        $this->artisan('documents:archive-superseded')->assertSuccessful();
        $this->artisan('documents:archive-superseded')->assertSuccessful();

        $this->assertDatabaseHas('engine_request_documents', ['id' => $doc->id, 'path' => null]);
    }
}
