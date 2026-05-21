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

    #[OA\Get(path: '/api/audit', tags: ['Audit'], summary: 'List audit logs', responses: [new OA\Response(response: 200, description: 'Audit logs retrieved')])]
    public function index()
    {
        $user = request()->user();
        if (!$this->isAuditAuthorized()) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'bank_id' => $user->bank_id,
                'path' => request()->path(),
                'method' => request()->method(),
                'reason' => 'audit requires CBY_ADMIN or COMMITTEE_DIRECTOR',
            ]);

            return ApiResponse::forbidden();
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
            return ApiResponse::forbidden();
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
            return ApiResponse::forbidden();
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
            ->get();

        $items = $requests->map(function ($r) use ($dupInvoices) {
            $firstId   = $dupInvoices[$r->invoice_number];
            $siblingId = $r->id === $firstId
                ? ImportRequest::where('invoice_number', $r->invoice_number)
                    ->where('id', '!=', $firstId)
                    ->value('id')
                : $firstId;

            $siblingRef = $siblingId
                ? 'IMP-' . ImportRequest::find($siblingId)?->created_at?->format('Y') . '-' . str_pad($siblingId, 4, '0', STR_PAD_LEFT)
                : '—';

            return [
                'id'             => $r->id,
                'ref'            => 'IMP-' . $r->created_at->format('Y') . '-' . str_pad($r->id, 4, '0', STR_PAD_LEFT),
                'importer'       => $r->supplier_name ?? '—',
                'invoice_number' => $r->invoice_number,
                'sibling_id'     => $siblingId,
                'sibling_ref'    => $siblingRef,
            ];
        });

        return ApiResponse::success(['data' => $items]);
    }

    public function riskIndicators(): JsonResponse
    {
        if (!$this->isAuditAuthorized()) {
            return ApiResponse::forbidden();
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
