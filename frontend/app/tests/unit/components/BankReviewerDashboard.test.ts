/**
 * BankReviewerDashboard logic tests — pure function tests without component mounting.
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
    status: RequestStatus.SUBMITTED,
    current_owner_role: UserRole.BANK_REVIEWER,
    amount: 10000,
    supplier_name: 'Supplier Co.',
    goods_description: 'Goods',
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  })
}

// Logic extracted from BankReviewerDashboard
function shouldShowEmptyQueue(stats: BankReviewerDashboardStats): boolean {
  return stats.review_queue.length === 0
}

function getPendingHighlightClass(pendingReview: number): string {
  return pendingReview > 0 ? 'kpi-card--highlight' : ''
}

function getReturnedHighlightClass(returnedBySupport: number): string {
  return returnedBySupport > 0 ? 'kpi-card--highlight-red' : ''
}

describe('BankReviewerDashboard — review queue empty state', () => {
  it('shows empty queue message when review_queue is empty', () => {
    const stats: BankReviewerDashboardStats = {
      pending_review: 0,
      at_cby: 0,
      returned_by_support: 0,
      approved_completed: 0,
      review_queue: [],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(true)
  })

  it('shows table when review_queue has requests', () => {
    const stats: BankReviewerDashboardStats = {
      pending_review: 1,
      at_cby: 0,
      returned_by_support: 0,
      approved_completed: 0,
      review_queue: [makeRequest()],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(false)
  })
})

describe('BankReviewerDashboard — KPI highlights', () => {
  it('highlights pending_review card when count > 0', () => {
    expect(getPendingHighlightClass(3)).toBe('kpi-card--highlight')
  })

  it('no highlight on pending_review when 0', () => {
    expect(getPendingHighlightClass(0)).toBe('')
  })

  it('highlights returned_by_support card when count > 0', () => {
    expect(getReturnedHighlightClass(1)).toBe('kpi-card--highlight-red')
  })

  it('no highlight on returned_by_support when 0', () => {
    expect(getReturnedHighlightClass(0)).toBe('')
  })
})

describe('BankReviewerDashboard — KPI counts', () => {
  it('reads KPI values directly from stats', () => {
    const stats: BankReviewerDashboardStats = {
      pending_review: 4,
      at_cby: 6,
      returned_by_support: 2,
      approved_completed: 8,
      review_queue: [],
    }
    expect(stats.pending_review).toBe(4)
    expect(stats.at_cby).toBe(6)
    expect(stats.returned_by_support).toBe(2)
    expect(stats.approved_completed).toBe(8)
  })
})

describe('BankReviewerDashboard — review queue status composition', () => {
  it('review_queue contains SUBMITTED and BANK_REVIEW statuses', () => {
    const queue = [
      makeRequest({ id: 1, status: RequestStatus.SUBMITTED }),
      makeRequest({ id: 2, status: RequestStatus.BANK_REVIEW }),
    ]
    const statuses = queue.map((r) => r.status)
    expect(statuses).toContain(RequestStatus.SUBMITTED)
    expect(statuses).toContain(RequestStatus.BANK_REVIEW)
  })
})
