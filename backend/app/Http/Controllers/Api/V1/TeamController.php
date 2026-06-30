<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Team::class);
        $page = Team::query()->with('organization')
            ->when($request->filled('organization_id'), fn ($q) => $q->where('organization_id', $request->integer('organization_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('code', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('name', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy(in_array($request->input('sort'), ['code', 'name', 'created_at'], true) ? $request->input('sort') : 'created_at', $request->input('direction') === 'asc' ? 'asc' : 'desc')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => TeamResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load('organization'));
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $this->authorize('create', Team::class);
        $team = DB::transaction(function () use ($request): Team {
            $team = Team::query()->create($request->safe()->except('role_code'))->refresh();
            $this->auditService->log(AuditAction::GOVERNANCE_CREATED, $request->user(), $team, ['after' => $team->toArray()]);

            return $team;
        });

        return (new TeamResource($team->load('organization')))->response()->setStatusCode(201);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);
        $expectedVersion = $request->integer('version');

        try {
            DB::transaction(function () use ($request, $team, $expectedVersion): void {
                $locked = Team::query()->lockForUpdate()->findOrFail($team->getKey());
                if ($expectedVersion !== $locked->version) {
                    throw new StaleResourceException;
                }
                $before = $locked->toArray();
                $locked->update(['name' => $request->string('name')->toString(), 'version' => $locked->version + 1]);
                $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $locked, ['before' => $before, 'after' => $locked->toArray()]);
            });
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The team was modified by another user.', 409);
        }

        return (new TeamResource($team->refresh()->load('organization')))->response();
    }

    public function activate(Request $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);
        $this->setActive($request, $team, true);

        return new TeamResource($team->refresh()->load('organization'));
    }

    public function deactivate(Request $request, Team $team): JsonResponse|TeamResource
    {
        $this->authorize('update', $team);
        if ($team->users()->exists()) {
            return $this->error('TEAM_IN_USE', 'Team cannot be deactivated while assigned to users.', 422);
        }
        $this->setActive($request, $team, false);

        return new TeamResource($team->refresh()->load('organization'));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);
        if ($team->isProtected() || $team->users()->exists()) {
            return $this->error('TEAM_PROTECTED', 'Team cannot be deleted.', 422);
        }
        $team->delete();

        return response()->json(null, 204);
    }

    private function setActive(Request $request, Team $team, bool $active): void
    {
        $before = $team->only(['is_active', 'version']);
        $team->update(['is_active' => $active, 'version' => $team->version + 1]);
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $team, ['before' => $before, 'after' => $team->only(['is_active', 'version'])]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
