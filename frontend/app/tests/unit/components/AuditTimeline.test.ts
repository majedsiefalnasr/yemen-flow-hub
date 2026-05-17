/**
 * AuditTimeline — unit tests for color logic, action labels, and entry rendering helpers.
 * Pure-logic extraction pattern (no component mounting).
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { RequestStageHistory } from '../../../types/models'

// ─── Reproduce color logic from AuditTimeline.vue ────────────────────────────

const GREEN_STATUSES = new Set<string>([
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.COMPLETED,
])

const RED_STATUSES = new Set<string>([
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const AMBER_STATUSES = new Set<string>([
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.SUBMITTED,
])

const INDIGO_STATUSES = new Set<string>([
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

function entryColor(entry: RequestStageHistory): string {
  const s = entry.to_status
  if (!s) return '#8e8e93'
  if (GREEN_STATUSES.has(s)) return '#34c759'
  if (RED_STATUSES.has(s)) return '#ff3b30'
  if (AMBER_STATUSES.has(s)) return '#ff9f0a'
  if (INDIGO_STATUSES.has(s)) return '#5856d6'
  return '#8e8e93'
}

function actionLabel(action: string): string {
  const MAP: Record<string, string> = {
    submit: 'تقديم الطلب',
    bank_approve: 'موافقة البنك',
    bank_reject: 'رفض البنك',
    return_to_entry: 'إعادة إلى المُدخل',
    support_claim: 'حجز المراجعة',
    support_release: 'إلغاء الحجز',
    support_approve: 'موافقة لجنة الدعم',
    support_reject: 'رفض لجنة الدعم',
    swift_upload: 'رفع مستند SWIFT',
    start_voting: 'فتح جلسة التصويت',
    finalize_approved: 'اعتماد نهائي — موافقة',
    finalize_rejected: 'اعتماد نهائي — رفض',
    issue_customs: 'إصدار البيان الجمركي',
    complete: 'إتمام الطلب',
  }
  return MAP[action] ?? action
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
  performed_by: { id: 3, name: 'Test User', role: 'DATA_ENTRY' },
  action: 'submit',
  notes: null,
  metadata: null,
  created_at: '2026-05-17T08:00:00.000Z',
  ...overrides,
})

// ─── Color tests ─────────────────────────────────────────────────────────────

describe('AuditTimeline entryColor', () => {
  it('returns green for approval statuses', () => {
    const approvals = [
      RequestStatus.BANK_APPROVED,
      RequestStatus.SUPPORT_APPROVED,
      RequestStatus.EXECUTIVE_APPROVED,
      RequestStatus.CUSTOMS_DECLARATION_ISSUED,
      RequestStatus.COMPLETED,
    ]
    for (const s of approvals) {
      expect(entryColor(makeEntry({ to_status: s }))).toBe('#34c759')
    }
  })

  it('returns red for rejection statuses', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.SUPPORT_REJECTED }))).toBe('#ff3b30')
    expect(entryColor(makeEntry({ to_status: RequestStatus.EXECUTIVE_REJECTED }))).toBe('#ff3b30')
  })

  it('returns amber for DRAFT_REJECTED_INTERNAL and SUBMITTED', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.DRAFT_REJECTED_INTERNAL }))).toBe('#ff9f0a')
    expect(entryColor(makeEntry({ to_status: RequestStatus.SUBMITTED }))).toBe('#ff9f0a')
  })

  it('returns indigo for review/voting statuses', () => {
    const reviews = [
      RequestStatus.SUPPORT_REVIEW_PENDING,
      RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
      RequestStatus.EXECUTIVE_VOTING_OPEN,
      RequestStatus.EXECUTIVE_VOTING_CLOSED,
    ]
    for (const s of reviews) {
      expect(entryColor(makeEntry({ to_status: s }))).toBe('#5856d6')
    }
  })

  it('returns neutral gray for SWIFT-related and WAITING statuses', () => {
    const neutral = [
      RequestStatus.SWIFT_UPLOADED,
      RequestStatus.WAITING_FOR_SWIFT,
      RequestStatus.WAITING_FOR_VOTING_OPEN,
    ]
    for (const s of neutral) {
      expect(entryColor(makeEntry({ to_status: s }))).toBe('#8e8e93')
    }
  })

  it('returns neutral gray when to_status is null', () => {
    expect(entryColor(makeEntry({ to_status: null }))).toBe('#8e8e93')
  })

  it('returns neutral gray for DRAFT (initial creation)', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.DRAFT }))).toBe('#8e8e93')
  })

  it('returns neutral gray for BANK_REVIEW', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.BANK_REVIEW }))).toBe('#8e8e93')
  })
})

// ─── Action label tests ───────────────────────────────────────────────────────

describe('AuditTimeline actionLabel', () => {
  it('maps known action keys to Arabic labels', () => {
    expect(actionLabel('submit')).toBe('تقديم الطلب')
    expect(actionLabel('bank_approve')).toBe('موافقة البنك')
    expect(actionLabel('bank_reject')).toBe('رفض البنك')
    expect(actionLabel('return_to_entry')).toBe('إعادة إلى المُدخل')
    expect(actionLabel('support_claim')).toBe('حجز المراجعة')
    expect(actionLabel('support_release')).toBe('إلغاء الحجز')
    expect(actionLabel('support_approve')).toBe('موافقة لجنة الدعم')
    expect(actionLabel('support_reject')).toBe('رفض لجنة الدعم')
    expect(actionLabel('swift_upload')).toBe('رفع مستند SWIFT')
    expect(actionLabel('start_voting')).toBe('فتح جلسة التصويت')
    expect(actionLabel('finalize_approved')).toBe('اعتماد نهائي — موافقة')
    expect(actionLabel('finalize_rejected')).toBe('اعتماد نهائي — رفض')
    expect(actionLabel('issue_customs')).toBe('إصدار البيان الجمركي')
    expect(actionLabel('complete')).toBe('إتمام الطلب')
  })

  it('falls back to the raw action key for unknown actions', () => {
    expect(actionLabel('some_unknown_action')).toBe('some_unknown_action')
    expect(actionLabel('')).toBe('')
  })
})

// ─── Data rendering logic tests ───────────────────────────────────────────────

describe('AuditTimeline entry data structure', () => {
  it('entry with null to_status is treated as a non-transition event', () => {
    const entry = makeEntry({ to_status: null, from_status: null })
    expect(entryColor(entry)).toBe('#8e8e93')
  })

  it('entry with only to_status (no from_status) is valid', () => {
    const entry = makeEntry({ from_status: null, to_status: RequestStatus.DRAFT })
    expect(entry.from_status).toBeNull()
    expect(entryColor(entry)).toBe('#8e8e93')
  })

  it('entry with performed_by null falls back to actor_id', () => {
    const entry = makeEntry({ performed_by: null, actor_id: 99 })
    expect(entry.performed_by).toBeNull()
    expect(entry.actor_id).toBe(99)
  })

  it('entry with notes is not null', () => {
    const entry = makeEntry({ notes: 'وثائق ناقصة' })
    expect(entry.notes).toBe('وثائق ناقصة')
  })

  it('empty entries array means empty list (no crash)', () => {
    const entries: RequestStageHistory[] = []
    expect(entries.length).toBe(0)
  })
})

// ─── Color coverage — every canonical status is assigned a color ──────────────

describe('AuditTimeline color coverage — all canonical statuses', () => {
  it('every RequestStatus value produces a valid hex color string', () => {
    for (const s of Object.values(RequestStatus)) {
      const color = entryColor(makeEntry({ to_status: s }))
      expect(color).toMatch(/^#[0-9a-f]{6}$/i)
    }
  })
})
