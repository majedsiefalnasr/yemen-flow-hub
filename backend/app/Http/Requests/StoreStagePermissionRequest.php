<?php

namespace App\Http\Requests;

use App\Enums\StageAccessLevel;
use App\Models\WorkflowStage;
use App\Support\InitialStageExecutorGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStagePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'user_id' => ['prohibited'],
            'access_level' => ['required', Rule::enum(StageAccessLevel::class)],
            'display_label' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                StagePermissionConsistency::check($validator, $this->all());

                /** @var WorkflowStage|null $stage */
                $stage = $this->route('workflowStage');
                if ($stage === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $accessLevel = StageAccessLevel::from($this->string('access_level')->toString());
                if (InitialStageExecutorGuard::isNonBankingInitialExecuteGrant(
                    $stage->is_initial,
                    $accessLevel,
                    $this->integer('organization_id'),
                )) {
                    $validator->errors()->add(
                        'organization_id',
                        'Only banking-sector organizations may hold EXECUTE on the initial stage.',
                    );
                }
            },
        ];
    }
}
