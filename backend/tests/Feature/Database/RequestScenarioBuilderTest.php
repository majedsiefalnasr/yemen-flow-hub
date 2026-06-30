<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\RequestDocument;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\Support\RequestScenarioBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestScenarioBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DocumentTypeSeeder::class);
    }

    public function test_seeded_request_documents_have_downloadable_files(): void
    {
        $bank = Bank::query()->create([
            'name' => 'بنك الاختبار',
            'code' => 'TST',
            'is_active' => true,
        ]);

        $this->makeUser(UserRole::DATA_ENTRY, $bank);
        $this->makeUser(UserRole::BANK_REVIEWER, $bank);
        $this->makeUser(UserRole::SWIFT_OFFICER, $bank);
        $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $admin = $this->makeUser(UserRole::CBY_ADMIN);

        Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => 'اختبار للتجارة',
            'commercial_register' => 'CR-001',
            'is_active' => true,
        ]);

        $request = (new RequestScenarioBuilder)->build('submitted', $bank, now()->subDay());
        $document = RequestDocument::query()->where('request_id', $request->id)->firstOrFail();

        foreach ($request->documents as $seededDocument) {
            Storage::disk('local')->assertExists('private/'.$seededDocument->stored_path);
        }

        $this->actingAs($admin)
            ->get("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        return User::query()->create([
            'name' => $role->value,
            'email' => strtolower($role->value).uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }
}
