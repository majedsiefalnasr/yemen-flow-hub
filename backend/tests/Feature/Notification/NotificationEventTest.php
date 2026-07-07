<?php

namespace Tests\Feature\Notification;

use App\Enums\UserRole;
use App\Jobs\DispatchNotification;
use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\EngineNotificationDispatcher;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NotificationEventTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernanceSeeder::class);

        $sysOrg = Organization::where('code', 'system_administration')->firstOrFail();
        $sysRole = Role::where('code', 'system_admin')->firstOrFail();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.cby',
            'password' => bcrypt('password'),
            'organization_id' => $sysOrg->id,
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($sysRole->id);
    }

    public function test_dispatch_notification_job_creates_notification_and_recipients(): void
    {
        $job = new DispatchNotification(
            type: 'transition',
            severity: 'info',
            title: 'Test notification',
            body: 'Test body',
            entityType: 'engine_request',
            entityId: 1,
            actionUrl: '/requests/1',
            recipientUserIds: [$this->admin->id],
        );

        $job->handle();

        $this->assertSame(1, EngineNotification::count());
        $this->assertSame(1, NotificationRecipient::count());

        $notification = EngineNotification::first();
        $this->assertSame('transition', $notification->type);
        $this->assertSame('info', $notification->severity);
        $this->assertSame('Test notification', $notification->title);

        $recipient = NotificationRecipient::first();
        $this->assertSame($notification->id, $recipient->notification_id);
        $this->assertSame($this->admin->id, $recipient->user_id);
        $this->assertNull($recipient->read_at);
    }

    public function test_dispatch_notification_job_handles_empty_recipients(): void
    {
        $job = new DispatchNotification(
            type: 'transition',
            severity: 'info',
            title: 'No recipients',
            body: null,
            entityType: null,
            entityId: null,
            actionUrl: null,
            recipientUserIds: [],
        );

        $job->handle();

        $this->assertSame(0, EngineNotification::count());
    }

    public function test_dispatch_notification_job_deduplicates_recipients(): void
    {
        $job = new DispatchNotification(
            type: 'test',
            severity: 'info',
            title: 'Dedup test',
            body: null,
            entityType: null,
            entityId: null,
            actionUrl: null,
            recipientUserIds: [$this->admin->id, $this->admin->id, $this->admin->id],
        );

        $job->handle();

        $this->assertSame(1, NotificationRecipient::count());
    }

    public function test_unique_recipient_constraint(): void
    {
        $notification = EngineNotification::create([
            'type' => 'test',
            'severity' => 'info',
            'title' => 'Unique test',
        ]);

        NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $this->admin->id,
        ]);

        $this->expectException(QueryException::class);

        NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_in_platform_only_no_email_channel(): void
    {
        Bus::fake([DispatchNotification::class]);

        $dispatcher = app(EngineNotificationDispatcher::class);
        $dispatcher->custom(
            type: 'test',
            severity: 'info',
            title: 'In-platform only',
            body: null,
            entityType: null,
            entityId: null,
            actionUrl: null,
            recipientUserIds: [$this->admin->id],
        );

        // The dispatcher queues via DB::afterCommit. In test mode with
        // RefreshDatabase, afterCommit callbacks fire immediately.
        Bus::assertDispatched(DispatchNotification::class);
    }

    public function test_no_notification_on_empty_audience(): void
    {
        Bus::fake([DispatchNotification::class]);

        $dispatcher = app(EngineNotificationDispatcher::class);
        $dispatcher->custom(
            type: 'test',
            severity: 'info',
            title: 'Empty audience',
            body: null,
            entityType: null,
            entityId: null,
            actionUrl: null,
            recipientUserIds: [],
        );

        Bus::assertNotDispatched(DispatchNotification::class);
    }

    public function test_permission_change_notifies_role_members(): void
    {
        $role = Role::where('code', 'system_admin')->firstOrFail();

        Bus::fake([DispatchNotification::class]);

        $dispatcher = app(EngineNotificationDispatcher::class);
        $dispatcher->afterPermissionChange($role->id, $role->name, $this->admin->id);

        Bus::assertDispatched(DispatchNotification::class);
    }
}
