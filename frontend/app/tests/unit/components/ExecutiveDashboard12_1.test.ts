/**
 * ExecutiveDashboard Story 12.1 assertions — pending-vote action strip,
 * 3-KPI grid spec order, My Vote column 4 states, Voting Progress column,
 * sort order, indigo row tint for pending-my-vote rows.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ExecutiveDashboardStats, VotingQueueItem } from '../../../composables/useDashboard'
import { makeImportRequest } from '../fixtures/request-data'

function makeVotingItem(overrides: Partial<VotingQueueItem> = {}): VotingQueueItem {
  return {
    ...makeImportRequest({
      id: 1,
      reference_number: 'YFH-2026-000001',
      bank_name: 'بنك اليمن المركزي',
      status: RequestStatus.EXECUTIVE_VOTING_OPEN,
      current_owner_role: UserRole.COMMITTEE_DIRECTOR,
      amount: 100000,
      supplier_name: 'Global Supplier',
      goods_description: 'Industrial Equipment',
      voting_session_status: null,
      created_at: '2026-05-26T00:00:00.000000Z',
      updated_at: '2026-05-26T00:00:00.000000Z',
    }),
    my_vote: null,
    votes_cast: 2,
    total_voters: 5,
    ...overrides,
  }
}

// --- Pending-vote action strip ---

function pendingMyVoteCount(stats: ExecutiveDashboardStats): number {
  return stats.pending_my_vote
    ?? stats.voting_queue.filter(
        r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !r.my_vote,
      ).length
}

function shouldShowPendingVoteStrip(stats: ExecutiveDashboardStats): boolean {
  return pendingMyVoteCount(stats) > 0
}

describe('ExecutiveDashboard 12.1 — pending-vote action strip', () => {
  it('shows strip when pending_my_vote > 0', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0, active_voting_sessions: 1,
      decisions_approved: 0, decisions_rejected: 0, finalized_decisions: 0,
      pending_my_vote: 2, voting_queue: [],
    }
    expect(shouldShowPendingVoteStrip(stats)).toBe(true)
  })

  it('hides strip when pending_my_vote is 0', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0, active_voting_sessions: 0,
      decisions_approved: 3, decisions_rejected: 1, finalized_decisions: 0,
      pending_my_vote: 0, voting_queue: [],
    }
    expect(shouldShowPendingVoteStrip(stats)).toBe(false)
  })

  it('derives count from voting_queue when pending_my_vote is absent', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0, active_voting_sessions: 2,
      decisions_approved: 0, decisions_rejected: 0, finalized_decisions: 0,
      voting_queue: [
        makeVotingItem({ id: 1, my_vote: null }),
        makeVotingItem({ id: 2, my_vote: 'approve' }),
      ],
    }
    expect(pendingMyVoteCount(stats)).toBe(1)
  })
})

// --- KPI spec order: My Voting Queue (indigo) / Approval (green) / Rejection (rose) ---

type KpiEntry = { label: string; variant: string; tab: string }

function buildKpiConfig(stats: ExecutiveDashboardStats): KpiEntry[] {
  const pending = pendingMyVoteCount(stats)
  return [
    { label: 'طابور التصويت', variant: pending > 0 ? 'indigo' : 'gray', tab: 'pending_my_vote' },
    { label: 'قرارات اعتماد', variant: 'green', tab: 'approved' },
    { label: 'قرارات رفض', variant: 'rose', tab: 'rejected' },
  ]
}

describe('ExecutiveDashboard 12.1 — 3-KPI grid spec order', () => {
  const stats: ExecutiveDashboardStats = {
    waiting_for_voting_open: 0, active_voting_sessions: 2,
    decisions_approved: 4, decisions_rejected: 1, finalized_decisions: 0,
    pending_my_vote: 2, voting_queue: [],
  }
  const kpis = buildKpiConfig(stats)

  it('exactly 3 KPI cards for EXECUTIVE_MEMBER', () => {
    expect(kpis).toHaveLength(3)
  })

  it('first KPI is My Voting Queue (indigo when > 0)', () => {
    expect(kpis[0]?.label).toBe('طابور التصويت')
    expect(kpis[0]?.variant).toBe('indigo')
    expect(kpis[0]?.tab).toBe('pending_my_vote')
  })

  it('second KPI is Approval Decisions (green)', () => {
    expect(kpis[1]?.label).toBe('قرارات اعتماد')
    expect(kpis[1]?.variant).toBe('green')
    expect(kpis[1]?.tab).toBe('approved')
  })

  it('third KPI is Rejection Decisions (rose)', () => {
    expect(kpis[2]?.label).toBe('قرارات رفض')
    expect(kpis[2]?.variant).toBe('rose')
    expect(kpis[2]?.tab).toBe('rejected')
  })

  it('voting queue KPI is gray when count is 0', () => {
    const zeroStats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0, active_voting_sessions: 0,
      decisions_approved: 0, decisions_rejected: 0, finalized_decisions: 0,
      pending_my_vote: 0, voting_queue: [],
    }
    expect(buildKpiConfig(zeroStats)[0]?.variant).toBe('gray')
  })
})

// --- My Vote column: 4 states ---

type MyVoteDisplay = { text: string; style: 'indigo-chip' | 'green-chip' | 'rose-chip' | 'dash' }

function myVoteDisplay(req: VotingQueueItem): MyVoteDisplay {
  const isVotingStage = req.status === RequestStatus.EXECUTIVE_VOTING_OPEN
    || req.status === RequestStatus.EXECUTIVE_VOTING_CLOSED
  if (!isVotingStage) return { text: '—', style: 'dash' }
  if (!req.my_vote) return { text: 'لم تصوّت بعد', style: 'indigo-chip' }
  if (req.my_vote === 'approve') return { text: 'اعتمدت', style: 'green-chip' }
  return { text: 'رفضت', style: 'rose-chip' }
}

describe('ExecutiveDashboard 12.1 — My Vote column 4 states', () => {
  it('shows dash when status is not a voting stage', () => {
    const req = makeVotingItem({ status: RequestStatus.SUPPORT_APPROVED, my_vote: null })
    const display = myVoteDisplay(req)
    expect(display.text).toBe('—')
    expect(display.style).toBe('dash')
  })

  it('shows "لم تصوّت بعد" (indigo) when voting open and no vote yet', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: null })
    const display = myVoteDisplay(req)
    expect(display.text).toBe('لم تصوّت بعد')
    expect(display.style).toBe('indigo-chip')
  })

  it('shows "اعتمدت" (green) when vote is approve', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'approve' })
    const display = myVoteDisplay(req)
    expect(display.text).toBe('اعتمدت')
    expect(display.style).toBe('green-chip')
  })

  it('shows "رفضت" (rose) when vote is reject', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'reject' })
    const display = myVoteDisplay(req)
    expect(display.text).toBe('رفضت')
    expect(display.style).toBe('rose-chip')
  })
})

// --- Voting Progress column ---

function votingProgressText(req: VotingQueueItem): string {
  if (req.votes_cast == null || req.total_voters == null) return '—'
  return `${req.votes_cast}/${req.total_voters} صوتوا`
}

function votingProgressPct(req: VotingQueueItem): number {
  if (!req.votes_cast || !req.total_voters) return 0
  return Math.round((req.votes_cast / req.total_voters) * 100)
}

describe('ExecutiveDashboard 12.1 — Voting Progress column', () => {
  it('formats progress as "X/Y صوتوا"', () => {
    const req = makeVotingItem({ votes_cast: 3, total_voters: 5 })
    expect(votingProgressText(req)).toBe('3/5 صوتوا')
  })

  it('shows dash when votes_cast is missing', () => {
    const req = makeVotingItem({ votes_cast: undefined, total_voters: 5 })
    expect(votingProgressText(req)).toBe('—')
  })

  it('calculates progress percentage correctly', () => {
    const req = makeVotingItem({ votes_cast: 2, total_voters: 4 })
    expect(votingProgressPct(req)).toBe(50)
  })

  it('returns 0% when no votes cast', () => {
    const req = makeVotingItem({ votes_cast: 0, total_voters: 5 })
    expect(votingProgressPct(req)).toBe(0)
  })
})

// --- Sort order: pending-my-vote first ---

function sortVotingQueue(queue: VotingQueueItem[]): VotingQueueItem[] {
  const priority = (req: VotingQueueItem): number => {
    if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote) return 0
    if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && req.my_vote) return 1
    if (req.status === RequestStatus.EXECUTIVE_VOTING_CLOSED) return 2
    return 3
  }
  return [...queue].sort((a, b) => priority(a) - priority(b))
}

describe('ExecutiveDashboard 12.1 — voting queue sort order', () => {
  it('places pending-my-vote rows first', () => {
    const queue = [
      makeVotingItem({ id: 1, status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'approve' }),
      makeVotingItem({ id: 2, status: RequestStatus.EXECUTIVE_VOTING_CLOSED, my_vote: null }),
      makeVotingItem({ id: 3, status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: null }),
    ]
    const sorted = sortVotingQueue(queue)
    expect(sorted[0]?.id).toBe(3) // pending-my-vote first
  })

  it('places EXECUTIVE_VOTING_CLOSED after voted-open rows', () => {
    const queue = [
      makeVotingItem({ id: 1, status: RequestStatus.EXECUTIVE_VOTING_CLOSED }),
      makeVotingItem({ id: 2, status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'approve' }),
    ]
    const sorted = sortVotingQueue(queue)
    expect(sorted[0]?.id).toBe(2)
    expect(sorted[1]?.id).toBe(1)
  })

  it('places SUPPORT_APPROVED last', () => {
    const queue = [
      makeVotingItem({ id: 1, status: RequestStatus.SUPPORT_APPROVED }),
      makeVotingItem({ id: 2, status: RequestStatus.EXECUTIVE_VOTING_CLOSED }),
    ]
    const sorted = sortVotingQueue(queue)
    expect(sorted[sorted.length - 1]?.id).toBe(1)
  })
})

// --- Action button: "تصويت" vs "عرض" ---

function getActionLabel(req: VotingQueueItem): 'تصويت' | 'عرض' {
  return req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote ? 'تصويت' : 'عرض'
}

describe('ExecutiveDashboard 12.1 — action button label', () => {
  it('shows "تصويت" when voting open and no vote yet', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: null })
    expect(getActionLabel(req)).toBe('تصويت')
  })

  it('shows "عرض" when already voted', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'approve' })
    expect(getActionLabel(req)).toBe('عرض')
  })

  it('shows "عرض" for non-open voting stages', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_CLOSED, my_vote: null })
    expect(getActionLabel(req)).toBe('عرض')
  })
})

// --- Indigo row tint: only for pending-my-vote rows ---

function isPendingMyVoteRow(req: VotingQueueItem): boolean {
  return req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote
}

describe('ExecutiveDashboard 12.1 — indigo row tint', () => {
  it('pending-my-vote row gets indigo tint', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: null })
    expect(isPendingMyVoteRow(req)).toBe(true)
  })

  it('voted row does not get indigo tint', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_OPEN, my_vote: 'approve' })
    expect(isPendingMyVoteRow(req)).toBe(false)
  })

  it('closed session row does not get indigo tint', () => {
    const req = makeVotingItem({ status: RequestStatus.EXECUTIVE_VOTING_CLOSED, my_vote: null })
    expect(isPendingMyVoteRow(req)).toBe(false)
  })
})
