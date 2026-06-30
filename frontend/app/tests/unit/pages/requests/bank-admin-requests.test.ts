/**
 * BANK_ADMIN requests page — ROLE_BUCKETS tests (Story 12.2).
 * Validates the operational tab structure including the new swift_fx bucket
 * and DRAFT_REJECTED_INTERNAL inclusion in pending.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS } from '../../../../constants/workflow'

const BANK_BUCKETS = ROLE_BUCKETS[UserRole.BANK_ADMIN]!

function bucketByKey(key: string) {
  return BANK_BUCKETS.find((b) => b.key === key)
}

// ── Bucket existence ──────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 5 operational tabs', () => {
    expect(BANK_BUCKETS).toHaveLength(5)
  })

  it('has pending bucket', () => {
    expect(bucketByKey('pending')).toBeDefined()
  })

  it('has at_cby bucket', () => {
    expect(bucketByKey('at_cby')).toBeDefined()
  })

  it('has swift_fx bucket (new in Story 12.2)', () => {
    expect(bucketByKey('swift_fx')).toBeDefined()
  })

  it('has completed bucket', () => {
    expect(bucketByKey('completed')).toBeDefined()
  })

  it('has rejected bucket', () => {
    expect(bucketByKey('rejected')).toBeDefined()
  })
})

// ── pending bucket ────────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — pending bucket', () => {
  const bucket = () => bucketByKey('pending')!

  it('includes DRAFT_REJECTED_INTERNAL (Story 12.2 addition)', () => {
    expect(bucket().statuses).toContain(RequestStatus.DRAFT_REJECTED_INTERNAL)
  })

  it('includes SUBMITTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUBMITTED)
  })

  it('includes BANK_REVIEW', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REVIEW)
  })

  it('includes BANK_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_RETURNED)
  })

  it('includes SUPPORT_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_RETURNED)
  })

  it('does NOT include DRAFT (plain draft not visible to BANK_ADMIN)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.DRAFT)
  })
})

// ── at_cby bucket ─────────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — at_cby bucket', () => {
  const bucket = () => bucketByKey('at_cby')!

  it('includes BANK_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_APPROVED)
  })

  it('includes SUPPORT_REVIEW_PENDING', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REVIEW_PENDING)
  })

  it('includes SUPPORT_REVIEW_IN_PROGRESS', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
  })

  it('includes SUPPORT_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_APPROVED)
  })

  it('includes WAITING_FOR_SWIFT', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
  })

  it('includes SWIFT_UPLOADED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SWIFT_UPLOADED)
  })

  it('includes WAITING_FOR_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
  })

  it('does NOT include EXECUTIVE_VOTING_OPEN (moved to swift_fx)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('does NOT include EXECUTIVE_VOTING_CLOSED (moved to swift_fx)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
  })

  it('does NOT include EXECUTIVE_APPROVED (moved to swift_fx)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.EXECUTIVE_APPROVED)
  })
})

// ── swift_fx bucket ───────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — swift_fx bucket (Story 12.2)', () => {
  const bucket = () => bucketByKey('swift_fx')!

  it('includes EXECUTIVE_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('includes EXECUTIVE_VOTING_CLOSED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
  })

  it('includes EXECUTIVE_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('has exactly 3 statuses', () => {
    expect(bucket().statuses).toHaveLength(3)
  })

  it('label contains SWIFT', () => {
    expect(bucket().label).toContain('SWIFT')
  })
})

// ── completed bucket ──────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — completed bucket', () => {
  const bucket = () => bucketByKey('completed')!

  it('includes CUSTOMS_DECLARATION_ISSUED', () => {
    expect(bucket().statuses).toContain(RequestStatus.CUSTOMS_DECLARATION_ISSUED)
  })

  it('includes FX_CONFIRMATION_PENDING', () => {
    expect(bucket().statuses).toContain(RequestStatus.FX_CONFIRMATION_PENDING)
  })

  it('includes COMPLETED', () => {
    expect(bucket().statuses).toContain(RequestStatus.COMPLETED)
  })
})

// ── rejected bucket ───────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — rejected bucket', () => {
  const bucket = () => bucketByKey('rejected')!

  it('includes SUPPORT_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })

  it('includes EXECUTIVE_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })

  it('includes BANK_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REJECTED)
  })
})

// ── Tab ordering ──────────────────────────────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — tab ordering', () => {
  it('pending is first tab', () => {
    expect(BANK_BUCKETS[0]!.key).toBe('pending')
  })

  it('at_cby is second tab', () => {
    expect(BANK_BUCKETS[1]!.key).toBe('at_cby')
  })

  it('swift_fx is third tab', () => {
    expect(BANK_BUCKETS[2]!.key).toBe('swift_fx')
  })

  it('completed is fourth tab', () => {
    expect(BANK_BUCKETS[3]!.key).toBe('completed')
  })

  it('rejected is fifth tab', () => {
    expect(BANK_BUCKETS[4]!.key).toBe('rejected')
  })
})

// ── No status duplication across buckets ──────────────────────────────────────

describe('BANK_ADMIN ROLE_BUCKETS — no cross-bucket status overlap', () => {
  it('each status appears in at most one bucket', () => {
    const seen = new Map<string, string>()
    for (const bucket of BANK_BUCKETS) {
      for (const status of bucket.statuses) {
        if (seen.has(status)) {
          throw new Error(`Status ${status} appears in both ${seen.get(status)} and ${bucket.key}`)
        }
        seen.set(status, bucket.key)
      }
    }
    expect(seen.size).toBeGreaterThan(0)
  })
})
