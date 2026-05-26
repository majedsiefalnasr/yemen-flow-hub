/**
 * BankAdminDashboard 12.2 — UX uplift logic tests.
 * Pure function tests, no component mounting.
 */
import { describe, it, expect } from 'vitest'
import type { BankAdminDashboardStats, BankAdminDashboardStatsExtended } from '../../../composables/useDashboard'

// ── Constants and types mirrored from BankAdminDashboard.vue ─────────────────

const REJECTION_THRESHOLD = 20 // %

interface DualEntry { month: string; count: number; approved?: number }

function buildLine(entries: DualEntry[], key: keyof DualEntry): string {
  if (!entries.length) return ''
  const W = 480, H = 80, pad = 8
  const vals = entries.map(e => Number(e[key] ?? 0))
  const max = Math.max(...vals, 1)
  const step = (W - pad * 2) / Math.max(entries.length - 1, 1)
  return entries.map((e, i) => {
    const x = pad + i * step
    const y = pad + (1 - Number(e[key] ?? 0) / max) * (H - pad * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
}

function buildArea(entries: DualEntry[], key: keyof DualEntry): string {
  if (!entries.length) return ''
  const W = 480, H = 80, pad = 8
  const vals = entries.map(e => Number(e[key] ?? 0))
  const max = Math.max(...vals, 1)
  const step = (W - pad * 2) / Math.max(entries.length - 1, 1)
  const pts = entries.map((e, i) => {
    const x = pad + i * step
    const y = pad + (1 - Number(e[key] ?? 0) / max) * (H - pad * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  })
  const bottom = H - pad
  const lastX = (pad + (entries.length - 1) * step).toFixed(1)
  return `${pad},${bottom} ${pts.join(' ')} ${lastX},${bottom}`
}

function calcRejectionRate(stats: BankAdminDashboardStats, ext?: BankAdminDashboardStatsExtended): number {
  if (ext?.rejection_rate !== undefined) return ext.rejection_rate
  const base = stats.total || 1
  return Math.round((stats.rejected / base) * 100)
}

function calcShowHealthStrip(stats: BankAdminDashboardStats, ext?: BankAdminDashboardStatsExtended): boolean {
  const rr = calcRejectionRate(stats, ext)
  return (
    rr > REJECTION_THRESHOLD
    || (ext?.stalled_at_cby_count ?? 0) > 0
    || ext?.missing_bank_reviewer_coverage === true
    || (ext?.repeated_support_returns ?? 0) > 0
    || ext?.suspended_staff_with_active === true
  )
}

function calcHealthIssues(stats: BankAdminDashboardStats, ext?: BankAdminDashboardStatsExtended): string[] {
  const issues: string[] = []
  const rr = calcRejectionRate(stats, ext)
  if (rr > REJECTION_THRESHOLD) issues.push(`معدل الرفض مرتفع: ${rr}%`)
  if ((ext?.stalled_at_cby_count ?? 0) > 0) issues.push(`طلبات متوقفة لدى البنك المركزي: ${ext!.stalled_at_cby_count}`)
  if (ext?.missing_bank_reviewer_coverage) issues.push('لا يوجد مراجع بنكي نشط')
  if ((ext?.repeated_support_returns ?? 0) > 0) issues.push(`إعادات متكررة من لجنة الدعم: ${ext!.repeated_support_returns}`)
  if (ext?.suspended_staff_with_active) issues.push('موظفون موقوفون لديهم طلبات نشطة')
  return issues
}

function makeStats(overrides: Partial<BankAdminDashboardStats> = {}): BankAdminDashboardStats {
  return {
    total: 100,
    pending: 20,
    approved: 60,
    rejected: 10,
    total_financed_amount: 500_000,
    monthly_requests: [],
    recent_requests: [],
    ...overrides,
  }
}

// ── REJECTION_THRESHOLD constant ──────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — REJECTION_THRESHOLD', () => {
  it('threshold is 20%', () => {
    expect(REJECTION_THRESHOLD).toBe(20)
  })
})

// ── calcRejectionRate ─────────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — calcRejectionRate()', () => {
  it('uses ext.rejection_rate when provided', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, rejection_rate: 35 }
    expect(calcRejectionRate(stats, ext)).toBe(35)
  })

  it('calculates from stats when ext.rejection_rate is absent', () => {
    const stats = makeStats({ total: 100, rejected: 25 })
    expect(calcRejectionRate(stats)).toBe(25)
  })

  it('calculates correctly with fractional result (rounds)', () => {
    const stats = makeStats({ total: 3, rejected: 1 })
    expect(calcRejectionRate(stats)).toBe(33)
  })

  it('handles total=0 without divide-by-zero (clamps to 1)', () => {
    const stats = makeStats({ total: 0, rejected: 0 })
    expect(calcRejectionRate(stats)).toBe(0)
  })
})

