/**
 * COMMITTEE_DIRECTOR requests page — ROLE_BUCKETS tests (implementation-plan §3).
 * Director buckets intentionally overlap on EXECUTIVE_VOTING_OPEN — custom
 * matchers (ready_to_close, tie_break, active_voting) differentiate sub-states.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS, CBY_BANK_FILTER_ROLES } from '../../../../constants/workflow'

const CD_BUCKETS = ROLE_BUCKETS[UserRole.COMMITTEE_DIRECTOR]!

function bucketByKey(key: string) {
  return CD_BUCKETS.find((b) => b.key === key)
}

// ── Bucket existence ──────────────────────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 6 operational tabs', () => {
    expect(CD_BUCKETS).toHaveLength(6)
  })

  it('has ready_to_close bucket', () => {
    expect(bucketByKey('ready_to_close')).toBeDefined()
  })
  it('has ready_to_finalize bucket', () => {
    expect(bucketByKey('ready_to_finalize')).toBeDefined()
  })
  it('has tie_break bucket', () => {
    expect(bucketByKey('tie_break')).toBeDefined()
  })
  it('has fx_pending bucket', () => {
    expect(bucketByKey('fx_pending')).toBeDefined()
  })
  it('has active_voting bucket', () => {
    expect(bucketByKey('active_voting')).toBeDefined()
  })
  it('has finalized bucket', () => {
    expect(bucketByKey('finalized')).toBeDefined()
  })
})

// ── Tab ordering ──────────────────────────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — tab ordering', () => {
  it('ready_to_close is first tab (most urgent — voting can be closed)', () => {
    expect(CD_BUCKETS[0]!.key).toBe('ready_to_close')
  })

  it('ready_to_finalize is second tab', () => {
    expect(CD_BUCKETS[1]!.key).toBe('ready_to_finalize')
  })

  it('tie_break is third tab', () => {
    expect(CD_BUCKETS[2]!.key).toBe('tie_break')
  })

  it('fx_pending is fourth tab', () => {
    expect(CD_BUCKETS[3]!.key).toBe('fx_pending')
  })

  it('active_voting is fifth tab', () => {
    expect(CD_BUCKETS[4]!.key).toBe('active_voting')
  })

  it('finalized is sixth tab', () => {
    expect(CD_BUCKETS[5]!.key).toBe('finalized')
  })
})

// ── ready_to_close bucket — custom matcher ────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — ready_to_close bucket', () => {
  const bucket = () => bucketByKey('ready_to_close')!

  it('includes EXECUTIVE_VOTING_OPEN in statuses', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('has a custom matches function', () => {
    expect(typeof bucket().matches).toBe('function')
  })

  it('matches voting session flagged ready_to_close', () => {
    const req = { status: RequestStatus.EXECUTIVE_VOTING_OPEN, ready_to_close: true }
    expect(bucket().matches!(req, null)).toBe(true)
  })

  it('does not match session not yet ready to close', () => {
    const req = { status: RequestStatus.EXECUTIVE_VOTING_OPEN, ready_to_close: false }
    expect(bucket().matches!(req, null)).toBe(false)
  })
})

// ── tie_break bucket — custom matcher ────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — tie_break bucket', () => {
  const bucket = () => bucketByKey('tie_break')!

  it('has a custom matches function', () => {
    expect(typeof bucket().matches).toBe('function')
  })

  it('matches tied voting session', () => {
    const req = { status: RequestStatus.EXECUTIVE_VOTING_OPEN, is_tie: true }
    expect(bucket().matches!(req, null)).toBe(true)
  })

  it('does not match non-tied session', () => {
    const req = { status: RequestStatus.EXECUTIVE_VOTING_OPEN, is_tie: false }
    expect(bucket().matches!(req, null)).toBe(false)
  })
})

// ── fx_pending bucket ─────────────────────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — fx_pending bucket', () => {
  const bucket = () => bucketByKey('fx_pending')!

  it('includes EXECUTIVE_APPROVED (Director action pending)', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('includes FX_CONFIRMATION_PENDING (issued, awaiting completion)', () => {
    expect(bucket().statuses).toContain(RequestStatus.FX_CONFIRMATION_PENDING)
  })
})

// ── finalized bucket ──────────────────────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — finalized bucket', () => {
  const bucket = () => bucketByKey('finalized')!

  it('includes EXECUTIVE_APPROVED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('includes EXECUTIVE_REJECTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_REJECTED)
  })
})

// ── active_voting bucket ──────────────────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — active_voting bucket', () => {
  it('includes EXECUTIVE_VOTING_OPEN', () => {
    expect(bucketByKey('active_voting')!.statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })
})

// ── ready_to_finalize bucket ──────────────────────────────────────────────────

describe('COMMITTEE_DIRECTOR ROLE_BUCKETS — ready_to_finalize bucket', () => {
  it('includes EXECUTIVE_VOTING_CLOSED', () => {
    expect(bucketByKey('ready_to_finalize')!.statuses).toContain(
      RequestStatus.EXECUTIVE_VOTING_CLOSED,
    )
  })
})

// ── COMMITTEE_DIRECTOR — in CBY bank filter roles ─────────────────────────────

describe('COMMITTEE_DIRECTOR — in CBY bank filter roles (cross-bank visibility)', () => {
  it('COMMITTEE_DIRECTOR is in CBY_BANK_FILTER_ROLES', () => {
    expect(CBY_BANK_FILTER_ROLES).toContain(UserRole.COMMITTEE_DIRECTOR)
  })
})
