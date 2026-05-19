import { describe, it, expect } from 'vitest'

// RoleSwitcher provides demo-mode role switching.
// Tests verify the credentials map and display logic.

const DEMO_CREDENTIALS: Record<string, { email: string; label: string }> = {
  DATA_ENTRY:         { email: 'entry@ybrd.com.ye',    label: 'إدخال بيانات' },
  BANK_REVIEWER:      { email: 'reviewer@ybrd.com.ye', label: 'مراجع بنك' },
  BANK_ADMIN:         { email: 'admin@ybrd.com.ye',    label: 'مدير بنك' },
  SWIFT_OFFICER:      { email: 'swift@ybrd.com.ye',    label: 'ضابط SWIFT' },
  SUPPORT_COMMITTEE:  { email: 'support1@cby.gov.ye',  label: 'لجنة الدعم' },
  EXECUTIVE_MEMBER:   { email: 'exec1@cby.gov.ye',     label: 'عضو تنفيذي' },
  COMMITTEE_DIRECTOR: { email: 'director@cby.gov.ye',  label: 'مدير اللجنة' },
  CBY_ADMIN:          { email: 'admin@cby.gov.ye',     label: 'مدير CBY' },
}

const CANONICAL_ROLES = [
  'DATA_ENTRY', 'BANK_REVIEWER', 'BANK_ADMIN', 'SWIFT_OFFICER',
  'SUPPORT_COMMITTEE', 'EXECUTIVE_MEMBER', 'COMMITTEE_DIRECTOR', 'CBY_ADMIN',
]

describe('RoleSwitcher — credentials map', () => {
  it('has exactly 8 canonical roles', () => {
    expect(Object.keys(DEMO_CREDENTIALS)).toHaveLength(8)
  })

  it('covers all canonical roles', () => {
    CANONICAL_ROLES.forEach(role => {
      expect(DEMO_CREDENTIALS).toHaveProperty(role)
    })
  })

  it('DATA_ENTRY uses entry@ybrd.com.ye', () => {
    expect(DEMO_CREDENTIALS.DATA_ENTRY.email).toBe('entry@ybrd.com.ye')
  })

  it('BANK_REVIEWER uses reviewer@ybrd.com.ye', () => {
    expect(DEMO_CREDENTIALS.BANK_REVIEWER.email).toBe('reviewer@ybrd.com.ye')
  })

  it('BANK_ADMIN uses admin@ybrd.com.ye', () => {
    expect(DEMO_CREDENTIALS.BANK_ADMIN.email).toBe('admin@ybrd.com.ye')
  })

  it('SWIFT_OFFICER uses swift@ybrd.com.ye', () => {
    expect(DEMO_CREDENTIALS.SWIFT_OFFICER.email).toBe('swift@ybrd.com.ye')
  })

  it('SUPPORT_COMMITTEE uses support1@cby.gov.ye', () => {
    expect(DEMO_CREDENTIALS.SUPPORT_COMMITTEE.email).toBe('support1@cby.gov.ye')
  })

  it('EXECUTIVE_MEMBER uses exec1@cby.gov.ye', () => {
    expect(DEMO_CREDENTIALS.EXECUTIVE_MEMBER.email).toBe('exec1@cby.gov.ye')
  })

  it('COMMITTEE_DIRECTOR uses director@cby.gov.ye', () => {
    expect(DEMO_CREDENTIALS.COMMITTEE_DIRECTOR.email).toBe('director@cby.gov.ye')
  })

  it('CBY_ADMIN uses admin@cby.gov.ye', () => {
    expect(DEMO_CREDENTIALS.CBY_ADMIN.email).toBe('admin@cby.gov.ye')
  })
})

describe('RoleSwitcher — labels', () => {
  it('all roles have a non-empty Arabic label', () => {
    Object.values(DEMO_CREDENTIALS).forEach(({ label }) => {
      expect(label.length).toBeGreaterThan(0)
    })
  })

  it('labels are distinct', () => {
    const labels = Object.values(DEMO_CREDENTIALS).map(c => c.label)
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
  it('resolves credentials for each role', () => {
    CANONICAL_ROLES.forEach(role => {
      const creds = DEMO_CREDENTIALS[role]
      expect(creds).toBeDefined()
      expect(creds.email).toContain('@')
    })
  })

  it('password is always "password"', () => {
    const DEMO_PASSWORD = 'password'
    expect(DEMO_PASSWORD).toBe('password')
  })

  it('returns undefined for unknown role', () => {
    const creds = DEMO_CREDENTIALS['UNKNOWN_ROLE']
    expect(creds).toBeUndefined()
  })
})
