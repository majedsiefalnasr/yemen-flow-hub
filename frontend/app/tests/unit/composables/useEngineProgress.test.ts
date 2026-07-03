import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { useEngineProgress } from '@/composables/useEngineProgress'
import type { EngineRequest, WorkflowGraph } from '@/types/models'

const node = (id: number, sort_order: number) => ({
  id,
  code: `S${id}`,
  name: `Stage ${id}`,
  display_label: null,
  is_initial: false,
  is_final: false,
  sort_order,
})

const graph: WorkflowGraph = {
  nodes: [node(1, 10), node(2, 20), node(3, 30), node(4, 40)],
  edges: [],
}

function request(overrides: Partial<EngineRequest> = {}): EngineRequest {
  return {
    id: 1,
    status: 'ACTIVE',
    current_stage: {
      id: 2,
      code: 'S2',
      name: 'Stage 2',
      is_initial: false,
      is_final: false,
      sla_duration_minutes: null,
      requires_claim: false,
    },
    ...overrides,
  } as EngineRequest
}

describe('useEngineProgress', () => {
  it('reports the current stage index and percent within the ordered path', () => {
    const { percent, currentIndex, total } = useEngineProgress(ref(graph), ref(request()))
    expect(total.value).toBe(4)
    expect(currentIndex.value).toBe(2)
    expect(percent.value).toBe(50)
  })

  it('reports 100% for a closed request regardless of stage position', () => {
    const { percent } = useEngineProgress(ref(graph), ref(request({ status: 'CLOSED' })))
    expect(percent.value).toBe(100)
  })

  it('reports 0% when the graph has no stages', () => {
    const { percent, total } = useEngineProgress(ref({ nodes: [], edges: [] }), ref(request()))
    expect(total.value).toBe(0)
    expect(percent.value).toBe(0)
  })
})
