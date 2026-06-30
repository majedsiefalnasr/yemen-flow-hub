<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Jobs\SendEmailDelivery;
use App\Listeners\SendWorkflowNotifications;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestReturnedNotification;
use App\Notifications\VotingOpenedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $dataEntryUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'EB01', 'is_active' => true]);

        $this->dataEntryUser = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'de-email@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
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

    private function dispatch(ImportRequest $request, RequestStatus $status, ?User $actor = null, ?string $reason = null): void
    {
        $actor ??= $this->dataEntryUser;
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

    // -----------------------------------------------------------------------
    // AC #2, #3, #10: email queued when email_notifications = true
    // -----------------------------------------------------------------------

    public function test_workflow_email_queued_when_email_notifications_enabled(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $creator = User::query()->create([
            'name' => 'Creator Email On',
            'email' => 'creator-on@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => true],
        ]);

        $request = $this->makeRequest(RequestStatus::BANK_APPROVED, $creator);
        $this->dispatch($request, RequestStatus::BANK_APPROVED, $creator);

        Mail::assertNothingQueued();
        $this->assertOutboxQueued(NotificationType::REQUEST_APPROVED, $request, $creator);
    }

    // -----------------------------------------------------------------------
    // AC #2, #10: email NOT queued when email_notifications = false
    // -----------------------------------------------------------------------

    public function test_workflow_email_not_queued_when_email_notifications_disabled(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $creator = User::query()->create([
            'name' => 'Creator Email Off',
            'email' => 'creator-off@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => false],
        ]);

        $request = $this->makeRequest(RequestStatus::BANK_APPROVED, $creator);
        $this->dispatch($request, RequestStatus::BANK_APPROVED, $creator);

        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------------
    // AC #10: email not queued when email_notifications preference absent (default false)
    // -----------------------------------------------------------------------

    public function test_workflow_email_not_queued_when_email_notifications_preference_absent(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $creator = User::query()->create([
            'name' => 'Creator No Pref',
            'email' => 'creator-nopref@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $request = $this->makeRequest(RequestStatus::BANK_APPROVED, $creator);
        $this->dispatch($request, RequestStatus::BANK_APPROVED, $creator);

        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------------
    // AC #3, #10: database notification always created regardless of email pref
    // -----------------------------------------------------------------------

    public function test_database_notification_always_created_regardless_of_email_pref(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        // email off — DB notification must still fire
        $creator = User::query()->create([
            'name' => 'Creator DB Only',
            'email' => 'creator-dbonly@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => false],
        ]);

        $request = $this->makeRequest(RequestStatus::BANK_APPROVED, $creator);
        $this->dispatch($request, RequestStatus::BANK_APPROVED, $creator);

        Notification::assertSentTo($creator, RequestApprovedNotification::class);
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------------
    // BANK_REJECTED block: in-app unconditional; email still gated
    // -----------------------------------------------------------------------

    public function test_bank_rejected_email_queued_when_email_notifications_enabled(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $dataEntry = User::query()->create([
            'name' => 'DE Email On',
            'email' => 'de-email-on@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => true],
        ]);

        $request = $this->makeRequest(RequestStatus::BANK_REJECTED, $dataEntry);
        $this->dispatch($request, RequestStatus::BANK_REJECTED, $dataEntry, 'Missing documents');

        Notification::assertSentTo($dataEntry, RequestRejectedNotification::class);
        Mail::assertNothingQueued();
        $this->assertOutboxQueued(NotificationType::REQUEST_REJECTED, $request, $dataEntry);
    }

    public function test_bank_rejected_db_notification_sent_even_when_email_off(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $dataEntry = User::query()->create([
            'name' => 'DE Email Off',
            'email' => 'de-email-off@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => false],
        ]);

        $request = $this->makeRequest(RequestStatus::BANK_REJECTED, $dataEntry);
        $this->dispatch($request, RequestStatus::BANK_REJECTED, $dataEntry);

        // In-app must fire unconditionally for BANK_REJECTED
        Notification::assertSentTo($dataEntry, RequestRejectedNotification::class);
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------------
    // REQUEST_REJECTED outbox email (SUPPORT_REJECTED)
    // -----------------------------------------------------------------------

    public function test_request_rejected_email_queued_for_creator_with_email_enabled(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $creator = User::query()->create([
            'name' => 'Creator Rejected Email',
            'email' => 'creator-rej@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => true],
        ]);

        $request = $this->makeRequest(RequestStatus::SUPPORT_REJECTED, $creator);
        $this->dispatch($request, RequestStatus::SUPPORT_REJECTED, $creator);

        Mail::assertNothingQueued();
        $this->assertOutboxQueued(NotificationType::REQUEST_REJECTED, $request, $creator);
    }

    // -----------------------------------------------------------------------
    // REQUEST_RETURNED outbox email
    // -----------------------------------------------------------------------

    public function test_request_returned_email_queued_when_email_notifications_enabled(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $dataEntry = User::query()->create([
            'name' => 'DE Returned Email On',
            'email' => 'de-ret-on@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['email_notifications' => true],
        ]);

        $request = $this->makeRequest(RequestStatus::DRAFT_REJECTED_INTERNAL, $dataEntry);
        $actor = User::query()->create([
            'name' => 'Reviewer Actor',
            'email' => 'reviewer-actor@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $this->dispatch($request, RequestStatus::DRAFT_REJECTED_INTERNAL, $actor);

        Notification::assertSentTo($dataEntry, RequestReturnedNotification::class);
        Mail::assertNothingQueued();
        $this->assertOutboxQueued(NotificationType::REQUEST_RETURNED, $request, $dataEntry);
    }

    // -----------------------------------------------------------------------
    // VOTING_OPENED outbox email
    // -----------------------------------------------------------------------

    public function test_voting_opened_email_queued_for_executive_with_email_enabled(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $executive = User::query()->create([
            'name' => 'Executive Email On',
            'email' => 'exec-email-on@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::EXECUTIVE_MEMBER,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => [
                'notification_preferences' => ['voting_opened' => true],
                'email_notifications' => true,
            ],
        ]);

        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->dispatch($request, RequestStatus::EXECUTIVE_VOTING_OPEN, $this->dataEntryUser);

        Notification::assertSentTo($executive, VotingOpenedNotification::class);
        Mail::assertNothingQueued();
        $this->assertOutboxQueued(NotificationType::VOTING_OPENED, $request, $executive);
    }

    public function test_voting_opened_email_not_queued_when_email_off(): void
    {
        Mail::fake();
        Queue::fake();
        Notification::fake();

        $executive = User::query()->create([
            'name' => 'Executive Email Off',
            'email' => 'exec-email-off@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::EXECUTIVE_MEMBER,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => [
                'notification_preferences' => ['voting_opened' => true],
                'email_notifications' => false,
            ],
        ]);

        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->dispatch($request, RequestStatus::EXECUTIVE_VOTING_OPEN, $this->dataEntryUser);

        Notification::assertSentTo($executive, VotingOpenedNotification::class);
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    private function assertOutboxQueued(NotificationType $type, ImportRequest $request, User $recipient): void
    {
        $this->assertDatabaseHas('email_deliveries', [
            'notification_type' => $type->value,
            'event_id' => $request->id.':'.$request->status->value,
            'recipient_user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'channel' => 'mail',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SendEmailDelivery::class);
    }
}
