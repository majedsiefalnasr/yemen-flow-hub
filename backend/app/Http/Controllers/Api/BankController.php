<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Resources\BankResource;
use App\Http\Resources\UserResource;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use App\Support\PasswordPolicy;
use App\Support\RoleCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class BankController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    #[OA\Get(
        path: '/api/banks',
        tags: ['Banks'],
        summary: 'List banks',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Banks retrieved')]
    )]
    public function index()
    {
        $this->authorize('viewAny', Bank::class);
        $actor = request()->user();

        $perPage = max(1, min(request()->integer('per_page', 20), 200));
        $banks = Bank::query()
            ->with('bankAdmin.bank')
            ->when($actor->hasAnyRoleCode(RoleCodes::BANK_ROLES), fn ($q) => $q->where('id', $actor->bank_id))
            ->when(request()->filled('search'), function ($q) {
                $s = request('search');
                $q->where(fn ($x) => $x->where('name_ar', 'like', "%{$s}%")
                    ->orWhere('name_en', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%"));
            })
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::success([
            'data' => BankResource::collection($banks->getCollection())->resolve(),
            'meta' => [
                'current_page' => $banks->currentPage(),
                'last_page' => $banks->lastPage(),
                'per_page' => $banks->perPage(),
                'total' => $banks->total(),
            ],
        ], 'Banks retrieved.');
    }

    #[OA\Post(
        path: '/api/banks',
        tags: ['Banks'],
        summary: 'Create bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Bank display name (Arabic, MVP single-language)'),
                    new OA\Property(property: 'code', type: 'string', maxLength: 20),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Bank created'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(StoreBankRequest $request)
    {
        $this->authorize('create', Bank::class);

        $payload = $request->validated();

        $bank = DB::transaction(function () use ($payload, $request): Bank {
            $organization = Organization::query()->findOrFail($payload['organization_id']);
            $bank = Bank::query()->create([
                ...Arr::only($payload, ['name', 'code', 'is_active']),
                'organization_id' => $organization->id,
            ]);

            $admin = User::query()->create([
                'name' => $payload['admin_name'],
                'email' => strtolower($payload['admin_email']),
                'password' => Hash::make($payload['admin_password']),
                'role' => UserRole::BANK_ADMIN,
                'bank_id' => $bank->id,
                'is_active' => true,
                'must_change_password' => true,
                'temporary_password_set_at' => now(),
            ]);

            $team = Team::query()->where('organization_id', $organization->id)->where('code', RoleCodes::BANK_ADMIN)->firstOrFail();
            $role = Role::query()->where('organization_id', $organization->id)->where('code', RoleCodes::BANK_ADMIN)->firstOrFail();

            $admin->forceFill([
                'organization_id' => $organization->id,
            ])->save();
            $admin->teams()->sync([$team->id]);
            $admin->roles()->sync([$role->id]);

            $this->auditService->log(AuditAction::BANK_CREATED, $request->user(), $bank, [
                'bank_id' => $bank->id,
            ]);

            $this->auditService->log(AuditAction::USER_CREATED, $request->user(), $admin, [
                'bank_id' => $bank->id,
                'target_role' => UserRole::BANK_ADMIN->value,
                'temporary_password' => true,
            ]);

            return $bank;
        });

        return ApiResponse::success(new BankResource($bank->load('bankAdmin.bank')), 'Bank created successfully.', 201);
    }

    #[OA\Get(
        path: '/api/banks/{id}',
        tags: ['Banks'],
        summary: 'Get bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Bank retrieved')]
    )]
    public function show(Bank $bank)
    {
        $this->authorize('view', $bank);

        return ApiResponse::success(new BankResource($bank->load('bankAdmin.bank')), 'Bank retrieved.');
    }

    #[OA\Put(
        path: '/api/banks/{id}',
        tags: ['Banks'],
        summary: 'Update bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Bank updated')]
    )]
    public function update(UpdateBankRequest $request, Bank $bank)
    {
        $this->authorize('update', $bank);

        $validated = $request->validated();
        $before = $bank->only(['name', 'code', 'is_active']);
        DB::transaction(function () use ($bank, $validated): void {
            $bank->update(Arr::only($validated, ['name', 'code', 'is_active']));

            if (isset($validated['admin_name']) || isset($validated['admin_email'])) {
                $bank->bankAdmin()->first()?->update(array_filter([
                    'name' => $validated['admin_name'] ?? null,
                    'email' => isset($validated['admin_email'])
                        ? strtolower($validated['admin_email'])
                        : null,
                ], fn ($value) => $value !== null));
            }
        });
        $bank->refresh();
        $after = $bank->only(['name', 'code', 'is_active']);
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($v, $k) => array_key_exists($k, $before) && $before[$k] !== $v,
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->auditService->log(AuditAction::BANK_UPDATED, $request->user(), $bank, [
            'bank_id' => $bank->id,
            'before' => array_intersect_key($before, array_flip($changedKeys)),
            'after' => array_intersect_key($after, array_flip($changedKeys)),
        ]);

        return ApiResponse::success(new BankResource($bank->load('bankAdmin.bank')), 'Bank updated successfully.');
    }

    public function resetAdminPassword(Request $request, Bank $bank)
    {
        $this->authorize('update', $bank);

        if (! $request->user()?->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return ApiResponse::forbidden('Only CBY Admin can reset Bank Admin credentials.');
        }

        $validated = $request->validate([
            'password' => ['required', ...PasswordPolicy::rules(), 'confirmed'],
        ], PasswordPolicy::messages());

        $admin = User::query()
            ->where('bank_id', $bank->id)
            ->whereHas('roles', fn ($q) => $q->where('code', RoleCodes::BANK_ADMIN))
            ->orderBy('id')
            ->first();

        if (! $admin) {
            return ApiResponse::notFound('No Bank Admin account found for this bank.');
        }

        $this->authorize('resetPassword', $admin);

        $admin->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
            'temporary_password_set_at' => now(),
        ])->save();
        $admin->tokens()->delete();

        $this->auditService->log(AuditAction::PASSWORD_RESET, $request->user(), $admin, [
            'mode' => 'bank_management',
            'target_role' => $admin->role?->value,
            'target_bank_id' => $bank->id,
        ]);

        return ApiResponse::success(new UserResource($admin->load('bank')), 'Bank Admin temporary password set successfully.');
    }

    #[OA\Delete(
        path: '/api/banks/{id}',
        tags: ['Banks'],
        summary: 'Delete bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Bank deleted')]
    )]
    public function destroy(Bank $bank)
    {
        $this->authorize('delete', $bank);

        $bank->delete();

        return ApiResponse::success((object) [], 'Bank deleted successfully.');
    }
}
