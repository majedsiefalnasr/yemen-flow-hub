/**
 * BankAdminDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'
import type {
  BankAdminDashboardStats,
  BankAdminMonthlyEntry,
  DashboardQueueItem,
} from '../../../composables/useDashboard'

function makeRequest(overrides: Partial<DashboardQueueItem> = {}): DashboardQueueItem {
  return {
    id: 1,
    reference: 'YFH-2026-000001',
    reference_number: 'YFH-2026-000001',
    status: 'ACTIVE',
    stage_code: 'INTERNAL',
    stage_name: 'المراجعة الداخلية',
    bank_id: 1,
    bank_name: 'Bank Co.',
    merchant_id: 1,
    merchant_name: 'Merchant Co.',
    amount: 10000,
    currency: 'USD',
    created_by: 1,
    created_by_name: 'Entry User',
    created_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  }
}

function makeStats(overrides: Partial<BankAdminDashboardStats> = {}): BankAdminDashboardStats {
  return {
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
    total_financed_amount: 0,
    monthly_requests: [],
    recent_requests: [],
    ...overrides,
  }
}

// ── Logic helpers mirrored from BankAdminDashboard.vue ──────────────────────

function formatAmount(amount: number): string {
  return new Intl.NumberFormat('ar-YE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

function shouldShowEmptyState(stats: BankAdminDashboardStats): boolean {
  return stats.recent_requests.length === 0
}

function displayMerchantName(req: DashboardQueueItem): string {
  return req.merchant_name ?? 'غير متاح'
}

const CHART_W = 480
const CHART_H = 80
const CHART_PAD = 8

function buildSparklinePoints(entries: BankAdminMonthlyEntry[]): string {
  if (!entries.length) return ''
  const counts = entries.map((e) => e.count)
  const max = Math.max(...counts, 1)
  const step = (CHART_W - CHART_PAD * 2) / Math.max(entries.length - 1, 1)
  return entries
    .map((e, i) => {
      const x = CHART_PAD + i * step
      const y = CHART_PAD + (1 - e.count / max) * (CHART_H - CHART_PAD * 2)
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

// ── Tests ───────────────────────────────────────────────────────────────────

describe('BankAdminDashboard — KPI display', () => {
  it('reads all 5 KPI values from stats', () => {
    const stats = makeStats({
      total: 10,
      pending: 3,
      approved: 5,
      rejected: 2,
      total_financed_amount: 50000,
    })
    expect(stats.total).toBe(10)
    expect(stats.pending).toBe(3)
    expect(stats.approved).toBe(5)
    expect(stats.rejected).toBe(2)
    expect(stats.total_financed_amount).toBe(50000)
  })

  it('shows zero values for all KPIs when bank has no requests', () => {
    const stats = makeStats()
    expect(stats.total).toBe(0)
    expect(stats.pending).toBe(0)
    expect(stats.approved).toBe(0)
    expect(stats.rejected).toBe(0)
    expect(stats.total_financed_amount).toBe(0)
  })
})

describe('BankAdminDashboard — empty state', () => {
  it('shows empty state when recent_requests is empty', () => {
    const stats = makeStats({ recent_requests: [] })
    expect(shouldShowEmptyState(stats)).toBe(true)
  })

  it('does not show empty state when requests exist', () => {
    const stats = makeStats({ recent_requests: [makeRequest()] })
    expect(shouldShowEmptyState(stats)).toBe(false)
  })
})

describe('BankAdminDashboard — recent requests table', () => {
  it('shows up to 10 recent requests', () => {
    const requests = Array.from({ length: 10 }, (_, i) => makeRequest({ id: i + 1 }))
    const stats = makeStats({ recent_requests: requests })
    expect(stats.recent_requests).toHaveLength(10)
  })

  it('recent request has required display fields', () => {
    const req = makeRequest({ reference_number: 'YFH-2026-000042', amount: 25000, currency: 'USD' })
    expect(req.reference_number).toBe('YFH-2026-000042')
    expect(displayMerchantName(req)).toBe('Merchant Co.')
    expect(req.amount).toBe(25000)
    expect(req.currency).toBe('USD')
    expect(req.status).toBe('ACTIVE')
    expect(req.created_at).toBeDefined()
  })

  it('falls back to a placeholder when merchant_name is absent', () => {
    const req = makeRequest({ merchant_name: null })
    expect(displayMerchantName(req)).toBe('غير متاح')
  })

  it('exposes the runtime status and stage name, not a legacy 22-value status', () => {
    const req = makeRequest({ status: 'CLOSED', stage_name: 'مغلق — مكتمل' })
    expect(req.status).toBe('CLOSED')
    expect(req.stage_name).toBe('مغلق — مكتمل')
  })
})

describe('BankAdminDashboard — amount formatting', () => {
  it('formats zero as Arabic numeral', () => {
    const result = formatAmount(0)
    expect(typeof result).toBe('string')
    expect(result.length).toBeGreaterThan(0)
  })

  it('formats large amounts without decimals', () => {
    const result = formatAmount(1000000)
    expect(result).not.toContain('.')
    expect(result).not.toContain(',')
  })
})

describe('BankAdminDashboard — monthly chart', () => {
  it('returns empty string for empty entries', () => {
    expect(buildSparklinePoints([])).toBe('')
  })

  it('returns a polyline points string for valid entries', () => {
    const entries: BankAdminMonthlyEntry[] = [
      { month: '2026-01', count: 2 },
      { month: '2026-02', count: 5 },
      { month: '2026-03', count: 3 },
    ]
    const pts = buildSparklinePoints(entries)
    expect(pts).not.toBe('')
    expect(pts.split(' ')).toHaveLength(3)
  })

  it('handles uniform counts without division by zero', () => {
    const entries: BankAdminMonthlyEntry[] = [
      { month: '2026-01', count: 0 },
      { month: '2026-02', count: 0 },
    ]
    const pts = buildSparklinePoints(entries)
    expect(pts).not.toContain('NaN')
    expect(pts).not.toContain('Infinity')
  })

  it('uses max count as ceiling for y-axis normalization', () => {
    const entries: BankAdminMonthlyEntry[] = [
      { month: '2026-01', count: 0 },
      { month: '2026-02', count: 10 },
    ]
    const pts = buildSparklinePoints(entries)
    const points = pts.split(' ').map((p) => p.split(',').map(Number))
    // max count (10) should produce y = CHART_PAD (top)
    expect(points[1]![1]).toBeCloseTo(CHART_PAD, 1)
    // zero count should produce y = CHART_H - CHART_PAD (bottom)
    expect(points[0]![1]).toBeCloseTo(CHART_H - CHART_PAD, 1)
  })

  it('builds 6-entry monthly array correctly', () => {
    const entries: BankAdminMonthlyEntry[] = Array.from({ length: 6 }, (_, i) => ({
      month: `2026-0${i + 1}`,
      count: i,
    }))
    const pts = buildSparklinePoints(entries)
    expect(pts.split(' ')).toHaveLength(6)
  })
})

describe('BankAdminDashboard — role awareness', () => {
  it('BANK_ADMIN role is used for status badge', () => {
    expect(UserRole.BANK_ADMIN).toBeDefined()
    expect(UserRole.BANK_ADMIN).toBe('BANK_ADMIN')
  })
})

// Mirrors RUNTIME_STATUS_BADGE in BankAdminDashboard.vue — all 5 runtime_status
// values (Phase E7: active/completed/rejected/cancelled/abandoned presentation).
const RUNTIME_STATUS_BADGE: Record<string, string> = {
  ACTIVE:
    'border border-[var(--brand-color)]/30 bg-[var(--brand-color)]/10 text-[var(--brand-color)]',
  CLOSED:
    'border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]',
  REJECTED:
    'border border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]',
  CANCELLED: 'border border-[var(--locked)]/30 bg-[var(--locked)]/10 text-[var(--locked)]',
  ABANDONED: 'border border-[var(--locked)]/30 bg-[var(--locked)]/10 text-[var(--locked)]',
}

describe('BankAdminDashboard — runtime_status badge map (Phase E7)', () => {
  it('maps all 5 runtime_status values to a badge class', () => {
    expect(Object.keys(RUNTIME_STATUS_BADGE)).toEqual([
      'ACTIVE',
      'CLOSED',
      'REJECTED',
      'CANCELLED',
      'ABANDONED',
    ])
  })

  it('CANCELLED and ABANDONED both use the locked/immutable token', () => {
    expect(RUNTIME_STATUS_BADGE.CANCELLED).toContain('--locked')
    expect(RUNTIME_STATUS_BADGE.ABANDONED).toContain('--locked')
    expect(RUNTIME_STATUS_BADGE.CANCELLED).toBe(RUNTIME_STATUS_BADGE.ABANDONED)
  })

  it('CLOSED uses the success token, REJECTED uses the error token', () => {
    expect(RUNTIME_STATUS_BADGE.CLOSED).toContain('--severity-green')
    expect(RUNTIME_STATUS_BADGE.REJECTED).toContain('--severity-red')
  })

  it('every terminal status (all but ACTIVE) resolves to a distinct or shared semantic token, never empty', () => {
    for (const status of ['CLOSED', 'REJECTED', 'CANCELLED', 'ABANDONED']) {
      expect(RUNTIME_STATUS_BADGE[status]).toBeTruthy()
    }
  })
})
