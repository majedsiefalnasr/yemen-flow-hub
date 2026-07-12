/**
 * Claim banner logic tests — ActiveReviewBanner and ClaimedByOthersBanner.
 * Pure logic tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'
import type { EngineRequest } from '../../../types/models'

function makeRequest(overrides: Partial<EngineRequest> = {}): EngineRequest {
  return {
    id: 1,
    reference: 'YFH-2026-000001',
    status: 'ACTIVE',
    version: 1,
    workflow_version_id: 1,
    current_stage: null,
    bank_id: null,
    bank: null,
    merchant_id: null,
    merchant: null,
    data: {},
    amount: 10000,
    currency: 'USD',
    invoice_number: null,
    sla_status: null,
    claimed_by: null,
    claimed_by_user: null,
    claimed_at: null,
    claim_expires_at: null,
    created_by: 1,
    creator: null,
    created_at: '2026-05-16T00:00:00.000000Z',
    updated_at: '2026-05-16T00:00:00.000000Z',
    ...overrides,
  }
}

// Logic from [id]/index.vue: showActiveReviewBanner
function shouldShowActiveReviewBanner(userRole: UserRole, isActiveReviewer: boolean): boolean {
  return userRole === UserRole.SUPPORT_COMMITTEE && isActiveReviewer
}

// Logic from [id]/index.vue: showClaimedByOthersBanner. `is_claimed_by_other`
// is the backend's from-the-current-user's-perspective claim flag
// (EngineRequestResource::toArray — true only when claimed AND not by me).
function shouldShowClaimedByOthersBanner(
  userRole: UserRole,
  isActiveReviewer: boolean,
  req: EngineRequest | null,
): boolean {
  if (!req || userRole !== UserRole.SUPPORT_COMMITTEE || isActiveReviewer) return false
  return req.is_claimed_by_other === true
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
      claimed_by: 99,
      claimed_by_user: { id: 99, name: 'خالد' },
      is_claimed_by_other: true,
    })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, req)).toBe(true)
  })

  it('hides when isActiveReviewer is true (active reviewer banner takes priority)', () => {
    const req = makeRequest({ claimed_by: 99, is_claimed_by_other: true })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, true, req)).toBe(false)
  })

  it('hides when the request is claimed by the current user', () => {
    const req = makeRequest({ claimed_by: 1, is_claimed_by_other: false })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, req)).toBe(false)
  })

  it('hides when request is not claimed', () => {
    const req = makeRequest({ claimed_by: null, is_claimed_by_other: false })
    expect(shouldShowClaimedByOthersBanner(UserRole.SUPPORT_COMMITTEE, false, req)).toBe(false)
  })

  it('hides for non-SUPPORT_COMMITTEE roles', () => {
    const req = makeRequest({ claimed_by: 99, is_claimed_by_other: true })
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
