import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequestHistory } from '@/composables/useEngineRequestHistory'

const mockGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

describe('useEngineRequestHistory', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchHistory populates history entries', async () => {
    mockGet.mockResolvedValue({ success: true, data: [{ id: 1, action_code: 'SUBMIT' }] })
    const { history, fetchHistory } = useEngineRequestHistory()

    await fetchHistory(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/history')
    expect(history.value).toHaveLength(1)
  })

  it('fetchGraph populates the graph', async () => {
    mockGet.mockResolvedValue({ success: true, data: { nodes: [], edges: [] } })
    const { graph, fetchGraph } = useEngineRequestHistory()

    await fetchGraph(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/graph')
    expect(graph.value).toEqual({ nodes: [], edges: [] })
  })
})
