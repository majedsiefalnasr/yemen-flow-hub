<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Resources\ImportRequestResource;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Support\ApiResponse;
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
            $user->hasRole(UserRole::SUPPORT_COMMITTEE)   => $this->supportCommitteeStats($user),
            $user->hasRole(UserRole::SWIFT_OFFICER)       => $this->swiftOfficerStats($user),
            $user->hasRole(UserRole::EXECUTIVE_MEMBER)    => $this->executiveMemberStats(),
            $user->hasRole(UserRole::COMMITTEE_DIRECTOR)  => $this->committeeDirectorStats(),
            $user->hasRole(UserRole::CBY_ADMIN)           => $this->cbyadminStats(),
            default                                       => ApiResponse::success([], 'Dashboard stats retrieved.'),
        };
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

        $recentRequests = (clone $base)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'draft'               => $draft,
            'returned'            => $returned,
            'under_cby_processing' => $underCby,
            'completed'           => $completed,
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
            ->with(['bank'])
            ->get();

        return ApiResponse::success([
            'pending_review'      => $pendingReview,
            'at_cby'              => $atCby,
            'returned_by_support' => $returnedBySupport,
            'approved_completed'  => $approvedCompleted,
            'review_queue'        => ImportRequestResource::collection($reviewQueue)->resolve(),
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

        // Counts requests where SWIFT has been uploaded, regardless of current status.
        // SWIFT_UPLOADED is transient — auto-chains immediately to WAITING_FOR_VOTING_OPEN.
        $uploaded = (clone $base)
            ->whereNotNull('swift_uploaded_at')
            ->count();

        $finalApproved = (clone $base)
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ])
            ->count();

        $finalRejected = (clone $base)
            ->where('status', RequestStatus::EXECUTIVE_REJECTED->value)
            ->count();

        $swiftQueue = (clone $base)
            ->where('status', RequestStatus::WAITING_FOR_SWIFT->value)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank'])
            ->get();

        return ApiResponse::success([
            'pending_swift_upload' => $pendingSwiftUpload,
            'uploaded'             => $uploaded,
            'final_approved'       => $finalApproved,
            'final_rejected'       => $finalRejected,
            'swift_queue'          => ImportRequestResource::collection($swiftQueue)->toArray(request()),
        ], 'Dashboard stats retrieved.');
    }

    private function executiveVotingStats(): array
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

        $votingQueue = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::WAITING_FOR_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_OPEN->value,
            ])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->with(['bank'])
            ->get();

        return [
            'waiting_for_voting_open' => $waitingForVotingOpen,
            'active_voting_sessions'  => $activeVotingSessions,
            'decisions_approved'      => $decisionsApproved,
            'decisions_rejected'      => $decisionsRejected,
            'voting_queue'            => ImportRequestResource::collection($votingQueue)->toArray(request()),
        ];
    }

    // EXECUTIVE_MEMBER: global CBY view — no org scope
    private function executiveMemberStats(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($this->executiveVotingStats(), 'Dashboard stats retrieved.');
    }

    // COMMITTEE_DIRECTOR: global CBY view — no org scope
    private function committeeDirectorStats(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($this->executiveVotingStats(), 'Dashboard stats retrieved.');
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

        return ApiResponse::success([
            'total'              => $total,
            'approved'           => $approved,
            'in_process'         => $inProcess,
            'rejected'           => $rejected,
            'compliance_alerts'  => $this->complianceAlerts(),
            'most_active_banks'  => $this->mostActiveBanks(),
        ], 'Dashboard stats retrieved.');
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

        // Stale pending: non-draft, non-terminal, updated > 14 days ago
        $stalePendingExcluded = array_merge($draftStatuses, $terminalStatuses);
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
                'updated_at'       => $r->updated_at->toIso8601String(),
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
}
