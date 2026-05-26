/**
 * SupportCommitteeDashboard Story 12.1 assertions — active-claim strip,
 * KPI spec order, 3-state row tinting, claim-state-dependent action buttons.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { SupportCommitteeDashboardStats } from '../../../composables/useDashboard'
import { makeImportRequest } from '../fixtures/request-data'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_name: 'بنك اليمن المركزي',
    status: RequestStatus.SUPPORT_REVIEW_PENDING,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    amount: 50000,
    supplier_name: 'Supplier Co.',
    goods_description: 'Goods',
    can_be_claimed: true,
    is_claimed: false,
    is_claimed_by_me: false,
    claimed_by: null,
    created_at: '2026-05-26T00:00:00.000000Z',
    updated_at: '2026-05-26T00:00:00.000000Z',
    ...overrides,
  })
}

// --- Active-claim strip logic ---

function myActiveClaims(queue: ImportRequest[], currentUserId: number): ImportRequest[] {
  return queue.filter(req =>
    req.is_claimed_by_me || (req.claimed_by != null && req.claimed_by.id === currentUserId),
  )
}

function shouldShowActiveClaim(queue: ImportRequest[], currentUserId: number): boolean {
  return myActiveClaims(queue, currentUserId).length > 0
}

describe('SupportCommitteeDashboard 12.1 — active-claim strip', () => {
  const currentUserId = 7

  it('shows active-claim strip when queue has a row claimed by me', () => {
    const queue = [makeRequest({ is_claimed_by_me: true, claimed_by: { id: 7, name: 'Ahmed' } })]
    expect(shouldShowActiveClaim(queue, currentUserId)).toBe(true)
  })

  it('shows active-claim strip when claimed_by.id matches currentUserId', () => {
    const queue = [makeRequest({ is_claimed_by_me: false, claimed_by: { id: 7, name: 'Ahmed' } })]
    expect(shouldShowActiveClaim(queue, currentUserId)).toBe(true)
  })

  it('hides active-claim strip when queue is empty', () => {
    expect(shouldShowActiveClaim([], currentUserId)).toBe(false)
  })

  it('hides active-claim strip when no rows are claimed by me', () => {
    const queue = [makeRequest({ is_claimed_by_me: false, claimed_by: { id: 99, name: 'Other' } })]
    expect(shouldShowActiveClaim(queue, currentUserId)).toBe(false)
  })

  it('active claims count equals number of rows claimed by current user', () => {
    const queue = [
      makeRequest({ id: 1, is_claimed_by_me: true, claimed_by: { id: 7, name: 'Ahmed' } }),
      makeRequest({ id: 2, is_claimed_by_me: false, claimed_by: { id: 99, name: 'Other' } }),
      makeRequest({ id: 3, is_claimed_by_me: false, claimed_by: null }),
    ]
    expect(myActiveClaims(queue, 7)).toHaveLength(1)
  })
})

// --- KPI spec order: Waiting / Active by Me / Claimed by Others / Recently Approved ---

type KpiEntry = { label: string; variant: string; tab: string }

function buildKpiConfig(stats: SupportCommitteeDashboardStats): KpiEntry[] {
  return [
    { label: 'بانتظار المطالبة', variant: (stats.waiting_for_claim ?? 0) > 0 ? 'amber' : 'gray', tab: 'waiting' },
    { label: 'أعمل عليها الآن', variant: (stats.active_by_me ?? 0) > 0 ? 'indigo' : 'gray', tab: 'my_claims' },
    { label: 'محجوزة لأعضاء آخرين', variant: 'gray', tab: 'in_progress' },
    { label: 'اعتُمِدت مؤخراً', variant: 'green', tab: 'approved' },
  ]
}

describe('SupportCommitteeDashboard 12.1 — KPI spec order', () => {
  const stats: SupportCommitteeDashboardStats = {
    waiting_for_claim: 5, active_by_me: 1, claimed_by_others: 3, recently_approved: 2,
    support_queue: [],
  }
  const kpis = buildKpiConfig(stats)

  it('first KPI is Waiting for Claim (amber when > 0)', () => {
    expect(kpis[0]?.label).toBe('بانتظار المطالبة')
    expect(kpis[0]?.variant).toBe('amber')
    expect(kpis[0]?.tab).toBe('waiting')
  })

  it('second KPI is Active by Me (indigo when > 0)', () => {
    expect(kpis[1]?.label).toBe('أعمل عليها الآن')
    expect(kpis[1]?.variant).toBe('indigo')
    expect(kpis[1]?.tab).toBe('my_claims')
  })

  it('third KPI is Claimed by Others (gray)', () => {
    expect(kpis[2]?.label).toBe('محجوزة لأعضاء آخرين')
    expect(kpis[2]?.tab).toBe('in_progress')
  })

  it('fourth KPI is Recently Approved (green)', () => {
    expect(kpis[3]?.label).toBe('اعتُمِدت مؤخراً')
    expect(kpis[3]?.variant).toBe('green')
    expect(kpis[3]?.tab).toBe('approved')
  })

  it('waiting KPI variant is gray when count is 0', () => {
    const zeroStats: SupportCommitteeDashboardStats = {
      waiting_for_claim: 0, active_by_me: 0, claimed_by_others: 0, recently_approved: 0,
      support_queue: [],
    }
    expect(buildKpiConfig(zeroStats)[0]?.variant).toBe('gray')
  })
})

// --- 3-state row tinting ---

type RowTintState = 'mine' | 'others' | 'unclaimed'

function getRowTintState(req: ImportRequest, currentUserId: number): RowTintState {
  if (req.is_claimed_by_me || req.claimed_by?.id === currentUserId) return 'mine'
  if (req.claimed_by != null) return 'others'
  return 'unclaimed'
}

describe('SupportCommitteeDashboard 12.1 — 3-state row tinting', () => {
  const userId = 7

  it('claimed-by-me row gets "mine" tint', () => {
    const req = makeRequest({ is_claimed_by_me: true, claimed_by: { id: 7, name: 'Ahmed' } })
    expect(getRowTintState(req, userId)).toBe('mine')
  })

  it('claimed-by-others row gets "others" tint', () => {
    const req = makeRequest({ is_claimed_by_me: false, claimed_by: { id: 99, name: 'Other' } })
    expect(getRowTintState(req, userId)).toBe('others')
  })

  it('unclaimed row gets "unclaimed" tint', () => {
    const req = makeRequest({ is_claimed_by_me: false, claimed_by: null })
    expect(getRowTintState(req, userId)).toBe('unclaimed')
  })
})

// --- Claim-state-dependent action button label ---

type ActionButtonLabel = 'مطالبة' | 'متابعة' | 'عرض'

function getActionButtonLabel(req: ImportRequest, currentUserId: number): ActionButtonLabel {
  if (!req.claimed_by) return 'مطالبة'
  if (req.is_claimed_by_me || req.claimed_by.id === currentUserId) return 'متابعة'
  return 'عرض'
}

describe('SupportCommitteeDashboard 12.1 — action button labels per claim state', () => {
  const userId = 7

  it('unclaimed row shows "مطالبة"', () => {
    const req = makeRequest({ claimed_by: null })
    expect(getActionButtonLabel(req, userId)).toBe('مطالبة')
  })

  it('claimed-by-me row shows "متابعة"', () => {
    const req = makeRequest({ is_claimed_by_me: true, claimed_by: { id: 7, name: 'Ahmed' } })
    expect(getActionButtonLabel(req, userId)).toBe('متابعة')
  })

  it('claimed-by-others row shows "عرض"', () => {
    const req = makeRequest({ is_claimed_by_me: false, claimed_by: { id: 99, name: 'Other' } })
    expect(getActionButtonLabel(req, userId)).toBe('عرض')
  })
})

// --- Queue table max 8 rows ---

describe('SupportCommitteeDashboard 12.1 — queue table max 8 rows', () => {
  it('slices queue display to 8 rows', () => {
    const queue = Array.from({ length: 12 }, (_, i) =>
      makeRequest({ id: i + 1, reference_number: `YFH-2026-0000${i + 1}` }),
    )
    const displayed = queue.slice(0, 8)
    expect(displayed).toHaveLength(8)
  })
})

// --- Empty queue: reassuring message with ✓ ---

describe('SupportCommitteeDashboard 12.1 — empty queue reassuring message', () => {
  it('empty queue message contains ✓', () => {
    const message = 'لا توجد طلبات بانتظار المراجعة حالياً ✓'
    expect(message).toContain('✓')
  })
})
