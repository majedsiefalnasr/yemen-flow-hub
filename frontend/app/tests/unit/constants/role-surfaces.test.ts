import { describe, expect, it } from 'vitest'
import {
  NAV_SURFACE_ROUTES,
  ROLE_SURFACE_MATRIX,
  roleHasSurface,
  rolesForSurface,
} from '../../../constants/role-surfaces'
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
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.external_fx_confirmation.complete')).toBe(
      false,
    )
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
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.external_fx_confirmation.complete')).toBe(
      false,
    )
  })

  it('cannot download external FX confirmation document', () => {
    expect(roleHasSurface(UserRole.DATA_ENTRY, 'action.external_fx_confirmation.download')).toBe(
      false,
    )
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

// ── BANK_REVIEWER allowed surfaces ───────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — BANK_REVIEWER allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.requests')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.settings')).toBe(true)
  })
})

// ── BANK_REVIEWER forbidden surfaces ─────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — BANK_REVIEWER forbidden surfaces (plan §1)', () => {
  it('cannot create new requests', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.new_request')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'action.swift_upload')).toBe(false)
  })

  it('cannot cast a vote', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'action.voting.cast')).toBe(false)
  })

  it('cannot close or finalize voting', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot complete external FX confirmation', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'action.external_fx_confirmation.complete')).toBe(
      false,
    )
  })

  it('cannot see staff nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.staff')).toBe(false)
  })

  it('cannot see merchants nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.merchants')).toBe(false)
  })

  it('cannot see reports nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.reports')).toBe(false)
  })

  it('cannot see audit nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.audit')).toBe(false)
  })

  it('cannot see external FX confirmation nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.external_fx_confirmation')).toBe(false)
  })

  it('cannot see admin entities nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.admin.entities')).toBe(false)
  })

  it('cannot see admin CBY staff nav', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'nav.admin.cby_staff')).toBe(false)
  })

  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.BANK_REVIEWER, 'action.support_claim')).toBe(false)
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

// ── BANK_ADMIN allowed surfaces ───────────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — BANK_ADMIN allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.requests')).toBe(true)
  })

  it('allows staff nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.staff')).toBe(true)
  })

  it('allows merchants nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.merchants')).toBe(true)
  })

  it('allows reports nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.reports')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.settings')).toBe(true)
  })
})

// ── BANK_ADMIN forbidden surfaces ─────────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — BANK_ADMIN forbidden surfaces (plan §1)', () => {
  it('cannot cast a vote', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.voting.cast')).toBe(false)
  })

  it('cannot close or finalize voting', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.swift_upload')).toBe(false)
  })

  it('cannot complete external FX confirmation', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.external_fx_confirmation.complete')).toBe(
      false,
    )
  })

  it('cannot download external FX confirmation document', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.external_fx_confirmation.download')).toBe(
      false,
    )
  })

  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'action.support_claim')).toBe(false)
  })

  it('cannot see external FX confirmation nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.external_fx_confirmation')).toBe(false)
  })

  it('cannot see audit nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.audit')).toBe(false)
  })

  it('cannot see admin entities nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.admin.entities')).toBe(false)
  })

  it('cannot see admin CBY staff nav', () => {
    expect(roleHasSurface(UserRole.BANK_ADMIN, 'nav.admin.cby_staff')).toBe(false)
  })
})

// ── SWIFT_OFFICER allowed surfaces ────────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — SWIFT_OFFICER allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.requests')).toBe(true)
  })

  it('allows SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.swift_upload')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.settings')).toBe(true)
  })
})

// ── SWIFT_OFFICER forbidden surfaces ──────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — SWIFT_OFFICER forbidden surfaces (plan §1)', () => {
  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.support_claim')).toBe(false)
  })

  it('cannot cast a vote', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.voting.cast')).toBe(false)
  })

  it('cannot close or finalize voting', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot complete external FX confirmation', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.external_fx_confirmation.complete')).toBe(
      false,
    )
  })

  it('cannot create new requests', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.new_request')).toBe(false)
  })

  it('cannot see staff nav', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.staff')).toBe(false)
  })

  it('cannot see merchants nav', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.merchants')).toBe(false)
  })

  it('cannot see reports nav', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.reports')).toBe(false)
  })

  it('cannot see audit nav', () => {
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'nav.audit')).toBe(false)
  })
})

// ── SUPPORT_COMMITTEE allowed surfaces ────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — SUPPORT_COMMITTEE allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.requests')).toBe(true)
  })

  it('allows support claim action', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'action.support_claim')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.settings')).toBe(true)
  })
})

