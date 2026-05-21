<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\ImportRequest;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AuditController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    private function isAuditAuthorized(): bool
    {
        $role = request()->user()->role;
        return in_array($role, [UserRole::CBY_ADMIN, UserRole::COMMITTEE_DIRECTOR], true);
    }

    private function forbiddenAuditResponse(string $reason): JsonResponse
    {
        $user = request()->user();

        $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
            'bank_id' => $user->bank_id,
            'path' => request()->path(),
            'method' => request()->method(),
            'reason' => $reason,
        ]);

        return ApiResponse::forbidden();
    }

    private function formatAuditRequestRef(?ImportRequest $request, ?int $fallbackId = null): string
    {
        $requestId = $request?->id ?? $fallbackId;

        if (!$requestId) {
            return '—';
        }

        $year = $request?->created_at?->format('Y') ?? now()->format('Y');

        return 'IMP-' . $year . '-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
    }

    #[OA\Get(path: '/api/audit', tags: ['Audit'], summary: 'List audit logs', responses: [new OA\Response(response: 200, description: 'Audit logs retrieved')])]
    public function index()
    {
        if (!$this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit requires CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        $items = AuditLog::query()
            ->with('user')
            ->when(request()->filled('user_id'), fn ($q) => $q->where('user_id', request('user_id')))
            ->when(request()->filled('action'), fn ($q) => $q->where('action', request('action')))
            ->when(request()->filled('entity_type'), fn ($q) => $q->where('subject_type', request('entity_type')))
            ->when(request()->filled('subject_type') && !request()->filled('entity_type'), fn ($q) => $q->where('subject_type', request('subject_type')))
            ->when(request()->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', request('from_date')))
            ->when(request()->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', request('to_date')))
            ->latest('id')
            ->paginate(30);

        return ApiResponse::success([
            'data' => AuditLogResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Audit logs retrieved.');
    }

    public function stats(): JsonResponse
    {
        if (!$this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit stats require CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        $todayCount = AuditLog::query()
            ->whereDate('created_at', today())
            ->count();

        $duplicateInvoiceCount = DB::table('import_requests')
            ->whereNotNull('invoice_number')
            ->select('invoice_number', DB::raw('COUNT(*) as cnt'))
            ->groupBy('invoice_number')
            ->havingRaw('cnt > 1')
            ->get()
            ->count();

        return ApiResponse::success([
            'today_count'             => $todayCount,
            'duplicate_invoice_count' => $duplicateInvoiceCount,
        ]);
    }

    public function duplicates(): JsonResponse
    {
        if (!$this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit duplicates require CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        $dupInvoices = DB::table('import_requests')
            ->whereNotNull('invoice_number')
            ->select('invoice_number', DB::raw('MIN(id) as first_id'))
            ->groupBy('invoice_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('first_id', 'invoice_number');

        $requests = ImportRequest::query()
            ->whereIn('invoice_number', $dupInvoices->keys())
            ->orderBy('invoice_number')
            ->orderBy('id')
            ->paginate(30);

        $invoiceNumbers = collect($requests->items())
            ->pluck('invoice_number')
            ->filter()
            ->unique()
            ->values();

        $requestsByInvoice = ImportRequest::query()
            ->whereIn('invoice_number', $invoiceNumbers)
            ->orderBy('invoice_number')
            ->orderBy('id')
            ->get()
            ->groupBy('invoice_number');

        $items = collect($requests->items())->map(function (ImportRequest $request) use ($dupInvoices, $requestsByInvoice) {
            /** @var Collection<int, ImportRequest> $siblings */
            $siblings = $requestsByInvoice->get($request->invoice_number, collect());
            $firstId = (int) ($dupInvoices[$request->invoice_number] ?? $request->id);
            $firstRequest = $siblings->firstWhere('id', $firstId) ?? $siblings->first();
            $sibling = $request->id === $firstId
                ? $siblings->first(fn (ImportRequest $candidate) => $candidate->id !== $request->id)
                : $firstRequest;

            return [
                'id' => $request->id,
                'ref' => $this->formatAuditRequestRef($request),
                'importer' => $request->supplier_name ?? '—',
                'invoice_number' => $request->invoice_number,
                'sibling_id' => $sibling?->id,
                'sibling_ref' => $this->formatAuditRequestRef($sibling, $sibling?->id),
            ];
        })->values();

        return ApiResponse::success([
            'data' => $items,
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function riskIndicators(): JsonResponse
    {
        if (!$this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit risk indicators require CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        return ApiResponse::success([
            'data' => [
                ['title' => 'نمط طلبات غير عادي',       'body' => 'مستخدم u00432 قدّم 14 طلب في 30 دقيقة',   'level' => 'عالية'],
                ['title' => 'محاولة تسجيل دخول مشبوهة',  'body' => '5 محاولات فاشلة من IP 196.4.112.18',     'level' => 'عالية'],
                ['title' => 'تعديل فاتورة بعد الاعتماد', 'body' => 'تعديل على IMP-2025-1011',                 'level' => 'متوسطة'],
                ['title' => 'وثيقة بصلاحية منتهية',       'body' => 'شهادة منشأ على IMP-2025-1027',           'level' => 'منخفضة'],
            ],
        ]);
    }
}
