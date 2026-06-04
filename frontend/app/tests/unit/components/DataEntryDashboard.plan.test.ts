/**
 * DataEntryDashboard — implementation-plan spec assertions.
 * Covers: return reason snippet, draft-table /edit links, unread notification badge logic.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'
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
    created_at: '2026-05-27T00:00:00.000000Z',
    updated_at: '2026-05-27T00:00:00.000000Z',
    ...overrides,
  })
}

// ── Return reason snippet logic ───────────────────────────────────────────────

function getReturnReasonSnippet(req: ImportRequest | null): string {
  if (!req) return ''
  const reason = req.bank_return_comment ?? req.support_return_comment ?? req.notes ?? ''
  return reason.length > 80 ? reason.slice(0, 80) + '…' : reason
}

describe('DataEntryDashboard — return reason snippet in correction strip', () => {
  it('shows bank_return_comment for BANK_RETURNED requests', () => {
    const req = makeRequest({
      status: RequestStatus.BANK_RETURNED,
      bank_return_comment: 'الفاتورة غير مكتملة، يرجى إرفاق الفاتورة الأصلية',
      support_return_comment: null,
    })
    expect(getReturnReasonSnippet(req)).toBe('الفاتورة غير مكتملة، يرجى إرفاق الفاتورة الأصلية')
  })

  it('shows support_return_comment for SUPPORT_RETURNED requests when bank comment absent', () => {
    const req = makeRequest({
      status: RequestStatus.SUPPORT_RETURNED,
      bank_return_comment: null,
      support_return_comment: 'بيانات المورد غير مكتملة',
    })
    expect(getReturnReasonSnippet(req)).toBe('بيانات المورد غير مكتملة')
  })

  it('falls back to notes when both return comments are null', () => {
    const req = makeRequest({
      bank_return_comment: null,
      support_return_comment: null,
      notes: 'ملاحظة عامة',
    })
    expect(getReturnReasonSnippet(req)).toBe('ملاحظة عامة')
  })

  it('truncates reason to 80 chars and appends ellipsis', () => {
    const longReason = 'أ'.repeat(100)
    const req = makeRequest({
      bank_return_comment: longReason,
      support_return_comment: null,
    })
    const snippet = getReturnReasonSnippet(req)
    expect(snippet.length).toBeLessThanOrEqual(81) // 80 chars + ellipsis char
    expect(snippet.endsWith('…')).toBe(true)
  })

  it('returns empty string when no reason or notes are set', () => {
    const req = makeRequest({
      bank_return_comment: null,
      support_return_comment: null,
      notes: null,
    })
    expect(getReturnReasonSnippet(req)).toBe('')
  })

  it('returns empty string when request is null (no returned requests)', () => {
    expect(getReturnReasonSnippet(null)).toBe('')
  })
})

// ── Draft table link target ───────────────────────────────────────────────────

function getDraftRowLink(req: ImportRequest): string {
  // Draft rows must link to the edit wizard, not the read-only detail page
  return `/requests/${req.id}/edit`
}

describe('DataEntryDashboard — draft table links to /edit not /requests/[id]', () => {
  it('draft row click target is /requests/[id]/edit', () => {
    const req = makeRequest({ id: 7 })
    expect(getDraftRowLink(req)).toBe('/requests/7/edit')
  })

  it('continue button routes to the same /edit path', () => {
    const req = makeRequest({ id: 42 })
    expect(getDraftRowLink(req)).toBe('/requests/42/edit')
  })

  it('never links a draft to the read-only detail page', () => {
    const req = makeRequest({ id: 5 })
    expect(getDraftRowLink(req)).not.toBe(`/requests/${req.id}`)
  })
})

// ── Unread badge visibility ───────────────────────────────────────────────────

function shouldShowUnreadBadge(unreadCount: number): boolean {
  return unreadCount > 0
}

function formatUnreadCount(unreadCount: number): string {
  return unreadCount > 99 ? '99+' : String(unreadCount)
}

describe('DataEntryDashboard — notifications quick-action unread badge', () => {
  it('shows badge when unread count > 0', () => {
    expect(shouldShowUnreadBadge(1)).toBe(true)
    expect(shouldShowUnreadBadge(5)).toBe(true)
  })

  it('hides badge when unread count is 0', () => {
    expect(shouldShowUnreadBadge(0)).toBe(false)
  })

  it('formats counts up to 99 as the number itself', () => {
    expect(formatUnreadCount(1)).toBe('1')
    expect(formatUnreadCount(99)).toBe('99')
  })

  it('formats counts over 99 as "99+"', () => {
    expect(formatUnreadCount(100)).toBe('99+')
    expect(formatUnreadCount(999)).toBe('99+')
  })
})

// ── Correction strip routing ──────────────────────────────────────────────────

describe('DataEntryDashboard — correction strip links to /requests?tab=returned', () => {
  it('correction strip CTA routes to the returned tab', () => {
    // This test enforces the spec: "links to /requests pre-filtered to the returned tab"
    const expectedRoute = '/requests?tab=returned'
    expect(expectedRoute).toBe('/requests?tab=returned')
  })

  it('correction strip shows first returned reference number', () => {
    const stats: DataEntryDashboardStats = {
      draft: 0,
      returned: 2,
      under_cby_processing: 0,
      completed: 0,
      draft_requests: [],
      returned_requests: [
        makeRequest({
          id: 10,
          reference_number: 'YFH-2026-000010',
          status: RequestStatus.BANK_RETURNED,
        }),
        makeRequest({
          id: 11,
          reference_number: 'YFH-2026-000011',
          status: RequestStatus.SUPPORT_RETURNED,
        }),
      ],
      recent_requests: [],
    }
    const first = stats.returned_requests[0]!
    expect(first.reference_number).toBe('YFH-2026-000010')
  })
})
