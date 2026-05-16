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

    private function supportCommitteeStats($user)
    {
        $base = ImportRequest::query();

        $waitingForClaim = (clone $base)
            ->where('status', RequestStatus::SUPPORT_REVIEW_PENDING)
            ->count();

        $activeByMe = (clone $base)
            ->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS)
            ->where('claimed_by', $user->id)
            ->count();

        $claimedByOthers = (clone $base)
            ->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS)
            ->where('claimed_by', '!=', $user->id)
            ->whereNotNull('claimed_by')
            ->count();

        $recentlyApproved = (clone $base)
            ->where('status', RequestStatus::SUPPORT_APPROVED)
            ->count();

        $supportQueue = (clone $base)
            ->whereIn('status', [
                RequestStatus::SUPPORT_REVIEW_PENDING->value,
                RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            ])
            ->orderBy('updated_at')
            ->limit(50)
            ->with(['claimedByUser'])
            ->get();

        return ApiResponse::success([
            'waiting_for_claim' => $waitingForClaim,
            'active_by_me'      => $activeByMe,
            'claimed_by_others' => $claimedByOthers,
            'recently_approved' => $recentlyApproved,
            'support_queue'     => ImportRequestResource::collection($supportQueue)->resolve(),
        ], 'Dashboard stats retrieved.');
    }
}
