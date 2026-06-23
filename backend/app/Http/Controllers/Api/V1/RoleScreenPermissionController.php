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

    public function show(Role $role)
    {
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

        // Last-admin protection: if removing MANAGE on screen_permissions, check this isn't the last
        $this->guardLastAdmin($role, $validated['grants']);

        $oldGrants = ScreenPermission::query()
            ->where('role_id', $role->id)
            ->join('screens', 'screens.id', '=', 'screen_permissions.screen_id')
            ->select('screens.key', 'screen_permissions.capability')
            ->get()
            ->groupBy('key')
            ->map(fn ($items) => $items->pluck('capability')->values()->toArray())
            ->toArray();

        DB::transaction(function () use ($role, $validated): void {
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

        // Check if the role currently holds MANAGE on screen_permissions
        $currentlyHoldsManage = ScreenPermission::query()
            ->where('role_id', $role->id)
            ->whereHas('screen', fn ($q) => $q->where('key', 'screen_permissions'))
            ->where('capability', 'MANAGE')
            ->exists();

        if (! $currentlyHoldsManage) {
            return;
        }

        // Count other roles that also hold MANAGE on screen_permissions
        $screenId = Screen::query()->where('key', 'screen_permissions')->value('id');
        $otherManagerCount = ScreenPermission::query()
            ->where('screen_id', $screenId)
            ->where('capability', 'MANAGE')
            ->where('role_id', '!=', $role->id)
            ->distinct('role_id')
            ->count('role_id');

        if ($otherManagerCount === 0) {
            throw ValidationException::withMessages([
                'grants' => ['Cannot remove permission-management capability from the last role that holds it.'],
            ]);
        }
    }
}
