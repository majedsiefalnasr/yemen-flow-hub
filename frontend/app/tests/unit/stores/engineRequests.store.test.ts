import { describe, expect, it, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

const mockFetchList = vi.fn()
const mockShow = vi.fn()
const mockCreate = vi.fn()
const mockSaveDraft = vi.fn()
const mockExecuteAction = vi.fn()
const mockFetchHistory = vi.fn()
const mockFetchGraph = vi.fn()
const mockFetchDocuments = vi.fn()

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: mockFetchList,
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: mockCreate,
    show: mockShow,
    saveDraft: mockSaveDraft,
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
    mockCreate.mockReset()
    mockSaveDraft.mockReset()
    mockExecuteAction.mockReset()
    mockFetchHistory.mockReset()
    mockFetchGraph.mockReset()
    mockFetchDocuments.mockReset()
  })

  it('loadList delegates to the composable', async () => {
    const store = useEngineRequestsStore()
    await store.loadList()
    expect(mockFetchList).toHaveBeenCalled()
  })

  it('createInstance delegates to the composable and returns the result', async () => {
    mockCreate.mockResolvedValue({ id: 9 })
    const store = useEngineRequestsStore()

    const result = await store.createInstance({ workflow_version_id: 1, data: {} })

    expect(mockCreate).toHaveBeenCalledWith({ workflow_version_id: 1, data: {} })
    expect(result.id).toBe(9)
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
})
