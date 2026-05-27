/**
 * SWIFT_OFFICER requests page — ROLE_BUCKETS tests (implementation-plan §3).
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS, CBY_BANK_FILTER_ROLES } from '../../../../constants/workflow'

const SO_BUCKETS = ROLE_BUCKETS[UserRole.SWIFT_OFFICER]!

function bucketByKey(key: string) {
  return SO_BUCKETS.find(b => b.key === key)
}

describe('SWIFT_OFFICER ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 4 operational tabs (pending_swift, swift_done, completed, rejected)', () => {
    expect(SO_BUCKETS).toHaveLength(4)
  })

  it('has pending_swift bucket', () => { expect(bucketByKey('pending_swift')).toBeDefined() })
  it('has swift_done bucket', () => { expect(bucketByKey('swift_done')).toBeDefined() })
  it('has completed bucket', () => { expect(bucketByKey('completed')).toBeDefined() })
  it('has rejected bucket', () => { expect(bucketByKey('rejected')).toBeDefined() })
})

describe('SWIFT_OFFICER ROLE_BUCKETS — tab ordering (pending_swift first)', () => {
  it('pending_swift is first tab', () => { expect(SO_BUCKETS[0]!.key).toBe('pending_swift') })
  it('swift_done is second tab', () => { expect(SO_BUCKETS[1]!.key).toBe('swift_done') })
  it('completed is third tab', () => { expect(SO_BUCKETS[2]!.key).toBe('completed') })
  it('rejected is fourth tab', () => { expect(SO_BUCKETS[3]!.key).toBe('rejected') })
})

describe('SWIFT_OFFICER ROLE_BUCKETS — pending_swift bucket', () => {
  const bucket = () => bucketByKey('pending_swift')!

  it('includes EXECUTIVE_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('includes WAITING_FOR_SWIFT', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
  })
})

describe('SWIFT_OFFICER ROLE_BUCKETS — swift_done bucket', () => {
  const bucket = () => bucketByKey('swift_done')!

  it('includes SWIFT_UPLOADED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SWIFT_UPLOADED)
  })

  it('includes WAITING_FOR_VOTING_OPEN', () => {
    expect(bucket().statuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
  })
})

describe('SWIFT_OFFICER ROLE_BUCKETS — completed bucket', () => {
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

describe('SWIFT_OFFICER ROLE_BUCKETS — rejected bucket', () => {
  const bucket = () => bucketByKey('rejected')!

  it('includes EXECUTIVE_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })

  it('includes SUPPORT_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_REJECTED)
  })

  it('includes BANK_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REJECTED)
  })
})

describe('SWIFT_OFFICER ROLE_BUCKETS — no cross-bucket status overlap', () => {
  it('each status appears in at most one bucket', () => {
    const seen = new Map<string, string>()
    for (const bucket of SO_BUCKETS) {
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

describe('SWIFT_OFFICER — not in CBY bank filter roles (bank-scoped)', () => {
  it('SWIFT_OFFICER is not in CBY_BANK_FILTER_ROLES', () => {
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.SWIFT_OFFICER)
  })
})
