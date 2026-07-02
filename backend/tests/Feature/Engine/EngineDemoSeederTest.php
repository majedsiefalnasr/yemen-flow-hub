<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowTransition;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EngineDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_engine_demo_requests_without_legacy_request_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Schema::hasTable('import_requests'));
        $this->assertFalse(Schema::hasTable('request_stage_history'));
        $this->assertFalse(Schema::hasTable('request_votes'));
        $this->assertFalse(Schema::hasTable('request_documents'));

        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail();
        $version = $definition->versions()->firstOrFail();

        $this->assertSame(20, EngineRequest::query()->count());
        $this->assertSame(
            Bank::query()->where('is_active', true)->count(),
            EngineRequest::query()->distinct('bank_id')->count('bank_id')
        );
        $this->assertDatabaseCount('workflow_history', 72);
        $this->assertDatabaseCount('engine_request_documents', 20);
        $this->assertDatabaseCount('customs_declarations', 2);
        $this->assertDatabaseCount('engine_notifications', 4);
        $this->assertDatabaseCount('notification_recipients', 4);
        $this->assertDatabaseCount('email_deliveries', 3);
        $this->assertDatabaseCount('report_exports', 2);

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

        $request = EngineRequest::query()->where('reference', 'ENG-2026-002001')->firstOrFail();
        $data = $request->data;

        $this->assertSame(120000, $data['amount']);
        $this->assertSame('INV-2026-10000', $data['invoice_number']);
        $this->assertSame(100, $data['request_percentage']);
        $this->assertArrayNotHasKey('financeAmount', $data);
        $this->assertArrayNotHasKey('invoiceNumber', $data);
        $this->assertArrayNotHasKey('requestPercentage', $data);

        $this->assertSame('120000.00', (string) $request->amount);
        $this->assertSame('INV-2026-10000', $request->invoice_number);
        $this->assertSame('100.00', (string) $request->request_percentage);

        $this->assertDatabaseHas('engine_request_documents', [
            'request_id' => $request->id,
            'original_name' => 'ENG-2026-002001-commercial-invoice.pdf',
        ]);
        $this->assertDatabaseHas('customs_declarations', [
            'engine_request_id' => EngineRequest::query()->where('reference', 'ENG-2026-002019')->value('id'),
            'declaration_number' => 'CD-2026-002019',
        ]);
    }

    public function test_workflow_demo_requests_are_visible_to_each_demo_bank_role_and_view_only_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Bank::query()->where('is_active', true)->orderBy('id')->get() as $bank) {
            $code = strtolower($bank->code);

            $this->actingAs(User::query()->where('email', "entry@{$code}.com.ye")->firstOrFail())
                ->getJson('/api/v1/engine-requests/my-queue')
                ->assertOk()
                ->assertJsonPath('meta.total', 1);

            $this->actingAs(User::query()->where('email', "reviewer@{$code}.com.ye")->firstOrFail())
                ->getJson('/api/v1/engine-requests/my-queue')
                ->assertOk()
                ->assertJsonPath('meta.total', 1);

            $this->actingAs(User::query()->where('email', "swift@{$code}.com.ye")->firstOrFail())
                ->getJson('/api/v1/engine-requests/my-queue')
                ->assertOk()
                ->assertJsonPath('meta.total', 1);
        }

        $admin = User::query()->where('email', 'admin@cby.gov.ye')->firstOrFail();
        $request = EngineRequest::query()->where('reference', 'ENG-2026-002001')->firstOrFail();
        $transition = WorkflowTransition::query()
            ->where('from_stage_id', $request->current_stage_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/engine-requests?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 20);

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
