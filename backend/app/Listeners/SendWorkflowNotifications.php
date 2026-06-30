<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\CustomsIssuedNotification;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestReturnedNotification;
use App\Notifications\RequestSubmittedNotification;
use App\Notifications\SwiftUploadRequestedNotification;
use App\Notifications\VotingOpenedNotification;
use App\Services\Notifications\SendEmailNotification;

class SendWorkflowNotifications
{
    /**
     * In-app notification preference keys that bypass per-type opt-out.
     * Email mandatory types are enforced separately in {@see SendEmailNotification}.
     */
    private const MANDATORY_TYPES = ['request_rejected', 'request_returned', 'request_approved'];

    public function __construct(private readonly SendEmailNotification $sendEmailNotification) {}

    public function handle(RequestTransitioned $event): void
    {
        $request = $event->requestModel->fresh('creator');
        $status = $request->status;

        if ($status === RequestStatus::SUBMITTED) {
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get()
                ->each(function (User $u) use ($request): void {
                    if ($this->shouldNotify($u, 'request_submitted')) {
                        $u->notify(new RequestSubmittedNotification($request));
                    }
                });
        }

        if (in_array($status, [RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_APPROVED, RequestStatus::EXECUTIVE_APPROVED], true)) {
            $creator = $request->creator;
            if ($creator && $this->shouldNotify($creator, 'request_approved')) {
                $creator->notify(new RequestApprovedNotification($request));
            }

            $this->dispatchWorkflowEmail(NotificationType::REQUEST_APPROVED, $request);
        }

        if (in_array($status, [RequestStatus::SUPPORT_REJECTED, RequestStatus::EXECUTIVE_REJECTED], true)) {
            $creator = $request->creator;
            $reviewers = User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get();
            if ($creator && $this->shouldNotify($creator, 'request_rejected')) {
                $creator->notify(new RequestRejectedNotification($request));
            }
            $reviewers->each(function (User $u) use ($request): void {
                if ($this->shouldNotify($u, 'request_rejected')) {
                    $u->notify(new RequestRejectedNotification($request));
                }
            });

            $this->dispatchWorkflowEmail(NotificationType::REQUEST_REJECTED, $request);
        }

        if ($status === RequestStatus::BANK_REJECTED) {
            $comment = $event->reason;
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::DATA_ENTRY->value)->get()
                ->each(function (User $u) use ($request, $comment): void {
                    $u->notify(new RequestRejectedNotification($request, true, $comment));
                });

            $this->dispatchWorkflowEmail(NotificationType::REQUEST_REJECTED, $request, [
                'terminal' => true,
                'comment' => $comment,
            ]);
        }

        if (in_array($status, [RequestStatus::DRAFT_REJECTED_INTERNAL, RequestStatus::BANK_RETURNED, RequestStatus::SUPPORT_RETURNED], true)) {
            $fromRole = $event->actor->role->value ?? '';
            $comment = $event->reason;
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::DATA_ENTRY->value)->get()
                ->each(function (User $u) use ($request, $fromRole, $comment): void {
                    if ($this->shouldNotify($u, 'request_returned')) {
                        $u->notify(new RequestReturnedNotification($request, $fromRole, $comment));
                    }
                });

            $this->dispatchWorkflowEmail(NotificationType::REQUEST_RETURNED, $request, [
                'fromRole' => $fromRole,
                'comment' => $comment,
            ]);
        }

        if ($status === RequestStatus::SUPPORT_APPROVED) {
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::SWIFT_OFFICER->value)->get()
                ->each(function (User $u) use ($request): void {
                    if ($this->shouldNotify($u, 'swift_upload_requested')) {
                        $u->notify(new SwiftUploadRequestedNotification($request));
                    }
                });
        }

        if ($status === RequestStatus::EXECUTIVE_VOTING_OPEN) {
            User::query()->whereIn('role', [UserRole::EXECUTIVE_MEMBER->value, UserRole::COMMITTEE_DIRECTOR->value])->get()
                ->each(function (User $u) use ($request): void {
                    if ($this->shouldNotify($u, 'voting_opened')) {
                        $u->notify(new VotingOpenedNotification($request));
                    }
                });

            $this->dispatchWorkflowEmail(NotificationType::VOTING_OPENED, $request);
        }

        if ($status === RequestStatus::CUSTOMS_DECLARATION_ISSUED) {
            $creator = $request->creator;
            if ($creator && $this->shouldNotify($creator, 'customs_issued')) {
                $creator->notify(new CustomsIssuedNotification($request));
            }
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get()
                ->each(function (User $u) use ($request): void {
                    if ($this->shouldNotify($u, 'customs_issued')) {
                        $u->notify(new CustomsIssuedNotification($request));
                    }
                });
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchWorkflowEmail(NotificationType $type, ImportRequest $request, array $context = []): void
    {
        $this->sendEmailNotification->sendWorkflow($type, $request, $context);
    }

    private function shouldNotify(User $user, string $type): bool
    {
        if (in_array($type, self::MANDATORY_TYPES, true)) {
            return true;
        }

        $prefs = $user->user_preferences['notification_preferences'] ?? [];

        return ($prefs[$type] ?? true) !== false;
    }
}