// ── SUPPORT_COMMITTEE forbidden surfaces ──────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — SUPPORT_COMMITTEE forbidden surfaces (plan §1)', () => {
  it('cannot cast a vote', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'action.voting.cast')).toBe(false)
  })

  it('cannot close or finalize voting', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'action.swift_upload')).toBe(false)
  })

  it('cannot complete external FX confirmation', () => {
    expect(
      roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'action.external_fx_confirmation.complete'),
    ).toBe(false)
  })

  it('cannot create new requests', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.new_request')).toBe(false)
  })

  it('cannot see staff nav', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.staff')).toBe(false)
  })

  it('cannot see merchants nav', () => {
    expect(roleHasSurface(UserRole.SUPPORT_COMMITTEE, 'nav.merchants')).toBe(false)
  })
})

// ── EXECUTIVE_MEMBER allowed surfaces ─────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — EXECUTIVE_MEMBER allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'nav.requests')).toBe(true)
  })

  it('allows voting cast action', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.voting.cast')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'nav.settings')).toBe(true)
  })
})

// ── EXECUTIVE_MEMBER forbidden surfaces ───────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — EXECUTIVE_MEMBER forbidden surfaces (plan §1)', () => {
  it('cannot close or finalize voting (Director-only)', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.swift_upload')).toBe(false)
  })

  it('cannot complete external FX confirmation', () => {
    expect(
      roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.external_fx_confirmation.complete'),
    ).toBe(false)
  })

  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.support_claim')).toBe(false)
  })

  it('cannot create new requests', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'nav.new_request')).toBe(false)
  })

  it('cannot see external FX confirmation nav', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'nav.external_fx_confirmation')).toBe(false)
  })
})

// ── COMMITTEE_DIRECTOR allowed surfaces ───────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — COMMITTEE_DIRECTOR allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'nav.requests')).toBe(true)
  })

  it('allows voting close/finalize action', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.voting.close_finalize')).toBe(true)
  })

  it('allows external FX confirmation nav', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'nav.external_fx_confirmation')).toBe(true)
  })

  it('allows external FX confirmation download', () => {
    expect(
      roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.external_fx_confirmation.download'),
    ).toBe(true)
  })
})

// ── COMMITTEE_DIRECTOR allowed action surfaces ────────────────────────────────

describe('ROLE_SURFACE_MATRIX — COMMITTEE_DIRECTOR action surfaces', () => {
  it('can complete external FX confirmation (Director is the sole actor for this surface)', () => {
    expect(
      roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.external_fx_confirmation.complete'),
    ).toBe(true)
  })
})

// ── COMMITTEE_DIRECTOR forbidden surfaces ─────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — COMMITTEE_DIRECTOR forbidden surfaces (plan §1)', () => {
  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.support_claim')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.swift_upload')).toBe(false)
  })

  it('cannot create new requests', () => {
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'nav.new_request')).toBe(false)
  })
})

// ── CBY_ADMIN allowed surfaces ────────────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — CBY_ADMIN allowed surfaces', () => {
  it('allows dashboard', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.dashboard')).toBe(true)
  })

  it('allows requests list', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.requests')).toBe(true)
  })

  it('allows audit nav', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.audit')).toBe(true)
  })

  it('allows reports nav', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.reports')).toBe(true)
  })

  it('allows admin entities nav', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.admin.entities')).toBe(true)
  })

  it('allows admin CBY staff nav', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.admin.cby_staff')).toBe(true)
  })

  it('allows notifications', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.notifications')).toBe(true)
  })

  it('allows settings', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.settings')).toBe(true)
  })
})

// ── CBY_ADMIN forbidden surfaces ──────────────────────────────────────────────

describe('ROLE_SURFACE_MATRIX — CBY_ADMIN forbidden surfaces (plan §1)', () => {
  it('cannot complete external FX confirmation (Director-only)', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.external_fx_confirmation.complete')).toBe(
      false,
    )
  })

  it('cannot cast a vote', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.voting.cast')).toBe(false)
  })

  it('cannot close or finalize voting', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.voting.close_finalize')).toBe(false)
  })

  it('cannot access SWIFT upload action', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.swift_upload')).toBe(false)
  })

  it('cannot access support claim action', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.support_claim')).toBe(false)
  })

  it('cannot create new requests', () => {
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'nav.new_request')).toBe(false)
  })
})
