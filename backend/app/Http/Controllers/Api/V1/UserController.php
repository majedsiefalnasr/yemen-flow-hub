<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Exceptions\StaleResourceException;
use App\Exceptions\UnmappedRoleException;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\GovernanceUserResource;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Rules\RoleBelongsToOrganization;
use App\Services\Audit\AuditService;
use App\Services\Auth\SessionInvalidationService;
use App\Support\PasswordPolicy;
use App\Support\RoleCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly SessionInvalidationService $sessionInvalidationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();
        $page = User::query()->with(['organization', 'teams.organization', 'roles.organization', 'bank.organization'])
            ->when($actor->hasRoleCode(RoleCodes::BANK_ADMIN), fn ($q) => $q->where('bank_id', $actor->bank_id))
            ->when($request->filled('organization_id'), fn ($q) => $q->where('organization_id', $request->integer('organization_id')))
            ->when($request->filled('team_id'), fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereKey($request->integer('team_id'))))
            ->when($request->filled('role_id'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereKey($request->integer('role_id'))))
            ->when($request->filled('bank_id'), fn ($q) => $q->where('bank_id', $request->integer('bank_id')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n->where('name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('email', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy(
                in_array($request->input('sort'), ['name', 'email', 'created_at'], true) ? $request->input('sort') : 'created_at',
                $request->input('direction') === 'asc' ? 'asc' : 'desc'
            )
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => GovernanceUserResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(User $user): GovernanceUserResource
    {
        $this->authorize('view', $user);

        return new GovernanceUserResource($this->loadIdentity($user));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $data = $this->validateIdentity($request);

        try {
            $user = DB::transaction(function () use ($request, $data): User {
                $role = Role::query()->findOrFail($data['role_id']);
                $user = User::query()->create([
                    'organization_id' => $data['organization_id'],
                    'bank_id' => $data['bank_id'] ?? null,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'is_active' => $data['is_active'] ?? true,
                    'mfa_enabled' => $data['mfa_enabled'] ?? false,
                    'role' => $this->legacyRoleFor($role->code),
                ])->refresh();
                $user->teams()->sync([$data['team_id']]);
                $user->roles()->sync([$data['role_id']]);
                $this->auditService->log(AuditAction::USER_CREATED, $request->user(), $user);

                return $user;
            });
        } catch (UnmappedRoleException $e) {
            return $this->error('ROLE_NOT_MAPPED', 'The selected role cannot yet be assigned to a user.', 422);
        }

        return (new GovernanceUserResource($this->loadIdentity($user)))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $data = $this->validateIdentity($request, $user);
        $expectedVersion = $request->integer('version');

        try {
            DB::transaction(function () use ($request, $user, $data, $expectedVersion): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
                if ($expectedVersion !== $locked->version) {
                    throw new StaleResourceException;
                }
                $role = Role::query()->findOrFail($data['role_id']);
                $locked->update([
                    'organization_id' => $data['organization_id'],
                    'bank_id' => $data['bank_id'] ?? null,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'is_active' => $data['is_active'] ?? $locked->is_active,
                    'mfa_enabled' => $data['mfa_enabled'] ?? $locked->mfa_enabled,
                    'role' => $this->legacyRoleFor($role->code),
                    'version' => $locked->version + 1,
                ]);
                $locked->teams()->sync([$data['team_id']]);
                $locked->roles()->sync([$data['role_id']]);
                $this->sessionInvalidationService->invalidate($locked);
                $this->auditService->log(AuditAction::USER_UPDATED, $request->user(), $locked);
            });
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The user was modified by another administrator.', 409);
        } catch (UnmappedRoleException $e) {
            return $this->error('ROLE_NOT_MAPPED', 'The selected role cannot yet be assigned to a user.', 422);
        }

        return (new GovernanceUserResource($this->loadIdentity($user->refresh())))->response();
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        if ($request->user()->getKey() === $user->getKey()) {
            return $this->error('USER_SELF_DEACTIVATION', 'You cannot deactivate your own account.', 422);
        }
        if ($this->hasActiveWork($user)) {
            return $this->error('USER_HAS_ACTIVE_WORK', 'Reassign or close active work before deactivating this user.', 422);
        }
        $user->update(['is_active' => false, 'version' => $user->version + 1]);
        $this->sessionInvalidationService->invalidate($user);
        $this->auditService->log(AuditAction::USER_DEACTIVATED, $request->user(), $user);

        return response()->json(['data' => new GovernanceUserResource($this->loadIdentity($user->refresh()))]);
    }

    public function resetPassword(Request $request, User $user): GovernanceUserResource
    {
        $this->authorize('resetPassword', $user);
        $data = $request->validate([
            'password' => ['required', ...PasswordPolicy::rules(), 'confirmed'],
        ], PasswordPolicy::messages());
        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => true])->save();
        $this->sessionInvalidationService->invalidate($user);
        $this->auditService->log(AuditAction::PASSWORD_RESET, $request->user(), $user);

        return new GovernanceUserResource($this->loadIdentity($user));
    }

    public function resetMfa(Request $request, User $user): GovernanceUserResource
    {
        $this->authorize('resetMfa', $user);
        $user->forceFill(['totp_secret' => null, 'totp_enabled' => false, 'totp_recovery_codes' => null, 'mfa_enabled' => false, 'pin_code_hash' => null, 'pin_enabled' => false])->save();
        $this->sessionInvalidationService->invalidate($user);
        $this->auditService->log(AuditAction::MFA_RESET, $request->user(), $user);

        return new GovernanceUserResource($this->loadIdentity($user));
    }

    private function validateIdentity(Request $request, ?User $user = null): array
    {
        $organizationId = $request->integer('organization_id');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            // One-each is a hard validation invariant: exactly one team and one
            // role. `integer` rejects array values for team_id/role_id, and the
            // plural keys are explicitly prohibited so a >1 assignment cannot be
            // silently dropped even though the join tables are M:N.
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organizationId)],
            'team_ids' => ['prohibited'],
            'role_id' => ['required', 'integer', new RoleBelongsToOrganization($organizationId)],
            'role_ids' => ['prohibited'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [$user ? 'nullable' : 'required', ...PasswordPolicy::rules()],
            'is_active' => ['sometimes', 'boolean'],
            'mfa_enabled' => ['sometimes', 'boolean'],
            'version' => [$user ? 'required' : 'sometimes', 'integer'],
        ], PasswordPolicy::messages());

        $organization = Organization::query()->findOrFail($organizationId);
        if ($organization->code === 'commercial_banks') {
            if (empty($data['bank_id']) || ! Bank::query()->whereKey($data['bank_id'])->where('organization_id', $organizationId)->exists()) {
                abort(response()->json(['error' => ['code' => 'BANK_REQUIRED', 'message' => 'A commercial-bank user requires a bank in the commercial-banks organization.', 'fields' => ['bank_id' => ['Invalid bank.']], 'request_id' => null]], 422));
            }
        } else {
            $data['bank_id'] = null;
        }

        return $data;
    }

    private function legacyRoleFor(string $roleCode): string
    {
        return match ($roleCode) {
            RoleCodes::INTAKE => UserRole::DATA_ENTRY->value,
            RoleCodes::INTERNAL_REVIEWER => UserRole::BANK_REVIEWER->value,
            RoleCodes::BANK_ADMIN => UserRole::BANK_ADMIN->value,
            RoleCodes::FX_SWIFT => UserRole::SWIFT_OFFICER->value,
            RoleCodes::SUPPORT => UserRole::SUPPORT_COMMITTEE->value,
            RoleCodes::SYSTEM_ADMIN => UserRole::CBY_ADMIN->value,
            RoleCodes::COMMITTEE_MANAGER => UserRole::COMMITTEE_DIRECTOR->value,
            // FX_CONFIRM has no legacy equivalent; map to the least-privileged
            // committee role (voter, not director) during the transition rather
            // than granting Director legacy semantics.
            RoleCodes::FX_CONFIRM => UserRole::EXECUTIVE_MEMBER->value,
            // Fail closed: an unmapped role code must NOT silently inherit the
            // CBY_ADMIN super-role. Reject the assignment instead.
            default => throw new UnmappedRoleException($roleCode),
        };
    }

    private function hasActiveWork(User $user): bool
    {
        return EngineRequest::query()
            ->whereNotIn('status', ['CLOSED', 'REJECTED'])
            ->where(function ($query) use ($user): void {
                $query->orWhere('created_by', $user->id)
                    ->orWhere('claimed_by', $user->id);
            })
            ->exists();
    }

    private function loadIdentity(User $user): User
    {
        return $user->load(['organization', 'teams.organization', 'roles.organization', 'bank.organization']);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
