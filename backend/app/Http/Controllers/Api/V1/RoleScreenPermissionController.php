<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\ScreenCapability;
use App\Http\Controllers\Api\Controller;
use App\Models\Role;
use App\Models\Screen;
use App\Models\ScreenPermission;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleScreenPermissionController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly PermissionService $permissionService,
        private readonly EngineNotificationDispatcher $notificationDispatcher,
    ) {}

    public function show(Request $request, Role $role)
    {
        if (! $this->permissionService->userHasCapability($request->user(), 'screen_permissions', 'VIEW')) {
            abort(403, 'You are not authorized to view screen permissions.');
        }

        $grants = ScreenPermission::query()
            ->where('role_id', $role->id)
            ->join('screens', 'screens.id', '=', 'screen_permissions.screen_id')
            ->select('screens.key', 'screen_permissions.capability')
            ->orderBy('screens.key')
            ->get()
            ->groupBy('key')
            ->map(fn ($items) => $items->pluck('capability')->values())
            ->toArray();

        return ApiResponse::success([
            'role_id' => $role->id,
            'role_code' => $role->code,
            'grants' => $grants,
        ], 'Screen permissions retrieved.');
    }

    public function update(Request $request, Role $role)
    {
        if (! $this->permissionService->userHasCapability($request->user(), 'screen_permissions', 'MANAGE')) {
            abort(403, 'You are not authorized to manage screen permissions.');
        }

        $validCapabilities = array_column(ScreenCapability::cases(), 'value');
        $validScreenKeys = Screen::query()->pluck('key')->toArray();

        $validated = $request->validate([
            'grants' => ['required', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['string', Rule::in($validCapabilities)],
        ]);

        // Validate screen keys
        foreach (array_keys($validated['grants']) as $screenKey) {
            if (! in_array($screenKey, $validScreenKeys, true)) {
                return ApiResponse::validationError(['grants' => ["Unknown screen: {$screenKey}"]]);
            }
        }

        $oldGrants = ScreenPermission::query()
            ->where('role_id', $role->id)
            ->join('screens', 'screens.id', '=', 'screen_permissions.screen_id')
            ->select('screens.key', 'screen_permissions.capability')
            ->get()
            ->groupBy('key')
            ->map(fn ($items) => $items->pluck('capability')->values()->toArray())
            ->toArray();

        DB::transaction(function () use ($role, $validated): void {
            // Last-admin protection runs inside the transaction with a lock on the
            // screen_permissions MANAGE rows so two concurrent removals cannot both pass.
            $this->guardLastAdmin($role, $validated['grants']);

            ScreenPermission::query()->where('role_id', $role->id)->delete();

            $rows = [];
            $now = now();
            foreach ($validated['grants'] as $screenKey => $capabilities) {
                $screenId = Screen::query()->where('key', $screenKey)->value('id');
                if (! $screenId) {
                    continue;
                }

                foreach (array_unique($capabilities) as $capability) {
                    $rows[] = [
                        'role_id' => $role->id,
                        'screen_id' => $screenId,
                        'capability' => $capability,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($rows)) {
                DB::table('screen_permissions')->insert($rows);
            }
        });

        $this->permissionService->clearScreenPermissionCache($role->id);

        $this->auditService->log(
            AuditAction::SCREEN_PERMISSION_UPDATED,
            $request->user(),
            $role,
            [],
            null,
            null,
            null,
            $oldGrants,
            $validated['grants']
        );

        $this->notificationDispatcher->afterPermissionChange(
            $role->id,
            $role->name,
            $request->user()->id,
        );

        return ApiResponse::success([
            'role_id' => $role->id,
            'grants' => $validated['grants'],
        ], 'Screen permissions updated.');
    }

    private function guardLastAdmin(Role $role, array $newGrants): void
    {
        $hasScreenPermissionsManage = in_array('MANAGE', $newGrants['screen_permissions'] ?? [], true);
        if ($hasScreenPermissionsManage) {
            return;
        }

        $screenId = Screen::query()->where('key', 'screen_permissions')->value('id');
        if ($screenId === null) {
            return;
        }

        // Lock every MANAGE grant on the screen_permissions screen for the duration of
        // the surrounding transaction. This serializes concurrent removals so two admins
        // cannot each observe the other's grant and both pass the last-admin check.
        $managerRoleIds = ScreenPermission::query()
            ->where('screen_id', $screenId)
            ->where('capability', 'MANAGE')
            ->lockForUpdate()
            ->pluck('role_id')
            ->all();

        if (! in_array($role->id, $managerRoleIds, true)) {
            return;
        }

        // Count OTHER roles that hold MANAGE and have at least one active user attached.
        // A role with no (or only inactive) members cannot manage permissions, so it does
        // not satisfy the "at least one active administrator remains" invariant.
        $otherManagerRoleIds = array_values(array_filter(
            array_unique($managerRoleIds),
            fn ($roleId) => (int) $roleId !== (int) $role->id,
        ));

        $otherManagerWithActiveUserCount = empty($otherManagerRoleIds) ? 0 : DB::table('user_roles')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->whereIn('user_roles.role_id', $otherManagerRoleIds)
            ->where('users.is_active', true)
            ->distinct('user_roles.role_id')
            ->count('user_roles.role_id');

        if ($otherManagerWithActiveUserCount === 0) {
            throw ValidationException::withMessages([
                'grants' => ['Cannot remove permission-management capability from the last role with an active administrator.'],
            ]);
        }
    }
}
