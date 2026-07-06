<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Services\Authorization\DataScope;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function duplicateInvoices(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->permissionService->userHasCapability($user, 'audit', 'VIEW')) {
            abort(403);
        }

        $scope = DataScope::forUser($user);

        $query = EngineRequest::query()
            ->select('invoice_number', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->groupBy('invoice_number')
            ->havingRaw('COUNT(*) > 1');

        DataScope::applyTo($query, $scope);
        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->integer('bank_id'));
        }

        $duplicateInvoices = $query->orderByDesc('duplicate_count')->paginate($this->perPage($request));

        $invoiceNumbers = collect($duplicateInvoices->items())->pluck('invoice_number');
        $detailQuery = EngineRequest::query()
            ->whereIn('invoice_number', $invoiceNumbers)
            ->with(['bank:id,name', 'merchant:id,name', 'currentStage:id,code,name']);

        DataScope::applyTo($detailQuery, $scope);
        $requests = $detailQuery
            ->get()
            ->groupBy('invoice_number');

        $data = collect($duplicateInvoices->items())->map(fn ($row) => [
            'invoice_number' => $row->invoice_number,
            'count' => (int) $row->duplicate_count,
            'requests' => ($requests[$row->invoice_number] ?? collect())->map(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'bank' => $r->bank?->name,
                'merchant' => $r->merchant?->name,
                'amount' => $r->amount,
                'currency' => $r->currency,
                'status' => $r->status,
                'stage' => $r->currentStage?->name,
                'created_at' => $r->created_at?->toISOString(),
            ])->values(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $duplicateInvoices->currentPage(),
                'last_page' => $duplicateInvoices->lastPage(),
                'per_page' => $duplicateInvoices->perPage(),
                'total' => $duplicateInvoices->total(),
            ],
        ]);
    }

    public function expiredDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->permissionService->userHasCapability($user, 'audit', 'VIEW')) {
            abort(403);
        }

        $scope = DataScope::forUser($user);

        $merchantQuery = Merchant::query()
            ->whereNotNull('tax_card_expiry')
            ->whereDate('tax_card_expiry', '<', now())
            ->with('bank:id,name');

        DataScope::applyTo($merchantQuery, $scope);

        $merchants = $merchantQuery->paginate($this->perPage($request));

        $data = collect($merchants->items())->map(fn (Merchant $m) => [
            'merchant_id' => $m->id,
            'merchant_name' => $m->name,
            'bank' => $m->bank?->name,
            'expired_documents' => [
                ['type' => 'tax_card', 'expired_at' => $m->tax_card_expiry->toDateString()],
            ],
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $merchants->currentPage(),
                'last_page' => $merchants->lastPage(),
                'per_page' => $merchants->perPage(),
                'total' => $merchants->total(),
            ],
        ]);
    }

    public function slaBreaches(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->permissionService->userHasCapability($user, 'audit', 'VIEW')) {
            abort(403);
        }

        $scope = DataScope::forUser($user);

        $query = EngineRequest::query()
            ->withStageEntry()
            ->active()
            ->whereNotNull('current_stage.sla_duration_minutes')
            ->whereRaw(EngineRequest::slaDeadlineEpochSql().' < '.EngineRequest::nowEpochSql())
            ->with(['currentStage:id,code,name,sla_duration_minutes', 'bank:id,name', 'creator:id,name']);

        DataScope::applyTo($query, $scope, 'engine_requests.bank_id');
        if ($request->filled('bank_id')) {
            $query->where('engine_requests.bank_id', $request->integer('bank_id'));
        }

        $page = $query
            ->orderByRaw(EngineRequest::slaDeadlineEpochSql().' ASC')
            ->paginate($this->perPage($request));

        $data = collect($page->items())->map(fn (EngineRequest $r) => [
            'id' => $r->id,
            'reference' => $r->reference,
            'bank' => $r->bank?->name,
            'stage' => $r->currentStage?->name,
            'stage_code' => $r->currentStage?->code,
            'sla_minutes' => $r->currentStage?->sla_duration_minutes,
            'stage_entered_at' => $r->stage_entered_at,
            'sla_status' => $r->sla_status,
            'amount' => $r->amount,
            'currency' => $r->currency,
            'created_at' => $r->created_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 25)));
    }
}
