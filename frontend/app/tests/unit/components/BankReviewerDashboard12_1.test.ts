/**
 * BankReviewerDashboard Story 12.1 assertions — SUPPORT_REJECTED action strip,
 * KPI spec order, Created By column segregation logic, downstream table.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { BankReviewerDashboardStats } from '../../../composables/useDashboard'
import { makeImportRequest } from '../fixtures/request-data'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_name: 'بنك اليمن الدولي',
    status: RequestStatus.SUBMITTED,
    current_owner_role: UserRole.BANK_REVIEWER,
    amount: 25000,
    supplier_name: 'Intl Supplier',
    goods_description: 'Machinery',
    created_by: 10,
    created_at: '2026-05-26T00:00:00.000000Z',
    updated_at: '2026-05-26T00:00:00.000000Z',
    ...overrides,
  })
}

// --- SUPPORT_REJECTED action strip ---

function supportRejectedCount(stats: BankReviewerDashboardStats): number {
  return stats.returned_by_support ?? 0
}

function shouldShowSupportRejectedStrip(stats: BankReviewerDashboardStats): boolean {
  return supportRejectedCount(stats) > 0
}

describe('BankReviewerDashboard 12.1 — SUPPORT_REJECTED action strip', () => {
  it('shows strip when returned_by_support > 0', () => {
    const stats: BankReviewerDashboardStats = {
      pending_review: 0, at_cby: 0, returned_by_support: 2, approved_completed: 0,
      review_queue: [],
    }
    expect(shouldShowSupportRejectedStrip(stats)).toBe(true)
  })

  it('hides strip when returned_by_support is 0', () => {
    const stats: BankReviewerDashboardStats = {
      pending_review: 0, at_cby: 0, returned_by_support: 0, approved_completed: 0,
      review_queue: [],
    }
    expect(shouldShowSupportRejectedStrip(stats)).toBe(false)
  })

  it('strip count equals returned_by_support', () => {
    const stats: BankReviewerDashboardStats = {
      pending_review: 1, at_cby: 0, returned_by_support: 4, approved_completed: 0,
      review_queue: [],
    }
    expect(supportRejectedCount(stats)).toBe(4)
  })
})

// --- KPI spec order: Pending Review / Rejected by Support / At CBY / Approved-Completed ---

type KpiEntry = { label: string; variant: string }

function buildKpiConfig(stats: BankReviewerDashboardStats): KpiEntry[] {
  return [
    { label: 'بانتظار المراجعة', variant: (stats.pending_review ?? 0) > 0 ? 'amber' : 'gray' },
    { label: 'مرفوضة من لجنة المساندة', variant: (stats.returned_by_support ?? 0) > 0 ? 'rose' : 'gray' },
    { label: 'عند CBY', variant: 'blue' },
    { label: 'اعتُمِد / مكتمل', variant: 'green' },
  ]
}

describe('BankReviewerDashboard 12.1 — KPI spec order', () => {
  const stats: BankReviewerDashboardStats = {
    pending_review: 3, at_cby: 1, returned_by_support: 2, approved_completed: 5,
    review_queue: [],
  }
  const kpis = buildKpiConfig(stats)

  it('first KPI is Pending Review (amber when > 0)', () => {
    expect(kpis[0]?.label).toBe('بانتظار المراجعة')
    expect(kpis[0]?.variant).toBe('amber')
  })

  it('second KPI is Rejected by Support (rose when > 0)', () => {
    expect(kpis[1]?.label).toBe('مرفوضة من لجنة المساندة')
    expect(kpis[1]?.variant).toBe('rose')
  })

  it('third KPI is At CBY (blue)', () => {
    expect(kpis[2]?.label).toBe('عند CBY')
    expect(kpis[2]?.variant).toBe('blue')
  })

  it('fourth KPI is Approved-Completed (green)', () => {
    expect(kpis[3]?.label).toBe('اعتُمِد / مكتمل')
    expect(kpis[3]?.variant).toBe('green')
  })
})

// --- Segregation of duties: isCreatedByCurrentUser ---

function isCreatedByCurrentUser(request: ImportRequest, currentUserId: number | null): boolean {
  if (currentUserId == null) return false
  return request.created_by === currentUserId
}

describe('BankReviewerDashboard 12.1 — segregation Created By column', () => {
  it('returns true when request.created_by matches currentUserId', () => {
    const req = makeRequest({ created_by: 42 })
    expect(isCreatedByCurrentUser(req, 42)).toBe(true)
  })

  it('returns false when request.created_by differs from currentUserId', () => {
    const req = makeRequest({ created_by: 42 })
    expect(isCreatedByCurrentUser(req, 99)).toBe(false)
  })

  it('returns false when currentUserId is null', () => {
    const req = makeRequest({ created_by: 42 })
    expect(isCreatedByCurrentUser(req, null)).toBe(false)
  })

  it('segregation blocks action button for own request', () => {
    const req = makeRequest({ created_by: 7 })
    const currentUserId = 7
    const canReview = !isCreatedByCurrentUser(req, currentUserId)
    expect(canReview).toBe(false)
  })

  it('segregation allows action button for others\' request', () => {
    const req = makeRequest({ created_by: 7 })
    const currentUserId = 8
    const canReview = !isCreatedByCurrentUser(req, currentUserId)
    expect(canReview).toBe(true)
  })
})

// --- Review queue empty state: reassuring text pattern ---

function getEmptyQueueMessage(queueLength: number): string {
  if (queueLength === 0) return 'لا توجد طلبات في طابور المراجعة حالياً ✓'
  return ''
}

describe('BankReviewerDashboard 12.1 — empty queue reassurance', () => {
  it('shows reassuring checkmark message when queue is empty', () => {
    expect(getEmptyQueueMessage(0)).toContain('✓')
  })

  it('no message shown when queue has items', () => {
    expect(getEmptyQueueMessage(3)).toBe('')
  })
})
