/**
 * Dashboard page capability-family routing logic tests (Phase D0.6) — pure
 * function tests. Routing is by capability, not role name: the operational
 * MyWorkDashboard is the fallback for every workflow user (incl. any new dynamic
 * executor role), and the two analytics/governance families are capability-gated.
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

const dashboardSources = ['dashboard.vue', 'index.vue'].map((page) =>
  readFileSync(resolve(process.cwd(), `app/pages/${page}`), 'utf8'),
)

type ScreenPermissions = Record<string, string[]>

// Logic mirrored from dashboard.vue / index.vue: the dashboardFamily computed.
function resolveDashboardFamily(
  screenPermissions: ScreenPermissions,
): 'SystemAdminDashboard' | 'BankAdminDashboard' | 'MyWorkDashboard' {
  const can = (screen: string, capability: string): boolean =>
    screenPermissions[screen]?.includes(capability) ?? false

  if (can('system_dashboard', 'VIEW')) return 'SystemAdminDashboard'
  if (can('org_analytics', 'VIEW')) return 'BankAdminDashboard'
  return 'MyWorkDashboard'
}

describe('Dashboard page — capability-family routing', () => {
  it('uses org_analytics in both production dashboard selectors', () => {
    for (const source of dashboardSources) {
      expect(source).toContain("can('org_analytics', 'VIEW')")
      expect(source).not.toContain("can('bank_analytics', 'VIEW')")
    }
  })

  it('routes a user with the system_dashboard screen + VIEW capability to SystemAdminDashboard', () => {
    expect(resolveDashboardFamily({ system_dashboard: ['VIEW', 'MANAGE'] })).toBe(
      'SystemAdminDashboard',
    )
  })

  it('routes a user with the org_analytics screen + VIEW capability to BankAdminDashboard', () => {
    expect(resolveDashboardFamily({ org_analytics: ['VIEW'] })).toBe('BankAdminDashboard')
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

  it('prefers system governance over organization analytics when both are held', () => {
    expect(resolveDashboardFamily({ system_dashboard: ['VIEW'], org_analytics: ['VIEW'] })).toBe(
      'SystemAdminDashboard',
    )
  })

  it('does not route by role name — capability absence alone decides the family', () => {
    // No screen capabilities at all still resolves to the operational dashboard,
    // never a role-specific component.
    expect(resolveDashboardFamily({})).toBe('MyWorkDashboard')
  })
})
