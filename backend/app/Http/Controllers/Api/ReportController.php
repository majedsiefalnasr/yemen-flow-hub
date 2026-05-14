<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\RequestStageHistory;
use App\Models\RequestVote;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    #[OA\Get(
        path: '/api/reports/workflow',
        tags: ['Reports'],
        summary: 'Workflow metrics report',
        description: 'Average time-per-stage and throughput. CBY only.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Workflow report retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function workflow()
    {
        $user = request()->user();
        if (!$user->isCbyUser()) {
            return ApiResponse::forbidden();
        }

        $stageDurations = [];
        $histories = RequestStageHistory::query()->orderBy('request_id')->orderBy('created_at')->get();
        $grouped = $histories->groupBy('request_id');

        foreach ($grouped as $rows) {
            $prev = null;
            foreach ($rows as $row) {
                if ($prev) {
                    $hours = $prev->created_at->diffInHours($row->created_at);
                    $key = $prev->to_status?->value ?? (string) $prev->to_status;
                    $stageDurations[$key][] = $hours;
                }
                $prev = $row;
            }
        }

        $avgTimePerStage = [];
        foreach ($stageDurations as $stage => $values) {
            $avgTimePerStage[$stage] = round(array_sum($values) / max(count($values), 1), 2);
        }

        $throughput = [
            'completed' => ImportRequest::query()->where('status', RequestStatus::COMPLETED->value)->count(),
            'approved' => ImportRequest::query()->where('status', RequestStatus::EXECUTIVE_APPROVED->value)->count(),
            'rejected' => ImportRequest::query()->whereIn('status', [
                RequestStatus::BANK_REJECTED->value,
                RequestStatus::SUPPORT_REJECTED->value,
                RequestStatus::EXECUTIVE_REJECTED->value,
            ])->count(),
        ];

        return ApiResponse::success([
            'avg_time_per_stage_hours' => $avgTimePerStage,
            'throughput' => $throughput,
        ], 'Workflow report retrieved.');
    }

    #[OA\Get(
        path: '/api/reports/voting',
        tags: ['Reports'],
        summary: 'Voting metrics report',
        description: 'Approval rate, tie rate, and average decision time. CBY only.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Voting report retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function voting()
    {
        $user = request()->user();
        if (!$user->isCbyUser()) {
            return ApiResponse::forbidden();
        }

        $executiveFinal = ImportRequest::query()
            ->whereIn('status', [RequestStatus::EXECUTIVE_APPROVED->value, RequestStatus::EXECUTIVE_REJECTED->value, RequestStatus::CUSTOMS_ISSUED->value, RequestStatus::COMPLETED->value])
            ->count();

        $approved = ImportRequest::query()->whereIn('status', [RequestStatus::EXECUTIVE_APPROVED->value, RequestStatus::CUSTOMS_ISSUED->value, RequestStatus::COMPLETED->value])->count();
        $rejected = ImportRequest::query()->where('status', RequestStatus::EXECUTIVE_REJECTED->value)->count();

        $ties = 0;
        $candidateIds = ImportRequest::query()
            ->whereIn('status', [RequestStatus::EXECUTIVE_APPROVED->value, RequestStatus::EXECUTIVE_REJECTED->value, RequestStatus::EXECUTIVE_VOTING->value, RequestStatus::CUSTOMS_ISSUED->value, RequestStatus::COMPLETED->value])
            ->pluck('id');
        foreach ($candidateIds as $requestId) {
            $counts = RequestVote::query()
                ->selectRaw("vote, COUNT(*) as aggregate")
                ->where('request_id', $requestId)
                ->groupBy('vote')
                ->pluck('aggregate', 'vote');
            $approveCount = (int) ($counts['APPROVE'] ?? 0);
            $rejectCount = (int) ($counts['REJECT'] ?? 0);
            $abstainCount = (int) ($counts['ABSTAIN'] ?? 0);
            if (($approveCount + $rejectCount + $abstainCount) === 6 && $approveCount < 4 && $rejectCount < 4) {
                $ties++;
            }
        }

        $decidedRequests = ImportRequest::query()
            ->whereNotNull('swift_uploaded_at')
            ->whereNotNull('executive_decided_at')
            ->get();
        $avgHours = $decidedRequests->count()
            ? round($decidedRequests->avg(fn ($r) => $r->swift_uploaded_at->diffInHours($r->executive_decided_at)), 2)
            : 0.0;

        return ApiResponse::success([
            'approval_rate' => $executiveFinal ? round(($approved / $executiveFinal) * 100, 2) : 0,
            'rejection_rate' => $executiveFinal ? round(($rejected / $executiveFinal) * 100, 2) : 0,
            'tie_rate' => $candidateIds->count() ? round(($ties / $candidateIds->count()) * 100, 2) : 0,
            'avg_time_to_decision_hours' => $avgHours,
        ], 'Voting report retrieved.');
    }
}
