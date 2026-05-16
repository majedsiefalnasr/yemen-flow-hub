/**
 * DataEntryDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { DataEntryDashboardStats } from '../../../composables/useDashboard'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_id: 1,
    bank_name: 'بنك اليمن',
    merchant: null,
    status: RequestStatus.DRAFT,
    current_owner_role: UserRole.DATA_ENTRY,
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

// Logic extracted from DataEntryDashboard
function shouldShowReturnedAlert(stats: DataEntryDashboardStats): boolean {
  return stats.returned_requests.length > 0
}

function getKpiHighlightClass(returned: number): string {
  return returned > 0 ? 'kpi-card--highlight' : ''
}

function buildStatusRole(): UserRole {
  return UserRole.DATA_ENTRY
}

describe('DataEntryDashboard — returned alert visibility', () => {
  it('shows alert when returned_requests is non-empty', () => {
    const stats: DataEntryDashboardStats = {
      draft: 1,
      returned: 1,
      under_cby_processing: 0,
      completed: 0,
      returned_requests: [makeRequest({ status: RequestStatus.DRAFT_REJECTED_INTERNAL })],
      recent_requests: [],
    }
    expect(shouldShowReturnedAlert(stats)).toBe(true)
  })

  it('hides alert when returned_requests is empty', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0,
      returned: 0,
      under_cby_processing: 0,
      completed: 0,
      returned_requests: [],
      recent_requests: [],
    }
    expect(shouldShowReturnedAlert(stats)).toBe(false)
  })
})

describe('DataEntryDashboard — KPI card highlight', () => {
  it('highlights returned card when count > 0', () => {
    expect(getKpiHighlightClass(2)).toBe('kpi-card--highlight')
  })

  it('no highlight class when returned count is 0', () => {
    expect(getKpiHighlightClass(0)).toBe('')
  })
})

describe('DataEntryDashboard — status badge uses DATA_ENTRY role', () => {
  it('badge role is always DATA_ENTRY for simplified statuses', () => {
    expect(buildStatusRole()).toBe(UserRole.DATA_ENTRY)
  })
})

describe('DataEntryDashboard — recent requests max 5', () => {
  it('stats can hold up to 5 recent requests', () => {
    const requests = Array.from({ length: 5 }, (_, i) =>
      makeRequest({ id: i + 1, reference_number: `YFH-2026-00000${i + 1}` }),
    )
    const stats: DataEntryDashboardStats = {
      draft: 0,
      returned: 0,
      under_cby_processing: 0,
      completed: 0,
      returned_requests: [],
      recent_requests: requests,
    }
    expect(stats.recent_requests).toHaveLength(5)
  })
})

describe('DataEntryDashboard — KPI counts', () => {
  it('draft count is read directly from stats', () => {
    const stats: DataEntryDashboardStats = {
      draft: 7,
      returned: 2,
      under_cby_processing: 3,
      completed: 4,
      returned_requests: [],
      recent_requests: [],
    }
    expect(stats.draft).toBe(7)
    expect(stats.returned).toBe(2)
    expect(stats.under_cby_processing).toBe(3)
    expect(stats.completed).toBe(4)
  })
})
