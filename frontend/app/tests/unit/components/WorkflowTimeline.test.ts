/**
 * WorkflowTimeline — unit tests for stage classification and terminal marking logic.
 * Uses the same pure-logic extraction pattern as ActionsPanel.test.ts
 * (no component mounting needed, no @vue/test-utils dependency).
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { RequestStageHistory } from '../../../types/models'

// ─── Reproduce the core logic from WorkflowTimeline.vue ──────────────────────

const WORKFLOW_STAGE_ORDER: RequestStatus[] = [
  RequestStatus.DRAFT,
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
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
]

const BRANCH_STATUSES = new Set<RequestStatus>([
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
])

// Only EXECUTIVE_REJECTED is a dead-end terminal — COMPLETED is a success state
const TERMINAL_STATUSES = new Set<RequestStatus>([
  RequestStatus.EXECUTIVE_REJECTED,
])

type StageState = 'completed' | 'current' | 'future' | 'terminal' | 'skipped'

function getStageState(
  status: RequestStatus,
  currentStatus: RequestStatus,
  visitedStatuses: Set<string>,
): StageState {
  const currentIndex = WORKFLOW_STAGE_ORDER.indexOf(currentStatus)
  const idx = WORKFLOW_STAGE_ORDER.indexOf(status)
  const isCurrent = status === currentStatus
  const isTerminal = TERMINAL_STATUSES.has(status) && isCurrent

  if (isTerminal) return 'terminal'
  if (isCurrent) return 'current'
  if (currentIndex === -1) return 'future'
  if (idx < currentIndex) {
    if (BRANCH_STATUSES.has(status) && !visitedStatuses.has(status)) return 'skipped'
    return 'completed'
  }
  return 'future'
}

function getCurrentEntry(history: RequestStageHistory[], currentStatus: RequestStatus): RequestStageHistory | null {
  const sorted = [...history].sort((a, b) => a.created_at.localeCompare(b.created_at))
  return [...sorted].reverse().find(e => e.to_status === currentStatus) ?? null
}

function getVisitedStatuses(history: RequestStageHistory[]): Set<string> {
  return new Set(history.map(e => e.to_status).filter(Boolean) as string[])
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const makeEntry = (overrides: Partial<RequestStageHistory> = {}): RequestStageHistory => ({
  id: 1,
  request_id: 5,
  from_status: 'DRAFT',
  to_status: 'SUBMITTED',
  from_owner_role: 'DATA_ENTRY',
  to_owner_role: 'BANK_REVIEWER',
  actor_id: 3,
  actor_role: 'DATA_ENTRY',
  performed_by: { id: 3, name: 'Ali Ahmed', role: 'DATA_ENTRY' },
  action: 'submit',
  notes: null,
  metadata: null,
  created_at: '2026-05-17T08:00:00.000Z',
  ...overrides,
})

/** Happy-path history: DRAFT → SUBMITTED → BANK_REVIEW → BANK_APPROVED */
const happyPathHistory: RequestStageHistory[] = [
  makeEntry({ id: 1, from_status: null, to_status: 'DRAFT', created_at: '2026-05-01T08:00:00Z' }),
  makeEntry({ id: 2, from_status: 'DRAFT', to_status: 'SUBMITTED', created_at: '2026-05-02T08:00:00Z' }),
  makeEntry({ id: 3, from_status: 'SUBMITTED', to_status: 'BANK_REVIEW', created_at: '2026-05-03T08:00:00Z' }),
  makeEntry({ id: 4, from_status: 'BANK_REVIEW', to_status: 'BANK_APPROVED', created_at: '2026-05-04T08:00:00Z' }),
]

// ─── Stage classification — happy path ───────────────────────────────────────

describe('WorkflowTimeline stage classification', () => {
  it('covers all 18 canonical stages — none missing', () => {
    expect(WORKFLOW_STAGE_ORDER).toHaveLength(18)
    const all = Object.values(RequestStatus)
    for (const s of all) {
      expect(WORKFLOW_STAGE_ORDER).toContain(s)
    }
  })

  it('classifies the current status as "current"', () => {
    const visited = new Set<string>()
    expect(getStageState(RequestStatus.SUBMITTED, RequestStatus.SUBMITTED, visited)).toBe('current')
    expect(getStageState(RequestStatus.BANK_APPROVED, RequestStatus.BANK_APPROVED, visited)).toBe('current')
  })

  it('classifies linear stages before current as "completed"', () => {
    const visited = new Set<string>(['DRAFT', 'SUBMITTED', 'BANK_REVIEW', 'BANK_APPROVED'])
    expect(getStageState(RequestStatus.DRAFT, RequestStatus.SUBMITTED, visited)).toBe('completed')
    expect(getStageState(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW, visited)).toBe('completed')
    expect(getStageState(RequestStatus.BANK_APPROVED, RequestStatus.SUPPORT_REVIEW_PENDING, visited)).toBe('completed')
  })

  it('classifies stages after current as "future"', () => {
    const visited = new Set<string>(['DRAFT', 'SUBMITTED'])
    expect(getStageState(RequestStatus.BANK_REVIEW, RequestStatus.SUBMITTED, visited)).toBe('future')
    expect(getStageState(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.BANK_APPROVED, visited)).toBe('future')
    expect(getStageState(RequestStatus.COMPLETED, RequestStatus.DRAFT, visited)).toBe('future')
  })

  it('DRAFT at index 0 has all other stages as future', () => {
    const visited = new Set<string>(['DRAFT'])
    const states = WORKFLOW_STAGE_ORDER.map(s => getStageState(s, RequestStatus.DRAFT, visited))
    expect(states[0]).toBe('current')
    states.slice(1).forEach(s => expect(s).toBe('future'))
  })

  it('SUPPORT_REVIEW_IN_PROGRESS: SUPPORT_REVIEW_PENDING is completed, further stages are future', () => {
    const visited = new Set<string>(['DRAFT', 'SUBMITTED', 'BANK_REVIEW', 'BANK_APPROVED', 'SUPPORT_REVIEW_PENDING', 'SUPPORT_REVIEW_IN_PROGRESS'])
    expect(getStageState(RequestStatus.SUPPORT_REVIEW_PENDING, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, visited)).toBe('completed')
    expect(getStageState(RequestStatus.SUPPORT_APPROVED, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, visited)).toBe('future')
  })
})

