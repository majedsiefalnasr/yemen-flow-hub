<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Resources\ImportRequestResource;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/dashboard/stats',
        tags: ['Dashboard'],
        summary: 'Dashboard stats',
        description: 'Returns role-scoped dashboard metrics.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard stats retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function stats()
    {
        $user = request()->user();

        return match (true) {
            $user->hasRole(UserRole::DATA_ENTRY)          => $this->dataEntryStats($user),
            $user->hasRole(UserRole::BANK_REVIEWER)       => $this->bankReviewerStats($user),
            $user->hasRole(UserRole::BANK_ADMIN)          => $this->bankAdminStats($user),
            $user->hasRole(UserRole::SUPPORT_COMMITTEE)   => $this->supportCommitteeStats($user),
            $user->hasRole(UserRole::SWIFT_OFFICER)       => $this->swiftOfficerStats($user),
            $user->hasRole(UserRole::EXECUTIVE_MEMBER)    => $this->executiveMemberStats($user),
            $user->hasRole(UserRole::COMMITTEE_DIRECTOR)  => $this->committeeDirectorStats(),
            $user->hasRole(UserRole::CBY_ADMIN)           => $this->cbyadminStats(),
            default                                       => ApiResponse::success([], 'Dashboard stats retrieved.'),
        };
    }

    private function bankAdminStats($user): \Illuminate\Http\JsonResponse
    {
        $asOf = CarbonImmutable::now();
        $bankId = $user->bank_id ? (int) $user->bank_id : null;

        if ($bankId === null) {
            return ApiResponse::success(
                $this->emptyBankAdminStats($asOf),
                'Dashboard stats retrieved.'
            );
        }

        $base = ImportRequest::query()->where('bank_id', $bankId);

        $approvedStatuses = [
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
        ];

        $rejectedStatuses = $this->bankFacingRejectedStatuses();

        $total    = (clone $base)->count();
        $pending  = (clone $base)->whereIn('status', [RequestStatus::SUBMITTED->value, RequestStatus::BANK_REVIEW->value])->count();
        $approved = (clone $base)->whereIn('status', $approvedStatuses)->count();
        $rejected = (clone $base)->whereIn('status', $rejectedStatuses)->count();
        $atCby    = (clone $base)->whereIn('status', [
            RequestStatus::BANK_APPROVED->value,
            RequestStatus::SUPPORT_REVIEW_PENDING->value,
            RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            RequestStatus::SUPPORT_APPROVED->value,
            RequestStatus::WAITING_FOR_SWIFT->value,
            RequestStatus::SWIFT_UPLOADED->value,
            RequestStatus::WAITING_FOR_VOTING_OPEN->value,
            RequestStatus::EXECUTIVE_VOTING_OPEN->value,
            RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
        ])->count();
        $activeUsers = User::query()
            ->where('bank_id', $bankId)
            ->whereIn('role', [UserRole::DATA_ENTRY->value, UserRole::BANK_REVIEWER->value])
            ->where('is_active', true)
            ->count();

        $totalFinancedAmount = (float) (clone $base)
            ->whereIn('status', $approvedStatuses)
            ->sum('amount');

        $recentRequests = (clone $base)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->with(['bank'])
            ->get();

        $monthlyRequests = $this->bankMonthlyRequests($bankId, $asOf);

        return ApiResponse::success([
            // New Story 6.3.2 fields
            'total'                 => $total,
            'pending'               => $pending,
            'approved'              => $approved,
            'rejected'              => $rejected,
            'total_financed_amount' => $totalFinancedAmount,
            'monthly_requests'      => $monthlyRequests,
            // Backward-compat fields retained for existing clients
            'pending_bank_review'   => $pending,
            'at_cby'                => $atCby,
            'completed'             => $approved,
            'active_users'          => $activeUsers,
            'recent_requests'       => ImportRequestResource::collection($recentRequests)->toArray(request()),
        ], 'Dashboard stats retrieved.');
    }

    private function bankMonthlyRequests(int $bankId, CarbonImmutable $asOf): array
    {
        $timezone = config('app.timezone', 'UTC');
        $anchorMonth = $asOf->setTimezone($timezone)->startOfMonth();
        $windowStart = $anchorMonth->subMonths(5);

        $monthKeys = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthKeys[] = $anchorMonth->subMonths($i)->format('Y-m');
        }

        $counts = array_fill_keys($monthKeys, 0);

        // Group in app layer using UTC to avoid DB/session timezone drift at month boundaries.
        $createdAtValues = ImportRequest::query()
            ->where('bank_id', $bankId)
            ->where('created_at', '>=', $windowStart)
            ->pluck('created_at');

        foreach ($createdAtValues as $createdAt) {
            $monthKey = ($createdAt instanceof \DateTimeInterface
                ? CarbonImmutable::instance($createdAt)
                : CarbonImmutable::parse((string) $createdAt))
                ->setTimezone($timezone)
                ->format('Y-m');
            if (array_key_exists($monthKey, $counts)) {
                $counts[$monthKey]++;
            }
        }

        return array_map(
            fn (string $month): array => ['month' => $month, 'count' => $counts[$month]],
            $monthKeys
        );
    }

    private function emptyBankAdminStats(CarbonImmutable $asOf): array
    {
        return [
            // New Story 6.3.2 fields
            'total'                 => 0,
            'pending'               => 0,
            'approved'              => 0,
            'rejected'              => 0,
            'total_financed_amount' => 0.0,
            'monthly_requests'      => $this->bankMonthlyRequestsForEmptyBank($asOf),
            // Backward-compat fields retained for existing clients
            'pending_bank_review'   => 0,
            'at_cby'                => 0,
            'completed'             => 0,
            'active_users'          => 0,
            'recent_requests'       => [],
        ];
    }

    private function bankMonthlyRequestsForEmptyBank(CarbonImmutable $asOf): array
    {
        $timezone = config('app.timezone', 'UTC');
        $anchorMonth = $asOf->setTimezone($timezone)->startOfMonth();
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $months[] = [
                'month' => $anchorMonth->subMonths($i)->format('Y-m'),
                'count' => 0,
            ];
        }

        return $months;
    }

    private function dataEntryStats($user)
    {
        $base = ImportRequest::query()->forUser($user);

        $draft     = (clone $base)->where('status', RequestStatus::DRAFT)->count();
        $returned  = (clone $base)->where('status', RequestStatus::DRAFT_REJECTED_INTERNAL)->count();

        $underCbyStatuses = [
            RequestStatus::BANK_APPROVED,
            RequestStatus::SUPPORT_REVIEW_PENDING,
            RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
            RequestStatus::SUPPORT_APPROVED,
            RequestStatus::WAITING_FOR_SWIFT,
            RequestStatus::SWIFT_UPLOADED,
            RequestStatus::WAITING_FOR_VOTING_OPEN,
            RequestStatus::EXECUTIVE_VOTING_OPEN,
            RequestStatus::EXECUTIVE_VOTING_CLOSED,
        ];
        $underCby = (clone $base)->whereIn('status', array_map(fn ($s) => $s->value, $underCbyStatuses))->count();

        $completedStatuses = [
            RequestStatus::EXECUTIVE_APPROVED,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED,
            RequestStatus::COMPLETED,
        ];
        $completed = (clone $base)->whereIn('status', array_map(fn ($s) => $s->value, $completedStatuses))->count();

        $returnedRequests = (clone $base)
            ->where('status', RequestStatus::DRAFT_REJECTED_INTERNAL)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $draftRequests = (clone $base)
            ->where('status', RequestStatus::DRAFT)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $recentRequests = (clone $base)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'draft'               => $draft,
            'returned'            => $returned,
            'under_cby_processing' => $underCby,
            'completed'           => $completed,
            'draft_requests'      => ImportRequestResource::collection($draftRequests)->resolve(),
            'returned_requests'   => ImportRequestResource::collection($returnedRequests)->resolve(),
            'recent_requests'     => ImportRequestResource::collection($recentRequests)->resolve(),
        ], 'Dashboard stats retrieved.');
    }

    private function bankReviewerStats($user)
    {
        $base = ImportRequest::query()->forUser($user);

        $pendingReview = (clone $base)->whereIn('status', [
            RequestStatus::SUBMITTED->value,
            RequestStatus::BANK_REVIEW->value,
        ])->count();

        $atCbyStatuses = [
            RequestStatus::BANK_APPROVED,
            RequestStatus::SUPPORT_REVIEW_PENDING,
            RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
            RequestStatus::SUPPORT_APPROVED,
            RequestStatus::WAITING_FOR_SWIFT,
            RequestStatus::SWIFT_UPLOADED,
            RequestStatus::WAITING_FOR_VOTING_OPEN,
            RequestStatus::EXECUTIVE_VOTING_OPEN,
            RequestStatus::EXECUTIVE_VOTING_CLOSED,
        ];
        $atCby = (clone $base)->whereIn('status', array_map(fn ($s) => $s->value, $atCbyStatuses))->count();

        $returnedBySupport = (clone $base)->where('status', RequestStatus::SUPPORT_REJECTED)->count();

        $approvedCompleted = (clone $base)->whereIn('status', [
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
        ])->count();

        $reviewQueue = (clone $base)
            ->whereIn('status', [RequestStatus::SUBMITTED->value, RequestStatus::BANK_REVIEW->value])
            ->orderBy('updated_at')
            ->limit(50)
            ->with(['bank', 'creator'])
            ->get();

        $downstreamQueue = (clone $base)
            ->whereIn('status', array_map(fn ($s) => $s->value, $atCbyStatuses))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->with(['bank'])
            ->get();

        return ApiResponse::success([
            'pending_review'      => $pendingReview,
            'at_cby'              => $atCby,
            'returned_by_support' => $returnedBySupport,
            'approved_completed'  => $approvedCompleted,
            'review_queue'        => ImportRequestResource::collection($reviewQueue)->resolve(),
            'downstream_queue'    => ImportRequestResource::collection($downstreamQueue)->resolve(),
        ], 'Dashboard stats retrieved.');
    }

    // SUPPORT_COMMITTEE is CBY-global by institutional design: committee members review
    // requests from all banks. No bank_id filter is applied here — this is intentional
    // governance behaviour, not a missing tenant scope.
    private function supportCommitteeStats(\App\Models\User $user): \Illuminate\Http\JsonResponse
    {
        $base = ImportRequest::query();

        $waitingForClaim = (clone $base)
            ->where('status', RequestStatus::SUPPORT_REVIEW_PENDING->value)
            ->count();

        $activeByMe = (clone $base)
            ->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value)
            ->where('claimed_by', $user->id)
            ->count();

        $claimedByOthers = (clone $base)
            ->whereNotNull('claimed_by')
            ->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value)
            ->where('claimed_by', '!=', $user->id)
            ->count();

        // Rolling 7-day window — "معتمد حديثاً" reflects active committee throughput,
        // not a cumulative total. Scoped globally (all SC members), not per-reviewer.
        $recentlyApproved = (clone $base)
            ->where('status', RequestStatus::SUPPORT_APPROVED->value)
            ->where('support_approved_at', '>=', now()->subDays(7))
            ->count();

        $supportQueue = (clone $base)
            ->whereIn('status', [
                RequestStatus::SUPPORT_REVIEW_PENDING->value,
                RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            ])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank', 'claimedByUser'])
            ->get();

        return ApiResponse::success([
            'waiting_for_claim'   => $waitingForClaim,
            'active_by_me'        => $activeByMe,
            'claimed_by_others'   => $claimedByOthers,
            'recently_approved'   => $recentlyApproved,
            'support_queue'       => ImportRequestResource::collection($supportQueue)->toArray(request()),
        ], 'Dashboard stats retrieved.');
    }

    // SWIFT_OFFICER is bank-scoped: sees only their bank's requests.
    private function swiftOfficerStats(\App\Models\User $user): \Illuminate\Http\JsonResponse
    {
        $base = ImportRequest::query()->forUser($user);

        $pendingSwiftUpload = (clone $base)
            ->where('status', RequestStatus::WAITING_FOR_SWIFT->value)
            ->count();

        $uploaded = (clone $base)
            ->whereNotNull('swift_uploaded_at')
            ->count();

        $finalApproved = (clone $base)
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::COMPLETED->value,
            ])
            ->count();

        $finalRejected = (clone $base)
            ->where('status', RequestStatus::EXECUTIVE_REJECTED->value)
            ->count();

        $swiftQueue = (clone $base)
            ->whereIn('status', [
                RequestStatus::WAITING_FOR_SWIFT->value,
                RequestStatus::SWIFT_UPLOADED->value,
            ])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank', 'documents'])
            ->get();

        return ApiResponse::success([
            'pending_swift_upload' => $pendingSwiftUpload,
            'uploaded'             => $uploaded,
            'final_approved'       => $finalApproved,
            'final_rejected'       => $finalRejected,
            'swift_queue'          => ImportRequestResource::collection($swiftQueue)->toArray(request()),
        ], 'Dashboard stats retrieved.');
    }

    private function executiveVotingStats(User $user): array
    {
        $waitingForVotingOpen = ImportRequest::query()
            ->where('status', RequestStatus::WAITING_FOR_VOTING_OPEN->value)
            ->count();

        $activeVotingSessions = ImportRequest::query()
            ->where('status', RequestStatus::EXECUTIVE_VOTING_OPEN->value)
            ->count();

        $decisionsApproved = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ])
            ->count();

        $decisionsRejected = ImportRequest::query()
            ->where('status', RequestStatus::EXECUTIVE_REJECTED->value)
            ->count();

        $finalizedDecisions = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::EXECUTIVE_REJECTED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ])
            ->count();

        $votingQueue = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::WAITING_FOR_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
            ])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank', 'votes'])
            ->get();

        $pendingMyVote = $votingQueue
            ->filter(fn (ImportRequest $request) =>
                $request->status === RequestStatus::EXECUTIVE_VOTING_OPEN
                && ! $request->votes->contains('user_id', $user->id)
            )
            ->count();

        return [
            'waiting_for_voting_open' => $waitingForVotingOpen,
            'active_voting_sessions'  => $activeVotingSessions,
            'decisions_approved'      => $decisionsApproved,
            'decisions_rejected'      => $decisionsRejected,
            'finalized_decisions'     => $finalizedDecisions,
            'pending_my_vote'         => $pendingMyVote,
            'voting_queue'            => $this->votingQueueResource($votingQueue, $user),
        ];
    }

    // EXECUTIVE_MEMBER: global CBY view — no org scope
    private function executiveMemberStats(User $user): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($this->executiveVotingStats($user), 'Dashboard stats retrieved.');
    }

    // COMMITTEE_DIRECTOR: global CBY view — no org scope
    private function committeeDirectorStats(): \Illuminate\Http\JsonResponse
    {
        $fxQueue = ImportRequest::query()
            ->where('status', RequestStatus::EXECUTIVE_APPROVED->value)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank', 'documents'])
            ->get();

        $votingQueue = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
            ])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank', 'votes'])
            ->get();

        $totalVoters = User::query()
            ->where('role', UserRole::EXECUTIVE_MEMBER->value)
            ->where('is_active', true)
            ->count();

        $sessionsReadyToClose = $votingQueue
            ->filter(fn (ImportRequest $request) =>
                $request->status === RequestStatus::EXECUTIVE_VOTING_OPEN
                && $totalVoters > 0
                && $request->votes->count() >= $totalVoters
            )
            ->count();

        $sessionsWithTie = $votingQueue
            ->filter(function (ImportRequest $request): bool {
                if ($request->status !== RequestStatus::EXECUTIVE_VOTING_OPEN) {
                    return false;
                }

                $approveCount = $request->votes->filter(fn (RequestVote $vote) => $vote->vote?->value === 'APPROVE')->count();
                $rejectCount = $request->votes->filter(fn (RequestVote $vote) => $vote->vote?->value === 'REJECT')->count();

                return $approveCount > 0 && $approveCount === $rejectCount;
            })
            ->count();

        $executiveStats = $this->executiveVotingStats(request()->user());

        return ApiResponse::success(array_merge($executiveStats, [
            // Director-specific lifecycle counters
            'sessions_ready_to_close' => $sessionsReadyToClose,
            'sessions_with_tie' => $sessionsWithTie,
            'fx_confirmation_pending' => $fxQueue->count(),
            'finalized_approved' => $executiveStats['decisions_approved'] ?? 0,
            'finalized_rejected' => $executiveStats['decisions_rejected'] ?? 0,
            // Director-specific lifecycle queues
            'voting_lifecycle_queue' => $this->votingQueueResource($votingQueue, request()->user()),
            'fx_confirmation_queue' => ImportRequestResource::collection($fxQueue)->toArray(request()),
            // Backward compatibility with existing frontend contract
            'customs_declaration_pending' => ImportRequestResource::collection($fxQueue)->toArray(request()),
        ]), 'Dashboard stats retrieved.');
    }

    private function votingQueueResource($requests, User $user): array
    {
        $totalVoters = User::query()
            ->where('role', UserRole::EXECUTIVE_MEMBER->value)
            ->where('is_active', true)
            ->count();

        return collect(ImportRequestResource::collection($requests)->toArray(request()))
            ->map(function (array $item, int $index) use ($requests, $user, $totalVoters) {
                /** @var ImportRequest $request */
                $request = $requests->values()->get($index);
                $myVote = $request->votes->firstWhere('user_id', $user->id);

                return [
                    ...$item,
                    'my_vote' => $this->dashboardVoteValue($myVote),
                    'votes_cast' => $request->votes->count(),
                    'total_voters' => $totalVoters,
                ];
            })
            ->values()
            ->all();
    }

    private function dashboardVoteValue(?RequestVote $vote): ?string
    {
        return match ($vote?->vote?->value) {
            'APPROVE' => 'approve',
            'REJECT' => 'reject',
            default => null,
        };
    }

    // CBY_ADMIN: full-system visibility across all banks
    private function cbyadminStats(): \Illuminate\Http\JsonResponse
    {
        $terminalStatuses = [
            RequestStatus::EXECUTIVE_REJECTED,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED,
            RequestStatus::COMPLETED,
        ];
        $approvedStatuses = [
            RequestStatus::EXECUTIVE_APPROVED,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED,
            RequestStatus::COMPLETED,
        ];
        $inProcessExcluded = array_merge(
            [RequestStatus::DRAFT, RequestStatus::DRAFT_REJECTED_INTERNAL],
            $terminalStatuses,
            $approvedStatuses,
        );

        $total    = ImportRequest::query()->count();
        $approved = ImportRequest::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, $approvedStatuses))
            ->count();
        $rejected = ImportRequest::query()
            ->where('status', RequestStatus::EXECUTIVE_REJECTED->value)
            ->count();
        $inProcess = ImportRequest::query()
            ->whereNotIn('status', array_map(fn ($s) => $s->value, array_unique($inProcessExcluded, SORT_REGULAR)))
            ->count();

        $recentRequests = ImportRequest::query()
            ->orderByDesc('updated_at')
            ->limit(10)
            ->with(['bank'])
            ->get();

        return ApiResponse::success([
            'total'                  => $total,
            'approved'               => $approved,
            'in_process'             => $inProcess,
            'rejected'               => $rejected,
            'compliance_alerts'      => $this->complianceAlerts(),
            'most_active_banks'      => $this->mostActiveBanks(),
            'monthly_requests'       => $this->cbyadminMonthlyRequests(CarbonImmutable::now()),
            'category_distribution'  => $this->cbyadminCategoryDistribution(),
            'recent_requests'        => ImportRequestResource::collection($recentRequests)->toArray(request()),
        ], 'Dashboard stats retrieved.');
    }

    private function cbyadminMonthlyRequests(CarbonImmutable $asOf): array
    {
        $timezone = config('app.timezone', 'UTC');
        $anchorMonth = $asOf->setTimezone($timezone)->startOfMonth();
        $windowStart = $anchorMonth->subMonths(5);

        $monthKeys = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthKeys[] = $anchorMonth->subMonths($i)->format('Y-m');
        }

        $submitted = array_fill_keys($monthKeys, 0);
        $approved  = array_fill_keys($monthKeys, 0);

        $approvedStatuses = [
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
        ];

        $rows = ImportRequest::query()
            ->where('created_at', '>=', $windowStart)
            ->get(['created_at', 'status']);

        foreach ($rows as $row) {
            $monthKey = (CarbonImmutable::instance($row->created_at))->setTimezone($timezone)->format('Y-m');
            if (!array_key_exists($monthKey, $submitted)) {
                continue;
            }
            $submitted[$monthKey]++;
            $statusValue = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            if (in_array($statusValue, $approvedStatuses, true)) {
                $approved[$monthKey]++;
            }
        }

        return array_map(
            fn (string $month): array => [
                'month'     => $month,
                'submitted' => $submitted[$month],
                'approved'  => $approved[$month],
            ],
            $monthKeys
        );
    }

    private function cbyadminCategoryDistribution(): array
    {
        // Category distribution by commodity_type field (if available), falling back to currency grouping
        // as a meaningful operational segmentation visible in the CBY_ADMIN dashboard.
        $colors = ['#0066cc', '#1b5e20', '#f57f17', '#c62828', '#5856d6', '#32ade6'];

        $groups = ImportRequest::query()
            ->selectRaw('currency as label, COUNT(*) as `count`')
            ->groupBy('currency')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->values()
            ->map(fn ($row, $i) => [
                'label' => $row->label ?? 'أخرى',
                'count' => (int) $row->count,
                'color' => $colors[$i] ?? '#8e8e93',
            ])
            ->all();

        return $groups;
    }

    private function complianceAlerts(): array
    {
        $draftStatuses = [RequestStatus::DRAFT, RequestStatus::DRAFT_REJECTED_INTERNAL];
        $terminalStatuses = [
            RequestStatus::EXECUTIVE_REJECTED,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED,
            RequestStatus::COMPLETED,
        ];

        // Duplicate supplier names in non-draft, non-terminal active requests
        $duplicateSuppliers = ImportRequest::query()
            ->whereNotIn('status', array_map(fn ($s) => $s->value, $draftStatuses))
            ->selectRaw('supplier_name, COUNT(*) as `count`')
            ->groupBy('supplier_name')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['supplier_name' => $row->supplier_name, 'count' => (int) $row->count])
            ->values()
            ->all();

        // USD requests exceeding $1,000,000 in non-terminal status
        $highAmountRequests = ImportRequest::query()
            ->where('currency', 'USD')
            ->where('amount', '>', 1_000_000)
            ->whereNotIn('status', array_map(fn ($s) => $s->value, $terminalStatuses))
            ->with(['bank'])
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'reference_number' => $r->reference_number,
                'bank_name'        => $r->bank?->name ?? '—',
                'amount'           => (float) $r->amount,
                'currency'         => $r->currency,
            ])
            ->values()
            ->all();

        // Stale pending: non-draft, non-terminal (and non-approved-awaiting-customs), updated > 14 days ago
        $stalePendingExcluded = array_merge($draftStatuses, $terminalStatuses, [RequestStatus::EXECUTIVE_APPROVED]);
        $stalePendingRequests = ImportRequest::query()
            ->whereNotIn('status', array_map(fn ($s) => $s->value, $stalePendingExcluded))
            ->where('updated_at', '<', now()->subDays(14))
            ->with(['bank'])
            ->orderBy('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'reference_number' => $r->reference_number,
                'bank_name'        => $r->bank?->name ?? '—',
                'updated_at'       => $r->updated_at?->toIso8601String() ?? null,
            ])
            ->values()
            ->all();

        return [
            'duplicate_suppliers'    => $duplicateSuppliers,
            'high_amount_requests'   => $highAmountRequests,
            'stale_pending_requests' => $stalePendingRequests,
        ];
    }

    private function mostActiveBanks(): array
    {
        return Bank::query()
            ->select('banks.id as bank_id', 'banks.name as bank_name')
            ->selectRaw('COUNT(import_requests.id) as request_count')
            ->leftJoin('import_requests', 'import_requests.bank_id', '=', 'banks.id')
            ->groupBy('banks.id', 'banks.name')
            ->orderByDesc('request_count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'bank_id'       => $row->bank_id,
                'bank_name'     => $row->bank_name,
                'request_count' => (int) $row->request_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Bank-facing rejected queues include support rejection, executive rejection,
     * and terminal bank rejection.
     */
    private function bankFacingRejectedStatuses(): array
    {
        return [
            RequestStatus::SUPPORT_REJECTED->value,
            RequestStatus::EXECUTIVE_REJECTED->value,
            RequestStatus::BANK_REJECTED->value,
        ];
    }
}
