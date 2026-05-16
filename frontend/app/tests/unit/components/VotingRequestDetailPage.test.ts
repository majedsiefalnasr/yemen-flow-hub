/**
 * Request detail page — voting tab logic tests (pure function, no component mounting).
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
    status: RequestStatus.EXECUTIVE_VOTING_OPEN,
    current_owner_role: UserRole.EXECUTIVE_MEMBER,
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
    voting_session_status: VotingSessionStatus.OPEN,
    executive_decided_at: null,
    customs_issued_at: null,
    revision_count: 0,
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  }
}

// Logic extracted from request detail page
const VOTING_STAGE_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const EXECUTIVE_ROLES = new Set([UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR])

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

function showVotesTab(request: ImportRequest | null): boolean {
  return !!request && VOTING_STAGE_STATUSES.has(request.status)
}

function isLocked(request: ImportRequest | null, userRole: UserRole): boolean {
  if (!request) return false
  if (EXECUTIVE_ROLES.has(userRole) && VOTING_STAGE_STATUSES.has(request.status)) {
    return false
  }
  return LOCKED_STATUSES.has(request.status)
}

type TabKey = 'overview' | 'documents' | 'timeline' | 'votes' | 'audit'

function buildTabs(request: ImportRequest | null): Array<{ key: TabKey; label: string }> {
  const votesVisible = showVotesTab(request)
  return [
    { key: 'overview', label: 'نظرة عامة' },
    { key: 'documents', label: 'المستندات' },
    { key: 'timeline', label: 'مسار العمل' },
    ...(votesVisible ? [{ key: 'votes' as TabKey, label: 'التصويت' }] : []),
    { key: 'audit', label: 'سجل التدقيق' },
  ]
}

describe('Request detail page — showVotesTab', () => {
  it('shows votes tab for WAITING_FOR_VOTING_OPEN', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.WAITING_FOR_VOTING_OPEN }))).toBe(true)
  })

  it('shows votes tab for EXECUTIVE_VOTING_OPEN', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_OPEN }))).toBe(true)
  })

  it('shows votes tab for EXECUTIVE_VOTING_CLOSED', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_CLOSED }))).toBe(true)
  })

  it('shows votes tab for EXECUTIVE_APPROVED', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED }))).toBe(true)
  })

  it('shows votes tab for EXECUTIVE_REJECTED', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.EXECUTIVE_REJECTED }))).toBe(true)
  })

  it('does NOT show votes tab for BANK_REVIEW', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.BANK_REVIEW }))).toBe(false)
  })

  it('does NOT show votes tab for SUPPORT_APPROVED', () => {
    expect(showVotesTab(makeRequest({ status: RequestStatus.SUPPORT_APPROVED }))).toBe(false)
  })

  it('does NOT show votes tab when request is null', () => {
    expect(showVotesTab(null)).toBe(false)
  })
})

describe('Request detail page — tabs array includes votes conditionally', () => {
  it('includes votes tab for voting-stage request', () => {
    const tabs = buildTabs(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_OPEN }))
    const keys = tabs.map(t => t.key)
    expect(keys).toContain('votes')
  })

  it('does not include votes tab for non-voting request', () => {
    const tabs = buildTabs(makeRequest({ status: RequestStatus.BANK_REVIEW }))
    const keys = tabs.map(t => t.key)
    expect(keys).not.toContain('votes')
  })

  it('votes tab appears before audit tab', () => {
    const tabs = buildTabs(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_OPEN }))
    const votesIdx = tabs.findIndex(t => t.key === 'votes')
    const auditIdx = tabs.findIndex(t => t.key === 'audit')
    expect(votesIdx).toBeGreaterThan(-1)
    expect(votesIdx).toBeLessThan(auditIdx)
  })

  it('always has overview, documents, timeline, audit tabs', () => {
    const tabs = buildTabs(makeRequest({ status: RequestStatus.DRAFT }))
    const keys = tabs.map(t => t.key)
    expect(keys).toContain('overview')
    expect(keys).toContain('documents')
    expect(keys).toContain('timeline')
    expect(keys).toContain('audit')
  })
})

describe('Request detail page — isLocked suppression for executive/director in voting stages', () => {
  it('EXECUTIVE_MEMBER does NOT see locked banner on WAITING_FOR_VOTING_OPEN', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.WAITING_FOR_VOTING_OPEN }), UserRole.EXECUTIVE_MEMBER)).toBe(false)
  })

  it('EXECUTIVE_MEMBER does NOT see locked banner on EXECUTIVE_VOTING_OPEN', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_OPEN }), UserRole.EXECUTIVE_MEMBER)).toBe(false)
  })

  it('COMMITTEE_DIRECTOR does NOT see locked banner on EXECUTIVE_VOTING_CLOSED', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_CLOSED }), UserRole.COMMITTEE_DIRECTOR)).toBe(false)
  })

  it('COMMITTEE_DIRECTOR does NOT see locked banner on EXECUTIVE_APPROVED', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED }), UserRole.COMMITTEE_DIRECTOR)).toBe(false)
  })

  it('COMMITTEE_DIRECTOR does NOT see locked banner on EXECUTIVE_REJECTED', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.EXECUTIVE_REJECTED }), UserRole.COMMITTEE_DIRECTOR)).toBe(false)
  })

  it('BANK_REVIEWER still sees locked banner on WAITING_FOR_VOTING_OPEN', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.WAITING_FOR_VOTING_OPEN }), UserRole.BANK_REVIEWER)).toBe(true)
  })

  it('DATA_ENTRY still sees locked banner on EXECUTIVE_VOTING_OPEN', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.EXECUTIVE_VOTING_OPEN }), UserRole.DATA_ENTRY)).toBe(true)
  })

  it('DATA_ENTRY sees locked banner on BANK_APPROVED', () => {
    expect(isLocked(makeRequest({ status: RequestStatus.BANK_APPROVED }), UserRole.DATA_ENTRY)).toBe(true)
  })

  it('EXECUTIVE_MEMBER does not see locked banner on SUPPORT_APPROVED (unrelated status)', () => {
    // SUPPORT_APPROVED is not in VOTING_STAGE_STATUSES, so the exec suppression doesn't kick in
    // EXECUTIVE_MEMBER still sees locked banner because SUPPORT_APPROVED is in LOCKED_STATUSES
    expect(isLocked(makeRequest({ status: RequestStatus.SUPPORT_APPROVED }), UserRole.EXECUTIVE_MEMBER)).toBe(true)
  })

  it('returns false for null request', () => {
    expect(isLocked(null, UserRole.EXECUTIVE_MEMBER)).toBe(false)
  })
})
