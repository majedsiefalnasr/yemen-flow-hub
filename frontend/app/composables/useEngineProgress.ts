import { computed, type ComputedRef, type Ref } from 'vue'
import type { EngineRequest, WorkflowGraph } from '@/types/models'

export interface EngineProgress {
  /** 0–100 completion across the non-terminal stage path. */
  percent: ComputedRef<number>
  /** 1-based index of the current stage within the ordered path. */
  currentIndex: ComputedRef<number>
  /** Total stages in the ordered path. */
  total: ComputedRef<number>
}

/**
 * Derives workflow progress for a request from its version graph. Progress is the
 * current stage's position within the ordered stage list. Closed/rejected
 * requests report 100%.
 */
export function useEngineProgress(
  graph: Ref<WorkflowGraph | null>,
  request: Ref<EngineRequest | null>,
): EngineProgress {
  const ordered = computed(() =>
    [...(graph.value?.nodes ?? [])].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id),
  )

  const total = computed(() => ordered.value.length)

  const currentIndex = computed(() => {
    const id = request.value?.current_stage?.id ?? null
    const i = ordered.value.findIndex((n) => n.id === id)
    return i === -1 ? 0 : i + 1
  })

  const percent = computed(() => {
    if (request.value?.status === 'CLOSED') return 100
    if (!total.value) return 0
    return Math.round((currentIndex.value / total.value) * 100)
  })

  return { percent, currentIndex, total }
}
