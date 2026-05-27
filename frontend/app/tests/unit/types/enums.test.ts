import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole, VoteType } from '../../../types/enums'

describe('RequestStatus', () => {
  const EXPECTED_STATUSES = [
    'DRAFT',
    'DRAFT_REJECTED_INTERNAL',
    'BANK_RETURNED',
    'BANK_REJECTED',
    'SUPPORT_RETURNED',
    'SUBMITTED',
    'BANK_REVIEW',
    'BANK_APPROVED',
    'SUPPORT_REVIEW_PENDING',
    'SUPPORT_REVIEW_IN_PROGRESS',
    'SUPPORT_APPROVED',
    'SUPPORT_REJECTED',
    'WAITING_FOR_SWIFT',
    'SWIFT_UPLOADED',
    'WAITING_FOR_VOTING_OPEN',
    'EXECUTIVE_VOTING_OPEN',
    'EXECUTIVE_VOTING_CLOSED',
    'EXECUTIVE_APPROVED',
    'EXECUTIVE_REJECTED',
    'CUSTOMS_DECLARATION_ISSUED',
    'FX_CONFIRMATION_PENDING',
    'COMPLETED',
  ]

  it('has exactly 22 canonical status values', () => {
    const values = Object.values(RequestStatus)
    expect(values).toHaveLength(22)
  })

  it.each(EXPECTED_STATUSES)('defines %s', (status) => {
    expect(Object.values(RequestStatus)).toContain(status)
  })

  it('enum values match their keys (no aliasing)', () => {
    for (const [key, value] of Object.entries(RequestStatus)) {
      expect(value).toBe(key)
    }
  })
})

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
