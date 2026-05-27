/**
 * BANK_REVIEWER requests page — ROLE_BUCKETS tests (implementation-plan §3).
 * Validates bucket ordering, pending-first, all relevant statuses covered,
 * no DATA_ENTRY or CBY surfaces exposed as bucket keys, and full canonical labels.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS, CBY_BANK_FILTER_ROLES } from '../../../../constants/workflow'

const BR_BUCKETS = ROLE_BUCKETS[UserRole.BANK_REVIEWER]!

function bucketByKey(key: string) {
  return BR_BUCKETS.find(b => b.key === key)
}

// ── Bucket existence ──────────────────────────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 7 operational tabs (pending, support_rejected, bank_returned, support_returned, at_cby, completed, rejected)', () => {
    expect(BR_BUCKETS).toHaveLength(7)
  })

  it('has pending bucket', () => {
    expect(bucketByKey('pending')).toBeDefined()
  })

  it('has support_rejected bucket', () => {
    expect(bucketByKey('support_rejected')).toBeDefined()
  })

  it('has bank_returned bucket', () => {
    expect(bucketByKey('bank_returned')).toBeDefined()
  })

  it('has support_returned bucket', () => {
    expect(bucketByKey('support_returned')).toBeDefined()
  })

  it('has at_cby bucket', () => {
    expect(bucketByKey('at_cby')).toBeDefined()
  })

  it('has completed bucket', () => {
    expect(bucketByKey('completed')).toBeDefined()
  })

  it('has rejected bucket', () => {
    expect(bucketByKey('rejected')).toBeDefined()
  })
})

// ── Tab ordering — pending MUST be first ────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — tab ordering (pending first)', () => {
  it('pending is first tab — most actionable work first', () => {
    expect(BR_BUCKETS[0]!.key).toBe('pending')
  })

  it('support_rejected is second tab — high-priority follow-up decisions', () => {
    expect(BR_BUCKETS[1]!.key).toBe('support_rejected')
  })

  it('bank_returned is third tab', () => {
    expect(BR_BUCKETS[2]!.key).toBe('bank_returned')
  })

  it('support_returned is fourth tab', () => {
    expect(BR_BUCKETS[3]!.key).toBe('support_returned')
  })

  it('at_cby is fifth tab', () => {
    expect(BR_BUCKETS[4]!.key).toBe('at_cby')
  })

  it('completed is sixth tab', () => {
    expect(BR_BUCKETS[5]!.key).toBe('completed')
  })

  it('rejected is seventh tab', () => {
    expect(BR_BUCKETS[6]!.key).toBe('rejected')
  })
})

// ── pending bucket — covers submitted + bank_review ─────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — pending bucket', () => {
  const bucket = () => bucketByKey('pending')!

  it('includes SUBMITTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUBMITTED)
  })

  it('includes BANK_REVIEW', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REVIEW)
  })

  it('has exactly 2 statuses', () => {
    expect(bucket().statuses).toHaveLength(2)
  })
})

// ── support_rejected bucket ──────────────────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — support_rejected bucket', () => {
  const bucket = () => bucketByKey('support_rejected')!

  it('includes SUPPORT_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })

  it('has exactly 1 status', () => {
    expect(bucket().statuses).toHaveLength(1)
  })
})

// ── bank_returned bucket ─────────────────────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — bank_returned bucket', () => {
  const bucket = () => bucketByKey('bank_returned')!

  it('includes BANK_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_RETURNED)
  })

  it('has exactly 1 status', () => {
    expect(bucket().statuses).toHaveLength(1)
  })
})

// ── support_returned bucket ──────────────────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — support_returned bucket', () => {
  const bucket = () => bucketByKey('support_returned')!

  it('includes SUPPORT_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_RETURNED)
  })

  it('has exactly 1 status', () => {
    expect(bucket().statuses).toHaveLength(1)
  })
})

// ── at_cby bucket — downstream CBY stages ───────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — at_cby bucket', () => {
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

  it('includes EXECUTIVE_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('includes EXECUTIVE_VOTING_CLOSED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
  })
})

// ── completed bucket ──────────────────────────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — completed bucket', () => {
  const bucket = () => bucketByKey('completed')!

  it('includes EXECUTIVE_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })

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

describe('BANK_REVIEWER ROLE_BUCKETS — rejected bucket', () => {
  const bucket = () => bucketByKey('rejected')!

  it('includes BANK_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REJECTED)
  })

  it('includes EXECUTIVE_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })

  it('does NOT include SUPPORT_REJECTED (that is its own bucket)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.SUPPORT_REJECTED)
  })
})

// ── No status duplication ─────────────────────────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — no cross-bucket status overlap', () => {
  it('each status appears in at most one bucket', () => {
    const seen = new Map<string, string>()
    for (const bucket of BR_BUCKETS) {
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

// ── No DATA_ENTRY or CBY internal bucket keys ─────────────────────────────────

describe('BANK_REVIEWER ROLE_BUCKETS — no DATA_ENTRY or CBY internal bucket keys', () => {
  it('does not expose draft as a bucket key', () => {
    expect(BR_BUCKETS.map(b => b.key)).not.toContain('draft')
  })

  it('does not expose returned as a bucket key (simplified DATA_ENTRY view)', () => {
    expect(BR_BUCKETS.map(b => b.key)).not.toContain('returned')
  })

  it('does not expose support_stage as a bucket key', () => {
    expect(BR_BUCKETS.map(b => b.key)).not.toContain('support_stage')
  })

  it('does not expose voting_stage as a bucket key', () => {
    expect(BR_BUCKETS.map(b => b.key)).not.toContain('voting_stage')
  })

  it('does not expose fx_pending as a bucket key (Director-only surface)', () => {
    expect(BR_BUCKETS.map(b => b.key)).not.toContain('fx_pending')
  })
})

// ── BANK_REVIEWER is in CBY_BANK_FILTER_ROLES ────────────────────────────────

describe('BANK_REVIEWER — excluded from CBY bank filter (own-bank scoped)', () => {
  it('BANK_REVIEWER is not in CBY_BANK_FILTER_ROLES (bank-scoped, no cross-bank visibility)', () => {
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.BANK_REVIEWER)
  })
})
