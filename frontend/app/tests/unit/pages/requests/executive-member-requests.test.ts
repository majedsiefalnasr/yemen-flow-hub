/**
 * EXECUTIVE_MEMBER requests page — ROLE_BUCKETS tests (implementation-plan §3).
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS, CBY_BANK_FILTER_ROLES } from '../../../../constants/workflow'

const EM_BUCKETS = ROLE_BUCKETS[UserRole.EXECUTIVE_MEMBER]!

function bucketByKey(key: string) {
  return EM_BUCKETS.find(b => b.key === key)
}

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 8 operational tabs', () => {
    expect(EM_BUCKETS).toHaveLength(8)
  })

  it('has pending_my_vote bucket', () => { expect(bucketByKey('pending_my_vote')).toBeDefined() })
  it('has voted_by_me bucket', () => { expect(bucketByKey('voted_by_me')).toBeDefined() })
  it('has pending_open bucket', () => { expect(bucketByKey('pending_open')).toBeDefined() })
  it('has voting_open bucket', () => { expect(bucketByKey('voting_open')).toBeDefined() })
  it('has voting_closed bucket', () => { expect(bucketByKey('voting_closed')).toBeDefined() })
  it('has approved bucket', () => { expect(bucketByKey('approved')).toBeDefined() })
  it('has rejected bucket', () => { expect(bucketByKey('rejected')).toBeDefined() })
  it('has post_approval bucket', () => { expect(bucketByKey('post_approval')).toBeDefined() })
})

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — tab ordering (pending_my_vote first)', () => {
  it('pending_my_vote is first tab — most actionable', () => {
    expect(EM_BUCKETS[0]!.key).toBe('pending_my_vote')
  })
  it('voted_by_me is second tab', () => { expect(EM_BUCKETS[1]!.key).toBe('voted_by_me') })
  it('pending_open is third tab', () => { expect(EM_BUCKETS[2]!.key).toBe('pending_open') })
  it('voting_open is fourth tab', () => { expect(EM_BUCKETS[3]!.key).toBe('voting_open') })
  it('voting_closed is fifth tab', () => { expect(EM_BUCKETS[4]!.key).toBe('voting_closed') })
  it('approved is sixth tab', () => { expect(EM_BUCKETS[5]!.key).toBe('approved') })
  it('rejected is seventh tab', () => { expect(EM_BUCKETS[6]!.key).toBe('rejected') })
  it('post_approval is eighth tab', () => { expect(EM_BUCKETS[7]!.key).toBe('post_approval') })
})

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — pending_my_vote bucket (custom matcher)', () => {
  const bucket = () => bucketByKey('pending_my_vote')!

  it('includes EXECUTIVE_VOTING_OPEN in statuses', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('has a custom matches function', () => {
    expect(typeof bucket().matches).toBe('function')
  })

  it('matches open voting session where member has not voted', () => {
    const req = { status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: null }
    expect(bucket().matches!(req, 1)).toBe(true)
  })

  it('does not match when member already voted', () => {
    const req = { status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'approve' as const }
    expect(bucket().matches!(req, 1)).toBe(false)
  })
})

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — pending_open bucket', () => {
  const bucket = () => bucketByKey('pending_open')!

  it('includes SUPPORT_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_APPROVED)
  })

  it('includes WAITING_FOR_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
  })
})

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — approved bucket', () => {
  it('includes EXECUTIVE_APPROVED', () => {
    expect(bucketByKey('approved')!.statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })
})

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — rejected bucket', () => {
  it('includes EXECUTIVE_REJECTED', () => {
    expect(bucketByKey('rejected')!.statuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })
})

describe('EXECUTIVE_MEMBER ROLE_BUCKETS — post_approval bucket', () => {
  const bucket = () => bucketByKey('post_approval')!

  it('includes WAITING_FOR_SWIFT', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
  })

  it('includes SWIFT_UPLOADED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SWIFT_UPLOADED)
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

describe('EXECUTIVE_MEMBER — in CBY bank filter roles (cross-bank visibility)', () => {
  it('EXECUTIVE_MEMBER is in CBY_BANK_FILTER_ROLES', () => {
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.EXECUTIVE_MEMBER)
  })
})
