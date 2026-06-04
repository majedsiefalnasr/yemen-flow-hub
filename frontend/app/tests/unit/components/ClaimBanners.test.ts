/**
 * Claim banner logic tests — ActiveReviewBanner and ClaimedByOthersBanner.
 * Pure logic tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import { makeImportRequest } from '../fixtures/request-data'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_name: null,
    status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    amount: 10000,
    supplier_name: 'Supplier',
    goods_description: 'Goods',
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  })
}

// Logic from [id]/index.vue: showActiveReviewBanner
function shouldShowActiveReviewBanner(userRole: UserRole, isActiveReviewer: boolean): boolean {
  return userRole === UserRole.SUPPORT_COMMITTEE && isActiveReviewer
}

// Logic from [id]/index.vue: showClaimedByOthersBanner
function shouldShowClaimedByOthersBanner(
  userRole: UserRole,
  isActiveReviewer: boolean,
  req: ImportRequest | null,
): boolean {
  if (!req || userRole !== UserRole.SUPPORT_COMMITTEE || isActiveReviewer) return false
  return req.is_claimed && !req.is_claimed_by_me
}

// Banner text: ClaimedByOthersBanner shows claimer name
function buildClaimedByMessage(claimerName: string): string {
  return `محجوز بواسطة ${claimerName} — يمكنك الاطلاع على الطلب فقط`
}

describe('ActiveReviewBanner — visibility logic', () => {
  it('shows when user is SUPPORT_COMMITTEE and isActiveReviewer is true', () => {
    expect(shouldShowActiveReviewBanner(UserRole.SUPPORT_COMMITTEE, true)).toBe(true)
  })

  it('hides when isActiveReviewer is false', () => {
    expect(shouldShowActiveReviewBanner(UserRole.SUPPORT_COMMITTEE, false)).toBe(false)
  })

  it('hides for non-SUPPORT_COMMITTEE roles even if isActiveReviewer is true', () => {
    expect(shouldShowActiveReviewBanner(UserRole.BANK_REVIEWER, true)).toBe(false)
    expect(shouldShowActiveReviewBanner(UserRole.DATA_ENTRY, true)).toBe(false)
  })
})

describe('ClaimedByOthersBanner — visibility logic', () => {
  it('shows when SUPPORT_COMMITTEE, not active reviewer, request claimed by others', () => {
    const req = makeRequest({
      is_claimed: true,
      is_claimed_by_me: false,
      claimed_by: { id: 99, name: 'خالد' },
    })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, req)).toBe(true)
  })

  it('hides when isActiveReviewer is true (active reviewer banner takes priority)', () => {
    const req = makeRequest({ is_claimed: true, is_claimed_by_me: false })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, true, req)).toBe(false)
  })

  it('hides when is_claimed_by_me is true', () => {
    const req = makeRequest({ is_claimed: true, is_claimed_by_me: true })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, req)).toBe(false)
  })

  it('hides when request is not claimed', () => {
    const req = makeRequest({ is_claimed: false, is_claimed_by_me: false })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, req)).toBe(false)
  })

  it('hides for non-SUPPORT_COMMITTEE roles', () => {
    const req = makeRequest({ is_claimed: true, is_claimed_by_me: false })
    expect(shouldShowClaimedByOthersBanner(UserRole.BANK_REVIEWER, false, req)).toBe(false)
  })

  it('hides when request is null', () => {
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, null)).toBe(false)
  })
})

describe('ClaimedByOthersBanner — claimer name display', () => {
  it('includes claimer name in message', () => {
    const msg = buildClaimedByMessage('سعد المطري')
    expect(msg).toContain('سعد المطري')
  })

  it('message indicates view-only access', () => {
    const msg = buildClaimedByMessage('خالد')
    expect(msg).toContain('يمكنك الاطلاع')
  })
})
