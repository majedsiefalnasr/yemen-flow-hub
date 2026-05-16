/**
 * Dashboard page role-routing logic tests — pure function tests.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'

// Logic mirrored from dashboard.vue: which component to render per role
function resolveDashboardComponent(role: UserRole | undefined): 'DataEntryDashboard' | 'BankReviewerDashboard' | 'Placeholder' {
  if (role === UserRole.DATA_ENTRY) return 'DataEntryDashboard'
  if (role === UserRole.BANK_REVIEWER) return 'BankReviewerDashboard'
  return 'Placeholder'
}

describe('Dashboard page — role-component routing', () => {
  it('renders DataEntryDashboard for DATA_ENTRY role', () => {
    expect(resolveDashboardComponent(UserRole.DATA_ENTRY)).toBe('DataEntryDashboard')
  })

  it('renders BankReviewerDashboard for BANK_REVIEWER role', () => {
    expect(resolveDashboardComponent(UserRole.BANK_REVIEWER)).toBe('BankReviewerDashboard')
  })

  it('renders Placeholder for SUPPORT_COMMITTEE role', () => {
    expect(resolveDashboardComponent(UserRole.SUPPORT_COMMITTEE)).toBe('Placeholder')
  })

  it('renders Placeholder for EXECUTIVE_MEMBER role', () => {
    expect(resolveDashboardComponent(UserRole.EXECUTIVE_MEMBER)).toBe('Placeholder')
  })

  it('renders Placeholder for COMMITTEE_DIRECTOR role', () => {
    expect(resolveDashboardComponent(UserRole.COMMITTEE_DIRECTOR)).toBe('Placeholder')
  })

  it('renders Placeholder for CBY_ADMIN role', () => {
    expect(resolveDashboardComponent(UserRole.CBY_ADMIN)).toBe('Placeholder')
  })

  it('renders Placeholder for SWIFT_OFFICER role', () => {
    expect(resolveDashboardComponent(UserRole.SWIFT_OFFICER)).toBe('Placeholder')
  })

  it('renders Placeholder when role is undefined (unauthenticated edge case)', () => {
    expect(resolveDashboardComponent(undefined)).toBe('Placeholder')
  })
})
