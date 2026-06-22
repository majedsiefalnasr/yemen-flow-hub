<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);
        $sort = in_array($request->input('sort'), ['code', 'name', 'created_at'], true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = Role::query()->with('organization')
            ->when($request->filled('organization_id'), fn ($q) => $q->where('organization_id', $request->integer('organization_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n->where('code', 'like', '%'.$request->string('search')->toString().'%')->orWhere('name', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy($sort, $direction)->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => RoleResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('organization'));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);
        $role = Role::query()->create($request->validated())->refresh();
        $this->auditService->log(AuditAction::GOVERNANCE_CREATED, $request->user(), $role, ['after' => $role->toArray()]);

        return (new RoleResource($role->load('organization')))->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);
        $expectedVersion = $request->integer('version');

        try {
            DB::transaction(function () use ($request, $role, $expectedVersion): void {
                $locked = Role::query()->lockForUpdate()->findOrFail($role->getKey());
                if ($expectedVersion !== $locked->version) {
                    throw new StaleResourceException;
                }
                $before = $locked->toArray();
                // Rename only. The role's permission set is unchanged, so assigned
                // users' sessions are intentionally NOT invalidated here — a cosmetic
                // rename must not force a mass logout, and authorization is still
                // driven by the legacy users.role column (no pivot propagation yet).
                $locked->update(['name' => $request->string('name')->toString(), 'version' => $locked->version + 1]);
                $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $locked, ['before' => $before, 'after' => $locked->toArray()]);
            });
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The role was modified by another user.', 409);
        }

        return (new RoleResource($role->refresh()->load('organization')))->response();
    }

    public function activate(Request $request, Role $role): RoleResource
    {
        $this->authorize('update', $role);
        $role->update(['is_active' => true, 'version' => $role->version + 1]);
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $role);

        return new RoleResource($role->refresh()->load('organization'));
    }

    public function deactivate(Request $request, Role $role): JsonResponse|RoleResource
    {
        $this->authorize('update', $role);
        if ($role->isProtected() || $role->users()->exists()) {
            return $this->error('ROLE_IN_USE', 'Assigned or system roles cannot be deactivated.', 422);
        }
        $role->update(['is_active' => false, 'version' => $role->version + 1]);
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $role);

        return new RoleResource($role->refresh()->load('organization'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);
        if ($role->isProtected() || $role->users()->exists()) {
            return $this->error('ROLE_PROTECTED', 'Role cannot be deleted.', 422);
        }
        $role->delete();

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
