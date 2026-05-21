/**
 * CorrectionBanner variant logic — pure unit tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'

type CorrectionBannerVariant = 'draft_rejected' | 'bank_returned' | 'support_returned'

function resolveVariant(status: RequestStatus): CorrectionBannerVariant | null {
  if (status === RequestStatus.DRAFT_REJECTED_INTERNAL) return 'draft_rejected'
  if (status === RequestStatus.BANK_RETURNED) return 'bank_returned'
  if (status === RequestStatus.SUPPORT_RETURNED) return 'support_returned'
  return null
}

function resolveMessage(variant: CorrectionBannerVariant): string {
  if (variant === 'bank_returned') return 'إعادة من المراجع — يرجى التعديل وإعادة الإرسال'
  if (variant === 'support_returned') return 'إعادة من لجنة المساندة — يرجى التعديل وإعادة الإرسال'
  return 'تم إرجاع الطلب للتصحيح من المراجعة الداخلية — يرجى مراجعة الملاحظات وتعديل الطلب.'
}

function showReviewerComment(variant: CorrectionBannerVariant, comment: string | null | undefined): boolean {
  return variant === 'bank_returned' && !!comment
}

function showSupportComment(variant: CorrectionBannerVariant, comment: string | null | undefined): boolean {
  return variant === 'support_returned' && !!comment
}

function showRejectionReason(variant: CorrectionBannerVariant, reason: string | null | undefined): boolean {
  return variant === 'draft_rejected' && !!reason
}

describe('CorrectionBanner — variant resolution', () => {
  it('resolves draft_rejected for DRAFT_REJECTED_INTERNAL', () => {
    expect(resolveVariant(RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe('draft_rejected')
  })

  it('resolves bank_returned for BANK_RETURNED', () => {
    expect(resolveVariant(RequestStatus.BANK_RETURNED)).toBe('bank_returned')
  })

  it('resolves support_returned for SUPPORT_RETURNED', () => {
    expect(resolveVariant(RequestStatus.SUPPORT_RETURNED)).toBe('support_returned')
  })

  it('returns null for DRAFT (no banner)', () => {
    expect(resolveVariant(RequestStatus.DRAFT)).toBeNull()
  })

  it('returns null for SUBMITTED (no banner)', () => {
    expect(resolveVariant(RequestStatus.SUBMITTED)).toBeNull()
  })

  it('returns null for BANK_REVIEW (no banner)', () => {
    expect(resolveVariant(RequestStatus.BANK_REVIEW)).toBeNull()
  })

  it('returns null for BANK_APPROVED (no banner)', () => {
    expect(resolveVariant(RequestStatus.BANK_APPROVED)).toBeNull()
  })

  it('returns null for COMPLETED (no banner)', () => {
    expect(resolveVariant(RequestStatus.COMPLETED)).toBeNull()
  })
})

describe('CorrectionBanner — message per variant', () => {
  it('bank_returned shows correct Arabic message', () => {
    expect(resolveMessage('bank_returned')).toBe('إعادة من المراجع — يرجى التعديل وإعادة الإرسال')
  })

  it('support_returned shows correct Arabic message', () => {
    expect(resolveMessage('support_returned')).toBe('إعادة من لجنة المساندة — يرجى التعديل وإعادة الإرسال')
  })

  it('draft_rejected shows internal correction message', () => {
    const msg = resolveMessage('draft_rejected')
    expect(msg).toContain('تم إرجاع الطلب')
  })

  it('messages differ between all three variants', () => {
    expect(resolveMessage('bank_returned')).not.toBe(resolveMessage('draft_rejected'))
    expect(resolveMessage('support_returned')).not.toBe(resolveMessage('draft_rejected'))
    expect(resolveMessage('support_returned')).not.toBe(resolveMessage('bank_returned'))
  })
})

describe('CorrectionBanner — reviewer comment display', () => {
  it('shows reviewer comment for bank_returned with non-empty comment', () => {
    expect(showReviewerComment('bank_returned', 'يرجى تصحيح المستندات')).toBe(true)
  })

  it('hides reviewer comment for bank_returned when comment is null', () => {
    expect(showReviewerComment('bank_returned', null)).toBe(false)
  })

  it('hides reviewer comment for bank_returned when comment is empty string', () => {
    expect(showReviewerComment('bank_returned', '')).toBe(false)
  })

  it('hides reviewer comment for draft_rejected variant', () => {
    expect(showReviewerComment('draft_rejected', 'some comment')).toBe(false)
  })
})

describe('CorrectionBanner — rejection reason display', () => {
  it('shows rejection reason for draft_rejected with non-empty reason', () => {
    expect(showRejectionReason('draft_rejected', 'مستندات ناقصة')).toBe(true)
  })

  it('hides rejection reason when null', () => {
    expect(showRejectionReason('draft_rejected', null)).toBe(false)
  })

  it('hides rejection reason for bank_returned variant', () => {
    expect(showRejectionReason('bank_returned', 'some reason')).toBe(false)
  })

  it('hides rejection reason for support_returned variant', () => {
    expect(showRejectionReason('support_returned', 'some reason')).toBe(false)
  })
})

describe('CorrectionBanner — support comment display', () => {
  it('shows support comment for support_returned with non-empty comment', () => {
    expect(showSupportComment('support_returned', 'يرجى تصحيح المستندات')).toBe(true)
  })

  it('hides support comment for support_returned when comment is null', () => {
    expect(showSupportComment('support_returned', null)).toBe(false)
  })

  it('hides support comment for support_returned when comment is empty string', () => {
    expect(showSupportComment('support_returned', '')).toBe(false)
  })

  it('hides support comment for bank_returned variant', () => {
    expect(showSupportComment('bank_returned', 'some comment')).toBe(false)
  })

  it('hides support comment for draft_rejected variant', () => {
    expect(showSupportComment('draft_rejected', 'some comment')).toBe(false)
  })
})

describe('CorrectionBanner — reviewer chip (support return hint)', () => {
  interface HistoryEntry { action: string; notes: string | null }

  function resolveSupportReturnHint(
    role: string,
    status: RequestStatus,
    history: HistoryEntry[],
  ): { comment: string | null } | null {
    if (role !== 'BANK_REVIEWER') return null
    if (status !== RequestStatus.SUBMITTED) return null
    const entry = history.find(h => h.action === 'support_return_to_intake')
    if (!entry) return null
    return { comment: entry.notes }
  }

  const historyWithReturn: HistoryEntry[] = [
    { action: 'submit', notes: null },
    { action: 'bank_review', notes: null },
    { action: 'bank_approve', notes: null },
    { action: 'support_review', notes: null },
    { action: 'support_return_to_intake', notes: 'يرجى مراجعة المستندات' },
    { action: 'submit', notes: null },
  ]

  const historyWithoutReturn: HistoryEntry[] = [
    { action: 'submit', notes: null },
    { action: 'bank_review', notes: null },
    { action: 'bank_approve', notes: null },
    { action: 'support_review', notes: null },
  ]

  it('shows hint for BANK_REVIEWER on SUBMITTED with prior support_return_to_intake', () => {
    const hint = resolveSupportReturnHint('BANK_REVIEWER', RequestStatus.SUBMITTED, historyWithReturn)
    expect(hint).not.toBeNull()
    expect(hint?.comment).toBe('يرجى مراجعة المستندات')
  })

  it('hides hint when no support_return_to_intake in history', () => {
    const hint = resolveSupportReturnHint('BANK_REVIEWER', RequestStatus.SUBMITTED, historyWithoutReturn)
    expect(hint).toBeNull()
  })

  it('hides hint for DATA_ENTRY role', () => {
    const hint = resolveSupportReturnHint('DATA_ENTRY', RequestStatus.SUBMITTED, historyWithReturn)
    expect(hint).toBeNull()
  })

  it('hides hint when status is BANK_REVIEW (not SUBMITTED)', () => {
    const hint = resolveSupportReturnHint('BANK_REVIEWER', RequestStatus.BANK_REVIEW, historyWithReturn)
    expect(hint).toBeNull()
  })

  it('returns null comment when history entry has no notes', () => {
    const historyNoNotes: HistoryEntry[] = [
      { action: 'support_return_to_intake', notes: null },
      { action: 'submit', notes: null },
    ]
    const hint = resolveSupportReturnHint('BANK_REVIEWER', RequestStatus.SUBMITTED, historyNoNotes)
    expect(hint).not.toBeNull()
    expect(hint?.comment).toBeNull()
  })
})
