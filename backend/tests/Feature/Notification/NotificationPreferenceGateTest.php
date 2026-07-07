<?php

namespace Tests\Feature\Notification;

use App\Enums\UserRole;
use App\Jobs\DispatchNotification;
use App\Models\NotificationRecipient;
use App\Models\Organization;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceGate;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
    }

    private function makeUser(array $prefs = []): User
    {
        $org = Organization::where('code', 'system_administration')->firstOrFail();

        return User::create([
            'name' => 'Prefs User',
            'email' => uniqid('prefs_', true).'@test.cby',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'is_active' => true,
            'user_preferences' => $prefs === [] ? null : $prefs,
        ]);
    }

    public function test_informational_transition_suppressed_when_pref_disabled(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => false],
        ]);

        $gate = app(NotificationPreferenceGate::class);

        $this->assertFalse($gate->shouldDeliver($user, 'transition', 'info'));
    }

    public function test_informational_transition_delivered_when_pref_enabled(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => true],
        ]);

        $gate = app(NotificationPreferenceGate::class);

        $this->assertTrue($gate->shouldDeliver($user, 'transition', 'info'));
    }

    public function test_sla_breach_always_delivers(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => false],
        ]);

        $gate = app(NotificationPreferenceGate::class);

        $this->assertTrue($gate->shouldDeliver($user, 'sla.breached', 'critical'));
    }

    public function test_permission_change_always_delivers(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => false],
        ]);

        $gate = app(NotificationPreferenceGate::class);

        $this->assertTrue($gate->shouldDeliver($user, 'permission.changed', 'warning'));
    }

    public function test_compliance_duplicate_invoice_always_delivers(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => false],
        ]);

        $gate = app(NotificationPreferenceGate::class);

        $this->assertTrue($gate->shouldDeliver($user, 'compliance.duplicate_invoice', 'warning'));
    }

    public function test_claim_released_honors_preference(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['claim_released' => false],
        ]);

        $gate = app(NotificationPreferenceGate::class);

        $this->assertFalse($gate->shouldDeliver($user, 'claim.released', 'info'));
    }

    public function test_disabled_pref_skips_notification_recipient(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => false],
        ]);

        $job = new DispatchNotification(
            type: 'transition',
            severity: 'info',
            title: 'Suppressed transition',
            body: null,
            entityType: 'engine_request',
            entityId: 1,
            actionUrl: '/workflows/instances/1',
            recipientUserIds: [$user->id],
        );

        $job->handle();

        $this->assertSame(0, NotificationRecipient::count());
    }

    public function test_enabled_pref_creates_notification_recipient(): void
    {
        $user = $this->makeUser([
            'notification_preferences' => ['request_submitted' => true],
        ]);

        $job = new DispatchNotification(
            type: 'transition',
            severity: 'info',
            title: 'Delivered transition',
            body: null,
            entityType: 'engine_request',
            entityId: 1,
            actionUrl: '/workflows/instances/1',
            recipientUserIds: [$user->id],
        );

        $job->handle();

        $this->assertSame(1, NotificationRecipient::where('user_id', $user->id)->count());
    }
}