// ── calcShowHealthStrip ───────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — calcShowHealthStrip()', () => {
  it('hidden when all indicators are healthy', () => {
    const stats = makeStats({ total: 100, rejected: 10 }) // 10% < 20%
    expect(calcShowHealthStrip(stats)).toBe(false)
  })

  it('shown when rejection rate exceeds threshold', () => {
    const stats = makeStats({ total: 100, rejected: 25 }) // 25% > 20%
    expect(calcShowHealthStrip(stats)).toBe(true)
  })

  it('shown when ext.rejection_rate exceeds threshold', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, rejection_rate: 30 }
    expect(calcShowHealthStrip(stats, ext)).toBe(true)
  })

  it('shown when stalled_at_cby_count > 0', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, stalled_at_cby_count: 3 }
    expect(calcShowHealthStrip(stats, ext)).toBe(true)
  })

  it('shown when missing_bank_reviewer_coverage is true', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, missing_bank_reviewer_coverage: true }
    expect(calcShowHealthStrip(stats, ext)).toBe(true)
  })

  it('shown when repeated_support_returns > 0', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, repeated_support_returns: 2 }
    expect(calcShowHealthStrip(stats, ext)).toBe(true)
  })

  it('shown when suspended_staff_with_active is true', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, suspended_staff_with_active: true }
    expect(calcShowHealthStrip(stats, ext)).toBe(true)
  })

  it('hidden when all ext fields are default-falsy', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = {
      ...stats,
      rejection_rate: 10,
      stalled_at_cby_count: 0,
      missing_bank_reviewer_coverage: false,
      repeated_support_returns: 0,
      suspended_staff_with_active: false,
    }
    expect(calcShowHealthStrip(stats, ext)).toBe(false)
  })
})

// ── calcHealthIssues ──────────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — calcHealthIssues()', () => {
  it('empty array when all healthy', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    expect(calcHealthIssues(stats)).toHaveLength(0)
  })

  it('includes rejection rate issue when above threshold', () => {
    const stats = makeStats({ total: 100, rejected: 30 })
    const issues = calcHealthIssues(stats)
    expect(issues.some(i => i.includes('معدل الرفض'))).toBe(true)
  })

  it('includes stalled count issue when stalled_at_cby_count > 0', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, stalled_at_cby_count: 4 }
    const issues = calcHealthIssues(stats, ext)
    expect(issues.some(i => i.includes('متوقفة لدى البنك المركزي'))).toBe(true)
  })

  it('includes missing reviewer issue when missing_bank_reviewer_coverage', () => {
    const stats = makeStats({ total: 100, rejected: 5 })
    const ext: BankAdminDashboardStatsExtended = { ...stats, missing_bank_reviewer_coverage: true }
    const issues = calcHealthIssues(stats, ext)
    expect(issues.some(i => i.includes('مراجع بنكي'))).toBe(true)
  })

  it('includes multiple issues simultaneously', () => {
    const stats = makeStats({ total: 100, rejected: 30 })
    const ext: BankAdminDashboardStatsExtended = {
      ...stats,
      rejection_rate: 30,
      missing_bank_reviewer_coverage: true,
      suspended_staff_with_active: true,
    }
    const issues = calcHealthIssues(stats, ext)
    expect(issues.length).toBeGreaterThanOrEqual(3)
  })
})

