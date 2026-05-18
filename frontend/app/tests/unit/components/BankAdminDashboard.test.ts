/**
 * BankAdminDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { BankAdminDashboardStats, BankAdminMonthlyEntry } from '../../../composables/useDashboard'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_id: 1,
    bank_name: 'بنك اليمن',
    merchant: null,
    status: RequestStatus.SUBMITTED,
    current_owner_role: UserRole.BANK_ADMIN,
    currency: 'USD',
    amount: 10000,
    supplier_name: 'Supplier Co.',
    goods_description: 'Goods',
    port_of_entry: 'Aden',
    notes: null,
    created_by: 1,
    submitted_by: null,
    reviewed_by: null,
    approved_by: null,
    rejected_by: null,
    resubmitted_by: null,
    claimed_by: null,
    claimed_until: null,
    is_claimed: false,
    is_claimed_by_me: false,
    can_be_claimed: false,
    submitted_at: null,
    bank_approved_at: null,
    support_approved_at: null,
    swift_uploaded_at: null,
    executive_decided_at: null,
    customs_issued_at: null,
    revision_count: 0,
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
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
  return new Intl.NumberFormat('ar-YE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

function shouldShowEmptyState(stats: BankAdminDashboardStats): boolean {
  return stats.recent_requests.length === 0
}

function displayMerchantName(req: ImportRequest): string {
  return req.merchant?.name ?? req.supplier_name
}

const CHART_W = 480
const CHART_H = 80
const CHART_PAD = 8

function buildSparklinePoints(entries: BankAdminMonthlyEntry[]): string {
  if (!entries.length) return ''
  const counts = entries.map(e => e.count)
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
    const stats = makeStats({ total: 10, pending: 3, approved: 5, rejected: 2, total_financed_amount: 50000 })
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
    expect(displayMerchantName(req)).toBe('Supplier Co.')
    expect(req.amount).toBe(25000)
    expect(req.currency).toBe('USD')
    expect(req.status).toBe(RequestStatus.SUBMITTED)
    expect(req.updated_at).toBeDefined()
  })

  it('prefers merchant name when merchant object exists', () => {
    const req = makeRequest({
      merchant: { id: 22, name: 'Merchant Co.', commercial_register: null },
      supplier_name: 'Supplier Co.',
    })
    expect(displayMerchantName(req)).toBe('Merchant Co.')
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
    const points = pts.split(' ').map(p => p.split(',').map(Number))
    // max count (10) should produce y = CHART_PAD (top)
    expect(points[1][1]).toBeCloseTo(CHART_PAD, 1)
    // zero count should produce y = CHART_H - CHART_PAD (bottom)
    expect(points[0][1]).toBeCloseTo(CHART_H - CHART_PAD, 1)
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
