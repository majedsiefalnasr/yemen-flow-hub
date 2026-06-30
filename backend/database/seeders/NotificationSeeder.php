<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Generate notifications tied to actual request workflow events.
     * Each request emits 1-3 notifications addressed to the role group that handled
     * (or currently owns) the request, mirroring what the live notification dispatcher
     * would emit. ~30% are pre-marked read to give the UI a mix.
     *
     * Cap: max 1 000 total rows so the notifications table stays demo-sized.
     */
    private const MAX_TOTAL = 1000;

    public function run(): void
    {
        $requests = ImportRequest::query()->with('bank')->orderBy('id')->get();
        if ($requests->isEmpty()) {
            return;
        }

        // Group users by role and by (role, bank_id) for fast lookup
        $usersByRole = User::query()->where('is_active', true)->get()->groupBy(fn (User $u) => $u->role?->value);

        $bulk = [];
        $totalInserted = 0;
        $flush = function () use (&$bulk, &$totalInserted): void {
            if (empty($bulk)) {
                return;
            }
            foreach (array_chunk($bulk, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
                $totalInserted += count($chunk);
            }
            $bulk = [];
        };

        foreach ($requests as $request) {
            if ($totalInserted + count($bulk) >= self::MAX_TOTAL) {
                break;
            }

            $events = $this->eventsFor($request);
            foreach ($events as $event) {
                $recipients = $this->recipients($event['audience'], $request, $usersByRole);
                if ($recipients->isEmpty()) {
                    continue;
                }

                foreach ($recipients as $user) {
                    if ($totalInserted + count($bulk) >= self::MAX_TOTAL) {
                        break 3;
                    }
                    $createdAt = ($event['at'] ?? now())->copy();
                    $readAt = fake()->boolean(30) ? $createdAt->copy()->addMinutes(rand(5, 720)) : null;

                    $bulk[] = [
                        'id' => (string) Str::uuid(),
                        'type' => $event['type'],
                        'notifiable_type' => User::class,
                        'notifiable_id' => $user->id,
                        'data' => json_encode([
                            'request_id' => $request->id,
                            'request_reference' => $request->reference_number,
                            'message_ar' => $event['ar'],
                            'message_en' => $event['en'],
                        ], JSON_UNESCAPED_UNICODE),
                        'read_at' => $readAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];

                    if (count($bulk) >= 500) {
                        $flush();
                    }
                }
            }
        }

        $flush();
    }

    /**
     * @return array<int, array{type: string, audience: string, ar: string, en: string, at: Carbon|null}>
     */
    private function eventsFor(ImportRequest $request): array
    {
        $events = [];
        $status = $request->status;

        // Every request that was submitted notifies its bank reviewers
        if ($request->submitted_at) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestSubmittedNotification',
                'audience' => 'bank_reviewers',
                'ar' => 'تم استلام طلب استيراد جديد بانتظار مراجعتك.',
                'en' => 'A new import request awaits your review.',
                'at' => $request->submitted_at,
            ];
        }

        // Bank approved → notify support committee
        if ($request->bank_approved_at) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestApprovedNotification',
                'audience' => 'support_committee',
                'ar' => 'طلب جاهز لمراجعة لجنة المساندة.',
                'en' => 'A request is ready for support committee review.',
                'at' => $request->bank_approved_at,
            ];
        }

        // Bank returned (need corrections) → data entry creator
        if ($status === RequestStatus::DRAFT_REJECTED_INTERNAL || $status === RequestStatus::BANK_RETURNED) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestReturnedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'تمت إعادة الطلب من مراجع البنك للتعديل — يرجى مراجعة الملاحظات.',
                'en' => 'Your request was returned by the bank reviewer for corrections.',
                'at' => $request->updated_at,
            ];
        }

        // Bank rejected (terminal) → data entry creator + bank admin
        if ($status === RequestStatus::BANK_REJECTED) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestRejectedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'تم رفض الطلب نهائياً من البنك.',
                'en' => 'Your request has been permanently rejected by the bank.',
                'at' => $request->updated_at,
            ];
            $events[] = [
                'type' => 'App\\Notifications\\RequestRejectedNotification',
                'audience' => 'bank_admin',
                'ar' => 'تم رفض أحد الطلبات نهائياً — يرجى المراجعة.',
                'en' => 'A request was permanently rejected — please review.',
                'at' => $request->updated_at,
            ];
        }

        // Support rejected → notify bank reviewers
        if ($status === RequestStatus::SUPPORT_REJECTED) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestReturnedNotification',
                'audience' => 'bank_reviewers',
                'ar' => 'رُفض الطلب من لجنة المساندة. يرجى المراجعة.',
                'en' => 'Request rejected by support committee.',
                'at' => $request->updated_at,
            ];
        }

        // Support returned → notify data entry creator
        if ($status === RequestStatus::SUPPORT_RETURNED) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestReturnedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'أعادت لجنة المساندة الطلب. يرجى استيفاء المتطلبات الإضافية.',
                'en' => 'Support committee returned your request — please provide additional documentation.',
                'at' => $request->updated_at,
            ];
        }

        // Support approved → SWIFT officer
        if ($request->support_approved_at) {
            $events[] = [
                'type' => 'App\\Notifications\\SwiftUploadRequestedNotification',
                'audience' => 'swift_officer',
                'ar' => 'طلب جاهز لرفع رسالة SWIFT.',
                'en' => 'Ready for SWIFT message upload.',
                'at' => $request->support_approved_at,
            ];
        }

        // SWIFT uploaded → notify Committee Director
        if ($request->swift_uploaded_at) {
            $events[] = [
                'type' => 'App\\Notifications\\VotingOpenedNotification',
                'audience' => 'director',
                'ar' => 'رسالة SWIFT جاهزة لفتح التصويت.',
                'en' => 'SWIFT uploaded — ready to open voting.',
                'at' => $request->swift_uploaded_at,
            ];
        }

        // Voting open → notify all executive members
        if (in_array($status, [
            RequestStatus::EXECUTIVE_VOTING_OPEN,
            RequestStatus::EXECUTIVE_VOTING_CLOSED,
            RequestStatus::EXECUTIVE_APPROVED,
            RequestStatus::EXECUTIVE_REJECTED,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED,
            RequestStatus::COMPLETED,
        ], true)) {
            $events[] = [
                'type' => 'App\\Notifications\\VotingOpenedNotification',
                'audience' => 'executive_members',
                'ar' => 'تم فتح جلسة تصويت تنفيذية — يرجى الإدلاء بصوتك.',
                'en' => 'An executive voting session has opened — please cast your vote.',
                'at' => $request->voting_opened_at ?? ($request->swift_uploaded_at ?? $request->updated_at)?->copy()->addDays(2),
            ];
        }

        // Executive approved → notify director (ready for FX confirmation)
        if ($status === RequestStatus::EXECUTIVE_APPROVED) {
            $events[] = [
                'type' => 'App\\Notifications\\ExecutiveApprovedNotification',
                'audience' => 'director',
                'ar' => 'وافقت اللجنة التنفيذية على الطلب. جاهز لإصدار وثيقة تأكيد المصارفة الخارجية.',
                'en' => 'Executive committee approved the request. Ready to issue the external FX confirmation document.',
                'at' => $request->executive_decided_at,
            ];
        }

        // Executive rejected → notify bank reviewer and data entry creator
        if ($status === RequestStatus::EXECUTIVE_REJECTED) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestRejectedNotification',
                'audience' => 'bank_reviewers',
                'ar' => 'رفضت اللجنة التنفيذية الطلب.',
                'en' => 'Executive committee rejected the request.',
                'at' => $request->executive_decided_at,
            ];
            $events[] = [
                'type' => 'App\\Notifications\\RequestRejectedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'رُفض طلبك من اللجنة التنفيذية.',
                'en' => 'Your request was rejected by the executive committee.',
                'at' => $request->executive_decided_at,
            ];
        }

        // FX confirmation issued → notify data entry creator + bank reviewers
        if ($request->customs_issued_at) {
            $events[] = [
                'type' => 'App\\Notifications\\CustomsIssuedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'تم إصدار وثيقة تأكيد المصارفة الخارجية لطلبك.',
                'en' => 'External FX confirmation document issued for your request.',
                'at' => $request->customs_issued_at,
            ];
            $events[] = [
                'type' => 'App\\Notifications\\CustomsIssuedNotification',
                'audience' => 'bank_reviewers',
                'ar' => 'تم إصدار وثيقة تأكيد المصارفة الخارجية.',
                'en' => 'External FX confirmation document issued.',
                'at' => $request->customs_issued_at,
            ];
        }

        return $events;
    }

    /**
     * @param  Collection<string, Collection<int, User>>  $usersByRole
     * @return Collection<int, User>
     */
    private function recipients(string $audience, ImportRequest $request, $usersByRole)
    {
        return match ($audience) {
            'bank_reviewers' => ($usersByRole->get(UserRole::BANK_REVIEWER->value) ?? collect())
                ->where('bank_id', $request->bank_id)
                ->values(),
            'bank_admin' => ($usersByRole->get(UserRole::BANK_ADMIN->value) ?? collect())
                ->where('bank_id', $request->bank_id)
                ->values(),
            'data_entry_creator' => User::query()->where('id', $request->created_by)->get(),
            'support_committee' => $usersByRole->get(UserRole::SUPPORT_COMMITTEE->value) ?? collect(),
            'swift_officer' => ($usersByRole->get(UserRole::SWIFT_OFFICER->value) ?? collect())
                ->where('bank_id', $request->bank_id)
                ->values(),
            'director' => $usersByRole->get(UserRole::COMMITTEE_DIRECTOR->value) ?? collect(),
            'executive_members' => $usersByRole->get(UserRole::EXECUTIVE_MEMBER->value) ?? collect(),
            default => collect(),
        };
    }
}
