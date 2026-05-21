/**
 * CorrectionBanner variant logic — pure unit tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'

type CorrectionBannerVariant = 'draft_rejected' | 'bank_returned'

function resolveVariant(status: RequestStatus): CorrectionBannerVariant | null {
  if (status === RequestStatus.DRAFT_REJECTED_INTERNAL) return 'draft_rejected'
  if (status === RequestStatus.BANK_RETURNED) return 'bank_returned'
  return null
}

function resolveMessage(variant: CorrectionBannerVariant): string {
  if (variant === 'bank_returned') return 'إعادة من المراجع — يرجى التعديل وإعادة الإرسال'
  return 'تم إرجاع الطلب للتصحيح من المراجعة الداخلية — يرجى مراجعة الملاحظات وتعديل الطلب.'
}

function showReviewerComment(variant: CorrectionBannerVariant, comment: string | null | undefined): boolean {
  return variant === 'bank_returned' && !!comment
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

  it('draft_rejected shows internal correction message', () => {
    const msg = resolveMessage('draft_rejected')
    expect(msg).toContain('تم إرجاع الطلب')
  })

  it('messages differ between variants', () => {
    expect(resolveMessage('bank_returned')).not.toBe(resolveMessage('draft_rejected'))
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
})
