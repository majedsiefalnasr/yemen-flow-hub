import { describe, expect, it } from 'vitest'
import { buildRequestsEmptyState } from '../../../composables/useRequestsEmptyState'
import { UserRole } from '../../../types/enums'

describe('buildRequestsEmptyState', () => {
  it('returns support-specific empty state when no requests exist', () => {
    const state = buildRequestsEmptyState({
      role: UserRole.SUPPORT_COMMITTEE,
      tab: 'all',
      hasAnyRequests: false,
      search: '',
      bankFilter: 'all',
      dateRangeFilter: 'all',
      createdByMeOnly: false,
      hideOthers: false,
      advancedFilterCount: 0,
    })
    expect(state.title).toContain('لا توجد طلبات دعم')
  })

  it('returns no-active-claims message for support my_claims tab', () => {
    const state = buildRequestsEmptyState({
      role: UserRole.SUPPORT_COMMITTEE,
      tab: 'my_claims',
      hasAnyRequests: true,
      search: '',
      bankFilter: 'all',
      dateRangeFilter: 'all',
      createdByMeOnly: false,
      hideOthers: false,
      advancedFilterCount: 0,
    })
    expect(state.title).toContain('لا توجد مطالبات نشطة')
  })

  it('returns no-assigned-sessions message for executive pending_my_vote tab', () => {
    const state = buildRequestsEmptyState({
      role: UserRole.EXECUTIVE_MEMBER,
      tab: 'pending_my_vote',
      hasAnyRequests: true,
      search: '',
      bankFilter: 'all',
      dateRangeFilter: 'all',
      createdByMeOnly: false,
      hideOthers: false,
      advancedFilterCount: 0,
    })
    expect(state.title).toContain('لا توجد جلسات مخصصة')
  })

  it('returns filter-oriented message when query filters are active', () => {
    const state = buildRequestsEmptyState({
      role: UserRole.BANK_REVIEWER,
      tab: 'all',
      hasAnyRequests: true,
      search: 'ABC',
      bankFilter: 'all',
      dateRangeFilter: 'all',
      createdByMeOnly: false,
      hideOthers: false,
      advancedFilterCount: 0,
    })
    expect(state.title).toBe('لا توجد طلبات مطابقة')
  })
})
