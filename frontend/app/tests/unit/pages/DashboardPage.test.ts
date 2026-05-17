/**
 * Dashboard page role-routing logic tests — pure function tests.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'

// Logic mirrored from dashboard.vue: which component to render per role
function resolveDashboardComponent(role: UserRole | undefined): 'DataEntryDashboard' | 'BankReviewerDashboard' | 'SupportCommitteeDashboard' | 'SwiftOfficerDashboard' | 'ExecutiveDashboard' | 'CbyAdminDashboard' | 'Placeholder' {
  if (role === UserRole.DATA_ENTRY) return 'DataEntryDashboard'
  if (role === UserRole.BANK_REVIEWER) return 'BankReviewerDashboard'
  if (role === UserRole.SUPPORT_COMMITTEE) return 'SupportCommitteeDashboard'
  if (role === UserRole.SWIFT_OFFICER) return 'SwiftOfficerDashboard'
  if (role === UserRole.EXECUTIVE_MEMBER || role === UserRole.COMMITTEE_DIRECTOR) return 'ExecutiveDashboard'
  if (role === UserRole.CBY_ADMIN) return 'CbyAdminDashboard'
  return 'Placeholder'
}

describe('Dashboard page — role-component routing', () => {
  it('renders DataEntryDashboard for DATA_ENTRY role', () => {
    expect(resolveDashboardComponent(UserRole.DATA_ENTRY)).toBe('DataEntryDashboard')
  })

  it('renders BankReviewerDashboard for BANK_REVIEWER role', () => {
    expect(resolveDashboardComponent(UserRole.BANK_REVIEWER)).toBe('BankReviewerDashboard')
  })

  it('renders SupportCommitteeDashboard for SUPPORT_COMMITTEE role', () => {
    expect(resolveDashboardComponent(UserRole.SUPPORT_COMMITTEE)).toBe('SupportCommitteeDashboard')
  })

  it('renders ExecutiveDashboard for EXECUTIVE_MEMBER role', () => {
    expect(resolveDashboardComponent(UserRole.EXECUTIVE_MEMBER)).toBe('ExecutiveDashboard')
  })

  it('renders ExecutiveDashboard for COMMITTEE_DIRECTOR role', () => {
    expect(resolveDashboardComponent(UserRole.COMMITTEE_DIRECTOR)).toBe('ExecutiveDashboard')
  })

  it('renders CbyAdminDashboard for CBY_ADMIN role', () => {
    expect(resolveDashboardComponent(UserRole.CBY_ADMIN)).toBe('CbyAdminDashboard')
  })

  it('renders SwiftOfficerDashboard for SWIFT_OFFICER role', () => {
    expect(resolveDashboardComponent(UserRole.SWIFT_OFFICER)).toBe('SwiftOfficerDashboard')
  })

  it('renders Placeholder when role is undefined (unauthenticated edge case)', () => {
    expect(resolveDashboardComponent(undefined)).toBe('Placeholder')
  })
})
