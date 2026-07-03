import { describe, it, expect } from 'vitest'
import { NAV_ITEMS } from '../../../constants/workflow'
import { UserRole } from '../../../types/enums'

function navItemsForRole(role: UserRole) {
  return NAV_ITEMS.filter((item) => item.roles.includes(role))
}

describe('NAV_ITEMS role filtering', () => {
  it('all roles see dashboard and workflows', () => {
    for (const role of Object.values(UserRole)) {
      const routes = navItemsForRole(role).map((i) => i.route)
      expect(routes).toContain('/dashboard')
      expect(routes).toContain('/workflows')
    }
  })

  it('DATA_ENTRY sees new request form', () => {
    const routes = navItemsForRole(UserRole.DATA_ENTRY).map((i) => i.route)
    expect(routes).toContain('/workflows/new')
  })

  it('BANK_REVIEWER does not see new request form', () => {
    const routes = navItemsForRole(UserRole.BANK_REVIEWER).map((i) => i.route)
    expect(routes).not.toContain('/workflows/new')
  })

  it('COMMITTEE_DIRECTOR sees customs', () => {
    const routes = navItemsForRole(UserRole.COMMITTEE_DIRECTOR).map((i) => i.route)
    expect(routes).toContain('/customs')
  })

  it('DATA_ENTRY does not see customs', () => {
    const routes = navItemsForRole(UserRole.DATA_ENTRY).map((i) => i.route)
    expect(routes).not.toContain('/customs')
  })

  it('CBY_ADMIN sees all admin routes', () => {
    const routes = navItemsForRole(UserRole.CBY_ADMIN).map((i) => i.route)
    expect(routes).toContain('/admin/staff')
    expect(routes).toContain('/admin/banks')
    expect(routes).toContain('/admin/workflows')
    expect(routes).toContain('/admin/roles')
    expect(routes).toContain('/audit')
    expect(routes).toContain('/settings')
    expect(routes).toContain('/merchants')
  })

  it('CBY_ADMIN does not see external-FX completion navigation', () => {
    const routes = navItemsForRole(UserRole.CBY_ADMIN).map((i) => i.route)
    expect(routes).not.toContain('/customs')
  })

  it('DATA_ENTRY does not see admin routes', () => {
    const routes = navItemsForRole(UserRole.DATA_ENTRY).map((i) => i.route)
    expect(routes).not.toContain('/admin/banks')
    expect(routes).not.toContain('/admin/staff')
    expect(routes).not.toContain('/audit')
    expect(routes).not.toContain('/merchants')
  })

  it('SUPPORT_COMMITTEE does not see admin routes', () => {
    const routes = navItemsForRole(UserRole.SUPPORT_COMMITTEE).map((i) => i.route)
    expect(routes).not.toContain('/admin/banks')
    expect(routes).not.toContain('/audit')
  })

  it('BANK_ADMIN sees merchants, staff, reports, notifications', () => {
    const routes = navItemsForRole(UserRole.BANK_ADMIN).map((i) => i.route)
    expect(routes).toContain('/dashboard')
    expect(routes).toContain('/workflows')
    expect(routes).toContain('/merchants')
    expect(routes).toContain('/staff')
    expect(routes).toContain('/reports')
    expect(routes).toContain('/notifications')
  })

  it('BANK_ADMIN does not see CBY admin routes', () => {
    const routes = navItemsForRole(UserRole.BANK_ADMIN).map((i) => i.route)
    expect(routes).not.toContain('/audit')
    expect(routes).not.toContain('/admin/staff')
    expect(routes).not.toContain('/admin/banks')
    expect(routes).not.toContain('/admin/roles')
  })

  it('no role sees /bank/users (removed — no production page)', () => {
    for (const role of Object.values(UserRole)) {
      const routes = navItemsForRole(role).map((i) => i.route)
      expect(routes).not.toContain('/bank/users')
    }
  })

  it('EXECUTIVE_MEMBER sees reports', () => {
    const routes = navItemsForRole(UserRole.EXECUTIVE_MEMBER).map((i) => i.route)
    expect(routes).toContain('/reports')
  })

  it('SUPPORT_COMMITTEE does not see reports', () => {
    const routes = navItemsForRole(UserRole.SUPPORT_COMMITTEE).map((i) => i.route)
    expect(routes).not.toContain('/reports')
  })

  it('DATA_ENTRY does not see reports', () => {
    const routes = navItemsForRole(UserRole.DATA_ENTRY).map((i) => i.route)
    expect(routes).not.toContain('/reports')
  })

  it('BANK_REVIEWER does not see reports', () => {
    const routes = navItemsForRole(UserRole.BANK_REVIEWER).map((i) => i.route)
    expect(routes).not.toContain('/reports')
  })

  it('all roles see notifications', () => {
    for (const role of Object.values(UserRole)) {
      const routes = navItemsForRole(role).map((i) => i.route)
      expect(routes).toContain('/notifications')
    }
  })
})
