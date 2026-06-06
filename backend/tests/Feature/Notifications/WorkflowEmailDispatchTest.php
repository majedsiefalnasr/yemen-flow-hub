<?php

namespace Tests\Feature\Notifications;

use App\Enums\EmailDeliveryStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Jobs\SendEmailDelivery;
use App\Listeners\SendWorkflowNotifications;
use App\Models\Bank;
use App\Models\EmailDelivery;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestReturnedNotification;
use App\Notifications\VotingOpenedNotification;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\SendEmailNotification;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class WorkflowEmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $dataEntryUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'WED01', 'is_active' => true]);
        $this->dataEntryUser = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, [
            'email_notifications' => false,
        ]);
    }

    public function test_workflow_email_goes_through_outbox_when_email_enabled(): void
    {
        Queue::fake();
        Mail::fake();
        Notification::fake();

        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, [
            'email_notifications' => true,
        ], 'creator@example.com');
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank, [
            'email_notifications' => true,
        ], 'reviewer@example.com');
        $otherBank = Bank::query()->create(['name' => 'Other Bank', 'code' => 'WED02', 'is_active' => true]);
        $otherBankUser = $this->makeUser(UserRole::DATA_ENTRY, $otherBank, [
            'email_notifications' => true,
        ], 'other-bank@example.com');
        $request = $this->makeRequest(RequestStatus::BANK_APPROVED, $creator);

        $this->dispatchTransition($request, RequestStatus::BANK_APPROVED, $reviewer);

        Notification::assertSentTo($creator, RequestApprovedNotification::class);
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('email_deliveries', 2);

        $this->assertDatabaseHas('email_deliveries', [
            'notification_type' => NotificationType::REQUEST_APPROVED->value,
            'event_id' => $request->id.':'.RequestStatus::BANK_APPROVED->value,
            'recipient_user_id' => $creator->id,
            'recipient_email' => $creator->email,
            'channel' => 'mail',
            'status' => EmailDeliveryStatus::QUEUED->value,
        ]);
        $this->assertDatabaseHas('email_deliveries', [
            'recipient_user_id' => $reviewer->id,
            'recipient_email' => $reviewer->email,
        ]);
        $this->assertDatabaseMissing('email_deliveries', [
            'recipient_user_id' => $otherBankUser->id,
            'recipient_email' => $otherBankUser->email,
        ]);

        Queue::assertPushed(SendEmailDelivery::class, 2);
        Queue::assertPushed(SendEmailDelivery::class, function (SendEmailDelivery $job): bool {
            $delivery = EmailDelivery::query()->find($job->deliveryId);

            return $job->connection === 'emails'
                && $job->queue === 'emails'
                && $delivery !== null
                && filled($delivery->rendered_subject)
                && filled($delivery->rendered_body);
        });
    }

    public function test_email_preference_off_skips_outbox_but_keeps_database_notification(): void
    {
        Queue::fake();
        Mail::fake();
        Notification::fake();

        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, [
            'email_notifications' => false,
        ], 'email-off@example.com');
        $request = $this->makeRequest(RequestStatus::BANK_APPROVED, $creator);

        $this->dispatchTransition($request, RequestStatus::BANK_APPROVED, $creator);

        Notification::assertSentTo($creator, RequestApprovedNotification::class);
        $this->assertDatabaseCount('email_deliveries', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingQueued();
    }

    public function test_registered_workflow_notifications_are_database_channel_only(): void
    {
        $request = $this->makeRequest(RequestStatus::BANK_APPROVED);

        $this->assertSame(['database'], (new RequestApprovedNotification($request))->via($this->dataEntryUser));
        $this->assertSame(['database'], (new RequestRejectedNotification($request))->via($this->dataEntryUser));
        $this->assertSame(['database'], (new RequestReturnedNotification($request))->via($this->dataEntryUser));
        $this->assertSame(['database'], (new VotingOpenedNotification($request))->via($this->dataEntryUser));
    }

    public function test_duplicate_event_stops_before_render_and_dispatch(): void
    {
        Queue::fake();

        $this->dataEntryUser->forceFill([
            'user_preferences' => ['email_notifications' => true],
        ])->save();
        $request = $this->makeRequest(RequestStatus::BANK_APPROVED);
        $this->mock(TemplateRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->once()->andReturn([
                'subject' => 'Rendered subject',
                'html' => '<p>Rendered body</p>',
                'text' => 'Rendered body',
                'source' => 'blade',
                'template_version_id' => null,
                'locale' => 'ar',
            ]);
        });

        $orchestrator = app(SendEmailNotification::class);
        $orchestrator->sendWorkflow(NotificationType::REQUEST_APPROVED, $request);
        $orchestrator->sendWorkflow(NotificationType::REQUEST_APPROVED, $request);

        $this->assertDatabaseCount('email_deliveries', 1);
        Queue::assertPushed(SendEmailDelivery::class, 1);
    }

    public function test_after_commit_dispatch_skips_rolled_back_transition(): void
    {
        Queue::fake();

        $this->dataEntryUser->forceFill([
            'user_preferences' => ['email_notifications' => true],
        ])->save();
        $request = $this->makeRequest(RequestStatus::BANK_APPROVED);

        try {
            DB::transaction(function () use ($request): void {
                app(SendEmailNotification::class)->sendWorkflow(NotificationType::REQUEST_APPROVED, $request);

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // Expected rollback probe.
        }

        $this->assertDatabaseCount('email_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_email_job_marks_sent_and_failed_statuses(): void
    {
        Mail::fake();

        $delivery = EmailDelivery::query()->create([
            'notification_type' => NotificationType::REQUEST_APPROVED->value,
            'event_id' => 'event-1',
            'recipient_user_id' => $this->dataEntryUser->id,
            'recipient_email' => $this->dataEntryUser->email,
            'channel' => 'mail',
            'status' => EmailDeliveryStatus::QUEUED,
            'rendered_subject' => 'Subject',
            'rendered_body' => '<p>Body</p>',
            'queued_at' => now(),
        ]);

        app(SendEmailDelivery::class, ['deliveryId' => $delivery->id])
            ->handle(app(EmailDeliveryService::class));

        $this->assertDatabaseHas('email_deliveries', [
            'id' => $delivery->id,
            'status' => EmailDeliveryStatus::SENT->value,
        ]);

        $failed = EmailDelivery::query()->create([
            'notification_type' => NotificationType::REQUEST_APPROVED->value,
            'event_id' => 'event-2',
            'recipient_user_id' => $this->dataEntryUser->id,
            'recipient_email' => $this->dataEntryUser->email,
            'channel' => 'mail',
            'status' => EmailDeliveryStatus::QUEUED,
            'rendered_subject' => 'Subject',
            'rendered_body' => '<p>Body</p>',
            'queued_at' => now(),
        ]);

        app(SendEmailDelivery::class, ['deliveryId' => $failed->id])->failed(new \RuntimeException('smtp down'));

        $this->assertDatabaseHas('email_deliveries', [
            'id' => $failed->id,
            'status' => EmailDeliveryStatus::FAILED->value,
            'error' => 'smtp down',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'EMAIL_DELIVERY_FAILED',
            'subject_id' => $failed->id,
        ]);
    }

    public function test_email_job_does_not_replay_terminal_delivery(): void
    {
        Mail::shouldReceive('html')->never();

        $delivery = EmailDelivery::query()->create([
            'notification_type' => NotificationType::REQUEST_APPROVED->value,
            'event_id' => 'event-replay',
            'recipient_user_id' => $this->dataEntryUser->id,
            'recipient_email' => $this->dataEntryUser->email,
            'channel' => 'mail',
            'status' => EmailDeliveryStatus::SENT,
            'rendered_subject' => 'Subject',
            'rendered_body' => '<p>Body</p>',
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        app(SendEmailDelivery::class, ['deliveryId' => $delivery->id])
            ->handle(app(EmailDeliveryService::class));

        $this->assertDatabaseHas('email_deliveries', [
            'id' => $delivery->id,
            'status' => EmailDeliveryStatus::SENT->value,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null, array $preferences = [], ?string $email = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => $email ?? "workflow-email-{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role,
            'bank_id' => $bank?->id,
            'is_active' => true,
            'user_preferences' => $preferences,
        ]);
    }

    private function makeRequest(RequestStatus $status, ?User $creator = null): ImportRequest
    {
        $creator ??= $this->dataEntryUser;
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 50000,
                'supplier_name' => 'Test Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function dispatchTransition(ImportRequest $request, RequestStatus $status, User $actor, ?string $reason = null): void
    {
        app()->instance('workflow.transition.active', true);
        try {
            $request->status = $status;
            $request->saveQuietly();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $event = new RequestTransitioned($request->fresh(), 'test_action', $actor, $reason);
        app(SendWorkflowNotifications::class)->handle($event);
    }
}
