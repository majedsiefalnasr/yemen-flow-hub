/**
 * ActionsPanel director voting controls — pure function logic tests.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus, VotingSessionStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_id: 1,
    bank_name: null,
    merchant: null,
    status: RequestStatus.WAITING_FOR_VOTING_OPEN,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    currency: 'USD',
    amount: 100000,
    supplier_name: 'Supplier',
    goods_description: 'Goods',
    port_of_entry: 'Aden',
    notes: null,
    created_by: 1,
    submitted_by: null,
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
    swift_uploaded_by: null,
    swift_uploaded_at: null,
    voting_opened_by: null,
    voting_opened_at: null,
    voting_closed_by: null,
    voting_closed_at: null,
    voting_session_status: null,
    executive_decided_at: null,
    customs_issued_at: null,
    revision_count: 0,
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  }
}

// Logic extracted from ActionsPanel
const DIRECTOR_VOTING_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

function showDirectorVotingActions(userRole: UserRole, status: RequestStatus): boolean {
  return userRole === UserRole.COMMITTEE_DIRECTOR && DIRECTOR_VOTING_STATUSES.has(status)
}

function showAnyActions(
  userRole: UserRole,
  status: RequestStatus,
  isClaimedByMe: boolean,
): boolean {
  const bankReviewer = userRole === UserRole.BANK_REVIEWER
    && (status === RequestStatus.SUBMITTED || status === RequestStatus.BANK_REVIEW)
  const dataEntry = userRole === UserRole.DATA_ENTRY
    && (status === RequestStatus.DRAFT || status === RequestStatus.DRAFT_REJECTED_INTERNAL)
  const support = userRole === UserRole.SUPPORT_COMMITTEE
    && status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS
    && isClaimedByMe
  const director = showDirectorVotingActions(userRole, status)
  return bankReviewer || dataEntry || support || director
}

function validateOverride(decision: 'APPROVE' | 'REJECT' | null, justification: string): { decisionError: string, justificationError: string } {
  let decisionError = ''
  let justificationError = ''
  if (!decision) decisionError = 'يجب اختيار قرار (موافقة أو رفض).'
  if (justification.trim().length < 10) justificationError = 'المبرر مطلوب ويجب أن يكون 10 أحرف على الأقل.'
  return { decisionError, justificationError }
}

describe('ActionsPanel — showDirectorVotingActions', () => {
  it('shows director actions for COMMITTEE_DIRECTOR on WAITING_FOR_VOTING_OPEN', () => {
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.WAITING_FOR_VOTING_OPEN)).toBe(true)
  })

  it('shows director actions for COMMITTEE_DIRECTOR on EXECUTIVE_VOTING_OPEN', () => {
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(true)
  })

  it('shows director actions for COMMITTEE_DIRECTOR on EXECUTIVE_VOTING_CLOSED', () => {
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe(true)
  })

  it('does NOT show director actions for EXECUTIVE_MEMBER', () => {
    expect(showDirectorVotingActions(UserRole.EXECUTIVE_MEMBER, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(false)
  })

  it('does NOT show director actions for BANK_REVIEWER', () => {
    expect(showDirectorVotingActions(UserRole.BANK_REVIEWER, RequestStatus.WAITING_FOR_VOTING_OPEN)).toBe(false)
  })

  it('does NOT show director actions for COMMITTEE_DIRECTOR on non-voting status', () => {
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_APPROVED)).toBe(false)
  })

  it('does NOT show director actions for COMMITTEE_DIRECTOR on EXECUTIVE_REJECTED', () => {
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_REJECTED)).toBe(false)
  })
})

describe('ActionsPanel — showAnyActions includes director voting', () => {
  it('returns true for director on WAITING_FOR_VOTING_OPEN', () => {
    expect(showAnyActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.WAITING_FOR_VOTING_OPEN, false)).toBe(true)
  })

  it('returns true for director on EXECUTIVE_VOTING_OPEN', () => {
    expect(showAnyActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_OPEN, false)).toBe(true)
  })

  it('returns true for director on EXECUTIVE_VOTING_CLOSED', () => {
    expect(showAnyActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_CLOSED, false)).toBe(true)
  })

  it('returns false for director on EXECUTIVE_APPROVED (terminal)', () => {
    expect(showAnyActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_APPROVED, false)).toBe(false)
  })
})

describe('ActionsPanel — director status-specific controls', () => {
  it('shows open session button only for WAITING_FOR_VOTING_OPEN', () => {
    const req = makeRequest({ status: RequestStatus.WAITING_FOR_VOTING_OPEN })
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, req.status)
      && req.status === RequestStatus.WAITING_FOR_VOTING_OPEN).toBe(true)
  })

  it('shows close/override buttons only for EXECUTIVE_VOTING_OPEN', () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, voting_session_status: VotingSessionStatus.OPEN })
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, req.status)
      && req.status === RequestStatus.EXECUTIVE_VOTING_OPEN).toBe(true)
  })

  it('shows finalize button only for EXECUTIVE_VOTING_CLOSED', () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_CLOSED, voting_session_status: VotingSessionStatus.CLOSED })
    expect(showDirectorVotingActions(UserRole.COMMITTEE_DIRECTOR, req.status)
      && req.status === RequestStatus.EXECUTIVE_VOTING_CLOSED).toBe(true)
  })
})

describe('ActionsPanel — director override validation', () => {
  it('errors when decision is null', () => {
    const { decisionError } = validateOverride(null, 'مبرر كافٍ لاتخاذ قرار')
    expect(decisionError).toBeTruthy()
  })

  it('errors when justification is empty', () => {
    const { justificationError } = validateOverride('APPROVE', '')
    expect(justificationError).toBeTruthy()
  })

  it('errors when justification is less than 10 chars', () => {
    const { justificationError } = validateOverride('APPROVE', 'قصير')
    expect(justificationError).toBeTruthy()
  })

  it('passes validation with APPROVE decision and sufficient justification', () => {
    const { decisionError, justificationError } = validateOverride('APPROVE', 'المستندات كاملة ومتوافقة مع اللوائح')
    expect(decisionError).toBe('')
    expect(justificationError).toBe('')
  })

  it('passes validation with REJECT decision and sufficient justification', () => {
    const { decisionError, justificationError } = validateOverride('REJECT', 'مخالفة صريحة للوائح البنك المركزي')
    expect(decisionError).toBe('')
    expect(justificationError).toBe('')
  })

  it('errors on both fields simultaneously when both are invalid', () => {
    const { decisionError, justificationError } = validateOverride(null, '')
    expect(decisionError).toBeTruthy()
    expect(justificationError).toBeTruthy()
  })

  it('trims whitespace when checking justification length', () => {
    const { justificationError } = validateOverride('APPROVE', '         ')
    expect(justificationError).toBeTruthy()
  })
})
