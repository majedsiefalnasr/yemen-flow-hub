import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type {
  BankAdminDashboardStats,
  CbyAdminDashboardStats,
} from '../../../composables/useDashboard'

const mockFetchStats = vi.fn()

vi.mock('../../../composables/useDashboard', () => ({
  useDashboard: () => ({ fetchStats: mockFetchStats }),
}))

const { useDashboardStore } = await import('../../../stores/dashboard.store')

const BANK_ADMIN_STATS: BankAdminDashboardStats = {
  total: 10,
  pending: 3,
  approved: 5,
  rejected: 2,
  total_financed_amount: 50000,
  monthly_requests: [],
  recent_requests: [],
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
  most_active_banks: [{ bank_id: 1, bank_name: 'بنك اليمن المركزي', request_count: 18 }],
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
    let resolveStats!: (v: BankAdminDashboardStats) => void
    mockFetchStats.mockReturnValue(
      new Promise((r) => {
        resolveStats = r
      }),
    )

    const store = useDashboardStore()
    const promise = store.loadStats()
    expect(store.loading).toBe(true)

    resolveStats(BANK_ADMIN_STATS)
    await promise

    expect(store.loading).toBe(false)
  })

  it('loadStats stores bank admin stats on success', async () => {
    mockFetchStats.mockResolvedValue(BANK_ADMIN_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(BANK_ADMIN_STATS)
    expect(store.error).toBeNull()
  })

  it('loadStats normalizes an undefined recent_requests to an empty array', async () => {
    mockFetchStats.mockResolvedValue({
      ...BANK_ADMIN_STATS,
      recent_requests: undefined,
    } as unknown as BankAdminDashboardStats)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats?.recent_requests).toEqual([])
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
    mockFetchStats.mockResolvedValueOnce(BANK_ADMIN_STATS)

    const store = useDashboardStore()
    await store.loadStats()
    expect(store.error).toBeTruthy()

    await store.loadStats()
    expect(store.error).toBeNull()
    expect(store.stats).toEqual(BANK_ADMIN_STATS)
  })

  it('loadStats stores CBY admin stats on success', async () => {
    mockFetchStats.mockResolvedValue(CBY_STATS)
    const store = useDashboardStore()
    await store.loadStats()

    expect(store.stats).toEqual(CBY_STATS)
    expect(store.error).toBeNull()
  })
})
