<?php

namespace Tests\Feature\Requests;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WizardFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private User $dataEntry;
    private User $bankAdmin;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->bank = Bank::query()->create(['name' => 'بنك اليمن', 'code' => 'YBK', 'is_active' => true]);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $this->merchant = $this->makeMerchant($this->bank);
    }

    private function seedPermissions(): void
    {
        $id = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'إنشاء طلب',
            'name_en' => 'Create request',
            'group' => 'requests',
        ]);
        foreach ([UserRole::DATA_ENTRY->value, UserRole::BANK_ADMIN->value] as $role) {
            DB::table('role_permissions')->insert(['permission_id' => $id, 'role' => $role]);
        }
    }

    private function makeUser(UserRole $role, Bank $bank): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "wizard{$counter}@test.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function makeMerchant(Bank $bank): Merchant
    {
        static $mc = 0;
        $mc++;
        return Merchant::query()->create([
            'name' => "تاجر {$mc}",
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function basePayload(): array
    {
        return [
            'merchant_id' => $this->merchant->id,
            'currency' => 'USD',
            'amount' => 50000,
            'supplier_name' => 'Cargill Trading Inc.',
            'goods_description' => 'Food commodities',
            'port_of_entry' => 'Aden Port',
        ];
    }

    private function fullWizardPayload(): array
    {
        return array_merge($this->basePayload(), [
            'goods_type' => 'مواد غذائية',
            'payment_terms' => 'LC',
            'due_date' => now()->addMonths(3)->toDateString(),
            'invoice_number' => 'INV-2026-001',
            'invoice_date' => now()->subDays(5)->toDateString(),
            'origin_country' => 'الولايات المتحدة',
            'arrival_port' => 'ميناء عدن',
            'shipping_port' => 'Port of Houston, USA',
            'customs_office' => 'جمارك عدن',
            'bl_number' => 'BL-2026-XXXX',
        ]);
    }

    // ─── New wizard fields stored and returned ─────────────────────────────────

    public function test_data_entry_can_create_request_with_all_wizard_fields(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->fullWizardPayload());

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertSame('مواد غذائية', $data['goods_type']);
        $this->assertSame('LC', $data['payment_terms']);
        $this->assertSame('INV-2026-001', $data['invoice_number']);
        $this->assertSame('الولايات المتحدة', $data['origin_country']);
        $this->assertSame('ميناء عدن', $data['arrival_port']);
        $this->assertSame('Port of Houston, USA', $data['shipping_port']);
        $this->assertSame('جمارك عدن', $data['customs_office']);
        $this->assertSame('BL-2026-XXXX', $data['bl_number']);
    }

    public function test_bank_admin_can_create_request_with_wizard_fields(): void
    {
        $response = $this->actingAs($this->bankAdmin)
            ->postJson('/api/requests', $this->fullWizardPayload());

        $response->assertStatus(201);
        $this->assertSame('LC', $response->json('data.payment_terms'));
        $this->assertSame('ميناء عدن', $response->json('data.arrival_port'));
    }

    public function test_wizard_fields_are_optional_on_create(): void
    {
        // Only base 6 fields — all new wizard fields omitted
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->basePayload());

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertNull($data['goods_type']);
        $this->assertNull($data['payment_terms']);
        $this->assertNull($data['invoice_number']);
        $this->assertNull($data['arrival_port']);
    }

    public function test_wizard_fields_persisted_in_database(): void
    {
        $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->fullWizardPayload());

        $request = ImportRequest::query()->latest()->first();

        $this->assertSame('مواد غذائية', $request->goods_type);
        $this->assertSame('LC', $request->payment_terms);
        $this->assertSame('INV-2026-001', $request->invoice_number);
        $this->assertSame('جمارك عدن', $request->customs_office);
    }

    // ─── Validation ────────────────────────────────────────────────────────────

    public function test_payment_terms_must_be_valid_enum_value(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', array_merge($this->basePayload(), [
                'payment_terms' => 'INVALID',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_terms']);
    }

    public function test_due_date_must_be_in_the_future(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', array_merge($this->basePayload(), [
                'due_date' => now()->subDay()->toDateString(),
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['due_date']);
    }

    public function test_due_date_future_is_accepted(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', array_merge($this->basePayload(), [
                'due_date' => now()->addMonth()->toDateString(),
            ]));

        $response->assertStatus(201);
        $this->assertSame(
            now()->addMonth()->toDateString(),
            $response->json('data.due_date')
        );
    }

    public function test_submit_rejects_draft_when_required_wizard_fields_are_missing(): void
    {
        $createResponse = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->basePayload());

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.id');

        $submitResponse = $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$requestId}/submit");

        $submitResponse->assertStatus(422);
        $this->assertStringContainsString(
            'Missing required wizard fields',
            (string) $submitResponse->json('message')
        );
    }

    public function test_submit_accepts_draft_when_required_wizard_fields_exist(): void
    {
        $createResponse = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->fullWizardPayload());

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.id');

        $submitResponse = $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$requestId}/submit");

        $submitResponse->assertStatus(200);
        $this->assertSame(RequestStatus::SUBMITTED->value, $submitResponse->json('data.status'));
    }

    public function test_goods_type_max_length_enforced(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', array_merge($this->basePayload(), [
                'goods_type' => str_repeat('x', 101),
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['goods_type']);
    }

    // ─── Update also accepts new fields ────────────────────────────────────────

    public function test_update_request_accepts_wizard_fields(): void
    {
        app()->instance('workflow.transition.active', true);
        $importRequest = ImportRequest::query()->create([
            'bank_id' => $this->bank->id,
            'merchant_id' => $this->merchant->id,
            'created_by' => $this->dataEntry->id,
            'currency' => 'USD',
            'amount' => 10000,
            'supplier_name' => 'Old Supplier',
            'goods_description' => 'Old goods',
            'port_of_entry' => 'Aden',
            'status' => RequestStatus::DRAFT,
            'current_owner_role' => UserRole::DATA_ENTRY,
        ]);
        app()->offsetUnset('workflow.transition.active');

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$importRequest->id}", array_merge($this->basePayload(), [
                'payment_terms' => 'TT',
                'bl_number' => 'BL-UPDATE-001',
            ]));

        $response->assertStatus(200);
        $this->assertSame('TT', $response->json('data.payment_terms'));
        $this->assertSame('BL-UPDATE-001', $response->json('data.bl_number'));
    }

    public function test_update_allows_unchanged_past_due_date(): void
    {
        $pastDueDate = now()->subDay()->toDateString();

        app()->instance('workflow.transition.active', true);
        $importRequest = ImportRequest::query()->create([
            'bank_id' => $this->bank->id,
            'merchant_id' => $this->merchant->id,
            'created_by' => $this->dataEntry->id,
            'currency' => 'USD',
            'amount' => 10000,
            'supplier_name' => 'Old Supplier',
            'goods_description' => 'Old goods',
            'port_of_entry' => 'Aden',
            'due_date' => $pastDueDate,
            'status' => RequestStatus::DRAFT,
            'current_owner_role' => UserRole::DATA_ENTRY,
        ]);
        app()->offsetUnset('workflow.transition.active');

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$importRequest->id}", array_merge($this->basePayload(), [
                'due_date' => $pastDueDate,
                'notes' => 'Updated note only',
            ]));

        $response->assertStatus(200);
        $this->assertSame($pastDueDate, $response->json('data.due_date'));
    }

    // ─── Resource includes all wizard fields ───────────────────────────────────

    public function test_request_detail_includes_all_wizard_fields(): void
    {
        $payload = $this->fullWizardPayload();
        $create = $this->actingAs($this->dataEntry)->postJson('/api/requests', $payload);
        $id = $create->json('data.id');

        $response = $this->actingAs($this->dataEntry)->getJson("/api/requests/{$id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach (['goods_type', 'payment_terms', 'invoice_number', 'invoice_date', 'origin_country', 'arrival_port', 'shipping_port', 'customs_office', 'bl_number'] as $field) {
            $this->assertArrayHasKey($field, $data, "Field {$field} missing from response");
        }
    }
}
