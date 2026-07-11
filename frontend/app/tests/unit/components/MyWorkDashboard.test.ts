/**
 * MyWorkDashboard logic tests (Phase D0.3) — pure-function tests without
 * mounting. The dashboard is driven entirely by the /dashboard/work API; these
 * cover the section fallbacks, the oldest-actionable pick, and amount formatting
 * that the template relies on. No role condition exists in the component, so
 * these tests are role-agnostic by construction.
 */
import { describe, it, expect } from 'vitest'
import type {
  DashboardWork,
  WorkQueueItem,
  WorkSection,
} from '../../../composables/useDashboardWork'

function makeItem(overrides: Partial<WorkQueueItem> = {}): WorkQueueItem {
  return {
    id: 1,
    reference: 'ENG-2026-YBRD-A011',
    reference_number: 'ENG-2026-YBRD-A011',
    status: 'ACTIVE',
    stage_code: 'FINAL',
    stage_name: 'الاعتماد النهائي',
    bank_name: 'YBRD',
    merchant_name: 'Merchant One',
    amount: 100000,
    currency: 'USD',
    created_at: '2026-05-01T09:00:00.000000Z',
    ...overrides,
  }
}

// Logic mirrored from MyWorkDashboard's computed properties.
const emptySection: WorkSection = { count: 0, items: [] }

function actionableOf(work: DashboardWork | null): WorkSection {
  return work?.actionable ?? { ...emptySection, queue_url: '/workflows?queue=mine' }
}

function trackingOf(work: DashboardWork | null): WorkSection {
  return work?.tracking ?? { ...emptySection, queue_url: '/workflows?scope=all' }
}

function slaOf(work: DashboardWork | null): { near_due: number; overdue: number } {
  return work?.sla ?? { near_due: 0, overdue: 0 }
}

function oldestActionable(work: DashboardWork | null): WorkQueueItem | null {
  return actionableOf(work).items[0] ?? null
}

function formatAmount(amount: number | null, currency: string | null): string {
  if (amount === null) return '—'
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency: currency ?? 'USD',
    minimumFractionDigits: 0,
  }).format(amount)
}

function makeWork(overrides: Partial<DashboardWork> = {}): DashboardWork {
  return {
    actionable: { count: 0, items: [], queue_url: '/workflows?queue=mine' },
    claimed: { count: 0, items: [] },
    tracking: { count: 0, items: [], queue_url: '/workflows?scope=all' },
    sla: { near_due: 0, overdue: 0 },
    recent_activity: [],
    metrics: [],
    ...overrides,
  }
}

describe('MyWorkDashboard — section fallbacks', () => {
  it('falls back to safe empty sections when work is null', () => {
    expect(actionableOf(null)).toEqual({ count: 0, items: [], queue_url: '/workflows?queue=mine' })
    expect(trackingOf(null)).toEqual({ count: 0, items: [], queue_url: '/workflows?scope=all' })
    expect(slaOf(null)).toEqual({ near_due: 0, overdue: 0 })
    expect(oldestActionable(null)).toBeNull()
  })

  it('reads the API sections through when work is present', () => {
    const work = makeWork({
      actionable: { count: 2, items: [makeItem({ id: 5 }), makeItem({ id: 6 })], queue_url: '/x' },
      sla: { near_due: 3, overdue: 1 },
    })
    expect(actionableOf(work).count).toBe(2)
    expect(oldestActionable(work)?.id).toBe(5)
    expect(slaOf(work)).toEqual({ near_due: 3, overdue: 1 })
  })
})

describe('MyWorkDashboard — amount formatting', () => {
  it('renders an em dash for a null amount', () => {
    expect(formatAmount(null, 'USD')).toBe('—')
  })

  it('formats a numeric amount rather than the empty dash', () => {
    const formatted = formatAmount(100000, 'USD')
    expect(formatted).not.toBe('—')
    expect(formatted.length).toBeGreaterThan(0)
  })
})
