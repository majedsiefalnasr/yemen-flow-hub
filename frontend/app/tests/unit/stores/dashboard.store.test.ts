import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { DataEntryDashboardStats, BankReviewerDashboardStats } from '../../../composables/useDashboard'

const mockFetchStats = vi.fn()

vi.mock('../../../composables/useDashboard', () => ({
  useDashboard: () => ({ fetchStats: mockFetchStats }),
}))

const { useDashboardStore } = await import('../../../stores/dashboard.store')

const DE_STATS: DataEntryDashboardStats = {
  draft: 3,
  returned: 1,
  under_cby_processing: 5,
  completed: 2,
  returned_requests: [],
  recent_requests: [],
}

const BR_STATS: BankReviewerDashboardStats = {
  pending_review: 4,
  at_cby: 6,
  returned_by_support: 2,
  approved_completed: 8,
  review_queue: [],
}

describe('useDashboardStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('initial state is empty', () => {
    const store = useDashboardStore()
    expect(store.stats).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('loadStats sets loading true during fetch then false after', async () => {
    let resolveStats!: (v: DataEntryDashboardStats) => void
    mockFetchStats.mockReturnValue(new Promise(r => { resolveStats = r }))

    const store = useDashboardStore()
    const promise = store.loadStats()
    expect(store.loading).toBe(true)

    resolveStats(DE_STATS)
    await promise

    expect(store.loading).toBe(false)
  })

  it('loadStats stores data entry stats on success', async () => {
    mockFetchStats.mockResolvedValue(DE_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(DE_STATS)
    expect(store.error).toBeNull()
  })

  it('loadStats stores bank reviewer stats on success', async () => {
    mockFetchStats.mockResolvedValue(BR_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(BR_STATS)
    expect(store.error).toBeNull()
  })

  it('loadStats sets error message on failure', async () => {
    mockFetchStats.mockRejectedValue(new Error('network'))
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.error).toBeTruthy()
    expect(store.stats).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('loadStats clears previous error on retry', async () => {
    mockFetchStats.mockRejectedValueOnce(new Error('network'))
    mockFetchStats.mockResolvedValueOnce(DE_STATS)

    const store = useDashboardStore()
    await store.loadStats()
    expect(store.error).toBeTruthy()

    await store.loadStats()
    expect(store.error).toBeNull()
    expect(store.stats).toEqual(DE_STATS)
  })
})
