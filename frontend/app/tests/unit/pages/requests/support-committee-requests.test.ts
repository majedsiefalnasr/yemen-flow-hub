/**
 * SUPPORT_COMMITTEE requests page — ROLE_BUCKETS tests (implementation-plan §3).
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS, CBY_BANK_FILTER_ROLES } from '../../../../constants/workflow'

const SC_BUCKETS = ROLE_BUCKETS[UserRole.SUPPORT_COMMITTEE]!

function bucketByKey(key: string) {
  return SC_BUCKETS.find((b) => b.key === key)
}

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 5 operational tabs (waiting, my_claims, in_progress, approved, rejected)', () => {
    expect(SC_BUCKETS).toHaveLength(5)
  })

  it('has waiting bucket', () => {
    expect(bucketByKey('waiting')).toBeDefined()
  })
  it('has my_claims bucket', () => {
    expect(bucketByKey('my_claims')).toBeDefined()
  })
  it('has in_progress bucket', () => {
    expect(bucketByKey('in_progress')).toBeDefined()
  })
  it('has approved bucket', () => {
    expect(bucketByKey('approved')).toBeDefined()
  })
  it('has rejected bucket', () => {
    expect(bucketByKey('rejected')).toBeDefined()
  })
})

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — tab ordering (waiting first)', () => {
  it('waiting is first tab — unclaimed queue is most actionable', () => {
    expect(SC_BUCKETS[0]!.key).toBe('waiting')
  })
  it('my_claims is second tab', () => {
    expect(SC_BUCKETS[1]!.key).toBe('my_claims')
  })
  it('in_progress is third tab', () => {
    expect(SC_BUCKETS[2]!.key).toBe('in_progress')
  })
  it('approved is fourth tab', () => {
    expect(SC_BUCKETS[3]!.key).toBe('approved')
  })
  it('rejected is fifth tab', () => {
    expect(SC_BUCKETS[4]!.key).toBe('rejected')
  })
})

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — waiting bucket', () => {
  const bucket = () => bucketByKey('waiting')!

  it('includes SUPPORT_REVIEW_PENDING', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REVIEW_PENDING)
  })

  it('has exactly 1 status', () => {
    expect(bucket().statuses).toHaveLength(1)
  })
})

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — my_claims bucket (custom matcher)', () => {
  const bucket = () => bucketByKey('my_claims')!

  it('includes SUPPORT_REVIEW_IN_PROGRESS in statuses list', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
  })

  it('has a custom matches function', () => {
    expect(typeof bucket().matches).toBe('function')
  })

  it('matches claimed-by-me requests', () => {
    const req = { status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, is_claimed_by_me: true }
    expect(bucket().matches!(req, 1)).toBe(true)
  })

  it('matches by currentUserId when is_claimed_by_me not set', () => {
    const req = {
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      claimed_by: { id: 42, name: 'Ali' },
    }
    expect(bucket().matches!(req, 42)).toBe(true)
  })

  it('does not match requests claimed by someone else', () => {
    const req = {
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      claimed_by: { id: 99, name: 'Other' },
      is_claimed_by_me: false,
    }
    expect(bucket().matches!(req, 42)).toBe(false)
  })
})

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — in_progress bucket (claimed by others)', () => {
  const bucket = () => bucketByKey('in_progress')!

  it('has a custom matches function', () => {
    expect(typeof bucket().matches).toBe('function')
  })

  it('matches requests claimed by someone else', () => {
    const req = {
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      claimed_by: { id: 99, name: 'Other' },
      is_claimed_by_me: false,
    }
    expect(bucket().matches!(req, 42)).toBe(true)
  })

  it('does not match own claims', () => {
    const req = {
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      claimed_by: { id: 42, name: 'Me' },
      is_claimed_by_me: true,
    }
    expect(bucket().matches!(req, 42)).toBe(false)
  })
})

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — approved bucket', () => {
  const bucket = () => bucketByKey('approved')!

  it('includes SUPPORT_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_APPROVED)
  })
})

describe('SUPPORT_COMMITTEE ROLE_BUCKETS — rejected bucket', () => {
  const bucket = () => bucketByKey('rejected')!

  it('includes SUPPORT_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })
})

describe('SUPPORT_COMMITTEE — in CBY bank filter roles (cross-bank visibility)', () => {
  it('SUPPORT_COMMITTEE is in CBY_BANK_FILTER_ROLES', () => {
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.SUPPORT_COMMITTEE)
  })
})
