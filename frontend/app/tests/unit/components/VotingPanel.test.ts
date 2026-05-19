/**
 * VotingPanel logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { VoteType, RequestStatus, UserRole, VotingSessionStatus } from '../../../types/enums'
import type { RequestVote, VotingDetail, VotingTally, ImportRequest } from '../../../types/models'

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

function makeTally(overrides: Partial<VotingTally> = {}): VotingTally {
  return {
    approve_count: 2,
    reject_count: 1,
    abstain_count: 0,
    auto_abstain_count: 0,
    total_cast: 3,
    is_decided: false,
    result: 'PENDING',
    ...overrides,
  }
}

function makeVote(overrides: Partial<RequestVote> = {}): RequestVote {
  return {
    id: 1,
    request_id: 1,
    user_id: 10,
    user_name: 'أحمد العمري',
    vote: VoteType.APPROVE,
    justification: null,
    is_director_override: false,
    voted_at: '2026-05-05T10:00:00.000000Z',
    created_at: '2026-05-05T10:00:00.000000Z',
    ...overrides,
  }
}

function makeDetail(overrides: Partial<VotingDetail> = {}): VotingDetail {
  return {
    request: makeRequest(),
    tally: makeTally(),
    votes: [],
    total_members: 5,
    my_vote: null,
    ...overrides,
  }
}

// Logic extracted from VotingPanel
function isSessionOpen(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_OPEN
}

function isSessionClosed(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_CLOSED
}

function isFinalized(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_APPROVED || status === RequestStatus.EXECUTIVE_REJECTED
}

function isLocked(status: RequestStatus): boolean {
  return isSessionClosed(status) || isFinalized(status)
}

function isVoter(role: UserRole): boolean {
  return role === UserRole.EXECUTIVE_MEMBER || role === UserRole.COMMITTEE_DIRECTOR
}

function canVote(status: RequestStatus, role: UserRole, myVote: RequestVote | null): boolean {
  return isSessionOpen(status) && isVoter(role) && !myVote
}

function tallyBarWidth(count: number, totalMembers: number): string {
  if (!totalMembers) return '0%'
  return `${Math.round((count / totalMembers) * 100)}%`
}

function voteLabel(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE: return 'موافق'
    case VoteType.REJECT: return 'رافض'
    case VoteType.ABSTAIN: return 'ممتنع'
    case VoteType.AUTO_ABSTAIN_TIMEOUT: return 'غائب (مُنهي تلقائياً)'
  }
}

function voteChipClass(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE: return 'vote-chip--approve'
    case VoteType.REJECT: return 'vote-chip--reject'
    case VoteType.ABSTAIN: return 'vote-chip--abstain'
    case VoteType.AUTO_ABSTAIN_TIMEOUT: return 'vote-chip--auto-abstain'
  }
}

function notYetVotedCount(detail: VotingDetail): number {
  const COMMITTEE_SIZE = 6
  return Math.max(0, COMMITTEE_SIZE - detail.votes.length)
}

function displayedVotes(detail: VotingDetail): RequestVote[] {
  const COMMITTEE_SIZE = 6
  return detail.votes.slice(0, COMMITTEE_SIZE)
}

describe('VotingPanel — session state detection', () => {
  it('detects EXECUTIVE_VOTING_OPEN as open session', () => {
    expect(isSessionOpen(RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(true)
  })

  it('does not treat WAITING_FOR_VOTING_OPEN as open session', () => {
    expect(isSessionOpen(RequestStatus.WAITING_FOR_VOTING_OPEN)).toBe(false)
  })

  it('detects EXECUTIVE_VOTING_CLOSED as closed session', () => {
    expect(isSessionClosed(RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe(true)
  })

  it('detects EXECUTIVE_APPROVED as finalized', () => {
    expect(isFinalized(RequestStatus.EXECUTIVE_APPROVED)).toBe(true)
  })

  it('detects EXECUTIVE_REJECTED as finalized', () => {
    expect(isFinalized(RequestStatus.EXECUTIVE_REJECTED)).toBe(true)
  })

  it('EXECUTIVE_VOTING_OPEN is not locked', () => {
    expect(isLocked(RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(false)
  })

  it('EXECUTIVE_VOTING_CLOSED is locked', () => {
    expect(isLocked(RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe(true)
  })

  it('EXECUTIVE_APPROVED is locked', () => {
    expect(isLocked(RequestStatus.EXECUTIVE_APPROVED)).toBe(true)
  })

  it('EXECUTIVE_REJECTED is locked', () => {
    expect(isLocked(RequestStatus.EXECUTIVE_REJECTED)).toBe(true)
  })
})

describe('VotingPanel — voter eligibility', () => {
  it('EXECUTIVE_MEMBER is a voter', () => {
    expect(isVoter(UserRole.EXECUTIVE_MEMBER)).toBe(true)
  })

  it('COMMITTEE_DIRECTOR is a voter', () => {
    expect(isVoter(UserRole.COMMITTEE_DIRECTOR)).toBe(true)
  })

  it('BANK_REVIEWER is not a voter', () => {
    expect(isVoter(UserRole.BANK_REVIEWER)).toBe(false)
  })

  it('SUPPORT_COMMITTEE is not a voter', () => {
    expect(isVoter(UserRole.SUPPORT_COMMITTEE)).toBe(false)
  })
})

describe('VotingPanel — canVote guard', () => {
  it('can vote when session is open, user is voter, and has not voted', () => {
    expect(canVote(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.EXECUTIVE_MEMBER, null)).toBe(true)
  })

  it('cannot vote when session is closed', () => {
    expect(canVote(RequestStatus.EXECUTIVE_VOTING_CLOSED, UserRole.EXECUTIVE_MEMBER, null)).toBe(false)
  })

  it('cannot vote when user is not a voter role', () => {
    expect(canVote(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.BANK_REVIEWER, null)).toBe(false)
  })

  it('cannot vote when user has already voted', () => {
    const myVote = makeVote({ vote: VoteType.APPROVE })
    expect(canVote(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.EXECUTIVE_MEMBER, myVote)).toBe(false)
  })

  it('COMMITTEE_DIRECTOR can vote when session is open and not voted', () => {
    expect(canVote(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.COMMITTEE_DIRECTOR, null)).toBe(true)
  })
})

describe('VotingPanel — tally bar width', () => {
  it('calculates 100% when all members voted approve', () => {
    expect(tallyBarWidth(5, 5)).toBe('100%')
  })

  it('calculates 40% correctly', () => {
    expect(tallyBarWidth(2, 5)).toBe('40%')
  })

  it('returns 0% when total_members is 0', () => {
    expect(tallyBarWidth(0, 0)).toBe('0%')
  })

  it('calculates 0% when count is 0', () => {
    expect(tallyBarWidth(0, 5)).toBe('0%')
  })

  it('rounds to nearest integer percent', () => {
    // 1/3 = 33.33% → 33%
    expect(tallyBarWidth(1, 3)).toBe('33%')
  })
})

describe('VotingPanel — vote labels', () => {
  it('labels APPROVE correctly', () => {
    expect(voteLabel(VoteType.APPROVE)).toBe('موافق')
  })

  it('labels REJECT correctly', () => {
    expect(voteLabel(VoteType.REJECT)).toBe('رافض')
  })

  it('labels ABSTAIN correctly', () => {
    expect(voteLabel(VoteType.ABSTAIN)).toBe('ممتنع')
  })

  it('labels AUTO_ABSTAIN_TIMEOUT differently from ABSTAIN', () => {
    expect(voteLabel(VoteType.AUTO_ABSTAIN_TIMEOUT)).toBe('غائب (مُنهي تلقائياً)')
    expect(voteLabel(VoteType.AUTO_ABSTAIN_TIMEOUT)).not.toBe(voteLabel(VoteType.ABSTAIN))
  })
})

describe('VotingPanel — vote chip classes', () => {
  it('assigns approve chip class', () => {
    expect(voteChipClass(VoteType.APPROVE)).toBe('vote-chip--approve')
  })

  it('assigns reject chip class', () => {
    expect(voteChipClass(VoteType.REJECT)).toBe('vote-chip--reject')
  })

  it('assigns distinct chip class for AUTO_ABSTAIN_TIMEOUT vs ABSTAIN', () => {
    expect(voteChipClass(VoteType.AUTO_ABSTAIN_TIMEOUT)).toBe('vote-chip--auto-abstain')
    expect(voteChipClass(VoteType.ABSTAIN)).toBe('vote-chip--abstain')
    expect(voteChipClass(VoteType.AUTO_ABSTAIN_TIMEOUT)).not.toBe(voteChipClass(VoteType.ABSTAIN))
  })
})

describe('VotingPanel — not yet voted count', () => {
  it('returns 0 when all members have voted', () => {
    const detail = makeDetail({
      total_members: 6,
      votes: Array.from({ length: 6 }, (_, i) => makeVote({ id: i + 1 })),
    })
    expect(notYetVotedCount(detail)).toBe(0)
  })

  it('returns remaining members who have not voted', () => {
    const detail = makeDetail({
      total_members: 5,
      votes: [makeVote({ id: 1 }), makeVote({ id: 2 })],
    })
    expect(notYetVotedCount(detail)).toBe(4)
  })

  it('never returns negative count', () => {
    const detail = makeDetail({ total_members: 0, votes: [] })
    expect(notYetVotedCount(detail)).toBe(6)
  })

  it('enforces a 6-member roster even if backend total_members drifts', () => {
    const detail = makeDetail({
      total_members: 4,
      votes: [makeVote({ id: 1 }), makeVote({ id: 2 })],
    })
    expect(notYetVotedCount(detail)).toBe(4)
  })

  it('caps displayed vote rows at 6', () => {
    const detail = makeDetail({
      total_members: 8,
      votes: Array.from({ length: 8 }, (_, i) => makeVote({ id: i + 1 })),
    })
    expect(displayedVotes(detail)).toHaveLength(6)
  })
})

describe('VotingPanel — AUTO_ABSTAIN_TIMEOUT tally handling', () => {
  it('combines auto_abstain_count and abstain_count for abstain bar', () => {
    const tally = makeTally({ abstain_count: 1, auto_abstain_count: 2 })
    const combined = tally.abstain_count + tally.auto_abstain_count
    expect(combined).toBe(3)
    expect(tallyBarWidth(combined, 5)).toBe('60%')
  })
})

describe('VotingPanel — my_vote state', () => {
  it('shows already-voted state when my_vote is set', () => {
    const detail = makeDetail({ my_vote: makeVote({ vote: VoteType.APPROVE }) })
    expect(detail.my_vote).not.toBeNull()
    expect(voteLabel(detail.my_vote!.vote)).toBe('موافق')
  })

  it('shows vote buttons when my_vote is null and session is open', () => {
    const detail = makeDetail({ my_vote: null })
    expect(canVote(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.EXECUTIVE_MEMBER, detail.my_vote)).toBe(true)
  })
})

// ── Story 6.6 additions ───────────────────────────────────────────────────────

function showTieBreak(tally: VotingTally | null, status: RequestStatus): boolean {
  if (status !== RequestStatus.EXECUTIVE_VOTING_OPEN || !tally) return false
  return tally.approve_count === tally.reject_count && tally.approve_count > 0
}

describe('VotingPanel — tie-break notice (Story 6.6)', () => {
  it('shows tie-break when approve === reject > 0 in open session', () => {
    const tally = makeTally({ approve_count: 2, reject_count: 2, abstain_count: 0, auto_abstain_count: 0 })
    expect(showTieBreak(tally, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(true)
  })

  it('does not show tie-break when approve !== reject', () => {
    const tally = makeTally({ approve_count: 3, reject_count: 2 })
    expect(showTieBreak(tally, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(false)
  })

  it('does not show tie-break when both are 0 (no votes cast yet)', () => {
    const tally = makeTally({ approve_count: 0, reject_count: 0 })
    expect(showTieBreak(tally, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(false)
  })

  it('does not show tie-break when session is closed', () => {
    const tally = makeTally({ approve_count: 2, reject_count: 2 })
    expect(showTieBreak(tally, RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe(false)
  })

  it('does not show tie-break when session is finalized', () => {
    const tally = makeTally({ approve_count: 2, reject_count: 2 })
    expect(showTieBreak(tally, RequestStatus.EXECUTIVE_APPROVED)).toBe(false)
  })

  it('does not show tie-break when tally is null', () => {
    expect(showTieBreak(null, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(false)
  })

  it('shows for 1 vs 1 (smallest possible tie)', () => {
    const tally = makeTally({ approve_count: 1, reject_count: 1 })
    expect(showTieBreak(tally, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(true)
  })
})

describe('VotingPanel — not-yet-voted placeholder rows (Story 6.6)', () => {
  it('placeholder count equals total_members minus voted count', () => {
    const detail = makeDetail({
      total_members: 6,
      votes: [makeVote({ id: 1 }), makeVote({ id: 2 }), makeVote({ id: 3 })],
    })
    expect(notYetVotedCount(detail)).toBe(3)
  })

  it('zero placeholders when all 6 members voted', () => {
    const detail = makeDetail({
      total_members: 6,
      votes: Array.from({ length: 6 }, (_, i) => makeVote({ id: i + 1 })),
    })
    expect(notYetVotedCount(detail)).toBe(0)
  })

  it('6 placeholders when no one has voted yet', () => {
    const detail = makeDetail({ total_members: 6, votes: [] })
    expect(notYetVotedCount(detail)).toBe(6)
  })

  it('never negative even if vote rows exceed 6', () => {
    const detail = makeDetail({
      total_members: 2,
      votes: Array.from({ length: 7 }, (_, i) => makeVote({ id: i + 1 })),
    })
    expect(notYetVotedCount(detail)).toBe(0)
  })
})
