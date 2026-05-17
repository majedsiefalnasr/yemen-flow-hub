import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { DataEntryDashboardStats, BankReviewerDashboardStats, SupportCommitteeDashboardStats, SwiftOfficerDashboardStats, ExecutiveDashboardStats, CbyAdminDashboardStats } from '../../../composables/useDashboard'

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

const SC_STATS: SupportCommitteeDashboardStats = {
  waiting_for_claim: 3,
  active_by_me: 1,
  claimed_by_others: 2,
  recently_approved: 5,
  support_queue: [],
}

const SO_STATS: SwiftOfficerDashboardStats = {
  pending_swift_upload: 4,
  uploaded: 2,
  final_approved: 10,
  final_rejected: 1,
  swift_queue: [],
}

const EXEC_STATS: ExecutiveDashboardStats = {
  waiting_for_voting_open: 3,
  active_voting_sessions: 1,
  decisions_approved: 8,
  decisions_rejected: 2,
  finalized_decisions: 10,
  voting_queue: [],
  customs_declaration_pending: [],
}

const CBY_STATS: CbyAdminDashboardStats = {
  total: 42,
  approved: 20,
  in_process: 15,
  rejected: 7,
  compliance_alerts: {
    duplicate_suppliers: [{ supplier_name: 'شركة الأمل', count: 3 }],
    high_amount_requests: [],
    stale_pending_requests: [],
  },
  most_active_banks: [
    { bank_id: 1, bank_name: 'بنك اليمن المركزي', request_count: 18 },
  ],
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

  it('loadStats stores support committee stats on success', async () => {
    mockFetchStats.mockResolvedValue(SC_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(SC_STATS)
    expect(store.error).toBeNull()
  })

  it('loadStats stores swift officer stats on success', async () => {
    mockFetchStats.mockResolvedValue(SO_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(SO_STATS)
    expect(store.error).toBeNull()
  })

  it('loadStats stores executive stats on success', async () => {
    mockFetchStats.mockResolvedValue(EXEC_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(EXEC_STATS)
    expect(store.error).toBeNull()
  })

  it('loadStats stores CBY admin stats on success', async () => {
    mockFetchStats.mockResolvedValue(CBY_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(CBY_STATS)
    expect(store.error).toBeNull()
  })
})
