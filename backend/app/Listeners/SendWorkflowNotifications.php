<?php

namespace App\Listeners;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Models\User;
use App\Notifications\CustomsIssuedNotification;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestReturnedNotification;
use App\Notifications\RequestSubmittedNotification;
use App\Notifications\SwiftUploadRequestedNotification;
use App\Notifications\VotingOpenedNotification;

class SendWorkflowNotifications
{
    public function handle(RequestTransitioned $event): void
    {
        $request = $event->requestModel->fresh('creator');
        $status = $request->status;

        if ($status === RequestStatus::SUBMITTED) {
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get()
                ->each(fn (User $u) => $u->notify(new RequestSubmittedNotification($request)));
        }

        if (in_array($status, [RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_APPROVED, RequestStatus::EXECUTIVE_APPROVED], true)) {
            $request->creator?->notify(new RequestApprovedNotification($request));
        }

        if (in_array($status, [RequestStatus::SUPPORT_REJECTED, RequestStatus::EXECUTIVE_REJECTED], true)) {
            $creator = $request->creator;
            $reviewers = User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get();
            if ($creator) {
                $creator->notify(new RequestRejectedNotification($request));
            }
            $reviewers->each(fn (User $u) => $u->notify(new RequestRejectedNotification($request)));
        }

        if ($status === RequestStatus::DRAFT_REJECTED_INTERNAL) {
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::DATA_ENTRY->value)->get()
                ->each(fn (User $u) => $u->notify(new RequestReturnedNotification($request)));
        }

        if ($status === RequestStatus::SUPPORT_APPROVED) {
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::SWIFT_OFFICER->value)->get()
                ->each(fn (User $u) => $u->notify(new SwiftUploadRequestedNotification($request)));
        }

        if ($status === RequestStatus::EXECUTIVE_VOTING_OPEN) {
            User::query()->whereIn('role', [UserRole::EXECUTIVE_MEMBER->value, UserRole::COMMITTEE_DIRECTOR->value])->get()
                ->each(fn (User $u) => $u->notify(new VotingOpenedNotification($request)));
        }

        if ($status === RequestStatus::CUSTOMS_DECLARATION_ISSUED) {
            $creator = $request->creator;
            if ($creator) {
                $creator->notify(new CustomsIssuedNotification($request));
            }
            User::query()->where('bank_id', $request->bank_id)->where('role', UserRole::BANK_REVIEWER->value)->get()
                ->each(fn (User $u) => $u->notify(new CustomsIssuedNotification($request)));
        }
    }
}
