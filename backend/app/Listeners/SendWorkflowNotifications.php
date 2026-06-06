<?php

namespace App\Listeners;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Mail\RequestApprovedMail;
use App\Mail\RequestRejectedMail;
use App\Mail\RequestReturnedMail;
use App\Mail\VotingOpenedMail;
use App\Models\User;
use App\Notifications\CustomsIssuedNotification;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestReturnedNotification;
use App\Notifications\RequestSubmittedNotification;
use App\Notifications\SwiftUploadRequestedNotification;
use App\Notifications\VotingOpenedNotification;
use Illuminate\Support\Facades\Mail;

class SendWorkflowNotifications
{
    /** Types that must always be delivered regardless of user preferences (governance-critical). */
    private const MANDATORY_TYPES = ['request_rejected', 'request_returned', 'request_approved'];

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
                if ($this->shouldEmailNotify($creator)) {
                    Mail::to($creator->email)->queue(new RequestApprovedMail($request));
                }
            }
        }

        if (in_array($status, [RequestStatus::SUPPORT_REJECTED, RequestStatus::EXECUTIVE_REJECTED], true)) {
            $creator = $request->creator;
            $reviewers = User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get();
            if ($creator && $this->shouldNotify($creator, 'request_rejected')) {
                $creator->notify(new RequestRejectedNotification($request));
                if ($this->shouldEmailNotify($creator)) {
                    Mail::to($creator->email)->queue(new RequestRejectedMail($request));
                }
            }
            $reviewers->each(function (User $u) use ($request): void {
                if ($this->shouldNotify($u, 'request_rejected')) {
                    $u->notify(new RequestRejectedNotification($request));
                    if ($this->shouldEmailNotify($u)) {
                        Mail::to($u->email)->queue(new RequestRejectedMail($request));
                    }
                }
            });
        }

        if ($status === RequestStatus::BANK_REJECTED) {
            $comment = $event->reason;
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::DATA_ENTRY->value)->get()
                ->each(function (User $u) use ($request, $comment): void {
                    $u->notify(new RequestRejectedNotification($request, true, $comment));
                    if ($this->shouldEmailNotify($u)) {
                        Mail::to($u->email)->queue(new RequestRejectedMail($request, true, $comment));
                    }
                });
        }

        if (in_array($status, [RequestStatus::DRAFT_REJECTED_INTERNAL, RequestStatus::BANK_RETURNED, RequestStatus::SUPPORT_RETURNED], true)) {
            $fromRole = $event->actor->role->value ?? '';
            $comment = $event->reason;
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::DATA_ENTRY->value)->get()
                ->each(function (User $u) use ($request, $fromRole, $comment): void {
                    if ($this->shouldNotify($u, 'request_returned')) {
                        $u->notify(new RequestReturnedNotification($request, $fromRole, $comment));
                        if ($this->shouldEmailNotify($u)) {
                            Mail::to($u->email)->queue(new RequestReturnedMail($request, $fromRole, $comment));
                        }
                    }
                });
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
                        if ($this->shouldEmailNotify($u)) {
                            Mail::to($u->email)->queue(new VotingOpenedMail($request));
                        }
                    }
                });
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

    private function shouldNotify(User $user, string $type): bool
    {
        if (in_array($type, self::MANDATORY_TYPES, true)) {
            return true;
        }

        $prefs = $user->user_preferences['notification_preferences'] ?? [];

        return ($prefs[$type] ?? true) !== false;
    }

    private function shouldEmailNotify(User $user): bool
    {
        return (bool) ($user->user_preferences['email_notifications'] ?? false);
    }
}
