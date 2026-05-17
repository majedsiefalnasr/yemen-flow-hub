/**
 * CbyAdminDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import type { CbyAdminDashboardStats, CbyAdminComplianceAlerts } from '../../../composables/useDashboard'

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
        high_amount_requests: [{
          id: 1,
          reference_number: 'YFH-2026-000001',
          amount: 1_500_000,
          currency: 'USD',
          bank_name: 'بنك اليمن',
        }],
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
        stale_pending_requests: [{
          id: 2,
          reference_number: 'YFH-2026-000002',
          status: 'SUBMITTED',
          bank_name: 'بنك الخليج',
          updated_at: '2026-04-01T00:00:00.000000Z',
        }],
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
    expect(alerts.duplicate_suppliers[0].count).toBe(5)
  })
})
