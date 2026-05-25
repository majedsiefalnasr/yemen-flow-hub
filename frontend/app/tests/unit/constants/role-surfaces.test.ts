import { describe, expect, it } from 'vitest'
import { NAV_SURFACE_ROUTES, ROLE_SURFACE_MATRIX, roleHasSurface, rolesForSurface } from '../../../constants/role-surfaces'
import { UserRole } from '../../../types/enums'

describe('ROLE_SURFACE_MATRIX', () => {
  it('covers all eight production roles', () => {
    const roles = Object.values(UserRole)
    expect(Object.keys(ROLE_SURFACE_MATRIX)).toHaveLength(roles.length)
    for (const role of roles) {
      expect(ROLE_SURFACE_MATRIX[role]).toBeDefined()
    }
  })

  it('enforces key forbidden surfaces per role', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.swift_upload')).toBe(false)
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.staff')).toBe(false)
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.voting.cast')).toBe(false)
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.support_claim')).toBe(false)
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'action.voting.cast')).toBe(false)
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.voting.close_finalize')).toBe(false)
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.voting.close_finalize')).toBe(true)
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.external_fx_confirmation.complete')).toBe(false)
  })

  it('maps navigation routes from surfaces consistently', () => {
    expect(NAV_SURFACE_ROUTES['nav.dashboard']).toBe('/dashboard')
    expect(NAV_SURFACE_ROUTES['nav.requests']).toBe('/requests')
    expect(NAV_SURFACE_ROUTES['nav.external_fx_confirmation']).toBe('/customs')
  })

  it('limits external-FX navigation to COMMITTEE_DIRECTOR', () => {
    expect(rolesForSurface('nav.external_fx_confirmation')).toEqual([UserRole.COMMITTEE_DIRECTOR])
  })
})
