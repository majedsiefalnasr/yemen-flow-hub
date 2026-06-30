/**
 * RequestDetail tab structure tests — Story 7.4.
 * Tests 3-tab layout (no votes tab) and inline VotingPanel visibility logic.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'

// ── Tab logic (mirrors index.vue) ──────────────────────────────────────────

type TabKey = 'overview' | 'documents' | 'parties'

function buildTabs(): Array<{ key: TabKey; label: string }> {
  return [
    { key: 'overview', label: 'المعلومات' },
    { key: 'documents', label: 'الوثائق' },
    { key: 'parties', label: 'الأطراف' },
  ]
}

function normalizeActiveTab(activeTab: string): TabKey {
  const valid: TabKey[] = ['overview', 'documents', 'parties']
  return valid.includes(activeTab as TabKey) ? (activeTab as TabKey) : 'overview'
}

// ── Inline VotingPanel logic (mirrors index.vue) ───────────────────────────

const EXECUTIVE_ROLES = new Set([UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR])

const VOTING_STAGE_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
])

function showVotingPanelInline(role: UserRole, status: RequestStatus): boolean {
  return EXECUTIVE_ROLES.has(role) && VOTING_STAGE_STATUSES.has(status)
}

// ── hasActions logic (mirrors index.vue) ──────────────────────────────────

const DIRECTOR_VOTING_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

function hasActions(
  role: UserRole,
  status: RequestStatus,
  opts: { isClaimedByMe?: boolean } = {},
): boolean {
  const bankReviewerAction =
    role === UserRole.BANK_REVIEWER &&
    (status === RequestStatus.SUBMITTED || status === RequestStatus.BANK_REVIEW)
  const dataEntryAction =
    (role === UserRole.DATA_ENTRY || role === UserRole.BANK_ADMIN) &&
    (status === RequestStatus.DRAFT ||
      status === RequestStatus.DRAFT_REJECTED_INTERNAL ||
      status === RequestStatus.BANK_RETURNED ||
      status === RequestStatus.SUPPORT_RETURNED)
  const supportAction =
    role === UserRole.SUPPORT_COMMITTEE &&
    status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS &&
    !!opts.isClaimedByMe
  const directorVotingAction =
    role === UserRole.COMMITTEE_DIRECTOR && DIRECTOR_VOTING_STATUSES.has(status)
  const directorCustomsAction =
    role === UserRole.COMMITTEE_DIRECTOR && status === RequestStatus.EXECUTIVE_APPROVED
  return (
    bankReviewerAction ||
    dataEntryAction ||
    supportAction ||
    directorVotingAction ||
    directorCustomsAction
  )
}

// ══════════════════════════════════════════════════════════════════════════

describe('Request detail — tab keys and labels (3-tab layout)', () => {
  it('has exactly 3 tabs', () => {
    expect(buildTabs().length).toBe(3)
  })

  it('uses "overview" key with label "المعلومات"', () => {
    const tab = buildTabs().find((t) => t.key === 'overview')
    expect(tab).toBeDefined()
    expect(tab!.label).toBe('المعلومات')
  })

  it('uses "documents" key with label "الوثائق"', () => {
    const tab = buildTabs().find((t) => t.key === 'documents')
    expect(tab).toBeDefined()
    expect(tab!.label).toBe('الوثائق')
  })

  it('uses "parties" key with label "الأطراف"', () => {
    const tab = buildTabs().find((t) => t.key === 'parties')
    expect(tab).toBeDefined()
    expect(tab!.label).toBe('الأطراف')
  })

  it('does NOT have a "votes" tab', () => {
    const keys = buildTabs().map((t) => t.key)
    expect(keys).not.toContain('votes')
  })

  it('does NOT have old "timeline" or "audit" tabs', () => {
    const keys = buildTabs().map((t) => t.key)
    expect(keys).not.toContain('timeline')
    expect(keys).not.toContain('audit')
  })

  it('tab order is overview → documents → parties', () => {
    const keys = buildTabs().map((t) => t.key)
    expect(keys).toEqual(['overview', 'documents', 'parties'])
  })
})

describe('Request detail — active tab normalization', () => {
  it('keeps overview tab active', () => {
    expect(normalizeActiveTab('overview')).toBe('overview')
  })

  it('keeps documents tab active', () => {
    expect(normalizeActiveTab('documents')).toBe('documents')
  })

  it('keeps parties tab active', () => {
    expect(normalizeActiveTab('parties')).toBe('parties')
  })

  it('resets unknown tab to overview', () => {
    expect(normalizeActiveTab('votes')).toBe('overview')
    expect(normalizeActiveTab('timeline')).toBe('overview')
    expect(normalizeActiveTab('audit')).toBe('overview')
    expect(normalizeActiveTab('anything')).toBe('overview')
  })

  it('resets empty string to overview', () => {
    expect(normalizeActiveTab('')).toBe('overview')
  })
})

describe('Request detail — inline VotingPanel visibility', () => {
  it('shows inline panel for EXECUTIVE_MEMBER in EXECUTIVE_VOTING_OPEN', () => {
    expect(
      showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.EXECUTIVE_VOTING_OPEN),
    ).toBe(true)
  })

  it('shows inline panel for EXECUTIVE_MEMBER in EXECUTIVE_VOTING_CLOSED', () => {
    expect(
      showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.EXECUTIVE_VOTING_CLOSED),
    ).toBe(true)
  })

  it('shows inline panel for EXECUTIVE_MEMBER in WAITING_FOR_VOTING_OPEN', () => {
    expect(
      showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.WAITING_FOR_VOTING_OPEN),
    ).toBe(true)
  })

  it('shows inline panel for COMMITTEE_DIRECTOR in EXECUTIVE_VOTING_OPEN', () => {
    expect(
      showVotingPanelInline(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_OPEN),
    ).toBe(true)
  })

  it('shows inline panel for COMMITTEE_DIRECTOR in EXECUTIVE_APPROVED', () => {
    expect(
      showVotingPanelInline(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_APPROVED),
    ).toBe(true)
  })

  it('shows inline panel for COMMITTEE_DIRECTOR in EXECUTIVE_REJECTED', () => {
    expect(
      showVotingPanelInline(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_REJECTED),
    ).toBe(true)
  })

  it('hides inline panel for DATA_ENTRY in any voting stage', () => {
    expect(showVotingPanelInline(UserRole.DATA_ENTRY, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(
      false,
    )
  })

  it('hides inline panel for BANK_REVIEWER in any voting stage', () => {
    expect(showVotingPanelInline(UserRole.BANK_REVIEWER, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(
      false,
    )
  })

  it('hides inline panel for SUPPORT_COMMITTEE in voting stages', () => {
    expect(
      showVotingPanelInline(UserRole.SUPPORT_COMMITTEE, RequestStatus.EXECUTIVE_VOTING_OPEN),
    ).toBe(false)
  })

  it('hides inline panel for EXECUTIVE_MEMBER in non-voting stage DRAFT', () => {
    expect(showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.DRAFT)).toBe(false)
  })

  it('hides inline panel for EXECUTIVE_MEMBER in BANK_REVIEW', () => {
    expect(showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.BANK_REVIEW)).toBe(false)
  })

  it('hides inline panel for EXECUTIVE_MEMBER in SUPPORT_REVIEW_IN_PROGRESS', () => {
    expect(
      showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS),
    ).toBe(false)
  })

  it('hides inline panel for EXECUTIVE_MEMBER in COMPLETED', () => {
    expect(showVotingPanelInline(UserRole.EXECUTIVE_MEMBER, RequestStatus.COMPLETED)).toBe(false)
  })
})

describe('Request detail — hasActions rail-card visibility', () => {
  it('BANK_REVIEWER has actions in SUBMITTED', () => {
    expect(hasActions(UserRole.BANK_REVIEWER, RequestStatus.SUBMITTED)).toBe(true)
  })

  it('BANK_REVIEWER has actions in BANK_REVIEW', () => {
    expect(hasActions(UserRole.BANK_REVIEWER, RequestStatus.BANK_REVIEW)).toBe(true)
  })

  it('BANK_REVIEWER has NO actions in BANK_APPROVED', () => {
    expect(hasActions(UserRole.BANK_REVIEWER, RequestStatus.BANK_APPROVED)).toBe(false)
  })

  it('DATA_ENTRY has actions in DRAFT', () => {
    expect(hasActions(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBe(true)
  })

  it('DATA_ENTRY has actions in DRAFT_REJECTED_INTERNAL', () => {
    expect(hasActions(UserRole.DATA_ENTRY, RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(true)
  })

  it('DATA_ENTRY has NO actions in SUBMITTED', () => {
    expect(hasActions(UserRole.DATA_ENTRY, RequestStatus.SUBMITTED)).toBe(false)
  })

  it('BANK_ADMIN has actions in DRAFT_REJECTED_INTERNAL', () => {
    expect(hasActions(UserRole.BANK_ADMIN, RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(true)
  })

  it('SUPPORT_COMMITTEE has actions in SUPPORT_REVIEW_IN_PROGRESS when claimed', () => {
    expect(
      hasActions(UserRole.SUPPORT_COMMITTEE, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, {
        isClaimedByMe: true,
      }),
    ).toBe(true)
  })

  it('SUPPORT_COMMITTEE has NO actions in SUPPORT_REVIEW_IN_PROGRESS when not claimed', () => {
    expect(
      hasActions(UserRole.SUPPORT_COMMITTEE, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, {
        isClaimedByMe: false,
      }),
    ).toBe(false)
  })

  it('SUPPORT_COMMITTEE has NO actions in SUPPORT_REVIEW_PENDING', () => {
    expect(
      hasActions(UserRole.SUPPORT_COMMITTEE, RequestStatus.SUPPORT_REVIEW_PENDING, {
        isClaimedByMe: true,
      }),
    ).toBe(false)
  })

  it('COMMITTEE_DIRECTOR has actions in WAITING_FOR_VOTING_OPEN', () => {
    expect(hasActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.WAITING_FOR_VOTING_OPEN)).toBe(
      true,
    )
  })

  it('COMMITTEE_DIRECTOR has actions in EXECUTIVE_VOTING_OPEN', () => {
    expect(hasActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(true)
  })

  it('COMMITTEE_DIRECTOR has actions in EXECUTIVE_VOTING_CLOSED', () => {
    expect(hasActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe(
      true,
    )
  })

  it('COMMITTEE_DIRECTOR has actions in EXECUTIVE_APPROVED (customs)', () => {
    expect(hasActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_APPROVED)).toBe(true)
  })

  it('COMMITTEE_DIRECTOR has NO actions in EXECUTIVE_REJECTED', () => {
    expect(hasActions(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_REJECTED)).toBe(false)
  })

  it('EXECUTIVE_MEMBER has NO actions (no ActionsPanel for this role)', () => {
    expect(hasActions(UserRole.EXECUTIVE_MEMBER, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(false)
  })

  it('CBY_ADMIN has NO actions', () => {
    expect(hasActions(UserRole.CBY_ADMIN, RequestStatus.DRAFT)).toBe(false)
  })
})
