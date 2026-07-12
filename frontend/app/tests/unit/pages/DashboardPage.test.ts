/**
 * Dashboard page capability-family routing logic tests (Phase D0.6) — pure
 * function tests. Routing is by capability, not role name: the operational
 * MyWorkDashboard is the fallback for every workflow user (incl. any new dynamic
 * executor role), and the two analytics/governance families are capability-gated.
 */
import { describe, it, expect } from 'vitest'

type ScreenPermissions = Record<string, string[]>

// Logic mirrored from dashboard.vue / index.vue: the dashboardFamily computed.
function resolveDashboardFamily(
  screenPermissions: ScreenPermissions,
): 'SystemAdminDashboard' | 'BankAdminDashboard' | 'MyWorkDashboard' {
  const can = (screen: string, capability: string): boolean =>
    screenPermissions[screen]?.includes(capability) ?? false

  if (can('system_dashboard', 'VIEW')) return 'SystemAdminDashboard'
  if (can('bank_analytics', 'VIEW')) return 'BankAdminDashboard'
  return 'MyWorkDashboard'
}

describe('Dashboard page — capability-family routing', () => {
  it('routes a user with system_dashboard.view to SystemAdminDashboard', () => {
    expect(resolveDashboardFamily({ system_dashboard: ['VIEW', 'MANAGE'] })).toBe(
      'SystemAdminDashboard',
    )
  })

  it('routes a user with bank_analytics.view to BankAdminDashboard', () => {
    expect(resolveDashboardFamily({ bank_analytics: ['VIEW'] })).toBe('BankAdminDashboard')
  })

  it('routes a workflow user (no analytics capability) to MyWorkDashboard', () => {
    // A typical executor holds only operational capabilities like `requests`.
    expect(resolveDashboardFamily({ requests: ['VIEW', 'UPDATE'] })).toBe('MyWorkDashboard')
  })

  it('routes a brand-new dynamic role with no analytics capability to MyWorkDashboard', () => {
    // The defining property: a new role gains its dashboard through capabilities,
    // with no frontend change. Absent analytics capabilities → operational family.
    expect(resolveDashboardFamily({})).toBe('MyWorkDashboard')
  })

  it('prefers system governance over bank analytics when both are held', () => {
    expect(resolveDashboardFamily({ system_dashboard: ['VIEW'], bank_analytics: ['VIEW'] })).toBe(
      'SystemAdminDashboard',
    )
  })

  it('does not route by role name — capability absence alone decides the family', () => {
    // No screen capabilities at all still resolves to the operational dashboard,
    // never a role-specific component.
    expect(resolveDashboardFamily({})).toBe('MyWorkDashboard')
  })
})
