import { describe, it, expect } from 'vitest'
import { UserRole, VoteType } from '../../../types/enums'

describe('UserRole', () => {
  const EXPECTED_ROLES = [
    'DATA_ENTRY',
    'BANK_REVIEWER',
    'BANK_ADMIN',
    'SWIFT_OFFICER',
    'SUPPORT_COMMITTEE',
    'EXECUTIVE_MEMBER',
    'COMMITTEE_DIRECTOR',
    'CBY_ADMIN',
  ]

  it('has exactly 8 canonical role values', () => {
    expect(Object.values(UserRole)).toHaveLength(8)
  })

  it.each(EXPECTED_ROLES)('defines %s', (role) => {
    expect(Object.values(UserRole)).toContain(role)
  })

  it('enum values match their keys (no aliasing)', () => {
    for (const [key, value] of Object.entries(UserRole)) {
      expect(value).toBe(key)
    }
  })
})

describe('VoteType', () => {
  it('defines all 4 vote types including AUTO_ABSTAIN_TIMEOUT', () => {
    expect(Object.values(VoteType)).toContain('APPROVE')
    expect(Object.values(VoteType)).toContain('REJECT')
    expect(Object.values(VoteType)).toContain('ABSTAIN')
    expect(Object.values(VoteType)).toContain('AUTO_ABSTAIN_TIMEOUT')
  })
})
