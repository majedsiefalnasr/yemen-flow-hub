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
  | 'MyWorkDashboard'
  | 'CbyAdminDashboard'
  | 'Placeholder' {
  // Analytics-oriented roles keep dedicated dashboards.
  if (role === UserRole.BANK_ADMIN) return 'BankAdminDashboard'
  if (role === UserRole.CBY_ADMIN) return 'CbyAdminDashboard'
  // D0.4/D0.5: every workflow-executor role — including the Committee Director —
  // is served by the shared MyWorkDashboard.
  if (
    role === UserRole.DATA_ENTRY ||
    role === UserRole.BANK_REVIEWER ||
    role === UserRole.SUPPORT_COMMITTEE ||
    role === UserRole.SWIFT_OFFICER ||
    role === UserRole.EXECUTIVE_MEMBER ||
    role === UserRole.COMMITTEE_DIRECTOR
  ) {
    return 'MyWorkDashboard'
  }
  return 'Placeholder'
}

describe('Dashboard page — role-component routing', () => {
  it.each([
    UserRole.DATA_ENTRY,
    UserRole.BANK_REVIEWER,
    UserRole.SUPPORT_COMMITTEE,
    UserRole.SWIFT_OFFICER,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
  ])('renders MyWorkDashboard for workflow-executor role %s', (role) => {
    expect(resolveDashboardComponent(role)).toBe('MyWorkDashboard')
  })

  it('renders BankAdminDashboard for BANK_ADMIN role (analytics-oriented)', () => {
    expect(resolveDashboardComponent(UserRole.BANK_ADMIN)).toBe('BankAdminDashboard')
  })

  it('renders CbyAdminDashboard for CBY_ADMIN role (analytics-oriented)', () => {
    expect(resolveDashboardComponent(UserRole.CBY_ADMIN)).toBe('CbyAdminDashboard')
  })

  it('renders Placeholder when role is undefined (unauthenticated edge case)', () => {
    expect(resolveDashboardComponent(undefined)).toBe('Placeholder')
  })
})
