/**
 * DATA_ENTRY requests page — ROLE_BUCKETS tests (implementation-plan §3).
 * Validates bucket ordering, returned-first tab, all-statuses coverage,
 * no CBY internals exposed as bucket keys, and simplified label mapping.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { ROLE_BUCKETS, DATA_ENTRY_STATUS_LABELS, CBY_BANK_FILTER_ROLES } from '../../../../constants/workflow'

const DE_BUCKETS = ROLE_BUCKETS[UserRole.DATA_ENTRY]!

function bucketByKey(key: string) {
  return DE_BUCKETS.find(b => b.key === key)
}

// ── Bucket existence ──────────────────────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — bucket existence', () => {
  it('has exactly 6 operational tabs (returned, draft, submitted, processing, completed, rejected)', () => {
    expect(DE_BUCKETS).toHaveLength(6)
  })

  it('has returned bucket', () => {
    expect(bucketByKey('returned')).toBeDefined()
  })

  it('has draft bucket', () => {
    expect(bucketByKey('draft')).toBeDefined()
  })

  it('has submitted bucket', () => {
    expect(bucketByKey('submitted')).toBeDefined()
  })

  it('has processing bucket', () => {
    expect(bucketByKey('processing')).toBeDefined()
  })

  it('has completed bucket', () => {
    expect(bucketByKey('completed')).toBeDefined()
  })

  it('has rejected bucket', () => {
    expect(bucketByKey('rejected')).toBeDefined()
  })
})

// ── Tab ordering — returned MUST be first (spec §3) ──────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — tab ordering (returned first)', () => {
  it('returned is first tab — most actionable work first', () => {
    expect(DE_BUCKETS[0]!.key).toBe('returned')
  })

  it('draft is second tab', () => {
    expect(DE_BUCKETS[1]!.key).toBe('draft')
  })

  it('submitted is third tab', () => {
    expect(DE_BUCKETS[2]!.key).toBe('submitted')
  })

  it('processing is fourth tab', () => {
    expect(DE_BUCKETS[3]!.key).toBe('processing')
  })

  it('completed is fifth tab', () => {
    expect(DE_BUCKETS[4]!.key).toBe('completed')
  })

  it('rejected is sixth tab', () => {
    expect(DE_BUCKETS[5]!.key).toBe('rejected')
  })
})

// ── returned bucket — covers all three returned states ───────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — returned bucket', () => {
  const bucket = () => bucketByKey('returned')!

  it('includes BANK_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_RETURNED)
  })

  it('includes SUPPORT_RETURNED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUPPORT_RETURNED)
  })

  it('includes DRAFT_REJECTED_INTERNAL', () => {
    expect(bucket().statuses).toContain(RequestStatus.DRAFT_REJECTED_INTERNAL)
  })

  it('label is مُعادة', () => {
    expect(bucket().label).toBe('مُعادة')
  })

  it('has exactly 3 statuses (all return paths)', () => {
    expect(bucket().statuses).toHaveLength(3)
  })
})

// ── draft bucket ──────────────────────────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — draft bucket', () => {
  const bucket = () => bucketByKey('draft')!

  it('includes only DRAFT', () => {
    expect(bucket().statuses).toContain(RequestStatus.DRAFT)
    expect(bucket().statuses).toHaveLength(1)
  })

  it('does NOT include DRAFT_REJECTED_INTERNAL (that is returned)', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.DRAFT_REJECTED_INTERNAL)
  })
})

// ── submitted bucket ──────────────────────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — submitted bucket', () => {
  const bucket = () => bucketByKey('submitted')!

  it('includes SUBMITTED', () => {
    expect(bucket().statuses).toContain(RequestStatus.SUBMITTED)
  })

  it('includes BANK_REVIEW', () => {
    expect(bucket().statuses).toContain(RequestStatus.BANK_REVIEW)
  })
})

// ── processing bucket — CBY internals hidden from DATA_ENTRY ─────────────────

describe('DATA_ENTRY ROLE_BUCKETS — processing bucket', () => {
  const bucket = () => bucketByKey('processing')!

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

  it('includes EXECUTIVE_VOTING_OPEN (hidden CBY stage — shown as قيد المعالجة)', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('includes EXECUTIVE_VOTING_CLOSED (hidden CBY stage)', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_VOTING_CLOSED)
  })

  it('does NOT include DRAFT or DRAFT_REJECTED_INTERNAL', () => {
    expect(bucket().statuses).not.toContain(RequestStatus.DRAFT)
    expect(bucket().statuses).not.toContain(RequestStatus.DRAFT_REJECTED_INTERNAL)
  })
})

// ── completed bucket ──────────────────────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — completed bucket', () => {
  const bucket = () => bucketByKey('completed')!

  it('includes EXECUTIVE_APPROVED (simplified label: مكتمل)', () => {
    expect(bucket().statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('includes CUSTOMS_DECLARATION_ISSUED (legacy terminal)', () => {
    expect(bucket().statuses).toContain(RequestStatus.CUSTOMS_DECLARATION_ISSUED)
  })

  it('includes FX_CONFIRMATION_PENDING (simplified label: مكتمل)', () => {
    expect(bucket().statuses).toContain(RequestStatus.FX_CONFIRMATION_PENDING)
  })

  it('includes COMPLETED', () => {
    expect(bucket().statuses).toContain(RequestStatus.COMPLETED)
  })
})

// ── rejected bucket ───────────────────────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — rejected bucket', () => {
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

// ── No status duplication ─────────────────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — no cross-bucket status overlap', () => {
  it('each status appears in at most one bucket', () => {
    const seen = new Map<string, string>()
    for (const bucket of DE_BUCKETS) {
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

// ── No raw CBY bucket keys exposed ───────────────────────────────────────────

describe('DATA_ENTRY ROLE_BUCKETS — no CBY internal bucket keys', () => {
  it('does not expose support_stage as a bucket key', () => {
    expect(DE_BUCKETS.map(b => b.key)).not.toContain('support_stage')
  })

  it('does not expose voting_stage as a bucket key', () => {
    expect(DE_BUCKETS.map(b => b.key)).not.toContain('voting_stage')
  })

  it('does not expose swift_stage as a bucket key', () => {
    expect(DE_BUCKETS.map(b => b.key)).not.toContain('swift_stage')
  })

  it('does not expose fx_pending as a bucket key (CBY Director surface)', () => {
    expect(DE_BUCKETS.map(b => b.key)).not.toContain('fx_pending')
  })
})

// ── DATA_ENTRY is not in CBY bank filter roles ────────────────────────────────

describe('DATA_ENTRY — excluded from CBY bank filter', () => {
  it('DATA_ENTRY is not in CBY_BANK_FILTER_ROLES (no cross-bank visibility)', () => {
    expect(CBY_BANK_FILTER_ROLES).not.toContain(UserRole.DATA_ENTRY)
  })
})

// ── Simplified status labels shown to DATA_ENTRY ─────────────────────────────

describe('DATA_ENTRY_STATUS_LABELS — CBY internals hidden', () => {
  it('BANK_APPROVED maps to قيد معالجة CBY', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.BANK_APPROVED]).toBe('قيد معالجة CBY')
  })

  it('SUPPORT_REVIEW_IN_PROGRESS maps to قيد معالجة CBY (hidden CBY internal)', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]).toBe('قيد معالجة CBY')
  })

  it('EXECUTIVE_VOTING_OPEN maps to قيد معالجة CBY (voting hidden from DE)', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.EXECUTIVE_VOTING_OPEN]).toBe('قيد معالجة CBY')
  })

  it('WAITING_FOR_SWIFT maps to قيد معالجة CBY (SWIFT hidden from DE)', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.WAITING_FOR_SWIFT]).toBe('قيد معالجة CBY')
  })

  it('BANK_RETURNED maps to مُعادة', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.BANK_RETURNED]).toBe('مُعادة')
  })

  it('SUPPORT_RETURNED maps to مُعادة', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.SUPPORT_RETURNED]).toBe('مُعادة')
  })

  it('DRAFT_REJECTED_INTERNAL maps to معاد للتعديل', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.DRAFT_REJECTED_INTERNAL]).toBe('معاد للتعديل')
  })

  it('EXECUTIVE_APPROVED maps to مكتمل (simplified success)', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.EXECUTIVE_APPROVED]).toBe('مكتمل')
  })

  it('FX_CONFIRMATION_PENDING maps to مكتمل (simplified success)', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.FX_CONFIRMATION_PENDING]).toBe('مكتمل')
  })

  it('EXECUTIVE_REJECTED maps to مرفوض نهائياً', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.EXECUTIVE_REJECTED]).toBe('مرفوض نهائياً')
  })

  it('SUPPORT_REJECTED maps to مرفوض', () => {
    expect(DATA_ENTRY_STATUS_LABELS[RequestStatus.SUPPORT_REJECTED]).toBe('مرفوض')
  })
})
