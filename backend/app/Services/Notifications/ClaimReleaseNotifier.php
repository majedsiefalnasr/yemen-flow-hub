<?php

namespace App\Services\Notifications;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\ClaimReleasedNotification;
use App\Services\Audit\AuditService;

class ClaimReleaseNotifier
{
    public function __construct(private readonly AuditService $auditService) {}

    public function dispatch(
        ImportRequest $request,
        string $reason,
        ?User $releasedBy = null,
        ?User $auditActor = null,
    ): void {
        $notification = new ClaimReleasedNotification($request, $reason, $releasedBy);

        User::query()
            ->where('role', UserRole::CBY_ADMIN->value)
            ->where('is_active', true)
            ->get()
            ->each(function (User $user) use ($notification): void {
                if ($this->shouldNotify($user, 'claim_released')) {
                    $user->notify($notification);
                }
            });

        $this->auditService->log(AuditAction::CLAIM_RELEASED, $auditActor, $request, [
            'reason' => $reason,
            'request_id' => $request->id,
            'reference_number' => $request->reference_number,
        ]);
    }

    private function shouldNotify(User $user, string $type): bool
    {
        $prefs = $user->user_preferences['notification_preferences'] ?? [];

        return ($prefs[$type] ?? true) !== false;
    }
}
