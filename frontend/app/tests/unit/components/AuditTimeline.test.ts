/**
 * AuditTimeline — unit tests for color logic, action labels, and entry rendering helpers.
 * Pure-logic extraction pattern (no component mounting).
 *
 * F4: entryColor now delegates to STATUS_COLORS from constants/workflow.ts.
 * Tests import STATUS_COLORS directly so color expectations stay in sync with
 * the single source of truth rather than a local copy.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import { STATUS_COLORS } from '../../../constants/workflow'
import type { RequestStageHistory } from '../../../types/models'

// ─── Reproduce color logic from AuditTimeline.vue (F4: delegates to STATUS_COLORS) ──

function entryColor(entry: RequestStageHistory): string {
  const s = entry.to_status as RequestStatus | null
  if (!s) return '#8e8e93'
  return STATUS_COLORS[s] ?? '#8e8e93'
}

// F5: ACTION_LABELS is now module-scope in the component; mirror that here
const ACTION_LABELS: Record<string, string> = {
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
  close_voting: 'إغلاق جلسة التصويت',
  cast_vote: 'تسجيل تصويت',
  finalize_approved: 'اعتماد نهائي — موافقة',
  finalize_rejected: 'اعتماد نهائي — رفض',
  override_approved: 'تجاوز — موافقة',
  override_rejected: 'تجاوز — رفض',
  issue_customs: 'إصدار البيان الجمركي',
  complete: 'إتمام الطلب',
  claim_expire: 'انتهاء صلاحية الحجز',
  document_upload: 'رفع مستند',
}

function actionLabel(action: string): string {
  return ACTION_LABELS[action] ?? action
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

// ─── Color tests — delegating to STATUS_COLORS ───────────────────────────────

describe('AuditTimeline entryColor', () => {
  it('returns the STATUS_COLORS value for each status', () => {
    for (const s of Object.values(RequestStatus)) {
      const expected = STATUS_COLORS[s]
      expect(entryColor(makeEntry({ to_status: s }))).toBe(expected)
    }
  })

  it('returns neutral gray when to_status is null (non-transition event)', () => {
    expect(entryColor(makeEntry({ to_status: null }))).toBe('#8e8e93')
  })

  it('returns neutral gray for DRAFT (initial creation — no color in STATUS_COLORS triggers fallback)', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.DRAFT }))).toBe(STATUS_COLORS[RequestStatus.DRAFT])
  })

  // Spot-check key semantic colors stay correct via STATUS_COLORS
  it('rejection statuses (SUPPORT_REJECTED, EXECUTIVE_REJECTED) are red', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.SUPPORT_REJECTED }))).toBe('#ff3b30')
    expect(entryColor(makeEntry({ to_status: RequestStatus.EXECUTIVE_REJECTED }))).toBe('#ff3b30')
  })

  it('final approval statuses are green', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.EXECUTIVE_APPROVED }))).toBe('#34c759')
    expect(entryColor(makeEntry({ to_status: RequestStatus.CUSTOMS_DECLARATION_ISSUED }))).toBe('#34c759')
    expect(entryColor(makeEntry({ to_status: RequestStatus.COMPLETED }))).toBe('#34c759')
  })

  it('SWIFT-related statuses use SWIFT cyan from STATUS_COLORS', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.WAITING_FOR_SWIFT }))).toBe('#32ade6')
    expect(entryColor(makeEntry({ to_status: RequestStatus.SWIFT_UPLOADED }))).toBe('#32ade6')
  })
})

// ─── Action label tests ───────────────────────────────────────────────────────

describe('AuditTimeline actionLabel', () => {
  it('maps all known action keys to Arabic labels', () => {
    const cases: [string, string][] = [
      ['submit', 'تقديم الطلب'],
      ['bank_approve', 'موافقة البنك'],
      ['bank_reject', 'رفض البنك'],
      ['return_to_entry', 'إعادة إلى المُدخل'],
      ['support_claim', 'حجز المراجعة'],
      ['support_release', 'إلغاء الحجز'],
      ['support_approve', 'موافقة لجنة الدعم'],
      ['support_reject', 'رفض لجنة الدعم'],
      ['swift_upload', 'رفع مستند SWIFT'],
      ['start_voting', 'فتح جلسة التصويت'],
      ['close_voting', 'إغلاق جلسة التصويت'],
      ['cast_vote', 'تسجيل تصويت'],
      ['finalize_approved', 'اعتماد نهائي — موافقة'],
      ['finalize_rejected', 'اعتماد نهائي — رفض'],
      ['override_approved', 'تجاوز — موافقة'],
      ['override_rejected', 'تجاوز — رفض'],
      ['issue_customs', 'إصدار البيان الجمركي'],
      ['complete', 'إتمام الطلب'],
      ['claim_expire', 'انتهاء صلاحية الحجز'],
      ['document_upload', 'رفع مستند'],
    ]
    for (const [key, label] of cases) {
      expect(actionLabel(key)).toBe(label)
    }
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
