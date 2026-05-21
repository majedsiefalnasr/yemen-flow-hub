<?php

namespace Tests\Unit\Notifications;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
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
use Tests\TestCase;

class NotificationPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(): ImportRequest
    {
        $bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB01', 'is_active' => true]);
        $user = User::query()->create([
            'name' => 'Creator',
            'email' => 'creator@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $user->id,
                'currency' => 'USD',
                'amount' => 50000,
                'supplier_name' => 'ACME',
                'goods_description' => 'Electronics',
                'port_of_entry' => 'Aden',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function callToArray(object $notification): array
    {
        return $notification->toArray(new \stdClass());
    }

    public function test_request_submitted_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new RequestSubmittedNotification($request));

        $this->assertSame('request_submitted', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
        $this->assertStringContainsString($request->reference_number, $payload['message']);
    }

    public function test_request_approved_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new RequestApprovedNotification($request));

        $this->assertSame('request_approved', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_request_rejected_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new RequestRejectedNotification($request));

        $this->assertSame('request_rejected', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_request_returned_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new RequestReturnedNotification($request));

        $this->assertSame('request_returned', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_request_returned_payload_includes_from_role_and_comment(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new RequestReturnedNotification($request, 'BANK_REVIEWER', 'يرجى تصحيح المستندات'));

        $this->assertSame('request_returned', $payload['type']);
        $this->assertSame('BANK_REVIEWER', $payload['from_role']);
        $this->assertSame('يرجى تصحيح المستندات', $payload['comment']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_request_returned_payload_defaults_when_no_from_role_or_comment(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new RequestReturnedNotification($request));

        $this->assertArrayHasKey('from_role', $payload);
        $this->assertArrayHasKey('comment', $payload);
        $this->assertSame('', $payload['from_role']);
        $this->assertNull($payload['comment']);
    }

    public function test_swift_upload_requested_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new SwiftUploadRequestedNotification($request));

        $this->assertSame('swift_upload_requested', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_voting_opened_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new VotingOpenedNotification($request));

        $this->assertSame('voting_opened', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_customs_issued_payload(): void
    {
        $request = $this->makeRequest();
        $payload = $this->callToArray(new CustomsIssuedNotification($request));

        $this->assertSame('customs_issued', $payload['type']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->reference_number, $payload['reference_number']);
    }

    public function test_all_notifications_have_required_fields(): void
    {
        $request = $this->makeRequest();
        $notifications = [
            new RequestSubmittedNotification($request),
            new RequestApprovedNotification($request),
            new RequestRejectedNotification($request),
            new RequestReturnedNotification($request),
            new SwiftUploadRequestedNotification($request),
            new VotingOpenedNotification($request),
            new CustomsIssuedNotification($request),
        ];

        foreach ($notifications as $notification) {
            $payload = $this->callToArray($notification);
            $class = get_class($notification);
            $this->assertArrayHasKey('type', $payload, "$class missing type");
            $this->assertArrayHasKey('message', $payload, "$class missing message");
            $this->assertArrayHasKey('request_id', $payload, "$class missing request_id");
            $this->assertArrayHasKey('reference_number', $payload, "$class missing reference_number");
            $this->assertNotEmpty($payload['type'], "$class type is empty");
            $this->assertNotEmpty($payload['message'], "$class message is empty");
        }
    }
}
