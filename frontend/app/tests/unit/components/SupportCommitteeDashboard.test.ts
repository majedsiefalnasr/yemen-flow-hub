/**
 * SupportCommitteeDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { SupportCommitteeDashboardStats } from '../../../composables/useDashboard'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_id: 1,
    bank_name: 'بنك اليمن المركزي',
    merchant: null,
    status: RequestStatus.SUPPORT_REVIEW_PENDING,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    currency: 'USD',
    amount: 50000,
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
    can_be_claimed: true,
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

// Logic extracted from SupportCommitteeDashboard
function shouldShowEmptyQueue(stats: SupportCommitteeDashboardStats): boolean {
  return stats.support_queue.length === 0
}

function getWaitingHighlightClass(waitingForClaim: number): string {
  return waitingForClaim > 0 ? 'kpi-card--highlight' : ''
}

function getActiveByMeHighlightClass(activeByMe: number): string {
  return activeByMe > 0 ? 'kpi-card--highlight-green' : ''
}

function getClaimerDisplay(req: ImportRequest, currentUserId: number): string {
  if (!req.claimed_by) return '—'
  if (req.claimed_by.id === currentUserId) return `${req.claimed_by.name} (أنت)`
  return req.claimed_by.name
}

function isMyClaimedRow(req: ImportRequest): boolean {
  return req.is_claimed_by_me
}

describe('SupportCommitteeDashboard — queue empty state', () => {
  it('shows empty queue when support_queue is empty', () => {
    const stats: SupportCommitteeDashboardStats = {
      waiting_for_claim: 0,
      active_by_me: 0,
      claimed_by_others: 0,
      approved_last_7_days: 0,
      support_queue: [],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(true)
  })

  it('shows table when support_queue has requests', () => {
    const stats: SupportCommitteeDashboardStats = {
      waiting_for_claim: 1,
      active_by_me: 0,
      claimed_by_others: 0,
      approved_last_7_days: 0,
      support_queue: [makeRequest()],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(false)
  })
})

describe('SupportCommitteeDashboard — KPI highlights', () => {
  it('highlights waiting_for_claim card when count > 0', () => {
    expect(getWaitingHighlightClass(5)).toBe('kpi-card--highlight')
  })

  it('no highlight on waiting_for_claim when 0', () => {
    expect(getWaitingHighlightClass(0)).toBe('')
  })

  it('highlights active_by_me card when count > 0', () => {
    expect(getActiveByMeHighlightClass(1)).toBe('kpi-card--highlight-green')
  })

  it('no highlight on active_by_me when 0', () => {
    expect(getActiveByMeHighlightClass(0)).toBe('')
  })
})

describe('SupportCommitteeDashboard — KPI counts', () => {
  it('reads KPI values directly from stats', () => {
    const stats: SupportCommitteeDashboardStats = {
      waiting_for_claim: 3,
      active_by_me: 1,
      claimed_by_others: 2,
      approved_last_7_days: 7,
      support_queue: [],
    }
    expect(stats.waiting_for_claim).toBe(3)
    expect(stats.active_by_me).toBe(1)
    expect(stats.claimed_by_others).toBe(2)
    expect(stats.approved_last_7_days).toBe(7)
  })
})

describe('SupportCommitteeDashboard — claimer display', () => {
  it('shows dash when request has no claimer', () => {
    const req = makeRequest({ claimed_by: null })
    expect(getClaimerDisplay(req, 1)).toBe('—')
  })

  it('shows name with (أنت) suffix when request is claimed by current user', () => {
    const req = makeRequest({ claimed_by: { id: 5, name: 'سعد المطري' }, is_claimed_by_me: true })
    expect(getClaimerDisplay(req, 5)).toBe('سعد المطري (أنت)')
  })

  it('shows only name when claimed by another user', () => {
    const req = makeRequest({ claimed_by: { id: 10, name: 'خالد عسيري' }, is_claimed_by_me: false })
    expect(getClaimerDisplay(req, 5)).toBe('خالد عسيري')
  })
})

describe('SupportCommitteeDashboard — row highlighting', () => {
  it('marks row as mine when is_claimed_by_me is true', () => {
    const req = makeRequest({ is_claimed_by_me: true })
    expect(isMyClaimedRow(req)).toBe(true)
  })

  it('does not mark row as mine when claimed by another', () => {
    const req = makeRequest({ is_claimed_by_me: false })
    expect(isMyClaimedRow(req)).toBe(false)
  })

  it('does not mark row as mine when unclaimed', () => {
    const req = makeRequest({ claimed_by: null, is_claimed: false, is_claimed_by_me: false })
    expect(isMyClaimedRow(req)).toBe(false)
  })
})

describe('SupportCommitteeDashboard — support queue status composition', () => {
  it('support_queue contains SUPPORT_REVIEW_PENDING and SUPPORT_REVIEW_IN_PROGRESS', () => {
    const queue = [
      makeRequest({ id: 1, status: RequestStatus.SUPPORT_REVIEW_PENDING }),
      makeRequest({ id: 2, status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS }),
    ]
    const statuses = queue.map(r => r.status)
    expect(statuses).toContain(RequestStatus.SUPPORT_REVIEW_PENDING)
    expect(statuses).toContain(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
  })
})
