import type { WorkflowGraphEdge } from '@/types/models'

/**
 * Picks the submit transition from the current stage:
 * 1. Edge flagged `is_default_submit`
 * 2. Else the sole outgoing edge from the stage
 * 3. Else null (caller shows Arabic error)
 */
export function resolveSubmitTransition(
  edges: WorkflowGraphEdge[],
  stageId: number,
): WorkflowGraphEdge | null {
  const outgoing = edges.filter((e) => e.from_stage_id === stageId)
  const flagged = outgoing.find((e) => e.is_default_submit)
  if (flagged) return flagged
  if (outgoing.length === 1) return outgoing[0] ?? null
  return null
}
