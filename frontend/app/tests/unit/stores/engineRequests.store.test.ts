import { describe, expect, it, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

const mockFetchList = vi.fn()
const mockShow = vi.fn()
const mockSubmit = vi.fn()
const mockExecuteAction = vi.fn()
const mockFetchHistory = vi.fn()
const mockFetchGraph = vi.fn()
const mockFetchDocuments = vi.fn()
const mockFetchStats = vi.fn()
const statsRef = { value: null as unknown }

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: null },
    currentWarnings: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchList: mockFetchList,
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    submit: mockSubmit,
    show: mockShow,
  }),
}))

vi.mock('@/composables/useEngineRequestStats', () => ({
  useEngineRequestStats: () => ({
    stats: statsRef,
    fetchStats: mockFetchStats,
  }),
}))

vi.mock('@/composables/useEngineRequestActions', () => ({
  useEngineRequestActions: () => ({
    executing: { value: false },
    conflictError: { value: false },
    fieldErrors: { value: {} },
    executeAction: mockExecuteAction,
  }),
}))

vi.mock('@/composables/useEngineRequestHistory', () => ({
  useEngineRequestHistory: () => ({
    history: { value: [] },
    graph: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchHistory: mockFetchHistory,
    fetchGraph: mockFetchGraph,
  }),
}))

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    documents: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchDocuments: mockFetchDocuments,
    upload: vi.fn(),
    remove: vi.fn(),
    downloadUrl: vi.fn(),
  }),
}))

describe('useEngineRequestsStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchList.mockReset()
    mockShow.mockReset()
    mockSubmit.mockReset()
    mockExecuteAction.mockReset()
    mockFetchHistory.mockReset()
    mockFetchGraph.mockReset()
    mockFetchDocuments.mockReset()
    mockFetchStats.mockReset()
    statsRef.value = null
  })

  it('loadList delegates to the composable', async () => {
    const store = useEngineRequestsStore()
    await store.loadList()
    expect(mockFetchList).toHaveBeenCalled()
  })

  it('submitInstance delegates to the composable and returns the created instance', async () => {
    mockSubmit.mockResolvedValue({ data: { id: 9 }, warnings: [] })
    const store = useEngineRequestsStore()

    const result = await store.submitInstance('idem-key-1', {
      workflow_version_id: 1,
      data: {},
    })

    expect(mockSubmit).toHaveBeenCalledWith('idem-key-1', { workflow_version_id: 1, data: {} })
    expect(result.id).toBe(9)
    expect(store.current?.id).toBe(9)
    expect(store.duplicateWarnings).toEqual([])
  })

  it('loadInstance loads the instance plus its history and graph', async () => {
    mockShow.mockResolvedValue({ id: 9 })
    const store = useEngineRequestsStore()

    await store.loadInstance(9)

    expect(mockShow).toHaveBeenCalledWith(9)
    expect(mockFetchHistory).toHaveBeenCalledWith(9)
    expect(mockFetchGraph).toHaveBeenCalledWith(9)
    expect(mockFetchDocuments).toHaveBeenCalledWith(9)
  })

  it('executeTransition delegates and reloads the instance on success', async () => {
    mockExecuteAction.mockResolvedValue({ id: 9, version: 2 })
    mockShow.mockResolvedValue({ id: 9, version: 2 })
    const store = useEngineRequestsStore()

    await store.executeTransition(9, 3, 'ok', {}, 1)

    expect(mockExecuteAction).toHaveBeenCalledWith(9, 3, 'ok', {}, 1)
  })

  describe('loadStats (API-UI-001)', () => {
    it('stores stats by scope and clears any error on success', async () => {
      statsRef.value = { total: 5, active: 3 }
      mockFetchStats.mockResolvedValue(undefined)
      const store = useEngineRequestsStore()

      await store.loadStats({ scope: 'all', page: 1 })

      expect(store.allStats).toEqual({ total: 5, active: 3 })
      expect(store.statsError).toBeNull()
      expect(store.statsRateLimited).toBe(false)
    })

    it('dedupes concurrent identical-scope calls (single-flight)', async () => {
      mockFetchStats.mockResolvedValue(undefined)
      const store = useEngineRequestsStore()

      await Promise.all([
        store.loadStats({ scope: 'queue', page: 10 }),
        store.loadStats({ scope: 'queue', page: 10 }),
      ])

      expect(mockFetchStats).toHaveBeenCalledTimes(1)
    })

    it('surfaces the error and blocks re-firing the same failing params', async () => {
      mockFetchStats.mockRejectedValue({ data: { message: 'boom' } })
      const store = useEngineRequestsStore()
      store.resetStatsErrorState()

      await store.loadStats({ scope: 'all', page: 2 })
      expect(store.statsError).toBe('boom')
      expect(mockFetchStats).toHaveBeenCalledTimes(1)

      // Same params again: short-circuited by the terminal-error circuit.
      await store.loadStats({ scope: 'all', page: 2 })
      expect(mockFetchStats).toHaveBeenCalledTimes(1)
    })

    it('resetStatsErrorState clears the circuit so an explicit retry re-hits', async () => {
      mockFetchStats.mockRejectedValue({ data: { message: 'boom' } })
      const store = useEngineRequestsStore()
      store.resetStatsErrorState()

      await store.loadStats({ scope: 'all', page: 3 })
      expect(mockFetchStats).toHaveBeenCalledTimes(1)

      store.resetStatsErrorState()
      expect(store.statsError).toBeNull()

      mockFetchStats.mockResolvedValue(undefined)
      statsRef.value = { total: 1 }
      await store.loadStats({ scope: 'all', page: 3 })
      expect(mockFetchStats).toHaveBeenCalledTimes(2)
      expect(store.allStats).toEqual({ total: 1 })
    })

    it('flags rate limiting on a 429 response', async () => {
      mockFetchStats.mockRejectedValue({ status: 429 })
      const store = useEngineRequestsStore()
      store.resetStatsErrorState()

      await store.loadStats({ scope: 'queue', page: 4 })

      expect(store.statsRateLimited).toBe(true)
      expect(store.statsError).toContain('كثرة الطلبات')
    })
  })
})
