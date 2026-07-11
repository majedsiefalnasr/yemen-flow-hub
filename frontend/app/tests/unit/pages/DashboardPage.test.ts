/**
 * Dashboard page role-routing logic tests — pure function tests.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'

// Logic mirrored from dashboard.vue: which component to render per role
function resolveDashboardComponent(
  role: UserRole | undefined,
):
  | 'DataEntryDashboard'
  | 'BankReviewerDashboard'
  | 'BankAdminDashboard'
  | 'SupportCommitteeDashboard'
  | 'MyWorkDashboard'
  | 'ExecutiveDashboard'
  | 'CommitteeDirectorDashboard'
  | 'CbyAdminDashboard'
  | 'Placeholder' {
  if (role === UserRole.DATA_ENTRY) return 'DataEntryDashboard'
  if (role === UserRole.BANK_REVIEWER) return 'BankReviewerDashboard'
  if (role === UserRole.BANK_ADMIN) return 'BankAdminDashboard'
  if (role === UserRole.SUPPORT_COMMITTEE) return 'SupportCommitteeDashboard'
  // D0.4 pilot: SWIFT is served by the shared MyWorkDashboard.
  if (role === UserRole.SWIFT_OFFICER) return 'MyWorkDashboard'
  if (role === UserRole.EXECUTIVE_MEMBER) return 'ExecutiveDashboard'
  if (role === UserRole.COMMITTEE_DIRECTOR) return 'CommitteeDirectorDashboard'
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

  it('renders BankAdminDashboard for BANK_ADMIN role', () => {
    expect(resolveDashboardComponent(UserRole.BANK_ADMIN)).toBe('BankAdminDashboard')
  })

  it('renders SupportCommitteeDashboard for SUPPORT_COMMITTEE role', () => {
    expect(resolveDashboardComponent(UserRole.SUPPORT_COMMITTEE)).toBe('SupportCommitteeDashboard')
  })

  it('renders ExecutiveDashboard for EXECUTIVE_MEMBER role', () => {
    expect(resolveDashboardComponent(UserRole.EXECUTIVE_MEMBER)).toBe('ExecutiveDashboard')
  })

  it('renders CommitteeDirectorDashboard for COMMITTEE_DIRECTOR role', () => {
    expect(resolveDashboardComponent(UserRole.COMMITTEE_DIRECTOR)).toBe(
      'CommitteeDirectorDashboard',
    )
  })

  it('renders CbyAdminDashboard for CBY_ADMIN role', () => {
    expect(resolveDashboardComponent(UserRole.CBY_ADMIN)).toBe('CbyAdminDashboard')
  })

  it('renders MyWorkDashboard for SWIFT_OFFICER role (D0.4 pilot)', () => {
    expect(resolveDashboardComponent(UserRole.SWIFT_OFFICER)).toBe('MyWorkDashboard')
  })

  it('renders Placeholder when role is undefined (unauthenticated edge case)', () => {
    expect(resolveDashboardComponent(undefined)).toBe('Placeholder')
  })
})
