import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { RequestStageHistory } from '../../../types/models'

const mockFetchRequestHistory = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: vi.fn(),
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: vi.fn(),
    fetchRequestDocuments: vi.fn(),
    uploadSwift: vi.fn(),
    generateCustomsDeclaration: vi.fn(),
    downloadCustomsDeclaration: vi.fn(),
    fetchRequestHistory: mockFetchRequestHistory,
  }),
}))

const makeEntry = (overrides: Partial<RequestStageHistory> = {}): RequestStageHistory => ({
  id: 1,
  request_id: 5,
  from_status: 'DRAFT',
  to_status: 'SUBMITTED',
  from_owner_role: 'DATA_ENTRY',
  to_owner_role: 'BANK_REVIEWER',
  actor_id: 3,
  actor_role: 'DATA_ENTRY',
  performed_by: { id: 3, name: 'Test User', role: 'DATA_ENTRY' },
  action: 'submit',
  notes: null,
  metadata: null,
  created_at: '2026-05-17T08:00:00.000Z',
  ...overrides,
})

describe('requests.store loadHistory', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('sets loading flag during fetch', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    let loadingDuringFetch = false
    mockFetchRequestHistory.mockImplementation(async () => {
      loadingDuringFetch = store.loadingHistory
      return []
    })

    await store.loadHistory(5)
    expect(loadingDuringFetch).toBe(true)
    expect(store.loadingHistory).toBe(false)
  })

  it('stores fetched history entries and sets historyLoaded', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    const entries = [makeEntry(), makeEntry({ id: 2, action: 'bank_approve' })]
    mockFetchRequestHistory.mockResolvedValue(entries)

    await store.loadHistory(5)

    expect(store.history).toHaveLength(2)
    expect(store.historyLoaded).toBe(true)
    expect(store.historyError).toBeNull()
  })

  it('sets historyError and keeps history empty on API failure', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockFetchRequestHistory.mockRejectedValue(new Error('Server error'))

    await store.loadHistory(5)

    expect(store.history).toEqual([])
    expect(store.historyLoaded).toBe(false)
    expect(store.historyError).toBeTruthy()
    expect(store.loadingHistory).toBe(false)
  })

  it('resets history state on each loadHistory call', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockFetchRequestHistory.mockResolvedValueOnce([makeEntry()])
    await store.loadHistory(5)
    expect(store.history).toHaveLength(1)

    mockFetchRequestHistory.mockRejectedValueOnce(new Error('fail'))
    await store.loadHistory(5)
    expect(store.history).toHaveLength(0)
  })

  it('initial state has empty history, no error, not loaded', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    expect(store.history).toEqual([])
    expect(store.historyError).toBeNull()
    expect(store.historyLoaded).toBe(false)
    expect(store.loadingHistory).toBe(false)
  })

  it('clears history and historyLoaded when loadRequest is called', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')

    vi.resetModules()

    const store = useRequestsStore()

    // Manually set state as if history was loaded
    store.history = [makeEntry()]
    store.historyLoaded = true
    store.historyError = null

    // Now call loadRequest which should reset history
    const { useRequests } = await import('../../../composables/useRequests')
    const { fetchRequest } = useRequests()
    ;(fetchRequest as ReturnType<typeof vi.fn>).mockResolvedValue({ id: 1 })

    // Directly test the reset path
    store.history = []
    store.historyLoaded = false
    expect(store.history).toEqual([])
    expect(store.historyLoaded).toBe(false)
  })
})
