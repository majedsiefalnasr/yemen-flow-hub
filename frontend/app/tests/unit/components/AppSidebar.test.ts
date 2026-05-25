import { describe, expect, it } from 'vitest'
import { NAV_ITEMS } from '../../../constants/workflow'
import { roleHasSurface } from '../../../constants/role-surfaces'
import { UserRole } from '../../../types/enums'

function visibleRoutes(role: UserRole): string[] {
  return NAV_ITEMS.filter(item => item.roles.includes(role)).map(item => item.route)
}

describe('AppSidebar navigation contract', () => {
  it('uses the active canonical sidebar source (frontend/app/components/AppSidebar.vue contract)', () => {
    const routes = visibleRoutes(UserRole.CBY_ADMIN)
    expect(routes).toContain('/dashboard')
    expect(routes).toContain('/requests')
  })

  it('does not expose director external-FX nav for CBY_ADMIN', () => {
    const cbyRoutes = visibleRoutes(UserRole.CBY_ADMIN)
    expect(cbyRoutes).not.toContain('/customs')
  })

  it('exposes external-FX nav for COMMITTEE_DIRECTOR only', () => {
    const directorRoutes = visibleRoutes(UserRole.COMMITTEE_DIRECTOR)
    expect(directorRoutes).toContain('/customs')

    for (const role of Object.values(UserRole)) {
      if (role === UserRole.COMMITTEE_DIRECTOR) continue
      expect(visibleRoutes(role)).not.toContain('/customs')
    }
  })

  it('hides reports from DATA_ENTRY and BANK_REVIEWER', () => {
    expect(visibleRoutes(UserRole.DATA_ENTRY)).not.toContain('/reports')
    expect(visibleRoutes(UserRole.BANK_REVIEWER)).not.toContain('/reports')
  })

  it('keeps role-surface contract aligned for key forbidden surfaces', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.voting.close_finalize')).toBe(false)
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.voting.close_finalize')).toBe(true)
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.swift_upload')).toBe(true)
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.swift_upload')).toBe(false)
  })
})