// ─── Branch state (skipped) logic ────────────────────────────────────────────

describe('WorkflowTimeline branch state — skipped vs completed', () => {
  it('DRAFT_REJECTED_INTERNAL shows "skipped" on a happy-path request at BANK_APPROVED', () => {
    const visited = getVisitedStatuses(happyPathHistory)
    expect(getStageState(RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.BANK_APPROVED, visited)).toBe('skipped')
  })

  it('SUPPORT_REJECTED shows "skipped" on a request at WAITING_FOR_SWIFT (no rejection)', () => {
    const history = [
      ...happyPathHistory,
      makeEntry({ id: 5, from_status: 'BANK_APPROVED', to_status: 'SUPPORT_REVIEW_PENDING', created_at: '2026-05-05T08:00:00Z' }),
      makeEntry({ id: 6, from_status: 'SUPPORT_REVIEW_PENDING', to_status: 'SUPPORT_REVIEW_IN_PROGRESS', created_at: '2026-05-06T08:00:00Z' }),
      makeEntry({ id: 7, from_status: 'SUPPORT_REVIEW_IN_PROGRESS', to_status: 'SUPPORT_APPROVED', created_at: '2026-05-07T08:00:00Z' }),
      makeEntry({ id: 8, from_status: 'SUPPORT_APPROVED', to_status: 'WAITING_FOR_SWIFT', created_at: '2026-05-08T08:00:00Z' }),
    ]
    const visited = getVisitedStatuses(history)
    expect(getStageState(RequestStatus.SUPPORT_REJECTED, RequestStatus.WAITING_FOR_SWIFT, visited)).toBe('skipped')
  })

  it('DRAFT_REJECTED_INTERNAL shows "completed" when the request actually visited it', () => {
    const history = [
      makeEntry({ id: 1, from_status: null, to_status: 'DRAFT', created_at: '2026-05-01T08:00:00Z' }),
      makeEntry({ id: 2, from_status: 'DRAFT', to_status: 'SUBMITTED', created_at: '2026-05-02T08:00:00Z' }),
      makeEntry({ id: 3, from_status: 'SUBMITTED', to_status: 'DRAFT_REJECTED_INTERNAL', created_at: '2026-05-03T08:00:00Z' }),
      makeEntry({ id: 4, from_status: 'DRAFT_REJECTED_INTERNAL', to_status: 'SUBMITTED', created_at: '2026-05-04T08:00:00Z' }),
      makeEntry({ id: 5, from_status: 'SUBMITTED', to_status: 'BANK_REVIEW', created_at: '2026-05-05T08:00:00Z' }),
    ]
    const visited = getVisitedStatuses(history)
    expect(getStageState(RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.BANK_REVIEW, visited)).toBe('completed')
  })

  it('EXECUTIVE_REJECTED shows "skipped" on a COMPLETED request (approved path)', () => {
    const history = [
      ...happyPathHistory,
      makeEntry({ id: 5, from_status: 'BANK_APPROVED', to_status: 'SUPPORT_APPROVED', created_at: '2026-05-05T08:00:00Z' }),
      makeEntry({ id: 6, from_status: 'SUPPORT_APPROVED', to_status: 'SWIFT_UPLOADED', created_at: '2026-05-06T08:00:00Z' }),
      makeEntry({ id: 7, from_status: 'SWIFT_UPLOADED', to_status: 'EXECUTIVE_APPROVED', created_at: '2026-05-07T08:00:00Z' }),
      makeEntry({ id: 8, from_status: 'EXECUTIVE_APPROVED', to_status: 'CUSTOMS_DECLARATION_ISSUED', created_at: '2026-05-08T08:00:00Z' }),
      makeEntry({ id: 9, from_status: 'CUSTOMS_DECLARATION_ISSUED', to_status: 'COMPLETED', created_at: '2026-05-09T08:00:00Z' }),
    ]
    const visited = getVisitedStatuses(history)
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.COMPLETED, visited)).toBe('skipped')
  })
})

