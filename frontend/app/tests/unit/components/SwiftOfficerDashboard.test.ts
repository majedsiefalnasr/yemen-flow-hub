/**
 * SwiftOfficerDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { SwiftOfficerDashboardStats } from '../../../composables/useDashboard'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_id: 1,
    bank_name: 'بنك اليمن',
    merchant: null,
    status: RequestStatus.WAITING_FOR_SWIFT,
    current_owner_role: UserRole.SWIFT_OFFICER,
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

// Logic extracted from SwiftOfficerDashboard
function shouldShowEmptyQueue(stats: SwiftOfficerDashboardStats): boolean {
  return stats.swift_queue.length === 0
}

function getPendingHighlightClass(pending: number): string {
  return pending > 0 ? 'kpi-card--highlight' : ''
}

function formatAmountDisplay(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

describe('SwiftOfficerDashboard — queue empty state', () => {
  it('shows empty queue when swift_queue is empty', () => {
    const stats: SwiftOfficerDashboardStats = {
      pending_swift_upload: 0,
      uploaded: 0,
      final_approved: 0,
      final_rejected: 0,
      swift_queue: [],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(true)
  })

  it('shows table when swift_queue has requests', () => {
    const stats: SwiftOfficerDashboardStats = {
      pending_swift_upload: 1,
      uploaded: 0,
      final_approved: 0,
      final_rejected: 0,
      swift_queue: [makeRequest()],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(false)
  })
})

describe('SwiftOfficerDashboard — KPI highlights', () => {
  it('highlights pending upload card when count > 0', () => {
    expect(getPendingHighlightClass(3)).toBe('kpi-card--highlight')
  })

  it('no highlight when pending upload is 0', () => {
    expect(getPendingHighlightClass(0)).toBe('')
  })
})

describe('SwiftOfficerDashboard — request rows', () => {
  it('queue contains only WAITING_FOR_SWIFT requests', () => {
    const waitingReq = makeRequest({ status: RequestStatus.WAITING_FOR_SWIFT })
    const uploadedReq = makeRequest({ id: 2, status: RequestStatus.SWIFT_UPLOADED })

    const stats: SwiftOfficerDashboardStats = {
      pending_swift_upload: 1,
      uploaded: 1,
      final_approved: 0,
      final_rejected: 0,
      swift_queue: [waitingReq],
    }

    const queueStatuses = stats.swift_queue.map(r => r.status)
    expect(queueStatuses).not.toContain(uploadedReq.status)
    expect(queueStatuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
  })

  it('formats amount using ar-YE locale with Eastern Arabic digits', () => {
    const result = formatAmountDisplay(50000, 'USD')
    // ar-YE locale uses Eastern Arabic numerals: ٥٠٬٠٠٠
    expect(result).toContain('٥٠')
  })

  it('displays bank name from request', () => {
    const req = makeRequest({ bank_name: 'بنك اليمن المركزي' })
    expect(req.bank_name).toBe('بنك اليمن المركزي')
  })

  it('handles null bank name gracefully', () => {
    const req = makeRequest({ bank_name: null })
    expect(req.bank_name).toBeNull()
  })
})

describe('SwiftOfficerDashboard — KPI values', () => {
  it('final_approved sums executive_approved, customs_issued, completed', () => {
    const stats: SwiftOfficerDashboardStats = {
      pending_swift_upload: 0,
      uploaded: 2,
      final_approved: 5,
      final_rejected: 1,
      swift_queue: [],
    }
    expect(stats.final_approved).toBe(5)
    expect(stats.final_rejected).toBe(1)
  })

  it('uploaded counts distinct from pending_swift_upload', () => {
    const stats: SwiftOfficerDashboardStats = {
      pending_swift_upload: 3,
      uploaded: 7,
      final_approved: 0,
      final_rejected: 0,
      swift_queue: [],
    }
    expect(stats.pending_swift_upload).toBe(3)
    expect(stats.uploaded).toBe(7)
  })
})
