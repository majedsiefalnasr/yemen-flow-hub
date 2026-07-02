import { describe, expect, it } from 'vitest'
import { buildStagePath } from '@/composables/useEngineStagePath'
import type { WorkflowGraph, EngineHistoryEntry } from '@/types/models'

const node = (id: number, sort_order: number, name: string, extra: Partial<WorkflowGraph['nodes'][number]> = {}) => ({
  id, code: `S${id}`, name, display_label: null, is_initial: false, is_final: false, sort_order, ...extra,
})

const graph: WorkflowGraph = {
  nodes: [
    node(3, 30, 'اكتمال', { is_final: true }),
    node(1, 10, 'الإدخال', { is_initial: true }),
    node(2, 20, 'المراجعة'),
  ],
  edges: [],
}

const history: EngineHistoryEntry[] = [
  { id: 1, from_stage: { id: 1, code: 'S1', name: 'الإدخال' }, to_stage: { id: 2, code: 'S2', name: 'المراجعة' }, action_code: 'SUBMIT', performed_by: null, comments: null, created_at: '2026-06-01T10:00:00Z' },
]

describe('buildStagePath', () => {
  it('orders nodes by sort_order and marks visited/current/upcoming', () => {
    const path = buildStagePath(graph, 2, history)
    expect(path.map((s) => s.id)).toEqual([1, 2, 3])
    expect(path.map((s) => s.status)).toEqual(['visited', 'current', 'upcoming'])
    expect(path[0]!.label).toBe('الإدخال')
  })

  it('prefers display_label over name', () => {
    const g: WorkflowGraph = { nodes: [node(1, 10, 'الإدخال', { display_label: 'إدخال الطلب' })], edges: [] }
    expect(buildStagePath(g, 1, []).at(0)?.label).toBe('إدخال الطلب')
  })

  it('marks history-visited stages even when sort_order is unusual', () => {
    const path = buildStagePath(graph, 3, history)
    // stage 2 visited via history to_stage; stage 1 visited via lower sort_order
    expect(path.find((s) => s.id === 2)?.status).toBe('visited')
    expect(path.find((s) => s.id === 1)?.status).toBe('visited')
  })

  it('returns [] for a null graph', () => {
    expect(buildStagePath(null, 1, [])).toEqual([])
  })
})
