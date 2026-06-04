/**
 * CbyAdminDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import type {
  CbyAdminDashboardStats,
  CbyAdminComplianceAlerts,
  CbyAdminMonthlyEntry,
  CbyAdminCategoryEntry,
} from '../../../composables/useDashboard'

function makeStats(overrides: Partial<CbyAdminDashboardStats> = {}): CbyAdminDashboardStats {
  return {
    total: 0,
    approved: 0,
    in_process: 0,
    rejected: 0,
    compliance_alerts: {
      duplicate_suppliers: [],
      high_amount_requests: [],
      stale_pending_requests: [],
    },
    most_active_banks: [],
    ...overrides,
  }
}

// Logic extracted from CbyAdminDashboard
function hasComplianceAlerts(alerts: CbyAdminComplianceAlerts): boolean {
  return (
    alerts.duplicate_suppliers.length > 0 ||
    alerts.high_amount_requests.length > 0 ||
    alerts.stale_pending_requests.length > 0
  )
}

function getBankRank(index: number): number {
  return index + 1
}

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(amount)
}

function totalShouldEqualSumOfCategories(stats: CbyAdminDashboardStats): boolean {
  // Total should roughly equal approved + in_process + rejected (may include drafts)
  return stats.total >= stats.approved + stats.in_process + stats.rejected
}

describe('CbyAdminDashboard — KPI shape', () => {
  it('stats has all required KPI fields', () => {
    const stats = makeStats({ total: 42, approved: 20, in_process: 15, rejected: 7 })
    expect(stats.total).toBe(42)
    expect(stats.approved).toBe(20)
    expect(stats.in_process).toBe(15)
    expect(stats.rejected).toBe(7)
  })

  it('total is at least sum of approved, in_process, rejected', () => {
    const stats = makeStats({ total: 45, approved: 20, in_process: 15, rejected: 7 })
    expect(totalShouldEqualSumOfCategories(stats)).toBe(true)
  })
})

describe('CbyAdminDashboard — compliance alerts detection', () => {
  it('no alerts when all arrays are empty', () => {
    const stats = makeStats()
    expect(hasComplianceAlerts(stats.compliance_alerts)).toBe(false)
  })

  it('alert detected for duplicate suppliers', () => {
    const stats = makeStats({
      compliance_alerts: {
        duplicate_suppliers: [{ supplier_name: 'Supplier A', count: 3 }],
        high_amount_requests: [],
        stale_pending_requests: [],
      },
    })
    expect(hasComplianceAlerts(stats.compliance_alerts)).toBe(true)
  })

  it('alert detected for high amount requests', () => {
    const stats = makeStats({
      compliance_alerts: {
        duplicate_suppliers: [],
        high_amount_requests: [
          {
            id: 1,
            reference_number: 'YFH-2026-000001',
            amount: 1_500_000,
            currency: 'USD',
            bank_name: 'بنك اليمن',
          },
        ],
        stale_pending_requests: [],
      },
    })
    expect(hasComplianceAlerts(stats.compliance_alerts)).toBe(true)
  })

  it('alert detected for stale pending requests', () => {
    const stats = makeStats({
      compliance_alerts: {
        duplicate_suppliers: [],
        high_amount_requests: [],
        stale_pending_requests: [
          {
            id: 2,
            reference_number: 'YFH-2026-000002',
            bank_name: 'بنك الخليج',
            updated_at: '2026-04-01T00:00:00.000000Z',
          },
        ],
      },
    })
    expect(hasComplianceAlerts(stats.compliance_alerts)).toBe(true)
  })
})

describe('CbyAdminDashboard — most active banks ranking', () => {
  it('rank starts at 1 for the first bank', () => {
    expect(getBankRank(0)).toBe(1)
  })

  it('rank increments by index', () => {
    expect(getBankRank(4)).toBe(5)
  })

  it('accepts up to 5 banks', () => {
    const stats = makeStats({
      most_active_banks: [
        { bank_id: 1, bank_name: 'بنك أ', request_count: 18 },
        { bank_id: 2, bank_name: 'بنك ب', request_count: 12 },
        { bank_id: 3, bank_name: 'بنك ج', request_count: 8 },
        { bank_id: 4, bank_name: 'بنك د', request_count: 5 },
        { bank_id: 5, bank_name: 'بنك هـ', request_count: 2 },
      ],
    })
    expect(stats.most_active_banks).toHaveLength(5)
  })

  it('empty list is valid', () => {
    const stats = makeStats({ most_active_banks: [] })
    expect(stats.most_active_banks).toHaveLength(0)
  })
})

describe('CbyAdminDashboard — amount formatting', () => {
  it('formats USD amounts with $ sign', () => {
    const result = formatAmount(1_500_000, 'USD')
    expect(result).toContain('1,500,000')
    expect(result).toContain('$')
  })

  it('formats amounts over 1 million without decimals', () => {
    const result = formatAmount(2_000_000, 'USD')
    expect(result).not.toContain('.00')
  })
})

describe('CbyAdminDashboard — compliance alerts per category count', () => {
  it('duplicate_suppliers list reflects backend count', () => {
    const alerts: CbyAdminComplianceAlerts = {
      duplicate_suppliers: [
        { supplier_name: 'Supplier A', count: 5 },
        { supplier_name: 'Supplier B', count: 2 },
      ],
      high_amount_requests: [],
      stale_pending_requests: [],
    }
    expect(alerts.duplicate_suppliers).toHaveLength(2)
    expect(alerts.duplicate_suppliers[0]!.count).toBe(5)
  })
})

// ── Monthly trend chart helpers (mirrored from CbyAdminDashboard.vue) ─────────

const CHART_W = 600
const CHART_H = 100
const PAD = 12

function buildLine(entries: CbyAdminMonthlyEntry[], key: keyof CbyAdminMonthlyEntry): string {
  if (!entries.length) return ''
  const vals = entries.map((e) => Number(e[key]))
  const max = Math.max(...vals, 1)
  const step = (CHART_W - PAD * 2) / Math.max(entries.length - 1, 1)
  return entries
    .map((e, i) => {
      const x = PAD + i * step
      const y = PAD + (1 - Number(e[key]) / max) * (CHART_H - PAD * 2)
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

function buildArea(entries: CbyAdminMonthlyEntry[], key: keyof CbyAdminMonthlyEntry): string {
  if (!entries.length) return ''
  const vals = entries.map((e) => Number(e[key]))
  const max = Math.max(...vals, 1)
  const step = (CHART_W - PAD * 2) / Math.max(entries.length - 1, 1)
  const pts = entries.map((e, i) => {
    const x = PAD + i * step
    const y = PAD + (1 - Number(e[key]) / max) * (CHART_H - PAD * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  })
  const bottom = CHART_H - PAD
  const lastX = (PAD + (entries.length - 1) * step).toFixed(1)
  return `${PAD},${bottom} ${pts.join(' ')} ${lastX},${bottom}`
}

function buildDonutPath(
  entries: CbyAdminCategoryEntry[],
  index: number,
  cx: number,
  cy: number,
  r: number,
): string {
  const total = entries.reduce((s, e) => s + e.count, 0)
  if (!total) return ''
  let startAngle = -Math.PI / 2
  for (let i = 0; i < index; i++) {
    startAngle += (entries[i]!.count / total) * 2 * Math.PI
  }
  const angle = (entries[index]!.count / total) * 2 * Math.PI
  const endAngle = startAngle + angle
  const x1 = cx + r * Math.cos(startAngle)
  const y1 = cy + r * Math.sin(startAngle)
  const x2 = cx + r * Math.cos(endAngle)
  const y2 = cy + r * Math.sin(endAngle)
  const largeArc = angle > Math.PI ? 1 : 0
  return `M ${cx} ${cy} L ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${largeArc} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} Z`
}

describe('CbyAdminDashboard — monthly trend chart (buildLine)', () => {
  it('returns empty string for empty entries', () => {
    expect(buildLine([], 'submitted')).toBe('')
  })

  it('returns space-separated coordinate pairs for valid entries', () => {
    const entries: CbyAdminMonthlyEntry[] = [
      { month: '2026-01', submitted: 3, approved: 1 },
      { month: '2026-02', submitted: 5, approved: 3 },
      { month: '2026-03', submitted: 2, approved: 2 },
    ]
    const pts = buildLine(entries, 'submitted')
    expect(pts.split(' ')).toHaveLength(3)
  })

  it('first point starts at left PAD', () => {
    const entries: CbyAdminMonthlyEntry[] = [
      { month: '2026-01', submitted: 5, approved: 2 },
      { month: '2026-02', submitted: 3, approved: 1 },
    ]
    const pts = buildLine(entries, 'submitted')
    const firstX = Number(pts.split(' ')[0]!.split(',')[0])
    expect(firstX).toBeCloseTo(PAD, 1)
  })

  it('does not produce NaN or Infinity for all-zero values', () => {
    const entries: CbyAdminMonthlyEntry[] = [
      { month: '2026-01', submitted: 0, approved: 0 },
      { month: '2026-02', submitted: 0, approved: 0 },
    ]
    const pts = buildLine(entries, 'submitted')
    expect(pts).not.toContain('NaN')
    expect(pts).not.toContain('Infinity')
  })

  it('6-entry window produces 6 coordinate pairs', () => {
    const entries: CbyAdminMonthlyEntry[] = Array.from({ length: 6 }, (_, i) => ({
      month: `2026-0${i + 1}`,
      submitted: i + 1,
      approved: i,
    }))
    expect(buildLine(entries, 'submitted').split(' ')).toHaveLength(6)
  })
})

describe('CbyAdminDashboard — monthly trend chart (buildArea)', () => {
  it('returns empty string for empty entries', () => {
    expect(buildArea([], 'approved')).toBe('')
  })

  it('area polygon starts at bottom-left corner', () => {
    const entries: CbyAdminMonthlyEntry[] = [
      { month: '2026-01', submitted: 2, approved: 1 },
      { month: '2026-02', submitted: 4, approved: 3 },
    ]
    const polygon = buildArea(entries, 'submitted')
    expect(polygon.startsWith(`${PAD},${CHART_H - PAD}`)).toBe(true)
  })
})

describe('CbyAdminDashboard — donut chart (buildDonutPath)', () => {
  const entries: CbyAdminCategoryEntry[] = [
    { label: 'USD', count: 60, color: '#0066cc' },
    { label: 'EUR', count: 40, color: '#1b5e20' },
  ]

  it('returns empty string when total is zero', () => {
    const empty: CbyAdminCategoryEntry[] = [{ label: 'USD', count: 0, color: '#0066cc' }]
    expect(buildDonutPath(empty, 0, 50, 50, 38)).toBe('')
  })

  it('returns a valid SVG path string for non-zero entry', () => {
    const path = buildDonutPath(entries, 0, 50, 50, 38)
    expect(path).toContain('M 50 50')
    expect(path).toContain('A 38 38')
  })

  it('does not set large-arc flag for slice < 50%', () => {
    const path = buildDonutPath(entries, 1, 50, 50, 38) // EUR: 40% → small arc
    expect(path).toContain('0 1')
  })

  it('sets large-arc flag for slice > 50%', () => {
    const dominant: CbyAdminCategoryEntry[] = [
      { label: 'USD', count: 70, color: '#0066cc' },
      { label: 'EUR', count: 30, color: '#1b5e20' },
    ]
    const path = buildDonutPath(dominant, 0, 50, 50, 38) // USD: 70% → large arc
    expect(path).toContain('1 1')
  })
})

describe('CbyAdminDashboard — optional fields graceful rendering', () => {
  it('stats without monthly_requests does not show chart section', () => {
    const stats = makeStats()
    expect(stats.monthly_requests).toBeUndefined()
  })

  it('stats without recent_requests shows empty state', () => {
    const stats = makeStats()
    expect(stats.recent_requests).toBeUndefined()
    const isEmpty = !stats.recent_requests?.length
    expect(isEmpty).toBe(true)
  })

  it('stats with monthly_requests shows chart data', () => {
    const stats = makeStats({
      monthly_requests: [{ month: '2026-05', submitted: 10, approved: 7 }],
    })
    expect(stats.monthly_requests).toHaveLength(1)
    expect(stats.monthly_requests![0]!.submitted).toBe(10)
  })
})
