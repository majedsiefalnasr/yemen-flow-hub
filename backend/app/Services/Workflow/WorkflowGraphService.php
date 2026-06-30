<?php

namespace App\Services\Workflow;

use App\Models\WorkflowVersion;

/**
 * Builds a {nodes, edges} graph fully derived from a version's stages +
 * transitions (FR-WD8). No graph table. The same shape is reused by the per-request
 * graph in Epic 18.5 (FR-REQ8). Node `display_label` (when set on a stage
 * permission) provides contextual naming.
 */
class WorkflowGraphService
{
    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function build(WorkflowVersion $version): array
    {
        $stages = $version->stages()->orderBy('sort_order')->orderBy('id')->get();
        $transitions = $version->transitions()->with('action:id,code,name')->get();

        // One display_label per stage (first permission row that has one) for
        // contextual naming, without a parallel routing source.
        $stageLabels = $version->stages()
            ->with(['stagePermissions:id,stage_id,display_label'])
            ->get()
            ->mapWithKeys(fn ($stage) => [
                $stage->id => $stage->stagePermissions->pluck('display_label')->filter()->first(),
            ]);

        $nodes = $stages->map(fn ($stage): array => [
            'id' => $stage->id,
            'code' => $stage->code,
            'name' => $stage->name,
            'display_label' => $stageLabels[$stage->id] ?? null,
            'is_initial' => (bool) $stage->is_initial,
            'is_final' => (bool) $stage->is_final,
            'sort_order' => (int) $stage->sort_order,
        ])->values()->all();

        $edges = $transitions->map(fn ($transition): array => [
            'id' => $transition->id,
            'from_stage_id' => $transition->from_stage_id,
            'to_stage_id' => $transition->to_stage_id,
            'action_id' => $transition->action_id,
            'action_code' => $transition->action?->code,
            'action_name' => $transition->action?->name,
            'requires_comment' => (bool) $transition->requires_comment,
            'is_self_loop' => $transition->from_stage_id === $transition->to_stage_id,
            'is_return' => $this->isReturnEdge($transition, $stages),
        ])->values()->all();

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * A "return" edge points back to an earlier stage (lower sort_order) — i.e. a
     * correction/return path rather than forward progress.
     */
    private function isReturnEdge($transition, $stages): bool
    {
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
