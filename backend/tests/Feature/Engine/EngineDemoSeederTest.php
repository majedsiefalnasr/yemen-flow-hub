<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowTransition;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EngineDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_engine_demo_anchors_without_legacy_request_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Schema::hasTable('import_requests'));
        $this->assertFalse(Schema::hasTable('request_stage_history'));
        $this->assertFalse(Schema::hasTable('request_votes'));
        $this->assertFalse(Schema::hasTable('request_documents'));

        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail();
        $version = $definition->versions()->firstOrFail();

        $this->assertSame(SeederCatalog::ANCHOR_COUNT, EngineRequest::query()->count());
        $this->assertSame(
            Bank::query()->where('is_active', true)->count(),
            EngineRequest::query()->distinct('bank_id')->count('bank_id')
        );

        $fieldKeys = FieldDefinition::query()
            ->where('workflow_version_id', $version->id)
            ->pluck('key')
            ->all();

        $this->assertContains('amount', $fieldKeys);
        $this->assertContains('invoice_number', $fieldKeys);
        $this->assertContains('request_percentage', $fieldKeys);
        $this->assertNotContains('financeAmount', $fieldKeys);
        $this->assertNotContains('invoiceNumber', $fieldKeys);
        $this->assertNotContains('requestPercentage', $fieldKeys);

        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION)->firstOrFail();
        $data = $request->data;

        $this->assertArrayNotHasKey('financeAmount', $data);
        $this->assertArrayNotHasKey('invoiceNumber', $data);
        $this->assertArrayNotHasKey('requestPercentage', $data);
        $this->assertSame((string) $data['amount'], (string) (int) $request->amount);
        $this->assertSame($data['invoice_number'], $request->invoice_number);

        $this->assertDatabaseHas('customs_declarations', [
            'engine_request_id' => EngineRequest::query()
                ->where('reference', SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY)
                ->value('id'),
        ]);
    }

    public function test_workflow_demo_requests_are_visible_to_each_demo_bank_role_and_view_only_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Bank::query()->where('is_active', true)->orderBy('id')->get() as $bank) {
            $code = strtolower($bank->code);

            $this->actingAs(User::query()->where('email', "entry@{$code}.com.ye")->firstOrFail())
                ->getJson('/api/v1/engine-requests/my-queue')
                ->assertOk();

            $this->actingAs(User::query()->where('email', "reviewer@{$code}.com.ye")->firstOrFail())
                ->getJson('/api/v1/engine-requests/my-queue')
                ->assertOk();

            $this->actingAs(User::query()->where('email', "swift@{$code}.com.ye")->firstOrFail())
                ->getJson('/api/v1/engine-requests/my-queue')
                ->assertOk();
        }

        $admin = User::query()->where('email', 'admin@cby.gov.ye')->firstOrFail();
        $request = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION)->firstOrFail();
        $transition = WorkflowTransition::query()
            ->where('from_stage_id', $request->current_stage_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/engine-requests?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.total', SeederCatalog::ANCHOR_COUNT);

        $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$request->id}")
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/v1/engine-requests/my-queue')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($admin)
            ->postJson("/api/v1/engine-requests/{$request->id}/actions", [
                'transition_id' => $transition->id,
                'version' => $request->version,
                'data' => [],
            ])
            ->assertForbidden();
    }
}
