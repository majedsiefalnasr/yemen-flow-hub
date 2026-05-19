/**
 * LockedBanner — variant prop logic tests.
 */
import { describe, it, expect } from 'vitest'

type LockedBannerVariant = 'locked' | 'readonly' | 'pending'

const VARIANT_CONFIG: Record<LockedBannerVariant, { icon: string; message: string }> = {
  locked: {
    icon: '🔒',
    message: 'هذا الطلب مقفل ولا يمكن اتخاذ أي إجراء عليه',
  },
  readonly: {
    icon: '👁',
    message: 'هذا الطلب في وضع القراءة فقط',
  },
  pending: {
    icon: '🕐',
    message: 'هذا الطلب قيد المراجعة — لا يمكن إجراء تعديلات حتى اكتمال المرحلة الحالية',
  },
}

describe('LockedBanner — variant config', () => {
  it('locked variant has lock icon', () => {
    expect(VARIANT_CONFIG.locked.icon).toBe('🔒')
  })

  it('readonly variant has eye icon', () => {
    expect(VARIANT_CONFIG.readonly.icon).toBe('👁')
  })

  it('pending variant has clock icon', () => {
    expect(VARIANT_CONFIG.pending.icon).toBe('🕐')
  })

  it('locked variant message mentions "مقفل"', () => {
    expect(VARIANT_CONFIG.locked.message).toContain('مقفل')
  })

  it('readonly variant message mentions "القراءة فقط"', () => {
    expect(VARIANT_CONFIG.readonly.message).toContain('القراءة فقط')
  })

  it('pending variant message mentions "قيد المراجعة"', () => {
    expect(VARIANT_CONFIG.pending.message).toContain('قيد المراجعة')
  })

  it('all three variants have distinct messages', () => {
    const messages = Object.values(VARIANT_CONFIG).map(c => c.message)
    const unique = new Set(messages)
    expect(unique.size).toBe(3)
  })

  it('all three variants have distinct icons', () => {
    const icons = Object.values(VARIANT_CONFIG).map(c => c.icon)
    const unique = new Set(icons)
    expect(unique.size).toBe(3)
  })
})

// Variant mapping logic (mirrors detail page lockedBannerVariant computed)
import { RequestStatus, UserRole } from '../../../types/enums'

const TERMINAL_STATUSES = new Set([
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.COMPLETED,
])

const READONLY_STATUSES = new Set([
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
  RequestStatus.BANK_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_APPROVED,
])

const PENDING_STATUSES = new Set([
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

const VOTING_STAGE_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const EXECUTIVE_ROLES = new Set([UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR])

function lockedBannerVariant(role: UserRole, status: RequestStatus): LockedBannerVariant | null {
  if (EXECUTIVE_ROLES.has(role) && VOTING_STAGE_STATUSES.has(status)) return null
  if (TERMINAL_STATUSES.has(status)) return 'locked'
  if (READONLY_STATUSES.has(status)) return 'readonly'
  if (PENDING_STATUSES.has(status)) return 'pending'
  return null
}

describe('LockedBanner — variant mapping from status', () => {
  it('COMPLETED → locked', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.COMPLETED)).toBe('locked')
  })

  it('EXECUTIVE_REJECTED → locked', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.EXECUTIVE_REJECTED)).toBe('locked')
  })

  it('CUSTOMS_DECLARATION_ISSUED → locked', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.CUSTOMS_DECLARATION_ISSUED)).toBe('locked')
  })

  it('SUPPORT_REJECTED → locked', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.SUPPORT_REJECTED)).toBe('locked')
  })

  it('SUBMITTED → readonly', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.SUBMITTED)).toBe('readonly')
  })

  it('BANK_REVIEW → readonly', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.BANK_REVIEW)).toBe('readonly')
  })

  it('BANK_APPROVED → readonly', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.BANK_APPROVED)).toBe('readonly')
  })

  it('WAITING_FOR_SWIFT → readonly', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.WAITING_FOR_SWIFT)).toBe('readonly')
  })

  it('SWIFT_UPLOADED → readonly', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.SWIFT_UPLOADED)).toBe('readonly')
  })

  it('WAITING_FOR_VOTING_OPEN → readonly for DATA_ENTRY', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.WAITING_FOR_VOTING_OPEN)).toBe('readonly')
  })

  it('EXECUTIVE_APPROVED → readonly for DATA_ENTRY', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.EXECUTIVE_APPROVED)).toBe('readonly')
  })

  it('SUPPORT_REVIEW_PENDING → pending', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.SUPPORT_REVIEW_PENDING)).toBe('pending')
  })

  it('SUPPORT_REVIEW_IN_PROGRESS → pending', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)).toBe('pending')
  })

  it('SUPPORT_APPROVED → pending', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.SUPPORT_APPROVED)).toBe('pending')
  })

  it('EXECUTIVE_VOTING_OPEN → pending for DATA_ENTRY', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe('pending')
  })

  it('EXECUTIVE_VOTING_CLOSED → pending for DATA_ENTRY', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBe('pending')
  })

  it('DRAFT → null (no banner)', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBeNull()
  })

  it('DRAFT_REJECTED_INTERNAL → null (no banner, correction banner shows instead)', () => {
    expect(lockedBannerVariant(UserRole.DATA_ENTRY, RequestStatus.DRAFT_REJECTED_INTERNAL)).toBeNull()
  })
})

describe('LockedBanner — executive roles bypass voting stage banners', () => {
  it('EXECUTIVE_MEMBER viewing EXECUTIVE_VOTING_OPEN → null (no banner)', () => {
    expect(lockedBannerVariant(UserRole.EXECUTIVE_MEMBER, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBeNull()
  })

  it('COMMITTEE_DIRECTOR viewing EXECUTIVE_VOTING_OPEN → null', () => {
    expect(lockedBannerVariant(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBeNull()
  })

  it('COMMITTEE_DIRECTOR viewing EXECUTIVE_VOTING_CLOSED → null', () => {
    expect(lockedBannerVariant(UserRole.COMMITTEE_DIRECTOR, RequestStatus.EXECUTIVE_VOTING_CLOSED)).toBeNull()
  })

  it('COMMITTEE_DIRECTOR viewing WAITING_FOR_VOTING_OPEN → null', () => {
    expect(lockedBannerVariant(UserRole.COMMITTEE_DIRECTOR, RequestStatus.WAITING_FOR_VOTING_OPEN)).toBeNull()
  })

  it('BANK_REVIEWER viewing EXECUTIVE_VOTING_OPEN → pending (not executive)', () => {
    expect(lockedBannerVariant(UserRole.BANK_REVIEWER, RequestStatus.EXECUTIVE_VOTING_OPEN)).toBe('pending')
  })
})
