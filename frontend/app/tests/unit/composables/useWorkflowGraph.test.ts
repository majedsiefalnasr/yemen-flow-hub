import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useWorkflowGraph } = await import('../../../composables/useWorkflowGraph')

const GRAPH = {
  nodes: [
    {
      id: 1,
      code: 'a',
      name: 'A',
      display_label: null,
      is_initial: true,
      is_final: false,
      sort_order: 0,
    },
  ],
  edges: [],
}

describe('useWorkflowGraph', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the graph for a version', async () => {
    mockGet.mockResolvedValueOnce({ data: GRAPH })
    const { graph, fetchGraph } = useWorkflowGraph()
    await fetchGraph(7)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-versions/7/graph')
    expect(graph.value?.nodes).toHaveLength(1)
  })

  it('records an error and clears the graph on failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { graph, error, fetchGraph } = useWorkflowGraph()
    await fetchGraph(7)

    expect(error.value).toBeTruthy()
    expect(graph.value).toBeNull()
  })
})
