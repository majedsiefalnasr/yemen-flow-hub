<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\OrganizationClassification;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\Audit\AuditService;
use App\Support\InitialStageExecutorGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);
        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $sort = in_array($request->input('sort'), ['code', 'name', 'created_at'], true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = Organization::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($nested) => $nested
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return response()->json([
            'data' => OrganizationResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        return new OrganizationResource($organization);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $organization = DB::transaction(function () use ($request): Organization {
            $organization = Organization::query()->create($request->validated());
            $this->auditService->log(AuditAction::GOVERNANCE_CREATED, $request->user(), $organization, [
                'after' => $organization->only(['code', 'name', 'classification', 'is_active', 'version']),
            ]);

            return $organization->refresh();
        });

        return (new OrganizationResource($organization))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $expectedVersion = (int) $request->integer('version');
        $validated = $request->validated();

        if (array_key_exists('classification', $validated)
            && $organization->classification === OrganizationClassification::BANKING_SECTOR
            && OrganizationClassification::from((string) $validated['classification']) !== OrganizationClassification::BANKING_SECTOR
            && InitialStageExecutorGuard::organizationHasPublishedInitialExecuteGrants($organization)) {
            return $this->businessError(
                'ORGANIZATION_CLASSIFICATION_IN_USE',
                'Organization classification cannot change while published workflows grant initial-stage EXECUTE to this organization.',
                422,
                ['classification' => ['Organization classification is in use by published workflow grants.']],
            );
        }

        try {
            DB::transaction(function () use ($request, $organization, $expectedVersion, $validated): void {
                $locked = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());
                if ($expectedVersion !== (int) $locked->version) {
                    throw new StaleResourceException;
                }

                $before = $locked->only(['code', 'name', 'classification', 'is_active', 'version']);
                $locked->update([
                    'name' => $validated['name'],
                    'classification' => $validated['classification'] ?? $locked->classification,
                    'version' => $locked->version + 1,
                ]);
                $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $locked, [
                    'before' => $before,
                    'after' => $locked->only(['code', 'name', 'classification', 'is_active', 'version']),
                ]);
            });
        } catch (StaleResourceException) {
            return $this->businessError('STALE_RESOURCE', 'The organization was modified by another user.', 409);
        }

        return (new OrganizationResource($organization->refresh()))->response();
    }

    public function activate(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('update', $organization);
        $this->setActive($request, $organization, true);

        return new OrganizationResource($organization->refresh());
    }

    public function deactivate(Request $request, Organization $organization): JsonResponse|OrganizationResource
    {
        $this->authorize('update', $organization);
        if ($organization->teams()->where('is_active', true)->exists()
            || $organization->roles()->where('is_active', true)->exists()
            || $organization->users()->where('is_active', true)->exists()
            || $organization->banks()->where('is_active', true)->exists()) {
            return $this->businessError('ORGANIZATION_IN_USE', 'Organization cannot be deactivated while it has active teams, roles, users, or banks.', 422);
        }

        $this->setActive($request, $organization, false);

        return new OrganizationResource($organization->refresh());
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);
        if ($organization->isProtected() || $organization->teams()->exists() || $organization->roles()->exists() || $organization->users()->exists() || $organization->banks()->exists()) {
            return $this->businessError('ORGANIZATION_PROTECTED', 'Organization cannot be deleted.', 422);
        }

        $organization->delete();

        return response()->json(null, 204);
    }

    private function setActive(Request $request, Organization $organization, bool $active): void
    {
        $before = $organization->only(['is_active', 'version']);
        $organization->update([
            'is_active' => $active,
            'version' => $organization->version + 1,
        ]);
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $organization, [
            'before' => $before,
            'after' => $organization->only(['is_active', 'version']),
        ]);
    }

    private function businessError(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => request()->header('X-Request-ID'),
            ],
        ], $status);
    }
}
