<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use App\Services\Settings\UserPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPreferencesServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserPreferencesService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserPreferencesService::class);

        $bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB01', 'is_active' => true]);
        $this->user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    // --- saveSection('notif') stores to correct keys ---

    public function test_save_section_notif_stores_to_notification_preferences_key(): void
    {
        $this->service->saveSection($this->user, 'notif', [
            'notification_preferences' => ['request_approved' => true, 'voting_opened' => false],
            'email_notifications' => true,
        ]);

        $this->user->refresh();
        $prefs = $this->user->user_preferences;

        $this->assertArrayHasKey('notification_preferences', $prefs);
        $this->assertArrayNotHasKey('notif', $prefs);
        $this->assertTrue($prefs['notification_preferences']['request_approved']);
        $this->assertFalse($prefs['notification_preferences']['voting_opened']);
    }

    public function test_save_section_notif_stores_email_notifications_at_top_level(): void
    {
        $this->service->saveSection($this->user, 'notif', [
            'notification_preferences' => [],
            'email_notifications' => true,
        ]);

        $this->user->refresh();
        $prefs = $this->user->user_preferences;

        $this->assertArrayHasKey('email_notifications', $prefs);
        $this->assertTrue($prefs['email_notifications']);
    }

    // --- Unknown keys are stripped ---

    public function test_unknown_notification_preference_keys_are_stripped(): void
    {
        $this->service->saveSection($this->user, 'notif', [
            'notification_preferences' => [
                'request_approved' => true,
                'hacked_key' => false,
                'malicious' => true,
            ],
            'email_notifications' => false,
        ]);

        $this->user->refresh();
        $saved = $this->user->user_preferences['notification_preferences'] ?? [];

        $this->assertArrayHasKey('request_approved', $saved);
        $this->assertArrayNotHasKey('hacked_key', $saved);
        $this->assertArrayNotHasKey('malicious', $saved);
    }

    // --- email_notifications defaults to false ---

    public function test_email_notifications_defaults_to_false(): void
    {
        // When email_notifications is not provided
        $this->service->saveSection($this->user, 'notif', [
            'notification_preferences' => ['voting_opened' => true],
        ]);

        $this->user->refresh();
        $prefs = $this->user->user_preferences;

        $this->assertArrayHasKey('email_notifications', $prefs);
        $this->assertFalse($prefs['email_notifications']);
    }

    public function test_email_notifications_defaults_to_false_in_get_for_user(): void
    {
        $result = $this->service->getForUser($this->user);

        $this->assertArrayHasKey('email_notifications', $result);
        $this->assertFalse($result['email_notifications']);
    }

    // --- Valid canonical keys accepted ---

    public function test_all_canonical_notification_keys_are_accepted(): void
    {
        $canonicalKeys = [
            'request_approved',
            'request_rejected',
            'request_returned',
            'voting_opened',
            'request_submitted',
            'swift_upload_requested',
            'claim_released',
        ];

        $prefs = array_fill_keys($canonicalKeys, true);

        $this->service->saveSection($this->user, 'notif', [
            'notification_preferences' => $prefs,
            'email_notifications' => false,
        ]);

        $this->user->refresh();
        $saved = $this->user->user_preferences['notification_preferences'] ?? [];

        foreach ($canonicalKeys as $key) {
            $this->assertArrayHasKey($key, $saved, "Canonical key '{$key}' should be saved");
        }
    }

    // --- Non-boolean values are rejected ---

    public function test_non_boolean_notification_preference_values_are_stripped(): void
    {
        $this->service->saveSection($this->user, 'notif', [
            'notification_preferences' => [
                'request_approved' => 'yes',
                'voting_opened' => 1,
                'request_returned' => false,
            ],
            'email_notifications' => false,
        ]);

        $this->user->refresh();
        $saved = $this->user->user_preferences['notification_preferences'] ?? [];

        $this->assertArrayNotHasKey('request_approved', $saved, 'String "yes" should be stripped');
        $this->assertArrayNotHasKey('voting_opened', $saved, 'Integer 1 should be stripped');
        $this->assertArrayHasKey('request_returned', $saved, 'Boolean false should be kept');
    }
}