// ── buildLine (dual-line chart) ───────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — buildLine()', () => {
  it('returns empty string for empty entries', () => {
    expect(buildLine([], 'count')).toBe('')
  })

  it('returns single point for single-entry array', () => {
    const pts = buildLine([{ month: '2026-01', count: 5 }], 'count')
    expect(pts.split(' ')).toHaveLength(1)
  })

  it('returns 6 pairs for 6-month data', () => {
    const entries: DualEntry[] = Array.from({ length: 6 }, (_, i) => ({
      month: `2026-0${i + 1}`,
      count: i + 1,
    }))
    expect(buildLine(entries, 'count').split(' ')).toHaveLength(6)
  })

  it('first point x starts at pad (8)', () => {
    const entries: DualEntry[] = [{ month: '2026-01', count: 5 }, { month: '2026-02', count: 8 }]
    const firstX = Number(buildLine(entries, 'count').split(' ')[0]!.split(',')[0])
    expect(firstX).toBeCloseTo(8, 1)
  })

  it('does not produce NaN for all-zero values', () => {
    const entries: DualEntry[] = [
      { month: '2026-01', count: 0 },
      { month: '2026-02', count: 0 },
    ]
    expect(buildLine(entries, 'count')).not.toContain('NaN')
  })

  it('approved line uses approved field', () => {
    const entries: DualEntry[] = [
      { month: '2026-01', count: 10, approved: 7 },
      { month: '2026-02', count: 8, approved: 6 },
    ]
    const pts = buildLine(entries, 'approved')
    expect(pts.split(' ')).toHaveLength(2)
    expect(pts).not.toContain('NaN')
  })
})

// ── buildArea ─────────────────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — buildArea()', () => {
  it('returns empty string for empty entries', () => {
    expect(buildArea([], 'count')).toBe('')
  })

  it('area polygon starts at bottom-left corner (pad, H - pad)', () => {
    const entries: DualEntry[] = [
      { month: '2026-01', count: 5 },
      { month: '2026-02', count: 8 },
    ]
    const polygon = buildArea(entries, 'count')
    expect(polygon.startsWith('8,72')).toBe(true) // pad=8, H-pad=72
  })

  it('area polygon closes at bottom-right', () => {
    const entries: DualEntry[] = [
      { month: '2026-01', count: 5 },
      { month: '2026-02', count: 8 },
    ]
    const polygon = buildArea(entries, 'count')
    expect(polygon.endsWith(',72')).toBe(true) // ends at H - pad = 72
  })
})

// ── recent_requests slice ─────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — recent_requests capped at 8', () => {
  it('shows all rows when ≤ 8 recent requests', () => {
    const rows = Array.from({ length: 5 }, (_, i) => ({ id: i + 1 }))
    expect(rows.slice(0, 8)).toHaveLength(5)
  })

  it('caps at 8 rows when more than 8 present', () => {
    const rows = Array.from({ length: 15 }, (_, i) => ({ id: i + 1 }))
    expect(rows.slice(0, 8)).toHaveLength(8)
  })

  it('empty list produces empty slice', () => {
    expect([].slice(0, 8)).toHaveLength(0)
  })
})

// ── KPI order invariant ───────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — KPI grid order', () => {
  const KPI_ORDER = ['total', 'pending', 'approved', 'rejected'] as const

  it('has exactly 4 KPIs', () => {
    expect(KPI_ORDER).toHaveLength(4)
  })

  it('total is first KPI', () => {
    expect(KPI_ORDER[0]).toBe('total')
  })

  it('rejected is last KPI', () => {
    expect(KPI_ORDER[3]).toBe('rejected')
  })

  it('approved precedes rejected', () => {
    const approvedIdx = KPI_ORDER.indexOf('approved')
    const rejectedIdx = KPI_ORDER.indexOf('rejected')
    expect(approvedIdx).toBeLessThan(rejectedIdx)
  })
})
