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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeOrphanDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private EngineRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Config::set('retention.orphan_file_grace_hours', 0);
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();
        $bank = Bank::create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@orphan.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->owner->teams()->attach($entryTeam);
        $this->owner->roles()->attach($entryRole);

        $def = WorkflowDefinition::create(['code' => 'ORPHAN_WF', 'name' => 'Orphan WF', 'is_active' => true]);
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
            'reference' => 'ENG-ORPHAN-1',
            'status' => 'ACTIVE',
            'created_by' => $this->owner->id,
            'bank_id' => $bank->id,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_purges_orphan_file_not_referenced_by_document_row(): void
    {
        Storage::disk('private')->put('engine-requests/99/orphan.pdf', 'pdf');

        $this->artisan('documents:purge-orphans')->assertSuccessful();

        Storage::disk('private')->assertMissing('engine-requests/99/orphan.pdf');
    }

    public function test_never_deletes_file_referenced_by_document_row(): void
    {
        $path = 'engine-requests/'.$this->request->id.'/referenced.pdf';
        Storage::disk('private')->put($path, 'pdf');

        EngineRequestDocument::create([
            'request_id' => $this->request->id,
            'uploaded_by' => $this->owner->id,
            'stage_id' => $this->request->current_stage_id,
            'original_name' => 'referenced.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => DocumentStatus::Active,
        ]);

        $this->artisan('documents:purge-orphans')->assertSuccessful();

        Storage::disk('private')->assertExists($path);
    }

    public function test_idempotent_second_run_deletes_nothing_extra(): void
    {
        Storage::disk('private')->put('engine-requests/99/orphan.pdf', 'pdf');

        $this->artisan('documents:purge-orphans')->assertSuccessful();
        $this->artisan('documents:purge-orphans')->assertSuccessful();

        Storage::disk('private')->assertMissing('engine-requests/99/orphan.pdf');
    }
}
