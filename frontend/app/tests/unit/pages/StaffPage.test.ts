/**
 * staff.vue page logic tests — pure function tests mirroring page behaviour.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'
import type { User } from '../../../types/models'

// ── Staff fixture factory ──────────────────────────────────────────────────────

function makeStaff(overrides: Partial<User> = {}): User {
  return {
    id: 1,
    name: 'أحمد السالمي',
    email: 'ahmed@bank.ye',
    role: UserRole.DATA_ENTRY,
    role_label: 'إدخال البيانات',
    bank_id: 1,
    bank_name_ar: 'بنك عدن',
    bank_name_en: 'Aden Bank',
    is_active: true,
    ...overrides,
  }
}

// ── formatJoinDate (mirrors staff.vue) ───────────────────────────────────────

function formatJoinDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ar-YE', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── isEmpty logic ─────────────────────────────────────────────────────────────

function isEmptyState(loading: boolean, error: string | null, staff: User[]): boolean {
  return !loading && !error && staff.length === 0
}

// ── Deactivation payload builder ──────────────────────────────────────────────

function buildDeactivatePayload(member: User, bankId: number | null) {
  return {
    name: member.name,
    email: member.email,
    role: member.role,
    bank_id: bankId,
    is_active: false,
  }
}

// ── Create payload builder ────────────────────────────────────────────────────

function buildCreatePayload(data: {
  name: string
  email: string
  role: UserRole
  password: string
}, bankId: number | null) {
  return {
    name: data.name,
    email: data.email,
    password: data.password,
    role: data.role,
    bank_id: bankId,
    is_active: true,
  }
}

// ── Page guard ────────────────────────────────────────────────────────────────

const PAGE_REQUIRED_ROLES = [UserRole.BANK_ADMIN]

function canAccessStaffPage(role: UserRole): boolean {
  return PAGE_REQUIRED_ROLES.includes(role)
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('staff.vue — page guard', () => {
  it('BANK_ADMIN can access staff page', () => {
    expect(canAccessStaffPage(UserRole.BANK_ADMIN)).toBe(true)
  })

  it('DATA_ENTRY cannot access staff page', () => {
    expect(canAccessStaffPage(UserRole.DATA_ENTRY)).toBe(false)
  })

  it('CBY_ADMIN cannot access staff page (has /users instead)', () => {
    expect(canAccessStaffPage(UserRole.CBY_ADMIN)).toBe(false)
  })

  it('BANK_REVIEWER cannot access staff page', () => {
    expect(canAccessStaffPage(UserRole.BANK_REVIEWER)).toBe(false)
  })
})

describe('staff.vue — empty state logic', () => {
  it('shows empty state when not loading, no error, no staff', () => {
    expect(isEmptyState(false, null, [])).toBe(true)
  })

  it('does NOT show empty state while loading', () => {
    expect(isEmptyState(true, null, [])).toBe(false)
  })

  it('does NOT show empty state when error is present', () => {
    expect(isEmptyState(false, 'تعذّر التحميل', [])).toBe(false)
  })

  it('does NOT show empty state when staff are loaded', () => {
    expect(isEmptyState(false, null, [makeStaff()])).toBe(false)
  })
})

describe('staff.vue — formatJoinDate', () => {
  it('returns "—" for null date', () => {
    expect(formatJoinDate(null)).toBe('—')
  })

  it('returns "—" for undefined date', () => {
    expect(formatJoinDate(undefined)).toBe('—')
  })

  it('formats a valid ISO date string', () => {
    const result = formatJoinDate('2025-01-15T00:00:00.000000Z')
    expect(typeof result).toBe('string')
    expect(result).not.toBe('—')
    expect(result.length).toBeGreaterThan(0)
  })
})

describe('staff.vue — deactivation payload', () => {
  it('builds deactivation payload with is_active=false', () => {
    const member = makeStaff({ id: 3, name: 'سالم', email: 'salem@bank.ye', is_active: true })
    const payload = buildDeactivatePayload(member, 1)
    expect(payload.is_active).toBe(false)
    expect(payload.name).toBe('سالم')
    expect(payload.email).toBe('salem@bank.ye')
    expect(payload.bank_id).toBe(1)
    expect(payload.role).toBe(UserRole.DATA_ENTRY)
  })

  it('preserves member role and email in deactivation payload', () => {
    const member = makeStaff({ role: UserRole.BANK_REVIEWER })
    const payload = buildDeactivatePayload(member, 2)
    expect(payload.role).toBe(UserRole.BANK_REVIEWER)
  })
})

describe('staff.vue — create payload', () => {
  it('builds create payload with bank_id from auth user', () => {
    const payload = buildCreatePayload(
      { name: 'موظف', email: 'emp@bank.ye', role: UserRole.DATA_ENTRY, password: 'password123' },
      7,
    )
    expect(payload.bank_id).toBe(7)
    expect(payload.is_active).toBe(true)
    expect(payload.role).toBe(UserRole.DATA_ENTRY)
    expect(payload.password).toBe('password123')
  })

  it('defaults is_active to true on create', () => {
    const payload = buildCreatePayload(
      { name: 'محمد', email: 'mohamad@bank.ye', role: UserRole.BANK_REVIEWER, password: 'securepass' },
      1,
    )
    expect(payload.is_active).toBe(true)
  })
})

describe('staff.vue — modal open/close state', () => {
  it('editingStaff is null when opening create modal', () => {
    let editingStaff: User | null = makeStaff()
    function openCreate() {
      editingStaff = null
    }
    openCreate()
    expect(editingStaff).toBeNull()
  })

  it('editingStaff is set when opening edit modal', () => {
    let editingStaff: User | null = null
    function openEdit(member: User) {
      editingStaff = member
    }
    const member = makeStaff({ id: 99 })
    openEdit(member)
    expect(editingStaff?.id).toBe(99)
  })
})

describe('staff.vue — deactivate confirm dialog', () => {
  it('stores the target member on open', () => {
    let deactivatingStaff: User | null = null
    let showDeactivateConfirm = false

    function openDeactivate(member: User) {
      deactivatingStaff = member
      showDeactivateConfirm = true
    }

    const target = makeStaff({ id: 42, name: 'علي' })
    openDeactivate(target)
    expect(deactivatingStaff?.id).toBe(42)
    expect(showDeactivateConfirm).toBe(true)
  })

  it('clears state on close', () => {
    let deactivatingStaff: User | null = makeStaff()
    let showDeactivateConfirm = true

    function closeDeactivate() {
      deactivatingStaff = null
      showDeactivateConfirm = false
    }

    closeDeactivate()
    expect(deactivatingStaff).toBeNull()
    expect(showDeactivateConfirm).toBe(false)
  })
})

describe('staff.vue — inline staff list update after actions', () => {
  it('prepends new staff member to list after create', () => {
    const staff: User[] = [makeStaff({ id: 1 }), makeStaff({ id: 2 })]
    const created = makeStaff({ id: 3, name: 'جديد' })
    staff.unshift(created)
    expect(staff[0]!.id).toBe(3)
    expect(staff.length).toBe(3)
  })

  it('updates existing staff member in list after edit', () => {
    const staff: User[] = [makeStaff({ id: 1, name: 'قديم' }), makeStaff({ id: 2 })]
    const updated = { ...staff[0]!, name: 'محدّث' }
    const idx = staff.findIndex(s => s.id === updated.id)
    if (idx !== -1) staff[idx] = updated
    expect(staff[0]!.name).toBe('محدّث')
  })

  it('updates is_active to false after deactivation', () => {
    const staff: User[] = [makeStaff({ id: 1, is_active: true })]
    const updated = { ...staff[0]!, is_active: false }
    const idx = staff.findIndex(s => s.id === updated.id)
    if (idx !== -1) staff[idx] = updated
    expect(staff[0]!.is_active).toBe(false)
  })
})
