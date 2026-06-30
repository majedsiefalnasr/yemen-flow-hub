/**
 * CBY_ADMIN requests page — operational ROLE_BUCKETS tests (Story 12.2).
 * Tests that bucket definitions match docs/user-view/cby-admin.md operational tabs.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS } from '../../../../constants/workflow'

const CBY_BUCKETS = ROLE_BUCKETS[UserRole.CBY_ADMIN]!

function bucketByKey(key: string) {
  return CBY_BUCKETS.find((b) => b.key === key)
}

// ── Bucket existence ──────────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 6 operational tabs', () => {
    expect(CBY_BUCKETS).toHaveLength(6)
  })

  it('has active bucket', () => {
    expect(bucketByKey('active')).toBeDefined()
  })

  it('has needs_attention bucket', () => {
    expect(bucketByKey('needs_attention')).toBeDefined()
  })

  it('has executive_voting bucket', () => {
    expect(bucketByKey('executive_voting')).toBeDefined()
  })

  it('has fx_pending bucket', () => {
    expect(bucketByKey('fx_pending')).toBeDefined()
  })

  it('has rejected bucket', () => {
    expect(bucketByKey('rejected')).toBeDefined()
  })

  it('has completed bucket', () => {
    expect(bucketByKey('completed')).toBeDefined()
  })
})

// ── active bucket ─────────────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — active bucket', () => {
  const active = () => bucketByKey('active')!

  it('includes SUBMITTED', () => {
    expect(active().statuses).toContain(RequestStatus.SUBMITTED)
  })

  it('includes BANK_REVIEW', () => {
    expect(active().statuses).toContain(RequestStatus.BANK_REVIEW)
  })

  it('includes BANK_APPROVED', () => {
    expect(active().statuses).toContain(RequestStatus.BANK_APPROVED)
  })

  it('includes SUPPORT_REVIEW_PENDING', () => {
    expect(active().statuses).toContain(RequestStatus.SUPPORT_REVIEW_PENDING)
  })

  it('includes SUPPORT_REVIEW_IN_PROGRESS', () => {
    expect(active().statuses).toContain(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
  })

  it('includes SUPPORT_APPROVED', () => {
    expect(active().statuses).toContain(RequestStatus.SUPPORT_APPROVED)
  })

  it('includes WAITING_FOR_SWIFT', () => {
    expect(active().statuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
  })

  it('includes SWIFT_UPLOADED', () => {
    expect(active().statuses).toContain(RequestStatus.SWIFT_UPLOADED)
  })

  it('includes WAITING_FOR_VOTING_OPEN', () => {
    expect(active().statuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
  })

  it('does NOT include terminal COMPLETED', () => {
    expect(active().statuses).not.toContain(RequestStatus.COMPLETED)
  })

  it('does NOT include DRAFT (not visible to CBY_ADMIN)', () => {
    expect(active().statuses).not.toContain(RequestStatus.DRAFT)
  })
})

// ── needs_attention bucket ────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — needs_attention bucket', () => {
  const bucket = () => bucketByKey('needs_attention')!

  it('includes DRAFT_REJECTED_INTERNAL', () => {
    expect(bucket().statuses).toContain(RequestStatus.DRAFT_REJECTED_INTERNAL)
  })

  it('includes BANK_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_RETURNED)
  })

  it('includes SUPPORT_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_RETURNED)
  })

  it('includes SUPPORT_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })
})

// ── executive_voting bucket ───────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — executive_voting bucket', () => {
  const bucket = () => bucketByKey('executive_voting')!

  it('includes WAITING_FOR_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
  })

  it('includes EXECUTIVE_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('includes EXECUTIVE_VOTING_CLOSED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
  })

  it('does NOT include EXECUTIVE_APPROVED (that is fx_pending)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.EXECUTIVE_APPROVED)
  })
})

// ── fx_pending bucket ─────────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — fx_pending bucket', () => {
  const bucket = () => bucketByKey('fx_pending')!

  it('contains exactly EXECUTIVE_APPROVED', () => {
    expect(bucket().statuses).toHaveLength(1)
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })
})

// ── rejected bucket ───────────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — rejected bucket', () => {
  const bucket = () => bucketByKey('rejected')!

  it('includes BANK_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REJECTED)
  })

  it('includes SUPPORT_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })

  it('includes EXECUTIVE_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })
})

// ── completed bucket ──────────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — completed bucket', () => {
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

// ── Bucket ordering ───────────────────────────────────────────────────────────

describe('CBY_ADMIN ROLE_BUCKETS — tab ordering', () => {
  it('active is first tab (most operational)', () => {
    expect(CBY_BUCKETS[0]!.key).toBe('active')
  })

  it('needs_attention is second tab', () => {
    expect(CBY_BUCKETS[1]!.key).toBe('needs_attention')
  })

  it('executive_voting is third tab', () => {
    expect(CBY_BUCKETS[2]!.key).toBe('executive_voting')
  })
})
