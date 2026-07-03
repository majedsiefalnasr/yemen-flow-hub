<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    private function isAuditAuthorized(): bool
    {
        return request()->user()->hasAnyRoleCode(['system_admin', 'committee_director']);
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

    #[OA\Get(path: '/api/audit', tags: ['Audit'], summary: 'List audit logs', responses: [new OA\Response(response: 200, description: 'Audit logs retrieved')])]
    public function index()
    {
        if (! $this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit requires CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        $items = AuditLog::query()
            ->with('user')
            ->when(request()->filled('user_id'), fn ($q) => $q->where('user_id', request('user_id')))
            ->when(request()->filled('action'), fn ($q) => $q->where('action', request('action')))
            ->when(request()->filled('entity_type'), fn ($q) => $q->where('subject_type', request('entity_type')))
            ->when(request()->filled('subject_type') && ! request()->filled('entity_type'), fn ($q) => $q->where('subject_type', request('subject_type')))
            ->when(request()->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', request('from_date')))
            ->when(request()->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', request('to_date')))
            ->latest('id')
            ->paginate(max(1, min(request()->integer('per_page', 30), 100)));

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
        if (! $this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit stats require CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        $todayCount = AuditLog::query()
            ->whereDate('created_at', today())
            ->count();

        return ApiResponse::success([
            'today_count' => $todayCount,
            'duplicate_invoice_count' => 0,
        ]);
    }

    public function duplicates(): JsonResponse
    {
        if (! $this->isAuditAuthorized()) {
            return $this->forbiddenAuditResponse('audit duplicates require CBY_ADMIN or COMMITTEE_DIRECTOR');
        }

        return ApiResponse::success(['data' => []]);
    }

    public function riskIndicators(): JsonResponse
    {
        if (! $this->isAuditAuthorized()) {
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
