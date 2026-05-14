<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class AuditController extends Controller
{
    #[OA\Get(path: '/api/audit', tags: ['Audit'], summary: 'List audit logs', responses: [new OA\Response(response: 200, description: 'Audit logs retrieved')])]
    public function index()
    {
        $user = request()->user();
        if (!$user->hasRole(UserRole::CBY_ADMIN) && !$user->hasRole(UserRole::EXECUTIVE_DIRECTOR)) {
            return ApiResponse::forbidden();
        }

        $items = AuditLog::query()
            ->when(request()->filled('user_id'), fn ($q) => $q->where('user_id', request('user_id')))
            ->when(request()->filled('action'), fn ($q) => $q->where('action', request('action')))
            ->when(request()->filled('subject_type'), fn ($q) => $q->where('subject_type', request('subject_type')))
            ->when(request()->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', request('from_date')))
            ->when(request()->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', request('to_date')))
            ->latest('id')
            ->paginate(30);

        return ApiResponse::success(AuditLogResource::collection($items), 'Audit logs retrieved.');
    }
}
