import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'
import {
  ROLE_BUCKETS,
  CBY_BANK_FILTER_ROLES,
  CURRENCY_OPTIONS,
  STATUS_PROGRESS,
  STATUS_LABELS,
  DATA_ENTRY_STATUS_LABELS,
} from '../../../constants/workflow'

// ── STATUS_PROGRESS ───────────────────────────────────────────────────────────

describe('STATUS_PROGRESS', () => {
  it('covers all 20 RequestStatus values', () => {
    const statuses = Object.values(RequestStatus)
    expect(Object.keys(STATUS_PROGRESS)).toHaveLength(statuses.length)
    for (const s of statuses) {
      expect(STATUS_PROGRESS[s], `missing progress for ${s}`).toBeDefined()
    }
  })

  it('BANK_RETURNED returns 18%', () => {
    expect(STATUS_PROGRESS[RequestStatus.BANK_RETURNED]).toBe(18)
  })

  it('DRAFT returns 5%', () => {
    expect(STATUS_PROGRESS[RequestStatus.DRAFT]).toBe(5)
  })

  it('COMPLETED returns 100%', () => {
    expect(STATUS_PROGRESS[RequestStatus.COMPLETED]).toBe(100)
  })

  it('all values are between 0 and 100 inclusive', () => {
    for (const [status, pct] of Object.entries(STATUS_PROGRESS)) {
      expect(pct, `${status} out of range`).toBeGreaterThanOrEqual(0)
      expect(pct, `${status} out of range`).toBeLessThanOrEqual(100)
    }
  })

  it('progress increases as workflow advances', () => {
    const ordered = [
      RequestStatus.DRAFT,
      RequestStatus.SUBMITTED,
      RequestStatus.BANK_APPROVED,
      RequestStatus.SUPPORT_APPROVED,
      RequestStatus.SWIFT_UPLOADED,
      RequestStatus.EXECUTIVE_APPROVED,
      RequestStatus.COMPLETED,
    ]
    for (let i = 1; i < ordered.length; i++) {
      expect(STATUS_PROGRESS[ordered[i]!]).toBeGreaterThan(STATUS_PROGRESS[ordered[i - 1]!]!)
    }
  })
})

// ── ROLE_BUCKETS ──────────────────────────────────────────────────────────────

