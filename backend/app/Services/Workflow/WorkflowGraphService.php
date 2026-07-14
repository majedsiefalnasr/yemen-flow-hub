<?php

namespace App\Services\Workflow;

use App\Enums\WorkflowTransitionType;
use App\Models\User;
use App\Models\WorkflowVersion;

/**
 * Builds a {nodes, edges} graph fully derived from a version's stages +
 * transitions (FR-WD8). No graph table. The same shape is reused by the per-request
 * graph in Epic 18.5 (FR-REQ8).
 *
 * Node display_label resolution (L-8): the Designer's "التسمية" (stage_permissions
 * .display_label) wins whenever a row sets one, falling back to the stage's own
 * `name`. When a viewing $user is given, the label is resolved from the row(s)
 * matching that user's identity (org/team/role/user, via StagePermissionResolver) so
 * different roles can see different labels for the same stage; without a viewer
 * (e.g. the Designer's own graph view), the first permission row by id is used.
 */
class WorkflowGraphService
{
    public function __construct(
        private readonly StagePermissionResolver $permissionResolver,
    ) {}

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function build(WorkflowVersion $version, ?User $viewer = null): array
    {
        $stages = $version->stages()->orderBy('sort_order')->orderBy('id')->get();
        $transitions = $version->transitions()->with('action:id,code,name')->get();

        $stagesWithPermissions = $version->stages()
            ->with(['stagePermissions' => fn ($q) => $q->orderBy('id')])
            ->get()
            ->keyBy('id');

        $nodes = $stages->map(function ($stage) use ($stagesWithPermissions, $viewer): array {
            $permissions = $stagesWithPermissions->get($stage->id)?->stagePermissions ?? collect();
            $permissionLabel = $viewer !== null
                ? $this->permissionResolver->matchingDisplayLabel($viewer, $permissions)
                : $permissions->pluck('display_label')->filter()->first();
            $displayLabel = $permissionLabel !== null && trim((string) $permissionLabel) !== ''
                ? $permissionLabel
                : $stage->name;

            return [
                'id' => $stage->id,
                'code' => $stage->code,
                'name' => $stage->name,
                'display_label' => $displayLabel,
                'is_initial' => (bool) $stage->is_initial,
                'is_final' => (bool) $stage->is_final,
                'sort_order' => (int) $stage->sort_order,
            ];
        })->values()->all();

        $edges = $transitions->map(fn ($transition): array => [
            'id' => $transition->id,
            'from_stage_id' => $transition->from_stage_id,
            'to_stage_id' => $transition->to_stage_id,
            'action_id' => $transition->action_id,
            'action_code' => $transition->action?->code,
            'action_name' => $transition->action?->name,
            'requires_comment' => (bool) $transition->requires_comment,
            'confirmation_message' => $transition->confirmation_message,
            'is_destructive' => (bool) $transition->is_destructive,
            'is_default_submit' => (bool) $transition->is_default_submit,
            'is_self_loop' => (bool) $transition->is_self_loop,
            'transition_type' => $transition->transition_type?->value ?? WorkflowTransitionType::FORWARD->value,
            'is_return' => $this->isReturnEdge($transition, $stages),
        ])->values()->all();

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * L-7: explicit RETURN type wins; sort-order heuristic is display fallback only.
     */
    private function isReturnEdge($transition, $stages): bool
    {
        if ($transition->transition_type === WorkflowTransitionType::RETURN) {
            return true;
        }

        if ($transition->transition_type !== null
            && $transition->transition_type !== WorkflowTransitionType::FORWARD) {
            return false;
        }

        if ($transition->from_stage_id === $transition->to_stage_id) {
            return false;
        }

        $from = $stages->firstWhere('id', $transition->from_stage_id);
        $to = $stages->firstWhere('id', $transition->to_stage_id);
        if ($from === null || $to === null) {
            return false;
        }

        return (int) $to->sort_order < (int) $from->sort_order;
    }
}