// ─── Terminal state ───────────────────────────────────────────────────────────

describe('WorkflowTimeline terminal state — EXECUTIVE_REJECTED only', () => {
  it('classifies EXECUTIVE_REJECTED as "terminal" when it is the current status', () => {
    const visited = new Set<string>(['EXECUTIVE_REJECTED'])
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.EXECUTIVE_REJECTED, visited)).toBe('terminal')
  })

  it('COMPLETED is NOT terminal — it is a success "current" state', () => {
    const visited = new Set<string>(['COMPLETED'])
    expect(getStageState(RequestStatus.COMPLETED, RequestStatus.COMPLETED, visited)).toBe('current')
  })

  it('only EXECUTIVE_REJECTED is in the terminal set', () => {
    expect(TERMINAL_STATUSES.has(RequestStatus.EXECUTIVE_REJECTED)).toBe(true)
    expect(TERMINAL_STATUSES.has(RequestStatus.COMPLETED)).toBe(false)
    expect(TERMINAL_STATUSES.size).toBe(1)
  })

  it('no other status is in the terminal set', () => {
    const nonTerminal = Object.values(RequestStatus).filter(
      s => s !== RequestStatus.EXECUTIVE_REJECTED,
    )
    for (const s of nonTerminal) {
      expect(TERMINAL_STATUSES.has(s)).toBe(false)
    }
  })

  it('EXECUTIVE_REJECTED is "future" when the request is at an earlier stage', () => {
    const visited = new Set<string>(['BANK_APPROVED'])
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.BANK_APPROVED, visited)).toBe('future')
  })

  it('EXECUTIVE_REJECTED is "skipped" on a COMPLETED (approved) request', () => {
    const visited = new Set<string>(['DRAFT', 'SUBMITTED', 'BANK_APPROVED', 'EXECUTIVE_APPROVED', 'COMPLETED'])
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.COMPLETED, visited)).toBe('skipped')
  })
})

// ─── getCurrentEntry ──────────────────────────────────────────────────────────

describe('WorkflowTimeline getCurrentEntry', () => {
  it('returns null when history is empty', () => {
    expect(getCurrentEntry([], RequestStatus.SUBMITTED)).toBeNull()
  })

  it('returns the last entry that transitioned TO the current status', () => {
    const entries = [
      makeEntry({ id: 1, to_status: 'SUBMITTED' }),
      makeEntry({ id: 2, to_status: 'BANK_REVIEW' }),
    ]
    expect(getCurrentEntry(entries, RequestStatus.BANK_REVIEW)?.id).toBe(2)
  })

  it('returns the most recent matching entry by created_at regardless of array order', () => {
    // Deliberately out of order to verify sort-first logic
    const entries = [
      makeEntry({ id: 3, to_status: 'SUBMITTED', created_at: '2026-05-03T00:00:00Z' }),
      makeEntry({ id: 1, to_status: 'SUBMITTED', created_at: '2026-05-01T00:00:00Z' }),
      makeEntry({ id: 2, to_status: 'BANK_REVIEW', created_at: '2026-05-02T00:00:00Z' }),
    ]
    expect(getCurrentEntry(entries, RequestStatus.SUBMITTED)?.id).toBe(3)
  })

  it('handles descending API response order correctly (defensive sort)', () => {
    // Simulates API returning newest-first
    const entries = [
      makeEntry({ id: 4, to_status: 'BANK_APPROVED', created_at: '2026-05-04T00:00:00Z' }),
      makeEntry({ id: 3, to_status: 'BANK_REVIEW', created_at: '2026-05-03T00:00:00Z' }),
      makeEntry({ id: 2, to_status: 'SUBMITTED', created_at: '2026-05-02T00:00:00Z' }),
      makeEntry({ id: 1, to_status: 'DRAFT', created_at: '2026-05-01T00:00:00Z' }),
    ]
    expect(getCurrentEntry(entries, RequestStatus.BANK_APPROVED)?.id).toBe(4)
  })

  it('returns null when no entry matches the current status', () => {
    const entries = [makeEntry({ to_status: 'SUBMITTED' })]
    expect(getCurrentEntry(entries, RequestStatus.BANK_REVIEW)).toBeNull()
  })

  it('does not mutate the original history array', () => {
    const entries = [
      makeEntry({ id: 1, to_status: 'SUBMITTED' }),
      makeEntry({ id: 2, to_status: 'BANK_REVIEW' }),
    ]
    const original = [...entries]
    getCurrentEntry(entries, RequestStatus.BANK_REVIEW)
    expect(entries).toEqual(original)
  })
})

// ─── Unknown currentStatus guard ─────────────────────────────────────────────

describe('WorkflowTimeline — unknown currentStatus guard', () => {
  it('treats all stages as "future" when currentStatus is not in the stage order', () => {
    const unknownStatus = 'UNKNOWN_STATUS' as RequestStatus
    const visited = new Set<string>()
    const states = WORKFLOW_STAGE_ORDER.map(s => getStageState(s, unknownStatus, visited))
    states.forEach(s => expect(s).toBe('future'))
  })
})
