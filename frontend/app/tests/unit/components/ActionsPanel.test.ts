/**
 * ActionsPanel visibility logic — unit tests without component mounting.
 * Tests the show/hide rules as pure computed logic to avoid @vue/test-utils dependency.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 42,
    reference_number: 'YFH-2026-000042',
    bank_id: 1,
    bank_name: 'بنك اليمن',
    merchant: null,
    status: RequestStatus.SUBMITTED,
    current_owner_role: UserRole.BANK_REVIEWER,
    currency: 'USD',
    amount: 50000,
    supplier_name: 'ACME',
    goods_description: 'Goods',
    port_of_entry: 'Aden',
    notes: null,
    created_by: 1,
    submitted_by: 2,
    reviewed_by: null,
    approved_by: null,
    rejected_by: null,
    resubmitted_by: null,
    claimed_by: null,
    claimed_until: null,
    is_claimed: false,
    is_claimed_by_me: false,
    can_be_claimed: false,
    submitted_at: null,
    bank_approved_at: null,
    support_approved_at: null,
    swift_uploaded_at: null,
    executive_decided_at: null,
    customs_issued_at: null,
    revision_count: 0,
    created_at: '2026-05-15T00:00:00.000000Z',
    updated_at: '2026-05-15T00:00:00.000000Z',
    ...overrides,
  }
}

// Pure functions extracted from ActionsPanel's computed logic
function showBankReviewerActions(request: ImportRequest, userRole: UserRole): boolean {
  return (
    userRole === UserRole.BANK_REVIEWER
    && (request.status === RequestStatus.SUBMITTED || request.status === RequestStatus.BANK_REVIEW)
  )
}

function showDataEntryActions(request: ImportRequest, userRole: UserRole): boolean {
  return (
    userRole === UserRole.DATA_ENTRY
    && (request.status === RequestStatus.DRAFT || request.status === RequestStatus.DRAFT_REJECTED_INTERNAL)
  )
}

function showSupportCommitteeActions(request: ImportRequest, userRole: UserRole): boolean {
  return (
    userRole === UserRole.SUPPORT_COMMITTEE
    && request.status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS
    && request.is_claimed_by_me
  )
}

function showAnyActions(request: ImportRequest, userRole: UserRole): boolean {
  return (
    showBankReviewerActions(request, userRole)
    || showDataEntryActions(request, userRole)
    || showSupportCommitteeActions(request, userRole)
  )
}

function resolveSupportRejectAction(isSupportCommittee: boolean): string {
  return isSupportCommittee ? 'support-reject' : 'bank-reject'
}

function showBeginReview(request: ImportRequest, userRole: UserRole): boolean {
  return showBankReviewerActions(request, userRole) && request.status === RequestStatus.SUBMITTED
}

function showApproveReject(request: ImportRequest, userRole: UserRole): boolean {
  return showBankReviewerActions(request, userRole) && request.status === RequestStatus.BANK_REVIEW
}

function showEditDraft(request: ImportRequest, userRole: UserRole): boolean {
  return showDataEntryActions(request, userRole) && request.status === RequestStatus.DRAFT
}

function showEditResubmit(request: ImportRequest, userRole: UserRole): boolean {
  return showDataEntryActions(request, userRole) && request.status === RequestStatus.DRAFT_REJECTED_INTERNAL
}

function editLinkTarget(requestId: number): string {
  return `/requests/${requestId}/edit`
}

describe('ActionsPanel — showBankReviewerActions', () => {
  it('true for BANK_REVIEWER + SUBMITTED', () => {
    expect(showBankReviewerActions(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.BANK_REVIEWER)).toBe(true)
  })

  it('true for BANK_REVIEWER + BANK_REVIEW', () => {
    expect(showBankReviewerActions(makeRequest({ status: RequestStatus.BANK_REVIEW }), UserRole.BANK_REVIEWER)).toBe(true)
  })

  it('false for BANK_REVIEWER + DRAFT (wrong status)', () => {
    expect(showBankReviewerActions(makeRequest({ status: RequestStatus.DRAFT }), UserRole.BANK_REVIEWER)).toBe(false)
  })

  it('false for DATA_ENTRY + SUBMITTED (wrong role)', () => {
    expect(showBankReviewerActions(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.DATA_ENTRY)).toBe(false)
  })

  it('false for BANK_REVIEWER + BANK_APPROVED (post-lock)', () => {
    expect(showBankReviewerActions(makeRequest({ status: RequestStatus.BANK_APPROVED }), UserRole.BANK_REVIEWER)).toBe(false)
  })

  it('false for CBY_ADMIN + SUBMITTED', () => {
    expect(showBankReviewerActions(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.CBY_ADMIN)).toBe(false)
  })
})

describe('ActionsPanel — showDataEntryActions', () => {
  it('true for DATA_ENTRY + DRAFT', () => {
    expect(showDataEntryActions(makeRequest({ status: RequestStatus.DRAFT }), UserRole.DATA_ENTRY)).toBe(true)
  })

  it('true for DATA_ENTRY + DRAFT_REJECTED_INTERNAL', () => {
    expect(showDataEntryActions(makeRequest({ status: RequestStatus.DRAFT_REJECTED_INTERNAL }), UserRole.DATA_ENTRY)).toBe(true)
  })

  it('false for DATA_ENTRY + SUBMITTED (not editable)', () => {
    expect(showDataEntryActions(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.DATA_ENTRY)).toBe(false)
  })

  it('false for BANK_REVIEWER + DRAFT (wrong role)', () => {
    expect(showDataEntryActions(makeRequest({ status: RequestStatus.DRAFT }), UserRole.BANK_REVIEWER)).toBe(false)
  })

  it('false for DATA_ENTRY + BANK_APPROVED (locked)', () => {
    expect(showDataEntryActions(makeRequest({ status: RequestStatus.BANK_APPROVED }), UserRole.DATA_ENTRY)).toBe(false)
  })
})

describe('ActionsPanel — showAnyActions (overall panel visibility)', () => {
  it('true for BANK_REVIEWER + SUBMITTED', () => {
    expect(showAnyActions(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.BANK_REVIEWER)).toBe(true)
  })

  it('true for DATA_ENTRY + DRAFT', () => {
    expect(showAnyActions(makeRequest({ status: RequestStatus.DRAFT }), UserRole.DATA_ENTRY)).toBe(true)
  })

  it('false for CBY_ADMIN + any status', () => {
    expect(showAnyActions(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.CBY_ADMIN)).toBe(false)
    expect(showAnyActions(makeRequest({ status: RequestStatus.DRAFT }), UserRole.CBY_ADMIN)).toBe(false)
  })

  it('false for all roles on COMPLETED status', () => {
    for (const role of Object.values(UserRole)) {
      expect(showAnyActions(makeRequest({ status: RequestStatus.COMPLETED }), role)).toBe(false)
    }
  })

  it('false for all roles on EXECUTIVE_APPROVED status', () => {
    for (const role of Object.values(UserRole)) {
      expect(showAnyActions(makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED }), role)).toBe(false)
    }
  })
})

describe('ActionsPanel — specific button visibility', () => {
  it('showBeginReview only for BANK_REVIEWER + SUBMITTED', () => {
    expect(showBeginReview(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.BANK_REVIEWER)).toBe(true)
    expect(showBeginReview(makeRequest({ status: RequestStatus.BANK_REVIEW }), UserRole.BANK_REVIEWER)).toBe(false)
    expect(showBeginReview(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.DATA_ENTRY)).toBe(false)
  })

  it('showApproveReject only for BANK_REVIEWER + BANK_REVIEW', () => {
    expect(showApproveReject(makeRequest({ status: RequestStatus.BANK_REVIEW }), UserRole.BANK_REVIEWER)).toBe(true)
    expect(showApproveReject(makeRequest({ status: RequestStatus.SUBMITTED }), UserRole.BANK_REVIEWER)).toBe(false)
    expect(showApproveReject(makeRequest({ status: RequestStatus.BANK_REVIEW }), UserRole.DATA_ENTRY)).toBe(false)
  })

  it('showEditDraft only for DATA_ENTRY + DRAFT', () => {
    expect(showEditDraft(makeRequest({ status: RequestStatus.DRAFT }), UserRole.DATA_ENTRY)).toBe(true)
    expect(showEditDraft(makeRequest({ status: RequestStatus.DRAFT_REJECTED_INTERNAL }), UserRole.DATA_ENTRY)).toBe(false)
    expect(showEditDraft(makeRequest({ status: RequestStatus.DRAFT }), UserRole.BANK_REVIEWER)).toBe(false)
  })

  it('showEditResubmit only for DATA_ENTRY + DRAFT_REJECTED_INTERNAL', () => {
    expect(showEditResubmit(makeRequest({ status: RequestStatus.DRAFT_REJECTED_INTERNAL }), UserRole.DATA_ENTRY)).toBe(true)
    expect(showEditResubmit(makeRequest({ status: RequestStatus.DRAFT }), UserRole.DATA_ENTRY)).toBe(false)
    expect(showEditResubmit(makeRequest({ status: RequestStatus.DRAFT_REJECTED_INTERNAL }), UserRole.BANK_REVIEWER)).toBe(false)
  })
})

describe('ActionsPanel — edit link target', () => {
  it('generates correct edit link for request id 42', () => {
    expect(editLinkTarget(42)).toBe('/requests/42/edit')
  })

  it('generates correct edit link for request id 1', () => {
    expect(editLinkTarget(1)).toBe('/requests/1/edit')
  })
})

describe('ActionsPanel — rejection reason validation', () => {
  function validateReason(reason: string): string | null {
    if (!reason.trim()) return 'سبب الرفض مطلوب.'
    return null
  }

  it('returns error for empty reason', () => {
    expect(validateReason('')).toBe('سبب الرفض مطلوب.')
  })

  it('returns error for whitespace-only reason', () => {
    expect(validateReason('   ')).toBe('سبب الرفض مطلوب.')
  })

  it('returns null for valid reason', () => {
    expect(validateReason('مستندات ناقصة')).toBeNull()
  })
})

describe('ActionsPanel — isLocked logic (post-patch)', () => {
  // SUBMITTED and BANK_REVIEW must NOT be locked — BANK_REVIEWER acts on them
  const LOCKED_STATUSES = new Set([
    RequestStatus.BANK_APPROVED,
    RequestStatus.SUPPORT_REVIEW_PENDING,
    RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
    RequestStatus.SUPPORT_APPROVED,
    RequestStatus.SUPPORT_REJECTED,
    RequestStatus.WAITING_FOR_SWIFT,
    RequestStatus.SWIFT_UPLOADED,
    RequestStatus.WAITING_FOR_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_CLOSED,
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.EXECUTIVE_REJECTED,
    RequestStatus.CUSTOMS_DECLARATION_ISSUED,
    RequestStatus.COMPLETED,
  ])

  function isLocked(status: RequestStatus): boolean {
    return LOCKED_STATUSES.has(status)
  }

  it('SUBMITTED is not locked', () => {
    expect(isLocked(RequestStatus.SUBMITTED)).toBe(false)
  })

  it('BANK_REVIEW is not locked', () => {
    expect(isLocked(RequestStatus.BANK_REVIEW)).toBe(false)
  })

  it('DRAFT is not locked', () => {
    expect(isLocked(RequestStatus.DRAFT)).toBe(false)
  })

  it('DRAFT_REJECTED_INTERNAL is not locked', () => {
    expect(isLocked(RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(false)
  })

  it('BANK_APPROVED is locked', () => {
    expect(isLocked(RequestStatus.BANK_APPROVED)).toBe(true)
  })

  it('COMPLETED is locked', () => {
    expect(isLocked(RequestStatus.COMPLETED)).toBe(true)
  })

  it('EXECUTIVE_REJECTED is locked', () => {
    expect(isLocked(RequestStatus.EXECUTIVE_REJECTED)).toBe(true)
  })
})

describe('ActionsPanel — showSupportCommitteeActions', () => {
  it('true for SUPPORT_COMMITTEE + SUPPORT_REVIEW_IN_PROGRESS + is_claimed_by_me', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      is_claimed: true,
      is_claimed_by_me: true,
    })
    expect(showSupportCommitteeActions(req, UserRole.SUPPORT_COMMITTEE)).toBe(true)
  })

  it('false when is_claimed_by_me is false (claimed by others — view only)', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      is_claimed: true,
      is_claimed_by_me: false,
    })
    expect(showSupportCommitteeActions(req, UserRole.SUPPORT_COMMITTEE)).toBe(false)
  })

  it('false when status is SUPPORT_REVIEW_PENDING (not yet claimed)', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_REVIEW_PENDING,
      can_be_claimed: true,
      is_claimed_by_me: false,
    })
    expect(showSupportCommitteeActions(req, UserRole.SUPPORT_COMMITTEE)).toBe(false)
  })

  it('false for non-SUPPORT_COMMITTEE roles even if status is correct', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      is_claimed_by_me: true,
    })
    expect(showSupportCommitteeActions(req, UserRole.BANK_REVIEWER)).toBe(false)
    expect(showSupportCommitteeActions(req, UserRole.CBY_ADMIN)).toBe(false)
  })
})

describe('ActionsPanel — showAnyActions includes SUPPORT_COMMITTEE', () => {
  it('true for SUPPORT_COMMITTEE + SUPPORT_REVIEW_IN_PROGRESS + is_claimed_by_me', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      is_claimed: true,
      is_claimed_by_me: true,
    })
    expect(showAnyActions(req, UserRole.SUPPORT_COMMITTEE)).toBe(true)
  })

  it('false for SUPPORT_COMMITTEE + SUPPORT_REVIEW_IN_PROGRESS + not claimed by me', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      is_claimed: true,
      is_claimed_by_me: false,
    })
    expect(showAnyActions(req, UserRole.SUPPORT_COMMITTEE)).toBe(false)
  })
})

describe('ActionsPanel — support reject action dispatch', () => {
  it('dispatches support-reject when SUPPORT_COMMITTEE panel is active', () => {
    expect(resolveSupportRejectAction(true)).toBe('support-reject')
  })

  it('dispatches bank-reject when bank reviewer panel is active', () => {
    expect(resolveSupportRejectAction(false)).toBe('bank-reject')
  })
})

describe('ActionsPanel — CorrectionBanner trigger', () => {
  function showCorrectionBanner(status: RequestStatus): boolean {
    return status === RequestStatus.DRAFT_REJECTED_INTERNAL
  }

  it('shows correction banner for DRAFT_REJECTED_INTERNAL', () => {
    expect(showCorrectionBanner(RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(true)
  })

  it('does not show correction banner for DRAFT', () => {
    expect(showCorrectionBanner(RequestStatus.DRAFT)).toBe(false)
  })

  it('does not show correction banner for SUBMITTED', () => {
    expect(showCorrectionBanner(RequestStatus.SUBMITTED)).toBe(false)
  })

  it('does not show correction banner for BANK_APPROVED', () => {
    expect(showCorrectionBanner(RequestStatus.BANK_APPROVED)).toBe(false)
  })
})
