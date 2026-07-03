import type { EngineHistoryEntry, WorkflowGraph, WorkflowGraphNode } from '@/types/models'

export type EngineStageStatus = 'visited' | 'current' | 'upcoming'

export interface EngineStageStep {
  id: number
  label: string
  status: EngineStageStatus
  isYours?: boolean
}

export function buildStagePath(
  graph: WorkflowGraph | null,
  currentStageId: number | null,
  history: EngineHistoryEntry[],
  currentStageIsYours = false,
): EngineStageStep[] {
  if (!graph) return []

  const ordered: WorkflowGraphNode[] = [...graph.nodes].sort(
    (a, b) => a.sort_order - b.sort_order || a.id - b.id,
  )

  const visitedIds = new Set<number>()
  for (const entry of history) {
    if (entry.to_stage) visitedIds.add(entry.to_stage.id)
  }

  // Stages the user can execute (any stage, not just the current one) get the
  // "دورك" marker. Fall back to the legacy single-stage flag when the graph does
  // not carry execute_stage_ids.
  const executeStageIds = new Set(graph.execute_stage_ids ?? [])

  const currentNode = ordered.find((n) => n.id === currentStageId) ?? null
  const currentSort = currentNode?.sort_order ?? null

  return ordered.map((node) => {
    let status: EngineStageStatus
    if (node.id === currentStageId) {
      status = 'current'
    } else if (visitedIds.has(node.id) || (currentSort !== null && node.sort_order < currentSort)) {
      status = 'visited'
    } else {
      status = 'upcoming'
    }

    const isYours = executeStageIds.size
      ? executeStageIds.has(node.id)
      : status === 'current' && currentStageIsYours

    return {
      id: node.id,
      label: node.display_label ?? node.name,
      status,
      isYours,
    }
  })
}
