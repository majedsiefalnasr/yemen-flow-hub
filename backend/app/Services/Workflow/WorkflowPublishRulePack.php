<?php

namespace App\Services\Workflow;

use App\Enums\FieldType;
use App\Enums\FinalOutcome;
use App\Enums\WorkflowActionKind;
use App\Models\FieldDefinition;
use App\Models\WorkflowAction;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Support\FieldDefinitionConstraintValidator;
use Illuminate\Support\Collection;

/**
 * WP-3 publish-time validation rules (V-1 through V-9).
 */
class WorkflowPublishRulePack
{
    public function __construct(
        private readonly StagePermissionAudience $audience,
    ) {}

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    public function validate(
        WorkflowVersion $version,
        Collection $stages,
        Collection $transitions,
        Collection $fields,
    ): array {
        $errors = [];
        $transitions = $transitions->loadMissing(['action', 'toStage', 'fromStage']);
        $stages = $stages->loadMissing(['stagePermissions', 'stageFieldRules.field']);
        $stageById = $stages->keyBy('id');
        $initial = $stages->firstWhere('is_initial', true);

        $errors = array_merge($errors, $this->validateInitialSubmitAmbiguity($initial, $transitions));
        $errors = array_merge($errors, $this->validateReachability($initial, $stages, $transitions));
        $errors = array_merge($errors, $this->validateEffectiveExecutors($stages));
        $errors = array_merge($errors, $this->validateFinalStageOutgoing($stages, $transitions));
        $errors = array_merge($errors, $this->validateActionOutcomeConsistency($transitions, $stageById));
        $errors = array_merge($errors, $this->validateStageActivity($initial, $stages, $transitions));
        $errors = array_merge($errors, $this->validateSelfLoops($transitions));
        $errors = array_merge($errors, $this->validateFieldRules($stages, $transitions));
        $errors = array_merge($errors, $this->validateFieldConstraints($fields));

        return $errors;
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateInitialSubmitAmbiguity(?WorkflowStage $initial, Collection $transitions): array
    {
        if ($initial === null) {
            return [];
        }

        $outgoing = $transitions->where('from_stage_id', $initial->id)->values();
        if ($outgoing->count() <= 1) {
            return [];
        }

        $flagged = $outgoing->where('is_default_submit', true);
        if ($flagged->count() !== 1) {
            return [$this->error(
                'INITIAL_SUBMIT_AMBIGUOUS',
                "stage:{$initial->code}",
                "Initial stage '{$initial->code}' has multiple outgoing transitions; exactly one must be marked as the default submit transition.",
            )];
        }

        return [];
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateReachability(?WorkflowStage $initial, Collection $stages, Collection $transitions): array
    {
        if ($initial === null) {
            return [];
        }

        $reachable = $this->reachableStageIds($initial->id, $transitions);
        $errors = [];

        foreach ($stages as $stage) {
            if (! in_array($stage->id, $reachable, true)) {
                $errors[] = $this->error(
                    'STAGE_UNREACHABLE',
                    "stage:{$stage->code}",
                    "Stage '{$stage->code}' is not reachable from the initial stage.",
                );
            }
        }

        $reachableFinal = $stages
            ->filter(fn (WorkflowStage $stage) => $stage->is_final && in_array($stage->id, $reachable, true))
            ->isNotEmpty();

        if (! $reachableFinal) {
            $errors[] = $this->error(
                'NO_REACHABLE_FINAL',
                'stages',
                'No final stage is reachable from the initial stage.',
            );
        }

        return $errors;
    }

    /**
     * @return list<int>
     */
    private function reachableStageIds(int $initialStageId, Collection $transitions): array
    {
        $adjacency = [];
        foreach ($transitions as $transition) {
            if ($transition->from_stage_id === $transition->to_stage_id) {
                continue;
            }
            $adjacency[$transition->from_stage_id][] = $transition->to_stage_id;
        }

        $visited = [];
        $queue = [$initialStageId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if (in_array($current, $visited, true)) {
                continue;
            }
            $visited[] = $current;
            foreach ($adjacency[$current] ?? [] as $next) {
                if (! in_array($next, $visited, true)) {
                    $queue[] = $next;
                }
            }
        }

        return $visited;
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateEffectiveExecutors(Collection $stages): array
    {
        $errors = [];
        foreach ($stages as $stage) {
            if ($stage->is_final) {
                continue;
            }

            if ($this->audience->executeHolderIds($stage) === []) {
                $errors[] = $this->error(
                    'STAGE_NO_EXECUTOR',
                    "stage:{$stage->code}",
                    "Non-final stage '{$stage->code}' has no effective executor (no active user matches its EXECUTE permissions).",
                );
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateFinalStageOutgoing(Collection $stages, Collection $transitions): array
    {
        $errors = [];
        $outgoingByFrom = $transitions->groupBy('from_stage_id');

        foreach ($stages->where('is_final', true) as $stage) {
            if (($outgoingByFrom[$stage->id] ?? collect())->isNotEmpty()) {
                $errors[] = $this->error(
                    'FINAL_STAGE_HAS_OUTGOING',
                    "stage:{$stage->code}",
                    "Final stage '{$stage->code}' must not have outgoing transitions.",
                );
            }
        }

        return $errors;
    }

    /**
     * @param  Collection<int, WorkflowStage>  $stageById
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateActionOutcomeConsistency(Collection $transitions, Collection $stageById): array
    {
        $errors = [];

        foreach ($transitions as $transition) {
            $action = $transition->action;
            $toStage = $stageById->get($transition->to_stage_id);
            if ($action === null || $toStage === null) {
                continue;
            }

            if ($this->requiresConfirmation($transition, $action) && trim((string) $transition->confirmation_message) === '') {
                $errors[] = $this->error(
                    'CONFIRMATION_REQUIRED',
                    "transition:{$transition->id}",
                    "Transition {$transition->id} requires a confirmation message.",
                );
            }

            if (! $toStage->is_final || $toStage->final_outcome === null) {
                continue;
            }

            $kind = $action->kind;
            if ($kind === WorkflowActionKind::REJECT && $toStage->final_outcome !== FinalOutcome::REJECTED) {
                $errors[] = $this->error(
                    'ACTION_OUTCOME_MISMATCH',
                    "transition:{$transition->id}",
                    "REJECT transition {$transition->id} must target a final stage with REJECTED outcome.",
                );
            }

            if (in_array($kind, [WorkflowActionKind::CLOSE, WorkflowActionKind::APPROVE], true)
                && $toStage->final_outcome !== FinalOutcome::COMPLETED) {
                $errors[] = $this->error(
                    'ACTION_OUTCOME_MISMATCH',
                    "transition:{$transition->id}",
                    "Completion transition {$transition->id} must target a final stage with COMPLETED outcome.",
                );
            }
        }

        return $errors;
    }

    private function requiresConfirmation(WorkflowTransition $transition, WorkflowAction $action): bool
    {
        if ($transition->is_destructive) {
            return true;
        }

        return in_array($action->kind, [
            WorkflowActionKind::REJECT,
            WorkflowActionKind::CLOSE,
            WorkflowActionKind::CUSTOM,
        ], true);
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateStageActivity(?WorkflowStage $initial, Collection $stages, Collection $transitions): array
    {
        $errors = [];
        $inactiveIds = $stages->where('status', 'INACTIVE')->pluck('id')->all();
        $reachable = $initial !== null ? $this->reachableStageIds($initial->id, $transitions) : [];

        if ($initial !== null && $initial->status === 'INACTIVE') {
            $errors[] = $this->error('INITIAL_STAGE_INACTIVE', "stage:{$initial->code}", 'The initial stage must be active.');
        }

        foreach ($stages->where('is_final', true) as $stage) {
            if ($stage->status === 'INACTIVE') {
                $errors[] = $this->error('FINAL_STAGE_INACTIVE', "stage:{$stage->code}", "Final stage '{$stage->code}' must be active.");
            }
        }

        foreach ($transitions as $transition) {
            if (in_array($transition->from_stage_id, $inactiveIds, true) || in_array($transition->to_stage_id, $inactiveIds, true)) {
                $errors[] = $this->error(
                    'TRANSITION_USES_INACTIVE_STAGE',
                    "transition:{$transition->id}",
                    "Transition {$transition->id} references an inactive stage.",
                );
            }
        }

        foreach ($stages as $stage) {
            if ($stage->is_final || $stage->status !== 'INACTIVE') {
                continue;
            }
            if (in_array($stage->id, $reachable, true)) {
                $errors[] = $this->error(
                    'REACHABLE_STAGE_INACTIVE',
                    "stage:{$stage->code}",
                    "Reachable stage '{$stage->code}' is inactive.",
                );
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateSelfLoops(Collection $transitions): array
    {
        $errors = [];
        foreach ($transitions as $transition) {
            if ($transition->from_stage_id === $transition->to_stage_id && ! $transition->is_self_loop) {
                $errors[] = $this->error(
                    'UNINTENTIONAL_SELF_LOOP',
                    "transition:{$transition->id}",
                    "Self-loop transition {$transition->id} must be explicitly flagged as intentional.",
                );
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateFieldRules(Collection $stages, Collection $transitions): array
    {
        $errors = [];
        $initial = $stages->firstWhere('is_initial', true);
        $reachable = $initial !== null ? $this->reachableStageIds($initial->id, $transitions) : [];
        $priorRequiredEditable = $this->priorRequiredEditableFields($stages, $reachable);

        foreach ($stages as $stage) {
            foreach ($stage->stageFieldRules as $rule) {
                $field = $rule->field;
                if ($field === null) {
                    continue;
                }

                if ($rule->is_required && ! $rule->is_visible) {
                    $errors[] = $this->error(
                        'REQUIRED_HIDDEN_CONFLICT',
                        "field_rule:{$stage->code}:{$field->key}",
                        "Field '{$field->key}' on stage '{$stage->code}' cannot be required and hidden.",
                    );
                }

                if ($rule->is_required && ! $rule->is_editable) {
                    $hasDefault = $field->default_value !== null && trim((string) $field->default_value) !== '';
                    $hasPrior = in_array($field->key, $priorRequiredEditable[$stage->id] ?? [], true);
                    if (! $hasDefault && ! $hasPrior) {
                        $errors[] = $this->error(
                            'REQUIRED_READONLY_NO_VALUE',
                            "field_rule:{$stage->code}:{$field->key}",
                            "Field '{$field->key}' on stage '{$stage->code}' is required but not editable with no default or prior required stage.",
                        );
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<int>  $reachableStageIds
     * @return array<int, list<string>>
     */
    private function priorRequiredEditableFields(Collection $stages, array $reachableStageIds): array
    {
        $ordered = $stages
            ->filter(fn (WorkflowStage $stage) => in_array($stage->id, $reachableStageIds, true))
            ->sortBy(fn (WorkflowStage $stage) => [$stage->sort_order, $stage->id])
            ->values();

        $priorKeysByStage = [];
        $accumulated = [];
        foreach ($ordered as $stage) {
            $priorKeysByStage[$stage->id] = array_values($accumulated);
            foreach ($stage->stageFieldRules as $rule) {
                if ($rule->is_required && $rule->is_editable && $rule->field !== null) {
                    $accumulated[] = $rule->field->key;
                }
            }
            $accumulated = array_values(array_unique($accumulated));
        }

        return $priorKeysByStage;
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @return array<int, array{code: string, target: string, message: string}>
     */
    private function validateFieldConstraints(Collection $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $fieldErrors = FieldDefinitionConstraintValidator::validate($field->toArray());
            foreach ($fieldErrors as $message) {
                $errors[] = $this->error('FIELD_CONSTRAINT_INVALID', "field:{$field->key}", $message);
            }

            if ($field->regex_pattern !== null && $field->regex_pattern !== '') {
                if (@preg_match($field->regex_pattern, '') === false) {
                    $errors[] = $this->error(
                        'FIELD_REGEX_INVALID',
                        "field:{$field->key}",
                        "Field '{$field->key}' has an invalid regex_pattern.",
                    );
                }
            }

            if ($field->type === FieldType::SELECT) {
                $optionError = FieldDefinitionConstraintValidator::validate(['type' => 'SELECT', 'options' => $field->options])['options'] ?? null;
                if ($optionError !== null) {
                    $errors[] = $this->error('FIELD_OPTIONS_INVALID', "field:{$field->key}", $optionError);
                }
            }
        }

        return $errors;
    }

    /**
     * @return array{code: string, target: string, message: string}
     */
    private function error(string $code, string $target, string $message): array
    {
        return ['code' => $code, 'target' => $target, 'message' => $message];
    }
}
