<?php

namespace App\Http\Requests;

use App\Enums\FinalOutcome;
use App\Enums\StageSemanticRole;
use App\Models\WorkflowVersion;
use App\Services\Workflow\SemanticRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $version = $this->route('workflowVersion');
        $versionId = $version instanceof WorkflowVersion ? $version->getKey() : null;
        $isFinal = $this->boolean('is_final');

        return [
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('workflow_stages', 'code')->where('workflow_version_id', $versionId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_initial' => ['sometimes', 'boolean'],
            'is_final' => ['sometimes', 'boolean'],
            'final_outcome' => [
                Rule::requiredIf($isFinal),
                'nullable',
                Rule::enum(FinalOutcome::class),
                Rule::prohibitedIf(! $isFinal),
            ],
            'requires_claim' => ['sometimes', 'boolean'],
            'sla_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE'],
            'semantic_role' => ['sometimes', 'nullable', Rule::enum(StageSemanticRole::class)],
            'attached_effects' => ['sometimes', 'nullable', 'array'],
            'attached_effects.*' => ['string', Rule::in(app(SemanticRegistry::class)->registeredEffectCodes())],
        ];
    }
}
