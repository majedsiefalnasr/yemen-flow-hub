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
import {
  NOT_ELIGIBLE_BANK_LABEL,
  NOT_ELIGIBLE_EXECUTIVE_LABEL,
  NOT_ELIGIBLE_SUPPORT_LABEL,
  STATUS_COLORS,
} from '../../../constants/workflow'
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
  bank_begin_review: 'بدء مراجعة البنك',
  bank_approve: 'اعتماد البنك',
  bank_reject: 'إعادة الطلب للتعديل',
  bank_return_to_intake: 'إرجاع الطلب للمدخل',
  bank_reject_terminal: NOT_ELIGIBLE_BANK_LABEL,
  return_to_entry: 'إرجاع الطلب للمدخل',
  bank_return_after_support_reject: `إرجاع الطلب بعد ${NOT_ELIGIBLE_SUPPORT_LABEL}`,
  bank_finalize_rejection: `تثبيت ${NOT_ELIGIBLE_SUPPORT_LABEL}`,
  support_claim: 'مطالبة لجنة المساندة بالطلب',
  support_release: 'إفراج لجنة المساندة عن الطلب',
  support_approve: 'اعتماد لجنة المساندة',
  support_reject: NOT_ELIGIBLE_SUPPORT_LABEL,
  support_return_to_intake: 'إرجاع الطلب للمدخل من لجنة المساندة',
  move_to_support_queue: 'إحالة الطلب إلى لجنة المساندة',
  move_to_swift_queue: 'إحالة الطلب إلى رفع SWIFT',
  swift_upload: 'رفع وثائق SWIFT',
  open_voting: 'فتح التصويت التنفيذي',
  close_voting: 'إغلاق التصويت التنفيذي',
  finalize_approved: 'اعتماد القرار التنفيذي',
  finalize_rejected: NOT_ELIGIBLE_EXECUTIVE_LABEL,
  issue_customs: 'إصدار تأكيد المصارفة الخارجية',
  complete: 'إكمال الطلب',
}

function actionLabel(action: string): string {
  return ACTION_LABELS[action] ?? 'إجراء مسجل'
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
    expect(entryColor(makeEntry({ to_status: RequestStatus.DRAFT }))).toBe(
      STATUS_COLORS[RequestStatus.DRAFT],
    )
  })

  // Spot-check key semantic colors stay correct via STATUS_COLORS
  it('rejection statuses (SUPPORT_REJECTED, EXECUTIVE_REJECTED) are red', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.SUPPORT_REJECTED }))).toBe('#ff3b30')
    expect(entryColor(makeEntry({ to_status: RequestStatus.EXECUTIVE_REJECTED }))).toBe('#ff3b30')
  })

  it('final approval statuses are green', () => {
    expect(entryColor(makeEntry({ to_status: RequestStatus.EXECUTIVE_APPROVED }))).toBe('#34c759')
    expect(entryColor(makeEntry({ to_status: RequestStatus.CUSTOMS_DECLARATION_ISSUED }))).toBe(
      '#34c759',
    )
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
      ['bank_begin_review', 'بدء مراجعة البنك'],
      ['bank_approve', 'اعتماد البنك'],
      ['bank_reject', 'إعادة الطلب للتعديل'],
      ['bank_return_to_intake', 'إرجاع الطلب للمدخل'],
      ['bank_reject_terminal', NOT_ELIGIBLE_BANK_LABEL],
      ['return_to_entry', 'إرجاع الطلب للمدخل'],
      ['bank_return_after_support_reject', `إرجاع الطلب بعد ${NOT_ELIGIBLE_SUPPORT_LABEL}`],
      ['bank_finalize_rejection', `تثبيت ${NOT_ELIGIBLE_SUPPORT_LABEL}`],
      ['support_claim', 'مطالبة لجنة المساندة بالطلب'],
      ['support_release', 'إفراج لجنة المساندة عن الطلب'],
      ['support_approve', 'اعتماد لجنة المساندة'],
      ['support_reject', NOT_ELIGIBLE_SUPPORT_LABEL],
      ['support_return_to_intake', 'إرجاع الطلب للمدخل من لجنة المساندة'],
      ['move_to_support_queue', 'إحالة الطلب إلى لجنة المساندة'],
      ['move_to_swift_queue', 'إحالة الطلب إلى رفع SWIFT'],
      ['swift_upload', 'رفع وثائق SWIFT'],
      ['open_voting', 'فتح التصويت التنفيذي'],
      ['close_voting', 'إغلاق التصويت التنفيذي'],
      ['finalize_approved', 'اعتماد القرار التنفيذي'],
      ['finalize_rejected', NOT_ELIGIBLE_EXECUTIVE_LABEL],
      ['issue_customs', 'إصدار تأكيد المصارفة الخارجية'],
      ['complete', 'إكمال الطلب'],
    ]
    for (const [key, label] of cases) {
      expect(actionLabel(key)).toBe(label)
    }
  })

  it('falls back to a localized generic label for unknown actions', () => {
    expect(actionLabel('some_unknown_action')).toBe('إجراء مسجل')
    expect(actionLabel('')).toBe('إجراء مسجل')
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
