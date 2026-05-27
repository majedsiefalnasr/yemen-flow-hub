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

// ── DATA_ENTRY surface matrix — spec-exact coverage ───────────────────────────

describe('ROLE_SURFACE_MATRIX — DATA_ENTRY allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.requests')).toBe(true)
  })

  it('allows new request creation', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.new_request')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.settings')).toBe(true)
  })
})

describe('ROLE_SURFACE_MATRIX — DATA_ENTRY forbidden surfaces (plan §1)', () => {
  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.support_claim')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.swift_upload')).toBe(false)
  })

  it('cannot access FX request upload action', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.fx_request_upload')).toBe(false)
  })

  it('cannot cast a vote', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.voting.cast')).toBe(false)
  })

  it('cannot close or finalize voting', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot complete external FX confirmation', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.external_fx_confirmation.complete')).toBe(false)
  })

  it('cannot download external FX confirmation document', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.external_fx_confirmation.download')).toBe(false)
  })

  it('cannot see external FX confirmation nav route (/customs)', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.external_fx_confirmation')).toBe(false)
  })

  it('cannot see audit nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.audit')).toBe(false)
  })

  it('cannot see reports nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.reports')).toBe(false)
  })

  it('cannot see merchants nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.merchants')).toBe(false)
  })

  it('cannot see staff nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.staff')).toBe(false)
  })

  it('cannot see admin entities nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.admin.entities')).toBe(false)
  })

  it('cannot see admin CBY staff nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.admin.cby_staff')).toBe(false)
  })

  it('cannot see admin workflow docs nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.admin.workflow_docs')).toBe(false)
  })

  it('cannot see admin roles nav', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'nav.admin.roles')).toBe(false)
  })
})

// ── DATA_ENTRY is the only role that can create new requests ─────────────────

describe('nav.new_request surface — DATA_ENTRY exclusive', () => {
  it('only DATA_ENTRY has nav.new_request allowed', () => {
    const allowedRoles = rolesForSurface('nav.new_request')
    expect(allowedRoles).toEqual([UserRole.DATA_ENTRY])
  })

  it('BANK_REVIEWER cannot create requests', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.new_request')).toBe(false)
  })

  it('BANK_ADMIN cannot create requests via the nav surface', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.new_request')).toBe(false)
  })

  it('CBY_ADMIN cannot create requests', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.new_request')).toBe(false)
  })
})
