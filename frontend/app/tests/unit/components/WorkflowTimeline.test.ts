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

const TERMINAL_STATUSES = new Set<RequestStatus>([
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.COMPLETED,
])

type StageState = 'completed' | 'current' | 'future' | 'terminal'

function getStageState(status: RequestStatus, currentStatus: RequestStatus): StageState {
  const currentIndex = WORKFLOW_STAGE_ORDER.indexOf(currentStatus)
  const idx = WORKFLOW_STAGE_ORDER.indexOf(status)
  const isCurrent = status === currentStatus
  const isTerminal = TERMINAL_STATUSES.has(status) && isCurrent

  if (isTerminal) return 'terminal'
  if (isCurrent) return 'current'
  if (idx < currentIndex) return 'completed'
  return 'future'
}

function getCurrentEntry(history: RequestStageHistory[], currentStatus: RequestStatus): RequestStageHistory | null {
  return [...history].reverse().find(e => e.to_status === currentStatus) ?? null
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

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('WorkflowTimeline stage classification', () => {
  it('covers all 18 canonical stages — none missing', () => {
    expect(WORKFLOW_STAGE_ORDER).toHaveLength(18)
    const all = Object.values(RequestStatus)
    for (const s of all) {
      expect(WORKFLOW_STAGE_ORDER).toContain(s)
    }
  })

  it('classifies the current status as "current"', () => {
    expect(getStageState(RequestStatus.SUBMITTED, RequestStatus.SUBMITTED)).toBe('current')
    expect(getStageState(RequestStatus.BANK_APPROVED, RequestStatus.BANK_APPROVED)).toBe('current')
  })

  it('classifies stages before current as "completed"', () => {
    expect(getStageState(RequestStatus.DRAFT, RequestStatus.SUBMITTED)).toBe('completed')
    expect(getStageState(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW)).toBe('completed')
    expect(getStageState(RequestStatus.BANK_APPROVED, RequestStatus.SUPPORT_REVIEW_PENDING)).toBe('completed')
  })

  it('classifies stages after current as "future"', () => {
    expect(getStageState(RequestStatus.BANK_REVIEW, RequestStatus.SUBMITTED)).toBe('future')
    expect(getStageState(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.BANK_APPROVED)).toBe('future')
    expect(getStageState(RequestStatus.COMPLETED, RequestStatus.DRAFT)).toBe('future')
  })

  it('classifies EXECUTIVE_REJECTED as "terminal" when it is current', () => {
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.EXECUTIVE_REJECTED)).toBe('terminal')
  })

  it('classifies COMPLETED as "terminal" when it is current', () => {
    expect(getStageState(RequestStatus.COMPLETED, RequestStatus.COMPLETED)).toBe('terminal')
  })

  it('does NOT classify EXECUTIVE_REJECTED as terminal when it is a future stage', () => {
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.BANK_APPROVED)).toBe('future')
  })

  it('does NOT classify EXECUTIVE_REJECTED as terminal when it is a completed stage', () => {
    expect(getStageState(RequestStatus.EXECUTIVE_REJECTED, RequestStatus.COMPLETED)).toBe('completed')
  })

  it('DRAFT at index 0 has no completed stages before it', () => {
    const states = WORKFLOW_STAGE_ORDER.map(s => getStageState(s, RequestStatus.DRAFT))
    expect(states[0]).toBe('current')
    // All others are future
    states.slice(1).forEach(s => expect(s).toBe('future'))
  })

  it('COMPLETED at last index has all other stages completed', () => {
    const states = WORKFLOW_STAGE_ORDER.map(s => getStageState(s, RequestStatus.COMPLETED))
    // Last one is terminal
    expect(states[states.length - 1]).toBe('terminal')
    // All before last are completed
    states.slice(0, -1).forEach(s => expect(s).toBe('completed'))
  })

  it('SUPPORT_REVIEW_IN_PROGRESS: SUPPORT_REVIEW_PENDING is completed, further stages are future', () => {
    const state = getStageState(RequestStatus.SUPPORT_REVIEW_PENDING, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
    expect(state).toBe('completed')
    const futureState = getStageState(RequestStatus.SUPPORT_APPROVED, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
    expect(futureState).toBe('future')
  })
})

describe('WorkflowTimeline getCurrentEntry', () => {
  it('returns null when history is empty', () => {
    const result = getCurrentEntry([], RequestStatus.SUBMITTED)
    expect(result).toBeNull()
  })

  it('returns the last entry that transitioned TO the current status', () => {
    const entries = [
      makeEntry({ id: 1, to_status: 'SUBMITTED' }),
      makeEntry({ id: 2, to_status: 'BANK_REVIEW' }),
    ]
    const result = getCurrentEntry(entries, RequestStatus.BANK_REVIEW)
    expect(result?.id).toBe(2)
  })

  it('returns the most recent matching entry when there are multiple with same to_status', () => {
    const entries = [
      makeEntry({ id: 1, to_status: 'SUBMITTED', created_at: '2026-05-01T00:00:00Z' }),
      makeEntry({ id: 2, to_status: 'BANK_REVIEW', created_at: '2026-05-02T00:00:00Z' }),
      makeEntry({ id: 3, to_status: 'SUBMITTED', created_at: '2026-05-03T00:00:00Z' }),
    ]
    const result = getCurrentEntry(entries, RequestStatus.SUBMITTED)
    expect(result?.id).toBe(3)
  })

  it('returns null when no entry matches the current status', () => {
    const entries = [makeEntry({ to_status: 'SUBMITTED' })]
    const result = getCurrentEntry(entries, RequestStatus.BANK_REVIEW)
    expect(result).toBeNull()
  })

  it('does not mutate the original history array (uses a copy)', () => {
    const entries = [
      makeEntry({ id: 1, to_status: 'SUBMITTED' }),
      makeEntry({ id: 2, to_status: 'BANK_REVIEW' }),
    ]
    const original = [...entries]
    getCurrentEntry(entries, RequestStatus.BANK_REVIEW)
    expect(entries).toEqual(original)
  })
})

describe('WorkflowTimeline terminal statuses set', () => {
  it('only EXECUTIVE_REJECTED and COMPLETED are terminal', () => {
    expect(TERMINAL_STATUSES.has(RequestStatus.EXECUTIVE_REJECTED)).toBe(true)
    expect(TERMINAL_STATUSES.has(RequestStatus.COMPLETED)).toBe(true)
    expect(TERMINAL_STATUSES.size).toBe(2)
  })

  it('no other status is in the terminal set', () => {
    const nonTerminal = Object.values(RequestStatus).filter(
      s => s !== RequestStatus.EXECUTIVE_REJECTED && s !== RequestStatus.COMPLETED,
    )
    for (const s of nonTerminal) {
      expect(TERMINAL_STATUSES.has(s)).toBe(false)
    }
  })
})
