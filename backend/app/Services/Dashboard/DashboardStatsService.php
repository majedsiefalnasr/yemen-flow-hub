<?php

namespace App\Services\Dashboard;

use App\DTOs\Authorization\DataScopeContext;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Authorization\DataScope;
use App\Support\ApiResponse;
use App\Support\EngineRequestReadModel;
use App\Support\RoleCodes;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DashboardStatsService
{
    public function stats(User $user): JsonResponse
    {
        $scope = $this->getScope($user);

        return match (true) {
            $user->hasRoleCode(RoleCodes::INTAKE) => $this->dataEntryStats($user, $scope),
            $user->hasRoleCode(RoleCodes::INTERNAL_REVIEWER) => $this->bankReviewerStats($user, $scope),
            $user->hasRoleCode(RoleCodes::BANK_ADMIN) => $this->bankAdminStats($user, $scope),
            $user->hasRoleCode(RoleCodes::SUPPORT) => $this->supportCommitteeStats($user, $scope),
            $user->hasRoleCode(RoleCodes::FX_SWIFT) => $this->swiftOfficerStats($user, $scope),
            $user->hasRoleCode(RoleCodes::COMMITTEE_MANAGER) => $this->executiveMemberStats($user, $scope),
            $user->hasRoleCode(RoleCodes::COMMITTEE_DIRECTOR) => $this->committeeDirectorStats($user, $scope),
            $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN) => $this->cbyadminStats($user, $scope),
            default => ApiResponse::success([], 'Dashboard stats retrieved.'),
        };
    }

    private function bankAdminStats($user, DataScopeContext $scope): JsonResponse
    {
        $asOf = CarbonImmutable::now();

        if (! $scope->systemWide && $scope->ownBankId === null) {
            return ApiResponse::success(
                $this->emptyBankAdminStats($asOf),
                'Dashboard stats retrieved.'
            );
        }

        $base = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($base, $scope);

        $total = (clone $base)->count();
        $pending = (clone $base)->where(EngineRequestReadModel::bucket('pending_bank_review'))->count();
        $approved = (clone $base)->where(EngineRequestReadModel::bucket('completed'))->count();
        $rejected = (clone $base)->where(EngineRequestReadModel::bucket('rejected'))->count();
        $atCby = (clone $base)->where(EngineRequestReadModel::bucket('at_cby'))->count();

        $activeUsersQuery = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('code', RoleCodes::BANK_ADMIN_MANAGED))
            ->where('is_active', true);

        DataScope::applyTo($activeUsersQuery, $scope, 'users.bank_id');
        $activeUsers = $activeUsersQuery->count();

        $totalFinancedAmount = (float) (clone $base)
            ->where(EngineRequestReadModel::bucket('completed'))
            ->sum('engine_requests.amount');

        $recentRequests = (clone $base)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $monthlyRequests = $this->bankMonthlyRequests($user, $asOf, $scope);

        return ApiResponse::success([
            // New Story 6.3.2 fields
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'total_financed_amount' => $totalFinancedAmount,
            'monthly_requests' => $monthlyRequests,
            // Backward-compat fields retained for existing clients
            'pending_bank_review' => $pending,
            'at_cby' => $atCby,
            'completed' => $approved,
            'active_users' => $activeUsers,
            'recent_requests' => EngineRequestReadModel::resourceCollection($recentRequests),
        ], 'Dashboard stats retrieved.');
    }

    private function bankMonthlyRequests(User $user, CarbonImmutable $asOf, DataScopeContext $scope): array
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
        $query = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($query, $scope);

        $createdAtValues = $query
            ->where('engine_requests.created_at', '>=', $windowStart)
            ->pluck('engine_requests.created_at');

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
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total_financed_amount' => 0.0,
            'monthly_requests' => $this->bankMonthlyRequestsForEmptyBank($asOf),
            // Backward-compat fields retained for existing clients
            'pending_bank_review' => 0,
            'at_cby' => 0,
            'completed' => 0,
            'active_users' => 0,
            'recent_requests' => [],
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

    private function dataEntryStats($user, DataScopeContext $scope)
    {
        $base = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($base, $scope);

        $draft = (clone $base)->where(EngineRequestReadModel::bucket('draft'))->count();
        // Draft-rejected-internal has no dedicated engine bucket; DRAFT_REJECTED_INTERNAL
        // ported to CREATE/REJECTED so it is distinguishable from an open draft.
        $returned = (clone $base)
            ->where(EngineRequestReadModel::bucket('draft'))
            ->where(EngineRequestReadModel::bucket('rejected'))
            ->count();
        $openDraft = (clone $base)
            ->where(EngineRequestReadModel::bucket('draft'))
            ->where(EngineRequestReadModel::bucket('active'))
            ->count();

        $underCby = (clone $base)->where(EngineRequestReadModel::bucket('at_cby'))->count();
        $completed = (clone $base)->where(EngineRequestReadModel::bucket('completed'))->count();

        $returnedRequests = (clone $base)
            ->where(EngineRequestReadModel::bucket('draft'))
            ->where(EngineRequestReadModel::bucket('rejected'))
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $draftRequests = (clone $base)
            ->where(EngineRequestReadModel::bucket('draft'))
            ->where(EngineRequestReadModel::bucket('active'))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $recentRequests = (clone $base)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'draft' => $openDraft,
            'returned' => $returned,
            'under_cby_processing' => $underCby,
            'completed' => $completed,
            'draft_requests' => EngineRequestReadModel::resourceCollection($draftRequests),
            'returned_requests' => EngineRequestReadModel::resourceCollection($returnedRequests),
            'recent_requests' => EngineRequestReadModel::resourceCollection($recentRequests),
        ], 'Dashboard stats retrieved.');
    }

    private function bankReviewerStats($user, DataScopeContext $scope)
    {
        $base = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($base, $scope);

        $pendingReview = (clone $base)->where(EngineRequestReadModel::bucket('pending_bank_review'))->count();
        $atCby = (clone $base)->where(EngineRequestReadModel::bucket('at_cby'))->count();

        // "Returned by support" maps to a rejected lifecycle outcome. The engine has
        // no distinct support-return status during coexistence, so this keys on
        // terminal REJECTED rather than the impossible "on SUPPORT stage AND rejected"
        // state the stage queue would imply.
        $returnedBySupport = (clone $base)
            ->where(EngineRequestReadModel::bucket('rejected'))
            ->count();

        $approvedCompleted = (clone $base)->where(EngineRequestReadModel::bucket('completed'))->count();

        $reviewQueue = (clone $base)
            ->where(EngineRequestReadModel::bucket('pending_bank_review'))
            ->orderBy('updated_at')
            ->limit(50)
            ->get();

        $downstreamQueue = (clone $base)
            ->where(EngineRequestReadModel::bucket('at_cby'))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'pending_review' => $pendingReview,
            'at_cby' => $atCby,
            'returned_by_support' => $returnedBySupport,
            'approved_completed' => $approvedCompleted,
            'review_queue' => EngineRequestReadModel::resourceCollection($reviewQueue),
            'downstream_queue' => EngineRequestReadModel::resourceCollection($downstreamQueue),
        ], 'Dashboard stats retrieved.');
    }

    // SUPPORT_COMMITTEE is CBY-global by institutional design: committee members review
    // requests from all banks. No bank_id filter is applied here — this is intentional
    // governance behaviour, not a missing tenant scope.
    private function supportCommitteeStats(User $user, DataScopeContext $scope): JsonResponse
    {
        // The support queue is active work parked on the SUPPORT stage: a request
        // that has closed or been rejected has left the stage and must not appear
        // here, so the stage bucket is scoped to ACTIVE.
        $base = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($base, $scope);

        $base->where(EngineRequestReadModel::bucket('support_queue'))
            ->active();

        $waitingForClaim = (clone $base)->whereNull('engine_requests.claimed_by')->count();

        $activeByMe = (clone $base)
            ->where('engine_requests.claimed_by', $user->id)
            ->count();

        $claimedByOthers = (clone $base)
            ->whereNotNull('engine_requests.claimed_by')
            ->where('engine_requests.claimed_by', '!=', $user->id)
            ->count();

        // Rolling 7-day window — "معتمد حديثاً" reflects active committee throughput,
        // not a cumulative total. Scoped globally (all SC members), not per-reviewer.
        // The engine has no support_approved_at column during coexistence, so this
        // keys on terminal completion (status CLOSED) within the window as the
        // best-available throughput signal.
        $recentlyApprovedQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($recentlyApprovedQuery, $scope);

        $recentlyApproved = $recentlyApprovedQuery
            ->where(EngineRequestReadModel::bucket('completed'))
            ->where('engine_requests.updated_at', '>=', now()->subDays(7))
            ->count();

        $supportQueue = (clone $base)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return ApiResponse::success([
            'waiting_for_claim' => $waitingForClaim,
            'active_by_me' => $activeByMe,
            'claimed_by_others' => $claimedByOthers,
            'recently_approved' => $recentlyApproved,
            'support_queue' => $this->withClaimedBy($supportQueue),
        ], 'Dashboard stats retrieved.');
    }

    // SWIFT_OFFICER is bank-scoped: sees only their bank's requests.
    private function swiftOfficerStats(User $user, DataScopeContext $scope): JsonResponse
    {
        $base = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($base, $scope);

        // The pending SWIFT queue is active work parked on the FX stage; a request
        // that has closed or been rejected has left the stage and must not appear.
        $pendingSwiftUpload = (clone $base)
            ->where(EngineRequestReadModel::bucket('swift_queue'))
            ->active()
            ->count();

        // The engine has no SWIFT-document signal during coexistence, so "uploaded"
        // is approximated as any request that has reached the FX stage (active or
        // beyond), matching the legacy SWIFT_UPLOADED throughput key.
        $uploaded = (clone $base)
            ->where(EngineRequestReadModel::bucket('swift_queue'))
            ->count();

        // Final outcomes key on terminal lifecycle status, not stage, because an
        // executive-decided request has left the EXEC stage. CLOSED == approved &
        // completed, REJECTED == rejected (DI-3 removed voting, so there is no
        // vote-derived signal here).
        $finalApproved = (clone $base)
            ->where(EngineRequestReadModel::bucket('completed'))
            ->count();

        $finalRejected = (clone $base)
            ->where(EngineRequestReadModel::bucket('rejected'))
            ->count();

        $swiftQueue = (clone $base)
            ->where(EngineRequestReadModel::bucket('swift_queue'))
            ->active()
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return ApiResponse::success([
            'pending_swift_upload' => $pendingSwiftUpload,
            'uploaded' => $uploaded,
            'final_approved' => $finalApproved,
            'final_rejected' => $finalRejected,
            'swift_queue' => EngineRequestReadModel::resourceCollection($swiftQueue),
        ], 'Dashboard stats retrieved.');
    }

    // Voting was removed by DI-3. Counters below are zeroed and queues are empty;
    // keys are retained for response-shape stability during frontend coexistence.
    private function executiveVotingStats(User $user): array
    {
        return [
            'waiting_for_voting_open' => 0,
            'active_voting_sessions' => 0,
            'decisions_approved' => 0,
            'decisions_rejected' => 0,
            'finalized_decisions' => 0,
            'pending_my_vote' => 0,
            'voting_queue' => [],
        ];
    }

    // EXECUTIVE_MEMBER: global CBY view — no org scope
    private function executiveMemberStats(User $user, DataScopeContext $scope): JsonResponse
    {
        return ApiResponse::success($this->executiveVotingStats($user), 'Dashboard stats retrieved.');
    }

    // COMMITTEE_DIRECTOR: global CBY view — no org scope
    private function committeeDirectorStats(User $user, DataScopeContext $scope): JsonResponse
    {
        // UI-FX-001: the Director's dashboard queue is the FINAL stage they
        // execute — the same requests /customs and my-queue surface — not the
        // FX_CONFIRM bucket (owned by the national FX team). Counting FINAL here
        // makes the dashboard headline, the dedicated /customs "ready" list, and
        // the Director's executable my-queue agree on the same records.
        $finalQueueQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($finalQueueQuery, $scope);

        $finalQueue = $finalQueueQuery
            ->where(EngineRequestReadModel::bucket('director_final_queue'))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $completedQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($completedQuery, $scope);
        $finalizedApproved = (clone $completedQuery)->where(EngineRequestReadModel::bucket('completed'))->count();

        $rejectedQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($rejectedQuery, $scope);
        $finalizedRejected = (clone $rejectedQuery)->where(EngineRequestReadModel::bucket('rejected'))->count();

        $finalQueueResource = EngineRequestReadModel::resourceCollection($finalQueue);

        return ApiResponse::success([
            // The Director's actionable queue (FINAL stage).
            'final_pending' => $finalQueue->count(),
            'final_pending_queue' => $finalQueueResource,
            'finalized_approved' => $finalizedApproved,
            'finalized_rejected' => $finalizedRejected,
            // Backward-compatible keys the existing shared dashboard still reads,
            // now pointed at the FINAL queue so no surface disagrees during the
            // migration to the dedicated Director dashboard.
            'fx_confirmation_pending' => $finalQueue->count(),
            'fx_confirmation_queue' => $finalQueueResource,
            'customs_declaration_pending' => $finalQueueResource,
        ], 'Dashboard stats retrieved.');
    }

    /**
     * Adds a `claimed_by` object (or null) to each resource-collection item, mirroring
     * the legacy claimedByUser eager-loaded relation shape consumed by the Support
     * Committee dashboard.
     *
     * @param  Collection<int, EngineRequest>  $requests
     */
    private function withClaimedBy($requests): array
    {
        return collect(EngineRequestReadModel::resourceCollection($requests))
            ->map(function (array $item, int $index) use ($requests) {
                $request = $requests->values()->get($index);

                return [
                    ...$item,
                    'claimed_by' => $request->claimedBy ? [
                        'id' => $request->claimedBy->id,
                        'name' => $request->claimedBy->name,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    // CBY_ADMIN: full-system visibility across all banks
    private function cbyadminStats(User $user, DataScopeContext $scope): JsonResponse
    {
        $base = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($base, $scope);

        $total = (clone $base)->count();
        $approved = (clone $base)->where(EngineRequestReadModel::bucket('completed'))->count();
        $rejected = (clone $base)->where(EngineRequestReadModel::bucket('rejected'))->count();
        $inProcess = (clone $base)
            ->where(EngineRequestReadModel::bucket('active'))
            ->whereDoesntHave('currentStage', fn ($q) => $q->whereIn('workflow_stages.code', ['CREATE']))
            ->count();

        $recentRequests = (clone $base)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'total' => $total,
            'approved' => $approved,
            'in_process' => $inProcess,
            'rejected' => $rejected,
            'compliance_alerts' => $this->complianceAlerts($user, $scope),
            'most_active_banks' => $this->mostActiveBanks($user, $scope),
            'monthly_requests' => $this->cbyadminMonthlyRequests($user, CarbonImmutable::now(), $scope),
            'category_distribution' => $this->cbyadminCategoryDistribution($user, $scope),
            'recent_requests' => EngineRequestReadModel::resourceCollection($recentRequests),
        ], 'Dashboard stats retrieved.');
    }

    private function cbyadminMonthlyRequests(User $user, CarbonImmutable $asOf, DataScopeContext $scope): array
    {
        $timezone = config('app.timezone', 'UTC');
        $anchorMonth = $asOf->setTimezone($timezone)->startOfMonth();
        $windowStart = $anchorMonth->subMonths(5);

        $monthKeys = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthKeys[] = $anchorMonth->subMonths($i)->format('Y-m');
        }

        $submitted = array_fill_keys($monthKeys, 0);
        $approved = array_fill_keys($monthKeys, 0);

        $rowsQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($rowsQuery, $scope);

        $rows = $rowsQuery
            ->where('engine_requests.created_at', '>=', $windowStart)
            ->get(['engine_requests.id', 'engine_requests.created_at', 'engine_requests.status']);

        $approvedIdsQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($approvedIdsQuery, $scope);

        $approvedIds = $approvedIdsQuery
            ->where('engine_requests.created_at', '>=', $windowStart)
            ->where(EngineRequestReadModel::bucket('completed'))
            ->pluck('engine_requests.id')
            ->all();

        foreach ($rows as $row) {
            $monthKey = (CarbonImmutable::instance($row->created_at))->setTimezone($timezone)->format('Y-m');
            if (! array_key_exists($monthKey, $submitted)) {
                continue;
            }
            $submitted[$monthKey]++;
            if (in_array($row->id, $approvedIds, true)) {
                $approved[$monthKey]++;
            }
        }

        return array_map(
            fn (string $month): array => [
                'month' => $month,
                'submitted' => $submitted[$month],
                'approved' => $approved[$month],
            ],
            $monthKeys
        );
    }

    private function cbyadminCategoryDistribution(User $user, DataScopeContext $scope): array
    {
        // Category distribution by commodity_type field (if available), falling back to currency grouping
        // as a meaningful operational segmentation visible in the CBY_ADMIN dashboard.
        $colors = ['#0066cc', '#1b5e20', '#f57f17', '#c62828', '#5856d6', '#32ade6'];

        $query = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($query, $scope);

        $groups = $query
            ->select([])
            ->selectRaw('engine_requests.currency as label, COUNT(*) as `count`')
            ->groupBy('engine_requests.currency')
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

    private function complianceAlerts(User $user, DataScopeContext $scope): array
    {
        // Duplicate suppliers: the engine has no dedicated supplier_name projection
        // column (supplier identity now lives on merchant/data payload), so this
        // surfaces duplicate merchants among non-draft requests instead.
        $duplicateSuppliersQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($duplicateSuppliersQuery, $scope);

        $duplicateSuppliers = $duplicateSuppliersQuery
            ->whereDoesntHave('currentStage', fn ($q) => $q->whereIn('workflow_stages.code', ['CREATE']))
            ->whereNotNull('engine_requests.merchant_id')
            ->join('merchants', 'merchants.id', '=', 'engine_requests.merchant_id')
            ->select([])
            ->selectRaw('merchants.name as supplier_name, COUNT(*) as `count`')
            ->groupBy('merchants.name')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['supplier_name' => $row->supplier_name, 'count' => (int) $row->count])
            ->values()
            ->all();

        // USD requests exceeding $1,000,000 that are not closed/rejected
        $highAmountRequestsQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($highAmountRequestsQuery, $scope);

        $highAmountRequests = $highAmountRequestsQuery
            ->where('engine_requests.currency', 'USD')
            ->where('engine_requests.amount', '>', 1_000_000)
            ->where(EngineRequestReadModel::bucket('active'))
            ->orderByDesc('engine_requests.amount')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'reference_number' => EngineRequestReadModel::reference($r),
                'bank_name' => $r->bank?->name ?? '—',
                'amount' => (float) $r->amount,
                'currency' => $r->currency,
            ])
            ->values()
            ->all();

        // Stale pending: not draft, not closed/rejected, not at the final FX-confirmation
        // hand-off stage, updated > 14 days ago.
        $stalePendingRequestsQuery = EngineRequestReadModel::queryFor($user);
        DataScope::applyTo($stalePendingRequestsQuery, $scope);

        $stalePendingRequests = $stalePendingRequestsQuery
            ->where(EngineRequestReadModel::bucket('active'))
            ->whereDoesntHave('currentStage', fn ($q) => $q->whereIn('workflow_stages.code', ['CREATE', 'FX_CONFIRM', 'FINAL']))
            ->where('engine_requests.updated_at', '<', now()->subDays(14))
            ->orderBy('engine_requests.updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'reference_number' => EngineRequestReadModel::reference($r),
                'bank_name' => $r->bank?->name ?? '—',
                'updated_at' => $r->updated_at?->toIso8601String() ?? null,
            ])
            ->values()
            ->all();

        return [
            'duplicate_suppliers' => $duplicateSuppliers,
            'high_amount_requests' => $highAmountRequests,
            'stale_pending_requests' => $stalePendingRequests,
        ];
    }

    private function mostActiveBanks(User $user, DataScopeContext $scope): array
    {
        $query = Bank::query()
            ->select('banks.id as bank_id', 'banks.name as bank_name')
            ->selectRaw('COUNT(engine_requests.id) as request_count')
            ->leftJoin('engine_requests', 'engine_requests.bank_id', '=', 'banks.id')
            ->groupBy('banks.id', 'banks.name')
            ->orderByDesc('request_count')
            ->limit(5);

        DataScope::applyTo($query, $scope, 'banks.id');

        return $query->get()
            ->map(fn ($row) => [
                'bank_id' => $row->bank_id,
                'bank_name' => $row->bank_name,
                'request_count' => (int) $row->request_count,
            ])
            ->values()
            ->all();
    }

    private function getScope(User $user): DataScopeContext
    {
        $scope = DataScope::forUser($user);

        if ($user->isSystemAdmin()) {
            return new DataScopeContext(systemWide: true);
        }

        return $scope;
    }
}
