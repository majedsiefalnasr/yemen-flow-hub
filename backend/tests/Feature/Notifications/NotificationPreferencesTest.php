<?php

namespace Tests\Feature\Notifications;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Listeners\SendWorkflowNotifications;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\CustomsIssuedNotification;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestReturnedNotification;
use App\Notifications\RequestSubmittedNotification;
use App\Notifications\SwiftUploadRequestedNotification;
use App\Notifications\VotingOpenedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private User $dataEntryUser;
    private User $bankReviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB01', 'is_active' => true]);

        $this->dataEntryUser = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'de@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $this->bankReviewer = User::query()->create([
            'name' => 'Bank Reviewer',
            'email' => 'br@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status = RequestStatus::SUBMITTED): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $this->dataEntryUser->id,
                'currency' => 'USD',
                'amount' => 10000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function dispatchFor(ImportRequest $request, RequestStatus $targetStatus): void
    {
        app()->instance('workflow.transition.active', true);
        try {
            $request->status = $targetStatus;
            $request->saveQuietly();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $actor = $this->dataEntryUser;
        $event = new RequestTransitioned($request->fresh(), 'test_action', $actor);
        app(SendWorkflowNotifications::class)->handle($event);
    }

    // --- Preference enforcement: non-mandatory types ---

    public function test_user_with_voting_opened_disabled_does_not_receive_voting_notification(): void
    {
        Notification::fake();

        $executive = User::query()->create([
            'name' => 'Executive',
            'email' => 'exec@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::EXECUTIVE_MEMBER,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['voting_opened' => false]],
        ]);

        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->dispatchFor($request, RequestStatus::EXECUTIVE_VOTING_OPEN);

        Notification::assertNotSentTo($executive, VotingOpenedNotification::class);
    }

    public function test_user_with_voting_opened_enabled_receives_voting_notification(): void
    {
        Notification::fake();

        $executive = User::query()->create([
            'name' => 'Executive',
            'email' => 'exec2@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::EXECUTIVE_MEMBER,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['voting_opened' => true]],
        ]);

        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->dispatchFor($request, RequestStatus::EXECUTIVE_VOTING_OPEN);

        Notification::assertSentTo($executive, VotingOpenedNotification::class);
    }

    public function test_user_with_no_preferences_receives_all_notifications(): void
    {
        Notification::fake();

        $request = $this->makeRequest(RequestStatus::SUBMITTED);
        $this->dispatchFor($request, RequestStatus::SUBMITTED);

        Notification::assertSentTo($this->bankReviewer, RequestSubmittedNotification::class);
    }

    public function test_user_with_request_submitted_disabled_does_not_receive_submitted_notification(): void
    {
        Notification::fake();

        $reviewer = User::query()->create([
            'name' => 'Reviewer2',
            'email' => 'rev2@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['request_submitted' => false]],
        ]);

        $request = $this->makeRequest(RequestStatus::SUBMITTED);
        $this->dispatchFor($request, RequestStatus::SUBMITTED);

        Notification::assertNotSentTo($reviewer, RequestSubmittedNotification::class);
    }

    // --- Mandatory types: always delivered regardless of preferences ---

    public function test_request_rejected_is_always_delivered_even_when_disabled_in_preferences(): void
    {
        Notification::fake();

        // creator with request_rejected disabled
        $creator = User::query()->create([
            'name' => 'Creator2',
            'email' => 'creator2@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['request_rejected' => false]],
        ]);

        app()->instance('workflow.transition.active', true);
        $request = ImportRequest::query()->create([
            'bank_id' => $this->bank->id,
            'created_by' => $creator->id,
            'currency' => 'USD',
            'amount' => 10000,
            'supplier_name' => 'Supplier',
            'goods_description' => 'Goods',
            'port_of_entry' => 'Aden',
            'status' => RequestStatus::SUPPORT_REJECTED,
            'current_owner_role' => UserRole::DATA_ENTRY,
        ]);
        app()->offsetUnset('workflow.transition.active');

        $event = new RequestTransitioned($request->fresh(['creator']), 'test_action', $creator);
        app(SendWorkflowNotifications::class)->handle($event);

        // mandatory — must still be sent
        Notification::assertSentTo($creator, RequestRejectedNotification::class);
    }

    public function test_request_returned_is_always_delivered_even_when_disabled_in_preferences(): void
    {
        Notification::fake();

        $dataEntry = User::query()->create([
            'name' => 'DE2',
            'email' => 'de2@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['request_returned' => false]],
        ]);

        $request = $this->makeRequest(RequestStatus::DRAFT_REJECTED_INTERNAL);
        $this->dispatchFor($request, RequestStatus::DRAFT_REJECTED_INTERNAL);

        // mandatory — must still be sent to all DATA_ENTRY users in bank
        Notification::assertSentTo($dataEntry, RequestReturnedNotification::class);
    }

    // --- swift_upload_requested preference ---

    public function test_swift_officer_with_preference_disabled_skips_swift_notification(): void
    {
        Notification::fake();

        $swiftOfficer = User::query()->create([
            'name' => 'SWIFT Officer',
            'email' => 'swift@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SWIFT_OFFICER,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['swift_upload_requested' => false]],
        ]);

        $request = $this->makeRequest(RequestStatus::SUPPORT_APPROVED);
        $this->dispatchFor($request, RequestStatus::SUPPORT_APPROVED);

        Notification::assertNotSentTo($swiftOfficer, SwiftUploadRequestedNotification::class);
    }

    // --- customs_issued preference ---

    public function test_user_with_customs_issued_disabled_skips_customs_notification(): void
    {
        Notification::fake();

        $reviewer = User::query()->create([
            'name' => 'Reviewer3',
            'email' => 'rev3@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'user_preferences' => ['notification_preferences' => ['customs_issued' => false]],
        ]);

        $request = $this->makeRequest(RequestStatus::CUSTOMS_DECLARATION_ISSUED);
        $this->dispatchFor($request, RequestStatus::CUSTOMS_DECLARATION_ISSUED);

        Notification::assertNotSentTo($reviewer, CustomsIssuedNotification::class);
    }
}
