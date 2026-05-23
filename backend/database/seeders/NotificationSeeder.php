<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Generate notifications tied to actual request workflow events.
     * Each request emits 1-3 notifications addressed to the role group that handled
     * (or currently owns) the request, mirroring what the live notification dispatcher
     * would emit. ~30% are pre-marked read to give the UI a mix.
     */
    public function run(): void
    {
        $requests = ImportRequest::query()->with('bank')->orderBy('id')->get();
        if ($requests->isEmpty()) {
            return;
        }

        // Group users by role and by (role, bank_id) for fast lookup
        $usersByRole = User::query()->where('is_active', true)->get()->groupBy(fn (User $u) => $u->role?->value);

        $bulk = [];
        $flush = function () use (&$bulk): void {
            if (empty($bulk)) {
                return;
            }
            foreach (array_chunk($bulk, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
            $bulk = [];
        };

        foreach ($requests as $request) {
            $events = $this->eventsFor($request);
            foreach ($events as $event) {
                $recipients = $this->recipients($event['audience'], $request, $usersByRole);
                if ($recipients->isEmpty()) {
                    continue;
                }

                foreach ($recipients as $user) {
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
     * @return array<int, array{type: string, audience: string, ar: string, en: string, at: \Illuminate\Support\Carbon|null}>
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
                'ar' => 'طلب جاهز لمراجعة لجنة الدعم.',
                'en' => 'A request is ready for support committee review.',
                'at' => $request->bank_approved_at,
            ];
        }

        // Bank-rejected back to data entry
        if ($status === RequestStatus::DRAFT_REJECTED_INTERNAL) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestRejectedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'تمت إعادة الطلب من مراجع البنك للتعديل.',
                'en' => 'Your request was returned by the bank reviewer.',
                'at' => $request->updated_at,
            ];
        }

        // Support rejected → notify bank
        if ($status === RequestStatus::SUPPORT_REJECTED) {
            $events[] = [
                'type' => 'App\\Notifications\\RequestReturnedNotification',
                'audience' => 'bank_reviewers',
                'ar' => 'رفض/إعادة من لجنة الدعم.',
                'en' => 'Returned by support committee.',
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
                'ar' => 'تم فتح جلسة تصويت تنفيذية.',
                'en' => 'An executive voting session has opened.',
                'at' => ($request->swift_uploaded_at ?? $request->updated_at)?->copy()->addDays(2),
            ];
        }

        // Customs issued → notify data entry creator + bank reviewers
        if ($request->customs_issued_at) {
            $events[] = [
                'type' => 'App\\Notifications\\CustomsIssuedNotification',
                'audience' => 'data_entry_creator',
                'ar' => 'تم إصدار البيان الجمركي لطلبك.',
                'en' => 'Customs declaration issued for your request.',
                'at' => $request->customs_issued_at,
            ];
            $events[] = [
                'type' => 'App\\Notifications\\CustomsIssuedNotification',
                'audience' => 'bank_reviewers',
                'ar' => 'تم إصدار البيان الجمركي.',
                'en' => 'Customs declaration issued.',
                'at' => $request->customs_issued_at,
            ];
        }

        return $events;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, User>>  $usersByRole
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipients(string $audience, ImportRequest $request, $usersByRole)
    {
        return match ($audience) {
            'bank_reviewers' => ($usersByRole->get(UserRole::BANK_REVIEWER->value) ?? collect())
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