describe('ROLE_BUCKETS', () => {
  it('is defined for all 8 roles', () => {
    const roles = Object.values(UserRole)
    for (const role of roles) {
      expect(ROLE_BUCKETS[role], `missing buckets for ${role}`).toBeDefined()
    }
  })

  it('DATA_ENTRY buckets include draft, submitted, processing, completed, rejected', () => {
    const keys = ROLE_BUCKETS[UserRole.DATA_ENTRY]!.map(b => b.key)
    expect(keys).toContain('draft')
    expect(keys).toContain('submitted')
    expect(keys).toContain('processing')
    expect(keys).toContain('completed')
    expect(keys).toContain('rejected')
  })

  it('SUPPORT_COMMITTEE buckets cover claim lifecycle statuses', () => {
    const allStatuses = ROLE_BUCKETS[UserRole.SUPPORT_COMMITTEE]!.flatMap(b => b.statuses)
    expect(allStatuses).toContain(RequestStatus.BANK_APPROVED)
    expect(allStatuses).toContain(RequestStatus.SUPPORT_REVIEW_PENDING)
    expect(allStatuses).toContain(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
    expect(allStatuses).toContain(RequestStatus.SUPPORT_APPROVED)
    expect(allStatuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })

  it('EXECUTIVE_MEMBER buckets cover voting statuses', () => {
    const allStatuses = ROLE_BUCKETS[UserRole.EXECUTIVE_MEMBER]!.flatMap(b => b.statuses)
    expect(allStatuses).toContain(RequestStatus.SUPPORT_APPROVED)
    expect(allStatuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })

  it('SWIFT_OFFICER buckets cover the full SWIFT queue lifecycle', () => {
    const allStatuses = ROLE_BUCKETS[UserRole.SWIFT_OFFICER]!.flatMap(b => b.statuses)
    expect(allStatuses).toContain(RequestStatus.BANK_APPROVED)
    expect(allStatuses).toContain(RequestStatus.SUPPORT_APPROVED)
    expect(allStatuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
    expect(allStatuses).toContain(RequestStatus.SWIFT_UPLOADED)
    expect(allStatuses.length).toBe(4)
  })

  it('COMMITTEE_DIRECTOR buckets cover voting-open, customs, and rejected states', () => {
    const allStatuses = ROLE_BUCKETS[UserRole.COMMITTEE_DIRECTOR]!.flatMap(b => b.statuses)
    expect(allStatuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
    expect(allStatuses).toContain(RequestStatus.CUSTOMS_DECLARATION_ISSUED)
    expect(allStatuses).toContain(RequestStatus.COMPLETED)
    expect(allStatuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })

  it('BANK_RETURNED is in BANK_REVIEWER returned_to_intake bucket', () => {
    const allStatuses = ROLE_BUCKETS[UserRole.BANK_REVIEWER]!.flatMap(b => b.statuses)
    expect(allStatuses).toContain(RequestStatus.BANK_RETURNED)
    const bucket = ROLE_BUCKETS[UserRole.BANK_REVIEWER]!.find(b => b.key === 'returned_to_intake')
    expect(bucket).toBeDefined()
    expect(bucket!.label).toBe('بحاجة تعديل')
    expect(bucket!.statuses).toContain(RequestStatus.BANK_RETURNED)
  })

  it('BANK_RETURNED is in DATA_ENTRY returned bucket', () => {
    const bucket = ROLE_BUCKETS[UserRole.DATA_ENTRY]!.find(b => b.key === 'returned')
    expect(bucket).toBeDefined()
    expect(bucket!.label).toBe('مُعادة')
    expect(bucket!.statuses).toContain(RequestStatus.BANK_RETURNED)
  })

  it('BANK_ADMIN buckets do not expose CBY-internal stages to UI consumers by accident', () => {
    // BANK_ADMIN can see at_cby bucket which groups all CBY stages, but should NOT see individual raw stage buckets
    const keys = ROLE_BUCKETS[UserRole.BANK_ADMIN]!.map(b => b.key)
    expect(keys).not.toContain('support_stage')
    expect(keys).not.toContain('voting_stage')
  })

  it('CBY_ADMIN buckets cover all 19 statuses across buckets', () => {
    const allStatuses = ROLE_BUCKETS[UserRole.CBY_ADMIN]!.flatMap(b => b.statuses)
    const allStatusValues = Object.values(RequestStatus)
    // DRAFT and DRAFT_REJECTED_INTERNAL are not included in CBY_ADMIN buckets
    // because they are bank-internal; verify at least 17 statuses are covered
    expect(allStatuses.length).toBeGreaterThanOrEqual(17)
    for (const s of allStatusValues) {
      if (s !== RequestStatus.DRAFT && s !== RequestStatus.DRAFT_REJECTED_INTERNAL) {
        expect(allStatuses, `CBY_ADMIN missing ${s}`).toContain(s)
      }
    }
  })

  it('each bucket has a non-empty key, label, and statuses array', () => {
    for (const [role, buckets] of Object.entries(ROLE_BUCKETS)) {
      for (const bucket of buckets!) {
        expect(bucket.key, `empty key for ${role}`).toBeTruthy()
        expect(bucket.label, `empty label for ${role}`).toBeTruthy()
        expect(bucket.statuses.length, `empty statuses for ${role}/${bucket.key}`).toBeGreaterThan(0)
      }
    }
  })
})

// ── CBY_BANK_FILTER_ROLES ─────────────────────────────────────────────────────

describe('CBY_BANK_FILTER_ROLES', () => {
  it('includes SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN', () => {
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.SUPPORT_COMMITTEE)
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.EXECUTIVE_MEMBER)
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.COMMITTEE_DIRECTOR)
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.CBY_ADMIN)
  })

  it('does NOT include bank-scoped roles', () => {
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.DATA_ENTRY)
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.BANK_REVIEWER)
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.BANK_ADMIN)
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.SWIFT_OFFICER)
  })
})

// ── CURRENCY_OPTIONS ──────────────────────────────────────────────────────────

describe('CURRENCY_OPTIONS', () => {
  it('includes the 5 supported currencies', () => {
    expect(CURRENCY_OPTIONS).toContain('USD')
    expect(CURRENCY_OPTIONS).toContain('EUR')
    expect(CURRENCY_OPTIONS).toContain('SAR')
    expect(CURRENCY_OPTIONS).toContain('AED')
    expect(CURRENCY_OPTIONS).toContain('CNY')
  })
})

// ── STATUS_LABELS (BANK_RETURNED) ─────────────────────────────────────────────

describe('STATUS_LABELS BANK_RETURNED', () => {
  it('defines BANK_RETURNED label as "إعادة للمدخل"', () => {
    expect(STATUS_LABELS[RequestStatus.BANK_RETURNED]).toBe('إعادة للمدخل')
  })

  it('covers all 20 RequestStatus values', () => {
    const statuses = Object.values(RequestStatus)
    for (const s of statuses) {
      expect(STATUS_LABELS[s], `STATUS_LABELS missing ${s}`).toBeDefined()
    }
  })
})

// ── DATA_ENTRY_STATUS_LABELS (BANK_RETURNED) ──────────────────────────────────

describe('DATA_ENTRY_STATUS_LABELS BANK_RETURNED', () => {
  it('maps BANK_RETURNED to "مُعادة"', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.BANK_RETURNED]).toBe('مُعادة')
  })
})
