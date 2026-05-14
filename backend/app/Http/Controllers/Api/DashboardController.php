<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Support\ApiResponse;
use Carbon\Carbon;
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
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function stats()
    {
        $user = request()->user();
        $base = ImportRequest::query()->forUser($user);

        $total = (clone $base)->count();
        $statusCounts = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        $byStatus = [];
        foreach (RequestStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $createdThisMonth = (clone $base)->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $approvedThisMonth = (clone $base)->whereIn('status', [
            RequestStatus::BANK_APPROVED->value,
            RequestStatus::SUPPORT_APPROVED->value,
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
        ])->whereBetween('updated_at', [$monthStart, $monthEnd])->count();
        $rejectedThisMonth = (clone $base)->whereIn('status', [
            RequestStatus::SUPPORT_REJECTED->value,
            RequestStatus::EXECUTIVE_REJECTED->value,
        ])->whereBetween('updated_at', [$monthStart, $monthEnd])->count();

        $pendingActionForMe = (clone $base)->where('current_owner_role', $user->role->value)->count();

        $openForMe = 0;
        $tiesPendingDirector = 0;
        if ($user->hasRole(UserRole::EXECUTIVE_MEMBER) || $user->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            $votingQuery = ImportRequest::query()->whereIn('status', [
                RequestStatus::EXECUTIVE_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
            ]);
            $openForMe = $votingQuery->count();

            if ($user->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
                $candidateIds = $votingQuery->pluck('id');
                foreach ($candidateIds as $requestId) {
                    $counts = RequestVote::query()
                        ->selectRaw("vote, COUNT(*) as aggregate")
                        ->where('request_id', $requestId)
                        ->groupBy('vote')
                        ->pluck('aggregate', 'vote');
                    $approve = (int) ($counts['APPROVE'] ?? 0);
                    $reject = (int) ($counts['REJECT'] ?? 0);
                    $abstain = (int) ($counts['ABSTAIN'] ?? 0);
                    $autoAbstain = (int) ($counts['AUTO_ABSTAIN_TIMEOUT'] ?? 0);
                    if (($approve + $reject + $abstain + $autoAbstain) === 6 && $approve < 4 && $reject < 4) {
                        $tiesPendingDirector++;
                    }
                }
            }
        }

        return ApiResponse::success([
            'total_requests' => $total,
            'by_status' => $byStatus,
            'pending_action_for_me' => $pendingActionForMe,
            'this_month' => [
                'created' => $createdThisMonth,
                'approved' => $approvedThisMonth,
                'rejected' => $rejectedThisMonth,
            ],
            'voting' => [
                'open_for_me' => $openForMe,
                'ties_pending_director' => $tiesPendingDirector,
            ],
        ], 'Dashboard stats retrieved.');
    }
}
