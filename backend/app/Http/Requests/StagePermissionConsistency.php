<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Cross-field consistency for stage_permissions rows: when an organization is set,
 * any referenced team/role/user must belong to that organization. Shared by the
 * store and update Form Requests.
 */
class StagePermissionConsistency
{
    public static function check(Validator $validator, array $input): void
    {
        $orgId = $input['organization_id'] ?? null;
        if ($orgId === null) {
            return;
        }

        if (! empty($input['team_id'])) {
            $team = Team::query()->find($input['team_id']);
            if ($team !== null && (int) $team->organization_id !== (int) $orgId) {
                $validator->errors()->add('team_id', 'The team does not belong to the selected organization.');
            }
        }

        if (! empty($input['role_id'])) {
            $role = Role::query()->find($input['role_id']);
            if ($role !== null && (int) $role->organization_id !== (int) $orgId) {
                $validator->errors()->add('role_id', 'The role does not belong to the selected organization.');
            }
        }

        if (! empty($input['user_id'])) {
            $user = User::query()->find($input['user_id']);
            if ($user !== null && $user->organization_id !== null && (int) $user->organization_id !== (int) $orgId) {
                $validator->errors()->add('user_id', 'The user does not belong to the selected organization.');
            }
        }
    }
}
