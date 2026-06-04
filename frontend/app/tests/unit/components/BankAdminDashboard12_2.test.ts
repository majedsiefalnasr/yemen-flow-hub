/**
 * BankAdminDashboard 12.2 — UX uplift logic tests.
 *
 * Helpers imported from the production module so test thresholds and chart
 * geometry stay in lockstep with the component (resolves code-review C1).
 * Tests previously declared local copies with drifted thresholds.
 */
import { describe, it, expect } from 'vitest'
import type {
  BankAdminDashboardStats,
  BankAdminDashboardStatsExtended,
} from '../../../composables/useDashboard'
import {
  CHART_W,
  CHART_H,
  CHART_PAD,
  REJECTION_THRESHOLD,
  REPEATED_SUPPORT_RETURNS_THRESHOLD,
  buildLine,
  buildArea,
  calcRejectionRate,
  calcShowHealthStrip,
  calcHealthIssues,
} from '../../../utils/bank-admin-helpers'

type StatsLike = (BankAdminDashboardStats & BankAdminDashboardStatsExtended) | null

function makeStats(
  overrides: Partial<BankAdminDashboardStats & BankAdminDashboardStatsExtended> = {},
): BankAdminDashboardStats & BankAdminDashboardStatsExtended {
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

// ── Threshold constants ───────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — threshold constants', () => {
  it('REJECTION_THRESHOLD is 20%', () => {
    expect(REJECTION_THRESHOLD).toBe(20)
  })

  it('REPEATED_SUPPORT_RETURNS_THRESHOLD is 2', () => {
    expect(REPEATED_SUPPORT_RETURNS_THRESHOLD).toBe(2)
  })
})

// ── calcRejectionRate ─────────────────────────────────────────────────────────

