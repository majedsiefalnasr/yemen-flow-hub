import { describe, expect, it } from 'vitest'
import { buildStagePath } from '@/composables/useEngineStagePath'
import type { WorkflowGraph, EngineHistoryEntry } from '@/types/models'

const node = (
  id: number,
  sort_order: number,
  name: string,
  extra: Partial<WorkflowGraph['nodes'][number]> = {},
) => ({
  id,
  code: `S${id}`,
  name,
  display_label: null,
  is_initial: false,
  is_final: false,
  sort_order,
  ...extra,
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
  {
    id: 1,
    from_stage: { id: 1, code: 'S1', name: 'الإدخال' },
    to_stage: { id: 2, code: 'S2', name: 'المراجعة' },
    action_code: 'SUBMIT',
    performed_by: null,
    comments: null,
    created_at: '2026-06-01T10:00:00Z',
    restricted: false,
    restricted_label: null,
  },
]

describe('buildStagePath', () => {
  it('orders nodes by sort_order and marks visited/current/upcoming', () => {
    const path = buildStagePath(graph, 2, history)
    expect(path.map((s) => s.id)).toEqual([1, 2, 3])
    expect(path.map((s) => s.status)).toEqual(['visited', 'current', 'upcoming'])
    expect(path[0]!.label).toBe('الإدخال')
  })

  it('prefers display_label over name', () => {
    const g: WorkflowGraph = {
      nodes: [node(1, 10, 'الإدخال', { display_label: 'إدخال الطلب' })],
      edges: [],
    }
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

  it('marks only the current stage as "yours" when the user can execute it', () => {
    const path = buildStagePath(graph, 2, history, true)
    expect(path.find((s) => s.id === 2)?.isYours).toBe(true)
    // Non-current stages are never marked, even for an executor.
    expect(path.find((s) => s.id === 1)?.isYours).toBe(false)
    expect(path.find((s) => s.id === 3)?.isYours).toBe(false)
  })

  it('does not mark the current stage when the user cannot execute it', () => {
    const path = buildStagePath(graph, 2, history, false)
    expect(path.find((s) => s.id === 2)?.isYours).toBe(false)
  })

  it('marks every stage in execute_stage_ids as "yours", not just the current one', () => {
    const g: WorkflowGraph = { ...graph, execute_stage_ids: [1, 3] }
    // The current stage (2) is not executable; stages 1 and 3 are.
    const path = buildStagePath(g, 2, history)
    expect(path.find((s) => s.id === 1)?.isYours).toBe(true)
    expect(path.find((s) => s.id === 3)?.isYours).toBe(true)
    expect(path.find((s) => s.id === 2)?.isYours).toBe(false)
  })

  it('omits a step for a stage the graph no longer includes (filtered by backend access control)', () => {
    // Simulates the backend (Task 3) filtering out a stage the viewer has no
    // access to — e.g. only DATA_ENTRY and REVIEW nodes come back, COMPLETED is
    // absent entirely rather than present-but-hidden.
    const g: WorkflowGraph = {
      nodes: [
        {
          id: 1,
          code: 'DATA_ENTRY',
          name: 'Data Entry',
          display_label: 'Data Entry',
          sort_order: 1,
          is_initial: true,
          is_final: false,
        },
        {
          id: 2,
          code: 'REVIEW',
          name: 'Review',
          display_label: 'Review',
          sort_order: 2,
          is_initial: false,
          is_final: false,
        },
      ],
      edges: [],
      execute_stage_ids: [1],
    }

    const steps = buildStagePath(g, 2, [])

    expect(steps).toHaveLength(2)
    expect(steps.map((s) => s.id)).toEqual([1, 2])
  })

  it('does not throw when a history entry references a stage absent from the filtered graph', () => {
    // A sanitized history entry (Task 2) has to_stage: null — buildStagePath must
    // not throw when scanning history for visited-stage ids.
    const g: WorkflowGraph = {
      nodes: [
        {
          id: 1,
          code: 'DATA_ENTRY',
          name: 'Data Entry',
          display_label: 'Data Entry',
          sort_order: 1,
          is_initial: true,
          is_final: false,
        },
      ],
      edges: [],
      execute_stage_ids: [],
    }

    const sanitizedHistory: EngineHistoryEntry[] = [
      {
        id: 1,
        from_stage: null,
        to_stage: null,
        action_code: null,
        performed_by: { id: 1, name: 'Someone' },
        comments: null,
        created_at: '2026-07-14T10:00:00Z',
        restricted: true,
        restricted_label: 'إجراء تم في مرحلة مقيدة',
      },
    ]

    expect(() => buildStagePath(g, 1, sanitizedHistory)).not.toThrow()
  })
})
