<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class AuditController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    #[OA\Get(path: '/api/audit', tags: ['Audit'], summary: 'List audit logs', responses: [new OA\Response(response: 200, description: 'Audit logs retrieved')])]
    public function index()
    {
        $user = request()->user();
        if (!$user->hasRole(UserRole::CBY_ADMIN)) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $user, null, [
                'bank_id' => $user->bank_id,
                'path' => request()->path(),
                'method' => request()->method(),
                'reason' => 'audit requires CBY_ADMIN',
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
}
