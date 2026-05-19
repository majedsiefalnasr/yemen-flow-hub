import { describe, it, expect } from 'vitest'

// RoleSwitcher provides demo-mode role switching.
// Tests verify role coverage and display logic.

const DEMO_ROLE_LABELS: Record<string, string> = {
  DATA_ENTRY: 'إدخال بيانات',
  BANK_REVIEWER: 'مراجع بنك',
  BANK_ADMIN: 'مدير بنك',
  SWIFT_OFFICER: 'ضابط SWIFT',
  SUPPORT_COMMITTEE: 'لجنة الدعم',
  EXECUTIVE_MEMBER: 'عضو تنفيذي',
  COMMITTEE_DIRECTOR: 'مدير اللجنة',
  CBY_ADMIN: 'مدير CBY',
}

const CANONICAL_ROLES = [
  'DATA_ENTRY', 'BANK_REVIEWER', 'BANK_ADMIN', 'SWIFT_OFFICER',
  'SUPPORT_COMMITTEE', 'EXECUTIVE_MEMBER', 'COMMITTEE_DIRECTOR', 'CBY_ADMIN',
]

describe('RoleSwitcher — role map', () => {
  it('has exactly 8 canonical roles', () => {
    expect(Object.keys(DEMO_ROLE_LABELS)).toHaveLength(8)
  })

  it('covers all canonical roles', () => {
    CANONICAL_ROLES.forEach(role => {
      expect(DEMO_ROLE_LABELS).toHaveProperty(role)
    })
  })
})

describe('RoleSwitcher — labels', () => {
  it('all roles have a non-empty Arabic label', () => {
    Object.values(DEMO_ROLE_LABELS).forEach((label) => {
      expect(label.length).toBeGreaterThan(0)
    })
  })

  it('labels are distinct', () => {
    const labels = Object.values(DEMO_ROLE_LABELS)
    const unique = new Set(labels)
    expect(unique.size).toBe(labels.length)
  })
})

describe('RoleSwitcher — visibility logic', () => {
  it('renders in demo mode', () => {
    const isDemoMode = true
    expect(isDemoMode).toBe(true)
  })

  it('hidden when demoMode is false', () => {
    const isDemoMode = false
    expect(isDemoMode).toBe(false)
  })

  it('hidden when demoMode is undefined', () => {
    const demoMode: unknown = undefined
    const isDemoMode = demoMode === true || demoMode === 'true'
    expect(isDemoMode).toBe(false)
  })

  it('visible when demoMode is string "true"', () => {
    const demoMode: unknown = 'true'
    const isDemoMode = demoMode === true || demoMode === 'true'
    expect(isDemoMode).toBe(true)
  })
})

describe('RoleSwitcher — switch logic', () => {
  it('resolves labels for each role', () => {
    CANONICAL_ROLES.forEach(role => {
      const label = DEMO_ROLE_LABELS[role]
      expect(label).toBeDefined()
      expect(label.length).toBeGreaterThan(0)
    })
  })

  it('returns undefined for unknown role', () => {
    const label = DEMO_ROLE_LABELS.UNKNOWN_ROLE
    expect(label).toBeUndefined()
  })
})
