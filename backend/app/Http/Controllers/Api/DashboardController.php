<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Resources\ImportRequestResource;
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
            $user->hasRole(UserRole::DATA_ENTRY)        => $this->dataEntryStats($user),
            $user->hasRole(UserRole::BANK_REVIEWER)     => $this->bankReviewerStats($user),
            $user->hasRole(UserRole::SUPPORT_COMMITTEE) => $this->supportCommitteeStats($user),
            $user->hasRole(UserRole::SWIFT_OFFICER)     => $this->swiftOfficerStats($user),
            default                                     => ApiResponse::success([], 'Dashboard stats retrieved.'),
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
        $approvedLast7Days = (clone $base)
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
            'approved_last_7_days' => $approvedLast7Days,
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
            ->where('status', RequestStatus::SWIFT_UPLOADED->value)
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
}
