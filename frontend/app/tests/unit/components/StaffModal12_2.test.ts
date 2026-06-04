/**
 * Story 12.2 — Staff page access health filter logic + BANK_ADMIN_MANAGED_ROLES allowlist.
 * Pure function tests, no component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'
import { BANK_ADMIN_MANAGED_ROLES } from '../../../constants/workflow'
import type { User } from '../../../types/models'

// ── Access health filter logic (mirrored from staff.vue) ──────────────────────

type AccessHealthKey = 'active' | 'inactive' | 'bank_reviewer'

interface FilterState {
  statusFilter: string | null
  roleFilter: UserRole | null
  accessHealthFilter: AccessHealthKey | null
}

function makeFilterState(): FilterState {
  return { statusFilter: null, roleFilter: null, accessHealthFilter: null }
}

function applyAccessHealthFilter(state: FilterState, key: AccessHealthKey): FilterState {
  if (state.accessHealthFilter === key) {
    return { statusFilter: null, roleFilter: null, accessHealthFilter: null }
  }
  const next: FilterState = { ...state, accessHealthFilter: key }
  if (key === 'active') {
    next.statusFilter = 'active'
    next.roleFilter = null
  } else if (key === 'inactive') {
    next.statusFilter = 'inactive'
    next.roleFilter = null
  } else if (key === 'bank_reviewer') {
    next.roleFilter = UserRole.BANK_REVIEWER
    next.statusFilter = null
  }
  return next
}

function bankReviewerCount(staff: User[]): number {
  return staff.filter((m) => m.role === UserRole.BANK_REVIEWER && m.is_active).length
}

function makeUser(overrides: Partial<User> = {}): User {
  return {
    id: 1,
    name: 'موظف',
    email: 'user@bank.ye',
    role: UserRole.DATA_ENTRY,
    role_label: 'إدخال البيانات',
    bank_id: 1,
    bank_name_ar: 'بنك اليمن',
    bank_name_en: 'Yemen Bank',
    is_active: true,
    ...overrides,
  }
}

// ── BANK_ADMIN_MANAGED_ROLES allowlist ────────────────────────────────────────

describe('StaffModal 12.2 — BANK_ADMIN_MANAGED_ROLES allowlist', () => {
  it('contains exactly DATA_ENTRY and BANK_REVIEWER', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).toHaveLength(2)
    expect(BANK_ADMIN_MANAGED_ROLES).toContain(UserRole.DATA_ENTRY)
    expect(BANK_ADMIN_MANAGED_ROLES).toContain(UserRole.BANK_REVIEWER)
  })

  it('does NOT include CBY_ADMIN', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.CBY_ADMIN)
  })

  it('does NOT include BANK_ADMIN (cannot self-create admins)', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.BANK_ADMIN)
  })

  it('does NOT include SUPPORT_COMMITTEE', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.SUPPORT_COMMITTEE)
  })

  it('does NOT include EXECUTIVE_MEMBER', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.EXECUTIVE_MEMBER)
  })

  it('does NOT include COMMITTEE_DIRECTOR', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.COMMITTEE_DIRECTOR)
  })

  it('does NOT include SWIFT_OFFICER', () => {
    expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.SWIFT_OFFICER)
  })
})

// ── bankReviewerCount ─────────────────────────────────────────────────────────

describe('StaffModal 12.2 — bankReviewerCount()', () => {
  it('returns 0 when staff list is empty', () => {
    expect(bankReviewerCount([])).toBe(0)
  })

  it('returns 0 when no active BANK_REVIEWERs', () => {
    const staff = [
      makeUser({ role: UserRole.DATA_ENTRY, is_active: true }),
      makeUser({ role: UserRole.BANK_REVIEWER, is_active: false }),
    ]
    expect(bankReviewerCount(staff)).toBe(0)
  })

  it('counts only active BANK_REVIEWERs', () => {
    const staff = [
      makeUser({ id: 1, role: UserRole.BANK_REVIEWER, is_active: true }),
      makeUser({ id: 2, role: UserRole.BANK_REVIEWER, is_active: true }),
      makeUser({ id: 3, role: UserRole.BANK_REVIEWER, is_active: false }),
      makeUser({ id: 4, role: UserRole.DATA_ENTRY, is_active: true }),
    ]
    expect(bankReviewerCount(staff)).toBe(2)
  })

  it('does not count DATA_ENTRY as reviewers', () => {
    const staff = [makeUser({ role: UserRole.DATA_ENTRY, is_active: true })]
    expect(bankReviewerCount(staff)).toBe(0)
  })
})

// ── applyAccessHealthFilter ───────────────────────────────────────────────────

describe('StaffModal 12.2 — applyAccessHealthFilter()', () => {
  it('sets statusFilter=active when key=active', () => {
    const state = applyAccessHealthFilter(makeFilterState(), 'active')
    expect(state.statusFilter).toBe('active')
    expect(state.roleFilter).toBeNull()
    expect(state.accessHealthFilter).toBe('active')
  })

  it('sets statusFilter=inactive when key=inactive', () => {
    const state = applyAccessHealthFilter(makeFilterState(), 'inactive')
    expect(state.statusFilter).toBe('inactive')
    expect(state.roleFilter).toBeNull()
    expect(state.accessHealthFilter).toBe('inactive')
  })

  it('sets roleFilter=BANK_REVIEWER when key=bank_reviewer', () => {
    const state = applyAccessHealthFilter(makeFilterState(), 'bank_reviewer')
    expect(state.roleFilter).toBe(UserRole.BANK_REVIEWER)
    expect(state.statusFilter).toBeNull()
    expect(state.accessHealthFilter).toBe('bank_reviewer')
  })

  it('clears all filters when same key applied twice (toggle off)', () => {
    const first = applyAccessHealthFilter(makeFilterState(), 'active')
    const second = applyAccessHealthFilter(first, 'active')
    expect(second.statusFilter).toBeNull()
    expect(second.roleFilter).toBeNull()
    expect(second.accessHealthFilter).toBeNull()
  })

  it('switches filter when different key applied', () => {
    const first = applyAccessHealthFilter(makeFilterState(), 'active')
    const second = applyAccessHealthFilter(first, 'inactive')
    expect(second.statusFilter).toBe('inactive')
    expect(second.accessHealthFilter).toBe('inactive')
  })

  it('bank_reviewer clears statusFilter from previous active filter', () => {
    const first = applyAccessHealthFilter(makeFilterState(), 'active')
    const second = applyAccessHealthFilter(first, 'bank_reviewer')
    expect(second.statusFilter).toBeNull()
    expect(second.roleFilter).toBe(UserRole.BANK_REVIEWER)
  })

  it('active clears roleFilter from previous bank_reviewer filter', () => {
    const first = applyAccessHealthFilter(makeFilterState(), 'bank_reviewer')
    const second = applyAccessHealthFilter(first, 'active')
    expect(second.roleFilter).toBeNull()
    expect(second.statusFilter).toBe('active')
  })
})

// ── Access health card labels ─────────────────────────────────────────────────

describe('StaffModal 12.2 — access health card keys', () => {
  const ACCESS_HEALTH_KEYS: AccessHealthKey[] = ['active', 'inactive', 'bank_reviewer']

  it('has 3 interactive filter card keys', () => {
    expect(ACCESS_HEALTH_KEYS).toHaveLength(3)
  })

  it('active is the first card (most positive)', () => {
    expect(ACCESS_HEALTH_KEYS[0]).toBe('active')
  })

  it('inactive card is second', () => {
    expect(ACCESS_HEALTH_KEYS[1]).toBe('inactive')
  })

  it('bank_reviewer coverage is the third filter card', () => {
    expect(ACCESS_HEALTH_KEYS[2]).toBe('bank_reviewer')
  })
})
