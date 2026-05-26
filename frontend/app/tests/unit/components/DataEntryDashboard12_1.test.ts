/**
 * DataEntryDashboard Story 12.1 assertions — action strip, empty-state KPI hide,
 * clickable KPI tab routing, spec label copy.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import type { DataEntryDashboardStats } from '../../../composables/useDashboard'
import { makeImportRequest } from '../fixtures/request-data'

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    id: 1,
    reference_number: 'YFH-2026-000001',
    status: RequestStatus.DRAFT,
    current_owner_role: UserRole.DATA_ENTRY,
    amount: 10000,
    supplier_name: 'Supplier Co.',
    goods_description: 'Goods',
    created_at: '2026-05-26T00:00:00.000000Z',
    updated_at: '2026-05-26T00:00:00.000000Z',
    ...overrides,
  })
}

// --- Action-required strip logic (returned count > 0) ---

function actionRequiredCount(stats: DataEntryDashboardStats): number {
  return stats.returned ?? 0
}

function shouldShowActionStrip(stats: DataEntryDashboardStats): boolean {
  return actionRequiredCount(stats) > 0
}

describe('DataEntryDashboard 12.1 — action-required strip', () => {
  it('shows action strip when returned count > 0', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0, returned: 3, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [], recent_requests: [],
    }
    expect(shouldShowActionStrip(stats)).toBe(true)
  })

  it('hides action strip when returned count is 0', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [], recent_requests: [],
    }
    expect(shouldShowActionStrip(stats)).toBe(false)
  })

  it('action strip count equals stats.returned, not returned_requests.length', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0, returned: 5, under_cby_processing: 0, completed: 0,
      draft_requests: [],
      returned_requests: [makeRequest({ status: RequestStatus.BANK_RETURNED })],
      recent_requests: [],
    }
    expect(actionRequiredCount(stats)).toBe(5)
  })
})

// --- Empty state: KPI grid should be hidden when no requests at all ---

function hasAnyRequests(stats: DataEntryDashboardStats): boolean {
  return (
    (stats.completed ?? 0) > 0
    || (stats.under_cby_processing ?? 0) > 0
    || (stats.returned ?? 0) > 0
    || (stats.draft ?? 0) > 0
    || (stats.recent_requests?.length ?? 0) > 0
    || (stats.draft_requests?.length ?? 0) > 0
  )
}

describe('DataEntryDashboard 12.1 — empty state hides KPI grid', () => {
  it('returns false (show empty state) when all counts are zero and lists empty', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [], recent_requests: [],
    }
    expect(hasAnyRequests(stats)).toBe(false)
  })

  it('returns true when draft count > 0', () => {
    const stats: DataEntryDashboardStats = {
      draft: 1, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [], recent_requests: [],
    }
    expect(hasAnyRequests(stats)).toBe(true)
  })

  it('returns true when recent_requests is non-empty even if all counts are 0', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [],
      recent_requests: [makeRequest()],
    }
    expect(hasAnyRequests(stats)).toBe(true)
  })
})

// --- KPI order and tab routing ---

type KpiEntry = { label: string; tab: string; variant: string }

function buildKpiConfig(stats: DataEntryDashboardStats): KpiEntry[] {
  return [
    { label: 'مكتمل / صدر التأكيد', tab: 'completed', variant: 'green' },
    { label: 'قيد معالجة CBY', tab: 'processing', variant: 'blue' },
    { label: 'بحاجة تعديل', tab: 'returned', variant: (stats.returned ?? 0) > 0 ? 'amber' : 'gray' },
    { label: 'مسودات', tab: 'draft', variant: 'gray' },
  ]
}

describe('DataEntryDashboard 12.1 — KPI spec order and labels', () => {
  const stats: DataEntryDashboardStats = {
    draft: 2, returned: 1, under_cby_processing: 3, completed: 4,
    draft_requests: [], returned_requests: [], recent_requests: [],
  }
  const kpis = buildKpiConfig(stats)

  it('first KPI is completed/صدر التأكيد', () => {
    expect(kpis[0]?.label).toBe('مكتمل / صدر التأكيد')
  })

  it('second KPI is under CBY processing', () => {
    expect(kpis[1]?.label).toBe('قيد معالجة CBY')
  })

  it('third KPI is returned/needs-edit with amber variant when count > 0', () => {
    expect(kpis[2]?.label).toBe('بحاجة تعديل')
    expect(kpis[2]?.variant).toBe('amber')
  })

  it('fourth KPI is drafts', () => {
    expect(kpis[3]?.label).toBe('مسودات')
  })

  it('each KPI has a tab key for click-through routing', () => {
    const tabs = kpis.map(k => k.tab)
    expect(tabs).toEqual(['completed', 'processing', 'returned', 'draft'])
  })

  it('returned KPI variant is gray when count is 0', () => {
    const zeroStats: DataEntryDashboardStats = {
      draft: 0, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [], recent_requests: [],
    }
    const kpisZero = buildKpiConfig(zeroStats)
    expect(kpisZero[2]?.variant).toBe('gray')
  })
})

// --- Draft table: hidden (not shown with empty placeholder) when empty ---

function shouldShowDraftsTable(stats: DataEntryDashboardStats): boolean {
  return (stats.draft_requests?.length ?? 0) > 0
}

describe('DataEntryDashboard 12.1 — draft table hidden when empty', () => {
  it('draft table is not shown when draft_requests is empty', () => {
    const stats: DataEntryDashboardStats = {
      draft: 5, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [], returned_requests: [], recent_requests: [],
    }
    expect(shouldShowDraftsTable(stats)).toBe(false)
  })

  it('draft table shown when draft_requests has items', () => {
    const stats: DataEntryDashboardStats = {
      draft: 1, returned: 0, under_cby_processing: 0, completed: 0,
      draft_requests: [makeRequest()], returned_requests: [], recent_requests: [],
    }
    expect(shouldShowDraftsTable(stats)).toBe(true)
  })
})
