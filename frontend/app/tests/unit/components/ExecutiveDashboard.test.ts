/**
 * ExecutiveDashboard logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus, VotingSessionStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { ExecutiveDashboardStats } from '../../../composables/useDashboard'
import { makeImportRequest } from '../fixtures/request-data'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_name: 'بنك اليمن المركزي',
    status: RequestStatus.WAITING_FOR_VOTING_OPEN,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    amount: 100000,
    supplier_name: 'Global Supplier',
    goods_description: 'Industrial Equipment',
    voting_session_status: null,
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  })
}

// Logic extracted from ExecutiveDashboard
function shouldShowEmptyQueue(stats: ExecutiveDashboardStats): boolean {
  return stats.voting_queue.length === 0
}

function shouldShowCustomsDeclarationPending(stats: ExecutiveDashboardStats): boolean {
  return (stats.customs_declaration_pending ?? []).length > 0
}

function isVotingOpen(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_OPEN
}

function getWaitingHighlightClass(count: number): string {
  return count > 0 ? 'kpi-card--highlight' : ''
}

function getActiveSessionsHighlightClass(count: number): string {
  return count > 0 ? 'kpi-card--highlight-indigo' : ''
}

function getVotingRowClass(req: ImportRequest): string {
  return isVotingOpen(req.status) ? 'req-table__row--voting-open' : ''
}

describe('ExecutiveDashboard — queue empty state', () => {
  it('shows empty queue when voting_queue is empty', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0,
      active_voting_sessions: 0,
      decisions_approved: 0,
      decisions_rejected: 0,
      finalized_decisions: 0,
      voting_queue: [],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(true)
  })

  it('shows table when voting_queue has requests', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 1,
      active_voting_sessions: 0,
      decisions_approved: 0,
      decisions_rejected: 0,
      finalized_decisions: 0,
      voting_queue: [makeRequest()],
    }
    expect(shouldShowEmptyQueue(stats)).toBe(false)
  })
})

describe('ExecutiveDashboard — KPI highlights', () => {
  it('highlights waiting_for_voting_open card when count > 0', () => {
    expect(getWaitingHighlightClass(3)).toBe('kpi-card--highlight')
  })

  it('no highlight on waiting_for_voting_open when 0', () => {
    expect(getWaitingHighlightClass(0)).toBe('')
  })

  it('highlights active_voting_sessions card when count > 0', () => {
    expect(getActiveSessionsHighlightClass(2)).toBe('kpi-card--highlight-indigo')
  })

  it('no highlight on active_voting_sessions when 0', () => {
    expect(getActiveSessionsHighlightClass(0)).toBe('')
  })
})

describe('ExecutiveDashboard — KPI counts', () => {
  it('reads all KPI values correctly from stats', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 2,
      active_voting_sessions: 1,
      decisions_approved: 10,
      decisions_rejected: 3,
      finalized_decisions: 13,
      voting_queue: [],
    }
    expect(stats.waiting_for_voting_open).toBe(2)
    expect(stats.active_voting_sessions).toBe(1)
    expect(stats.decisions_approved).toBe(10)
    expect(stats.decisions_rejected).toBe(3)
    expect(stats.finalized_decisions).toBe(13)
  })
})

describe('ExecutiveDashboard — EXECUTIVE_VOTING_OPEN badge detection', () => {
  it('detects EXECUTIVE_VOTING_OPEN status for badge display', () => {
    expect(isVotingOpen(RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe(true)
  })

  it('does not flag WAITING_FOR_VOTING_OPEN as voting open', () => {
    expect(isVotingOpen(RequestStatus.WAITING_FOR_VOTING_OPEN)).toBe(false)
  })

  it('does not flag EXECUTIVE_VOTING_CLOSED as voting open', () => {
    expect(isVotingOpen(RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe(false)
  })

  it('does not flag EXECUTIVE_APPROVED as voting open', () => {
    expect(isVotingOpen(RequestStatus.EXECUTIVE_APPROVED)).toBe(false)
  })
})

describe('ExecutiveDashboard — row class for voting open', () => {
  it('applies voting-open row class to EXECUTIVE_VOTING_OPEN requests', () => {
    const req = makeRequest({
      status: RequestStatus.EXECUTIVE_VOTING_OPEN,
      voting_session_status: VotingSessionStatus.OPEN,
    })
    expect(getVotingRowClass(req)).toBe('req-table__row--voting-open')
  })

  it('applies no extra row class to WAITING_FOR_VOTING_OPEN requests', () => {
    const req = makeRequest({ status: RequestStatus.WAITING_FOR_VOTING_OPEN })
    expect(getVotingRowClass(req)).toBe('')
  })
})

describe('ExecutiveDashboard — mixed queue composition', () => {
  it('voting_queue can contain both WAITING_FOR_VOTING_OPEN and EXECUTIVE_VOTING_OPEN', () => {
    const queue = [
      makeRequest({ id: 1, status: RequestStatus.WAITING_FOR_VOTING_OPEN }),
      makeRequest({ id: 2, status: RequestStatus.EXECUTIVE_VOTING_OPEN, voting_session_status: VotingSessionStatus.OPEN }),
    ]
    const statuses = queue.map(r => r.status)
    expect(statuses).toContain(RequestStatus.WAITING_FOR_VOTING_OPEN)
    expect(statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)

    const openCount = queue.filter(r => isVotingOpen(r.status)).length
    expect(openCount).toBe(1)
  })
})

describe('ExecutiveDashboard — director customs pending section', () => {
  it('shows customs declaration pending section when director stats include requests', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0,
      active_voting_sessions: 0,
      decisions_approved: 1,
      decisions_rejected: 0,
      finalized_decisions: 1,
      voting_queue: [],
      customs_declaration_pending: [
        makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED }),
      ],
    }

    expect(shouldShowCustomsDeclarationPending(stats)).toBe(true)
  })

  it('hides customs declaration pending rows when list is absent or empty', () => {
    const stats: ExecutiveDashboardStats = {
      waiting_for_voting_open: 0,
      active_voting_sessions: 0,
      decisions_approved: 0,
      decisions_rejected: 0,
      finalized_decisions: 0,
      voting_queue: [],
    }

    expect(shouldShowCustomsDeclarationPending(stats)).toBe(false)
  })
})
