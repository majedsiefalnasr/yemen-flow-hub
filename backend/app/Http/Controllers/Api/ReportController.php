<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    // ─── Date validation helper ───────────────────────────────────────────────

    private function validateDateParams(): ?array
    {
        $fromDate = request()->query('from_date');
        $toDate = request()->query('to_date');

        $errors = [];
        if ($fromDate && ! $this->isValidDate($fromDate)) {
            $errors['from_date'] = ['The from_date must be in Y-m-d format.'];
        }
        if ($toDate && ! $this->isValidDate($toDate)) {
            $errors['to_date'] = ['The to_date must be in Y-m-d format.'];
        }

        if ($errors) {
            return $errors;
        }

        return null;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        $errors = \DateTime::getLastErrors();

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function validateExportFormat(string $format): ?array
    {
        if (! in_array($format, ['excel', 'pdf'], true)) {
            return ['format' => ['The format must be either excel or pdf.']];
        }

        return null;
    }

    private function buildAnalyticsForScope(?int $bankId, ?string $fromDate, ?string $toDate, string $driver): array
    {
        $monthFormat = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $base = fn () => ImportRequest::query()->when($bankId, fn ($q) => $q->where('bank_id', $bankId));

        $monthlyRows = $base()
            ->selectRaw("{$monthFormat} as month")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as approved', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as rejected', [
                RequestStatus::SUPPORT_REJECTED->value,
                RequestStatus::EXECUTIVE_REJECTED->value,
                RequestStatus::BANK_REJECTED->value,
            ]);
        $this->applyDateFilter($monthlyRows, $fromDate, $toDate);
        $monthlyRows = $monthlyRows->groupByRaw($monthFormat)->orderBy('month')->limit(12)->get();
        $monthlyTrend = $monthlyRows->map(fn ($r) => [
            'month' => $r->month,
            'total' => (int) $r->total,
            'approved' => (int) ($r->approved ?? 0),
            'rejected' => (int) ($r->rejected ?? 0),
        ])->values()->all();

        $categoryRows = $base()->select('goods_type')->selectRaw('COUNT(*) as count')->groupBy('goods_type');
        $this->applyDateFilter($categoryRows, $fromDate, $toDate);
        $categoryDist = $categoryRows->orderByDesc('count')->get()->map(fn ($r) => [
            'category' => $r->goods_type ?? 'Uncategorized',
            'count' => (int) $r->count,
        ])->values()->all();

        $currencyRows = $base()->select('currency')->selectRaw('SUM(amount) as total_amount')->groupBy('currency');
        $this->applyDateFilter($currencyRows, $fromDate, $toDate);
        $amountByCurrency = $currencyRows->orderByDesc('total_amount')->get()->map(fn ($r) => [
            'currency' => $r->currency,
            'amount' => (float) ($r->total_amount ?? 0),
        ])->values()->all();

        if ($driver === 'sqlite') {
            $dayOfWeek = "CAST((julianday(created_at) - julianday('2000-01-03')) % 7 AS INTEGER) + 1";
            $hour = "CAST(strftime('%H', created_at) AS INTEGER)";
        } else {
            $dayOfWeek = 'DAYOFWEEK(created_at)';
            $hour = 'HOUR(created_at)';
        }
        $timeSlot = "FLOOR({$hour} / 2) * 2";

        $heatmapRows = $base()
            ->selectRaw("{$dayOfWeek} as day_of_week")
            ->selectRaw("{$timeSlot} as time_slot")
            ->selectRaw('COUNT(*) as count')
            ->whereRaw("{$hour} >= 8 AND {$hour} < 20");
        $this->applyDateFilter($heatmapRows, $fromDate, $toDate);
        $heatmapData = $heatmapRows
            ->groupByRaw("{$dayOfWeek}, {$timeSlot}")
            ->orderBy('day_of_week')->orderBy('time_slot')
            ->get()
            ->map(fn ($r) => [
                'day' => (int) ($r->day_of_week ?? 1),
                'slot' => (int) ($r->time_slot ?? 8),
                'count' => (int) $r->count,
            ])->values()->all();

        return [
            'monthly_trend' => $monthlyTrend,
            'category_distribution' => $categoryDist,
            'amount_by_currency' => $amountByCurrency,
            'submission_heatmap' => $heatmapData,
        ];
    }

    private function applyDateFilter($query, ?string $fromDate, ?string $toDate, string $column = 'created_at')
    {
        if ($fromDate) {
            $query->whereDate($column, '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate($column, '<=', $toDate);
        }

        return $query;
    }

    // ─── Workflow report ──────────────────────────────────────────────────────

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
        if (! $user->isCbyUser()) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'bank_id' => $user->bank_id,
                'path' => request()->path(),
                'method' => request()->method(),
                'reason' => 'workflow reports require CBY role',
            ]);

            return ApiResponse::forbidden();
        }

        $dateErrors = $this->validateDateParams();
        if ($dateErrors) {
            return ApiResponse::validationError($dateErrors);
        }

        $fromDate = request()->query('from_date');
        $toDate = request()->query('to_date');

        // Counts by status
        $countsByStatus = [];
        foreach (RequestStatus::cases() as $status) {
            $q = ImportRequest::query()->where('status', $status->value);
            $this->applyDateFilter($q, $fromDate, $toDate);
            $countsByStatus[$status->value] = $q->count();
        }

        // Counts by bank
        $countsByBank = Bank::query()
            ->select('banks.id as bank_id', 'banks.name as bank_name')
            ->selectRaw('COUNT(import_requests.id) as total')
            ->leftJoin('import_requests', function ($join) use ($fromDate, $toDate) {
                $join->on('import_requests.bank_id', '=', 'banks.id');
                if ($fromDate) {
                    $join->whereDate('import_requests.created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $join->whereDate('import_requests.created_at', '<=', $toDate);
                }
            })
            ->groupBy('banks.id', 'banks.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'bank_id' => $row->bank_id,
                'bank_name' => $row->bank_name,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        // D1+D2+D3 fix: aggregated DB query instead of full table load + PHP grouping
        $stageDurations = []; // D2 fix: always initialized

        $driver = DB::connection()->getDriverName();
        $hoursDiff = $driver === 'sqlite'
            ? '(julianday(h2.created_at) - julianday(h1.created_at)) * 24'
            : 'TIMESTAMPDIFF(HOUR, h1.created_at, h2.created_at)';

        $stageRows = DB::table('request_stage_history as h1')
            ->selectRaw("COALESCE(h1.to_status, 'unknown') as stage_status")
            ->selectRaw("AVG({$hoursDiff}) as avg_hours")
            ->join(DB::raw('request_stage_history h2'), function ($join) {
                $join->on('h2.request_id', '=', 'h1.request_id')
                    ->whereRaw('h2.id = (
                        SELECT MIN(h3.id) FROM request_stage_history h3
                        WHERE h3.request_id = h1.request_id AND h3.created_at > h1.created_at
                    )');
            });
        $this->applyDateFilter($stageRows, $fromDate, $toDate, 'h1.created_at');
        $stageRows = $stageRows
            ->groupBy('stage_status')
            ->get();

        $avgTimePerStage = [];
        foreach ($stageRows as $row) {
            // D1 fix: null to_status is explicitly reported as "unknown".
            $key = $row->stage_status ?? 'unknown';
            $avgTimePerStage[$key] = round((float) $row->avg_hours, 2);
        }

        $throughput = [
            'completed' => $this->applyDateFilter(ImportRequest::query()->where('status', RequestStatus::COMPLETED->value), $fromDate, $toDate)->count(),
            'approved' => $this->applyDateFilter(ImportRequest::query()->where('status', RequestStatus::EXECUTIVE_APPROVED->value), $fromDate, $toDate)->count(),
            'rejected' => $this->applyDateFilter(ImportRequest::query()->whereIn('status', $this->rejectedStatuses()), $fromDate, $toDate)->count(),
        ];

        $driver = DB::connection()->getDriverName();
        $analytics = $this->buildAnalyticsForScope(null, $fromDate, $toDate, $driver);

        // Total financing value: sum of approved requests
        $totalFinancing = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ]);
        $this->applyDateFilter($totalFinancing, $fromDate, $toDate);
        $totalFinancing = (float) ($totalFinancing->sum('amount') ?? 0);

        // Duplicate invoice count
        $duplicateCount = 0;
        $duplicateQuery = DB::table('import_requests')
            ->selectRaw('invoice_number, COUNT(*) as cnt');
        $this->applyDateFilter($duplicateQuery, $fromDate, $toDate, 'created_at');
        $duplicateQuery = $duplicateQuery
            ->whereNotNull('invoice_number')
            ->groupBy('invoice_number')
            ->havingRaw('COUNT(*) > 1');
        $duplicateCounts = $duplicateQuery->get();
        foreach ($duplicateCounts as $row) {
            $duplicateCount += $row->cnt - 1;
        }

        return ApiResponse::success([
            'counts_by_status' => $countsByStatus,
            'counts_by_bank' => $countsByBank,
            'avg_time_per_stage_hours' => $avgTimePerStage,
            'throughput' => $throughput,
            'monthly_trend' => $analytics['monthly_trend'],
            'category_distribution' => $analytics['category_distribution'],
            'amount_by_currency' => $analytics['amount_by_currency'],
            'submission_heatmap' => $analytics['submission_heatmap'],
            'total_financing_value' => $totalFinancing,
            'duplicate_invoice_count' => $duplicateCount,
        ], 'Workflow report retrieved.');
    }

    // ─── Voting report ────────────────────────────────────────────────────────

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
        if (! $user->isCbyUser()) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'bank_id' => $user->bank_id,
                'path' => request()->path(),
                'method' => request()->method(),
                'reason' => 'voting reports require CBY role',
            ]);

            return ApiResponse::forbidden();
        }

        $dateErrors = $this->validateDateParams();
        if ($dateErrors) {
            return ApiResponse::validationError($dateErrors);
        }

        $fromDate = request()->query('from_date');
        $toDate = request()->query('to_date');

        $votingStatuses = [
            RequestStatus::EXECUTIVE_VOTING_OPEN->value,
            RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::EXECUTIVE_REJECTED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
        ];

        $totalVotingSessions = ImportRequest::query()
            ->whereIn('status', $votingStatuses);
        $this->applyDateFilter($totalVotingSessions, $fromDate, $toDate);
        $totalVotingSessions = $totalVotingSessions
            ->count();

        $executiveFinal = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::EXECUTIVE_REJECTED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ]);
        $this->applyDateFilter($executiveFinal, $fromDate, $toDate);
        $executiveFinal = $executiveFinal
            ->count();

        $approved = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ]);
        $this->applyDateFilter($approved, $fromDate, $toDate);
        $approved = $approved
            ->count();

        $rejected = ImportRequest::query()
            ->where('status', RequestStatus::EXECUTIVE_REJECTED->value);
        $this->applyDateFilter($rejected, $fromDate, $toDate);
        $rejected = $rejected
            ->count();

        $candidateIds = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::EXECUTIVE_REJECTED->value,
                RequestStatus::EXECUTIVE_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ]);
        $this->applyDateFilter($candidateIds, $fromDate, $toDate);
        $candidateIds = $candidateIds->pluck('id');

        // Raw vote tallies
        $tallyRows = RequestVote::query()
            ->whereIn('request_id', $candidateIds)
            ->selectRaw('vote, COUNT(*) as `count`')
            ->groupBy('vote')
            ->pluck('count', 'vote');

        $voteTallies = [
            'approve' => (int) ($tallyRows['APPROVE'] ?? 0),
            'reject' => (int) ($tallyRows['REJECT'] ?? 0),
            // D6 fix: include AUTO_ABSTAIN_TIMEOUT in abstain count
            'abstain' => (int) ($tallyRows['ABSTAIN'] ?? 0) + (int) ($tallyRows['AUTO_ABSTAIN_TIMEOUT'] ?? 0),
        ];

        // D4 fix: single aggregated query replacing N+1 per-request loop
        $ties = 0;
        if ($candidateIds->isNotEmpty()) {
            // D6 fix: group ABSTAIN and AUTO_ABSTAIN_TIMEOUT together in tie detection
            $voteCountsByRequest = RequestVote::query()
                ->whereIn('request_id', $candidateIds)
                ->selectRaw("request_id,
                    SUM(CASE WHEN vote = 'APPROVE' THEN 1 ELSE 0 END) as approve_count,
                    SUM(CASE WHEN vote = 'REJECT' THEN 1 ELSE 0 END) as reject_count,
                    SUM(CASE WHEN vote IN ('ABSTAIN','AUTO_ABSTAIN_TIMEOUT') THEN 1 ELSE 0 END) as abstain_count")
                ->groupBy('request_id')
                ->get();

            foreach ($voteCountsByRequest as $row) {
                $total = $row->approve_count + $row->reject_count + $row->abstain_count;
                if ($total === 6 && $row->approve_count < 4 && $row->reject_count < 4) {
                    $ties++;
                }
            }
        }

        $decidedRequests = ImportRequest::query()
            ->whereNotNull('swift_uploaded_at')
            ->whereNotNull('executive_decided_at');
        $this->applyDateFilter($decidedRequests, $fromDate, $toDate);
        $decidedRequests = $decidedRequests
            ->get();
        $avgHours = $decidedRequests->count()
            ? round($decidedRequests->avg(fn ($r) => $r->swift_uploaded_at->diffInHours($r->executive_decided_at)), 2)
            : 0.0;

        return ApiResponse::success([
            'total_voting_sessions' => $totalVotingSessions,
            'vote_tallies' => $voteTallies,
            'approval_rate' => $executiveFinal ? round(($approved / $executiveFinal) * 100, 2) : 0,
            'rejection_rate' => $executiveFinal ? round(($rejected / $executiveFinal) * 100, 2) : 0,
            'tie_rate' => $candidateIds->count() ? round(($ties / $candidateIds->count()) * 100, 2) : 0,
            'avg_time_to_decision_hours' => $avgHours,
        ], 'Voting report retrieved.');
    }

    // ─── Bank report ──────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/reports/bank',
        tags: ['Reports'],
        summary: 'Bank-scoped request statistics',
        description: 'Bank users see own-bank data; CBY_ADMIN sees cross-bank breakdown.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Bank report retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function bank()
    {
        $user = request()->user();

        $bankRoles = [
            UserRole::DATA_ENTRY->value,
            UserRole::BANK_REVIEWER->value,
            UserRole::BANK_ADMIN->value,
        ];

        $isBankReportingUser = in_array($user->role?->value, $bankRoles, true);
        $isCbyAdmin = $user->role === UserRole::CBY_ADMIN;

        if (! $isBankReportingUser && ! $isCbyAdmin) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'path' => request()->path(),
                'method' => request()->method(),
                'reason' => 'bank reports require bank role or CBY_ADMIN',
            ]);

            return ApiResponse::forbidden();
        }

        $dateErrors = $this->validateDateParams();
        if ($dateErrors) {
            return ApiResponse::validationError($dateErrors);
        }

        $fromDate = request()->query('from_date');
        $toDate = request()->query('to_date');

        if ($isCbyAdmin) {
            // Cross-bank breakdown for CBY admin
            $banks = Bank::query()->where('is_active', true)->get();
            $perBank = [];
            foreach ($banks as $b) {
                $q = ImportRequest::query()->where('bank_id', $b->id);
                $this->applyDateFilter($q, $fromDate, $toDate);
                $total = $q->count();
                $approved = (clone $q)->whereIn('status', [
                    RequestStatus::EXECUTIVE_APPROVED->value,
                    RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                    RequestStatus::COMPLETED->value,
                ])->count();
                $rejected = (clone $q)->whereIn('status', $this->rejectedStatuses())->count();
                $pending = (clone $q)->whereNotIn('status', [
                    RequestStatus::EXECUTIVE_APPROVED->value,
                    RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                    RequestStatus::COMPLETED->value,
                    ...$this->rejectedStatuses(),
                ])->count();

                $perBank[] = [
                    'bank_id' => $b->id,
                    'bank_name' => $b->name,
                    'total_requests' => $total,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'pending_count' => $pending,
                    'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
                    'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
                ];
            }

            $summaryQuery = ImportRequest::query();
            $this->applyDateFilter($summaryQuery, $fromDate, $toDate);
            $summaryTotal = $summaryQuery->count();
            $summaryApproved = (clone $summaryQuery)->whereIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
            ])->count();
            $summaryRejected = (clone $summaryQuery)->whereIn('status', $this->rejectedStatuses())->count();
            $summaryPending = (clone $summaryQuery)->whereNotIn('status', [
                RequestStatus::EXECUTIVE_APPROVED->value,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
                RequestStatus::COMPLETED->value,
                ...$this->rejectedStatuses(),
            ])->count();

            return ApiResponse::success([
                'total_requests' => $summaryTotal,
                'approved_count' => $summaryApproved,
                'rejected_count' => $summaryRejected,
                'pending_count' => $summaryPending,
                'approval_rate' => $summaryTotal > 0 ? round(($summaryApproved / $summaryTotal) * 100, 2) : 0,
                'rejection_rate' => $summaryTotal > 0 ? round(($summaryRejected / $summaryTotal) * 100, 2) : 0,
                'avg_processing_hours' => $this->avgProcessingHours(null, $fromDate, $toDate),
                'per_bank' => $perBank,
            ], 'Bank report retrieved.');
        }

        // Bank-scoped view
        $q = ImportRequest::query()->where('bank_id', $user->bank_id);
        $this->applyDateFilter($q, $fromDate, $toDate);

        $total = $q->count();
        $approved = (clone $q)->whereIn('status', [
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
        ])->count();
        $rejected = (clone $q)->whereIn('status', $this->rejectedStatuses())->count();
        $pending = (clone $q)->whereNotIn('status', [
            RequestStatus::EXECUTIVE_APPROVED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
            RequestStatus::COMPLETED->value,
            ...$this->rejectedStatuses(),
        ])->count();

        // Avg processing hours: created_at to executive_decided_at for decided requests
        $avgHours = $this->avgProcessingHours($user->bank_id, $fromDate, $toDate);

        // Analytics fields (bank-scoped)
        $bankAnalytics = $this->buildAnalyticsForScope($user->bank_id, $fromDate, $toDate, DB::connection()->getDriverName());

        return ApiResponse::success([
            'total_requests' => $total,
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'pending_count' => $pending,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
            'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
            'avg_processing_hours' => $avgHours,
            'monthly_trend' => $bankAnalytics['monthly_trend'],
            'category_distribution' => $bankAnalytics['category_distribution'],
            'amount_by_currency' => $bankAnalytics['amount_by_currency'],
            'submission_heatmap' => $bankAnalytics['submission_heatmap'],
        ], 'Bank report retrieved.');
    }

    // ─── Export: workflow ─────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/reports/workflow/export',
        tags: ['Reports'],
        summary: 'Export workflow report',
        description: 'Export workflow metrics as CSV or PDF. CBY only.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Report exported'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function exportWorkflow(Request $request)
    {
        $user = $request->user();
        if (! $user->isCbyUser()) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'path' => $request->path(),
                'reason' => 'workflow export requires CBY role',
            ]);

            return ApiResponse::forbidden();
        }

        $dateErrors = $this->validateDateParams();
        if ($dateErrors) {
            return ApiResponse::validationError($dateErrors);
        }

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $format = $request->query('format', 'excel');

        $formatErrors = $this->validateExportFormat($format);
        if ($formatErrors) {
            return ApiResponse::validationError($formatErrors);
        }

        // Collect counts by status
        $rows = [];
        foreach (RequestStatus::cases() as $status) {
            $q = ImportRequest::query()->where('status', $status->value);
            $this->applyDateFilter($q, $fromDate, $toDate);
            $rows[] = ['status' => $status->value, 'count' => $q->count()];
        }

        $this->auditService->log(AuditAction::REPORT_EXPORTED, $user, null, [
            'report_type' => 'workflow',
            'format' => $format,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        if ($format === 'pdf') {
            return $this->streamPdf('reports.workflow-pdf', ['rows' => $rows, 'from_date' => $fromDate, 'to_date' => $toDate], 'workflow-report.pdf');
        }

        return $this->streamCsv($rows, ['status', 'count'], 'workflow-report.csv');
    }

    // ─── Export: bank ─────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/reports/bank/export',
        tags: ['Reports'],
        summary: 'Export bank report',
        description: 'Export bank-scoped metrics as CSV or PDF.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Report exported'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function exportBank(Request $request)
    {
        $user = $request->user();
        $bankRoles = [UserRole::DATA_ENTRY->value, UserRole::BANK_REVIEWER->value, UserRole::BANK_ADMIN->value];
        $isBankReportingUser = in_array($user->role?->value, $bankRoles, true);
        $isCbyAdmin = $user->role === UserRole::CBY_ADMIN;

        if (! $isBankReportingUser && ! $isCbyAdmin) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'path' => $request->path(),
                'reason' => 'bank export requires bank role or CBY_ADMIN',
            ]);

            return ApiResponse::forbidden();
        }

        $dateErrors = $this->validateDateParams();
        if ($dateErrors) {
            return ApiResponse::validationError($dateErrors);
        }

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $format = $request->query('format', 'excel');

        $formatErrors = $this->validateExportFormat($format);
        if ($formatErrors) {
            return ApiResponse::validationError($formatErrors);
        }

        $rows = $this->buildBankRows($user, $isCbyAdmin, $fromDate, $toDate);

        $this->auditService->log(AuditAction::REPORT_EXPORTED, $user, null, [
            'report_type' => 'bank',
            'format' => $format,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        $headers = ['bank_name', 'total_requests', 'approved_count', 'rejected_count', 'pending_count', 'approval_rate', 'rejection_rate'];

        if ($format === 'pdf') {
            return $this->streamPdf('reports.bank-pdf', ['rows' => $rows, 'from_date' => $fromDate, 'to_date' => $toDate], 'bank-report.pdf');
        }

        return $this->streamCsv($rows, $headers, 'bank-report.csv');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function buildBankRows($user, bool $isCbyAdmin, ?string $fromDate, ?string $toDate): array
    {
        if ($isCbyAdmin) {
            $banks = Bank::query()->where('is_active', true)->get();
            $rows = [];
            foreach ($banks as $b) {
                $q = ImportRequest::query()->where('bank_id', $b->id);
                $this->applyDateFilter($q, $fromDate, $toDate);
                $total = $q->count();
                $approved = (clone $q)->whereIn('status', [RequestStatus::EXECUTIVE_APPROVED->value, RequestStatus::CUSTOMS_DECLARATION_ISSUED->value, RequestStatus::COMPLETED->value])->count();
                $rejected = (clone $q)->whereIn('status', $this->rejectedStatuses())->count();
                $pending = $total - $approved - $rejected;
                $rows[] = [
                    'bank_name' => $b->name,
                    'total_requests' => $total,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'pending_count' => max(0, $pending),
                    'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
                    'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
                ];
            }

            return $rows;
        }

        $q = ImportRequest::query()->where('bank_id', $user->bank_id);
        $this->applyDateFilter($q, $fromDate, $toDate);
        $total = $q->count();
        $approved = (clone $q)->whereIn('status', [RequestStatus::EXECUTIVE_APPROVED->value, RequestStatus::CUSTOMS_DECLARATION_ISSUED->value, RequestStatus::COMPLETED->value])->count();
        $rejected = (clone $q)->whereIn('status', $this->rejectedStatuses())->count();
        $pending = $total - $approved - $rejected;

        return [[
            'bank_name' => $user->bank?->name ?? '',
            'total_requests' => $total,
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'pending_count' => max(0, $pending),
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
            'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
        ]];
    }

    private function streamCsv(array $rows, array $columns, string $filename)
    {
        $bom = "\xEF\xBB\xBF";
        $csv = $bom.implode(',', $columns)."\n";
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                // Escape values that contain commas or quotes
                if (str_contains((string) $value, ',') || str_contains((string) $value, '"')) {
                    $value = '"'.str_replace('"', '""', (string) $value).'"';
                }
                $line[] = $value;
            }
            $csv .= implode(',', $line)."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Request-level reporting treats support, executive, and terminal bank
     * rejection as rejected outcomes.
     */
    private function rejectedStatuses(): array
    {
        return [
            RequestStatus::SUPPORT_REJECTED->value,
            RequestStatus::EXECUTIVE_REJECTED->value,
            RequestStatus::BANK_REJECTED->value,
        ];
    }

    private function avgProcessingHours(?int $bankId, ?string $fromDate, ?string $toDate): float
    {
        $query = ImportRequest::query()
            ->whereNotNull('executive_decided_at');

        if ($bankId !== null) {
            $query->where('bank_id', $bankId);
        }

        $this->applyDateFilter($query, $fromDate, $toDate);

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return 0.0;
        }

        $total = $rows->sum(fn ($r) => $r->created_at->diffInHours($r->executive_decided_at));

        return round($total / $rows->count(), 2);
    }

    private function streamPdf(string $view, array $data, string $filename)
    {
        $previousCompiledPath = config('view.compiled');
        $fallbackCompiledPath = storage_path('framework/views');

        if (! is_string($previousCompiledPath) || $previousCompiledPath === '' || ! is_dir($previousCompiledPath)) {
            app('files')->ensureDirectoryExists($fallbackCompiledPath);
            config(['view.compiled' => $fallbackCompiledPath]);
        }

        try {
            $pdf = app('dompdf.wrapper')->loadView($view, $data);
            $pdf->getDomPDF()->set_option('isPhpEnabled', false);
            $pdf->getDomPDF()->set_option('isRemoteEnabled', false);
            $output = $pdf->output();
        } finally {
            config(['view.compiled' => $previousCompiledPath]);
        }

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