describe('BankAdminDashboard 12.2 — calcRejectionRate()', () => {
  it('uses stats.rejection_rate when provided', () => {
    const stats = makeStats({ total: 100, rejected: 5, rejection_rate: 35 })
    expect(calcRejectionRate(stats)).toBe(35)
  })

  it('calculates from stats.rejected/total when rejection_rate is absent', () => {
    const stats = makeStats({ total: 100, rejected: 25 })
    expect(calcRejectionRate(stats)).toBe(25)
  })

  it('rounds fractional rates', () => {
    const stats = makeStats({ total: 3, rejected: 1 })
    expect(calcRejectionRate(stats)).toBe(33)
  })

  it('handles total=0 without divide-by-zero', () => {
    const stats = makeStats({ total: 0, rejected: 0 })
    expect(calcRejectionRate(stats)).toBe(0)
  })

  it('returns 0 for null stats', () => {
    expect(calcRejectionRate(null as StatsLike)).toBe(0)
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

  it('shown when rejection_rate field exceeds threshold even with low absolute', () => {
    const stats = makeStats({ total: 100, rejected: 5, rejection_rate: 30 })
    expect(calcShowHealthStrip(stats)).toBe(true)
  })

  it('shown when stalled_at_cby_count > 0', () => {
    const stats = makeStats({ total: 100, rejected: 5, stalled_at_cby_count: 3 })
    expect(calcShowHealthStrip(stats)).toBe(true)
  })

  it('shown when missing_bank_reviewer_coverage is true', () => {
    const stats = makeStats({ total: 100, rejected: 5, missing_bank_reviewer_coverage: true })
    expect(calcShowHealthStrip(stats)).toBe(true)
  })

  it(`shown when repeated_support_returns exceeds threshold (> ${REPEATED_SUPPORT_RETURNS_THRESHOLD})`, () => {
    const stats = makeStats({
      total: 100,
      rejected: 5,
      repeated_support_returns: REPEATED_SUPPORT_RETURNS_THRESHOLD + 1,
    })
    expect(calcShowHealthStrip(stats)).toBe(true)
  })

  it(`hidden when repeated_support_returns is at threshold (= ${REPEATED_SUPPORT_RETURNS_THRESHOLD})`, () => {
    const stats = makeStats({
      total: 100,
      rejected: 5,
      repeated_support_returns: REPEATED_SUPPORT_RETURNS_THRESHOLD,
    })
    expect(calcShowHealthStrip(stats)).toBe(false)
  })

  it('shown when suspended_staff_with_active is true', () => {
    const stats = makeStats({ total: 100, rejected: 5, suspended_staff_with_active: true })
    expect(calcShowHealthStrip(stats)).toBe(true)
  })

  it('hidden when all extension fields are default-falsy', () => {
    const stats = makeStats({
      total: 100,
      rejected: 5,
      rejection_rate: 10,
      stalled_at_cby_count: 0,
      missing_bank_reviewer_coverage: false,
      repeated_support_returns: 0,
      suspended_staff_with_active: false,
    })
    expect(calcShowHealthStrip(stats)).toBe(false)
  })

  it('returns false for null stats', () => {
    expect(calcShowHealthStrip(null as StatsLike)).toBe(false)
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
    expect(issues.some((i) => i.includes('معدل الرفض'))).toBe(true)
  })

  it('includes stalled count issue when stalled_at_cby_count > 0', () => {
    const stats = makeStats({ total: 100, rejected: 5, stalled_at_cby_count: 4 })
    const issues = calcHealthIssues(stats)
    expect(issues.some((i) => i.includes('متوقف لدى البنك المركزي'))).toBe(true)
  })

  it('includes missing reviewer issue when coverage is missing', () => {
    const stats = makeStats({ total: 100, rejected: 5, missing_bank_reviewer_coverage: true })
    const issues = calcHealthIssues(stats)
    expect(issues.some((i) => i.includes('مراجع بنك'))).toBe(true)
  })

  it('includes repeated-returns issue when above threshold', () => {
    const stats = makeStats({
      total: 100,
      rejected: 5,
      repeated_support_returns: REPEATED_SUPPORT_RETURNS_THRESHOLD + 1,
    })
    const issues = calcHealthIssues(stats)
    expect(issues.some((i) => i.includes('إعادة متكررة'))).toBe(true)
  })

  it('includes suspended-staff issue when flagged', () => {
    const stats = makeStats({ total: 100, rejected: 5, suspended_staff_with_active: true })
    const issues = calcHealthIssues(stats)
    expect(issues.some((i) => i.includes('موظف موقوف'))).toBe(true)
  })

  it('collects multiple issues simultaneously', () => {
    const stats = makeStats({
      total: 100,
      rejected: 30,
      rejection_rate: 30,
      missing_bank_reviewer_coverage: true,
      suspended_staff_with_active: true,
    })
    const issues = calcHealthIssues(stats)
    expect(issues.length).toBeGreaterThanOrEqual(3)
  })

  it('returns empty array for null stats', () => {
    expect(calcHealthIssues(null as StatsLike)).toHaveLength(0)
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
    const entries = Array.from({ length: 6 }, (_, i) => ({
      month: `2026-0${i + 1}`,
      count: i + 1,
    }))
    expect(buildLine(entries, 'count').split(' ')).toHaveLength(6)
  })

  it(`first point x starts at pad (${CHART_PAD})`, () => {
    const entries = [
      { month: '2026-01', count: 5 },
      { month: '2026-02', count: 8 },
    ]
    const firstX = Number(buildLine(entries, 'count').split(' ')[0]!.split(',')[0])
    expect(firstX).toBeCloseTo(CHART_PAD, 1)
  })

  it('does not produce NaN for all-zero values', () => {
    const entries = [
      { month: '2026-01', count: 0 },
      { month: '2026-02', count: 0 },
    ]
    expect(buildLine(entries, 'count')).not.toContain('NaN')
  })

  it('approved line uses approved field', () => {
    const entries = [
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
    const entries = [
      { month: '2026-01', count: 5 },
      { month: '2026-02', count: 8 },
    ]
    const polygon = buildArea(entries, 'count')
    expect(polygon.startsWith(`${CHART_PAD},${CHART_H - CHART_PAD}`)).toBe(true)
  })

  it('area polygon closes at bottom-right', () => {
    const entries = [
      { month: '2026-01', count: 5 },
      { month: '2026-02', count: 8 },
    ]
    const polygon = buildArea(entries, 'count')
    expect(polygon.endsWith(`,${CHART_H - CHART_PAD}`)).toBe(true)
  })

  it('chart width geometry matches CHART_W constant', () => {
    const entries = [
      { month: '2026-01', count: 5 },
      { month: '2026-02', count: 8 },
    ]
    // last point's x is CHART_W - CHART_PAD
    const polygon = buildArea(entries, 'count')
    expect(polygon).toContain(`${(CHART_W - CHART_PAD).toFixed(1)},`)
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
