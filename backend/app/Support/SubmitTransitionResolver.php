<?php

namespace App\Support;

use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;

/**
 * Server-side port of the frontend's resolveSubmitTransition() picking logic
 * — used both at Designer publish-time validation (can a workflow ever be
 * submitted from its initial stage?) and at submission time (which
 * transition actually executes). The two callers must never diverge, so
 * this is the single implementation both share.
 *
 * A resolved transition must also actually leave the stage it starts from —
 * a self-loop is valid mid-workflow but can never be a valid *initial*
 * submit, since the request would never leave its initial stage.
 */
class SubmitTransitionResolver
{
    /**
     * @param  Collection<int, WorkflowTransition>  $transitions  all transitions for the version
     */
    public static function resolve(Collection $transitions, int $fromStageId): ?WorkflowTransition
    {
        $outgoing = $transitions->filter(
            fn (WorkflowTransition $t) => (int) $t->from_stage_id === $fromStageId,
        );

        $advancing = $outgoing->filter(
            fn (WorkflowTransition $t) => (int) $t->to_stage_id !== $fromStageId,
        );

        $flagged = $advancing->filter(fn (WorkflowTransition $t) => (bool) $t->is_default_submit);
        if ($flagged->count() === 1) {
            return $flagged->first();
        }
        if ($flagged->count() > 1) {
            // Multiple transitions marked is_default_submit is ambiguous by
            // construction — never silently pick the first one.
            return null;
        }

        if ($advancing->count() === 1) {
            return $advancing->first();
        }

        return null;
    }
}
