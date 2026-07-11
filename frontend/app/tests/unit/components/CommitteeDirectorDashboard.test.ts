/**
 * CommitteeDirectorDashboard logic tests (UI-FX-001) — pure-function tests
 * without mounting. The Director's queue is the FINAL stage, mirroring the
 * backend `final_pending` contract; there is no voting UI.
 */
import { describe, it, expect } from 'vitest'
import type {
  CommitteeDirectorDashboardStats,
  DirectorQueueItem,
} from '../../../composables/useDashboard'

function makeItem(overrides: Partial<DirectorQueueItem> = {}): DirectorQueueItem {
  return {
    id: 1,
    reference: 'ENG-2026-YBRD-A011',
    reference_number: 'ENG-2026-YBRD-A011',
    status: 'ACTIVE',
    stage_code: 'FINAL',
    stage_name: 'الاعتماد النهائي',
    bank_name: 'YBRD',
    merchant_name: 'Merchant One',
    amount: 100000,
    currency: 'USD',
    created_at: '2026-05-01T09:00:00.000000Z',
    ...overrides,
  }
}

// Logic mirrored from CommitteeDirectorDashboard.
function normalizeStats(
  raw: Partial<CommitteeDirectorDashboardStats> | null,
): CommitteeDirectorDashboardStats | null {
  if (!raw) return null
  return {
    final_pending: raw.final_pending ?? 0,
    final_pending_queue: Array.isArray(raw.final_pending_queue) ? raw.final_pending_queue : [],
    finalized_approved: raw.finalized_approved ?? 0,
    finalized_rejected: raw.finalized_rejected ?? 0,
  }
}

function sortQueue(items: DirectorQueueItem[]): DirectorQueueItem[] {
  return [...items].sort((a, b) => {
    const at = new Date(a.created_at ?? 0).getTime()
    const bt = new Date(b.created_at ?? 0).getTime()
    return at - bt
  })
}

function formatAmount(amount: number | null, currency: string | null): string {
  if (amount === null) return '—'
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency: currency ?? 'USD',
    minimumFractionDigits: 0,
  }).format(amount)
}

describe('CommitteeDirectorDashboard — stats normalization', () => {
  it('returns null when there are no stats', () => {
    expect(normalizeStats(null)).toBeNull()
  })

  it('defaults every counter and coerces a non-array queue to []', () => {
    const stats = normalizeStats({ final_pending_queue: undefined })
    expect(stats).toEqual({
      final_pending: 0,
      final_pending_queue: [],
      finalized_approved: 0,
      finalized_rejected: 0,
    })
  })

  it('passes through the FINAL queue and counters', () => {
    const queue = [makeItem()]
    const stats = normalizeStats({
      final_pending: 1,
      final_pending_queue: queue,
      finalized_approved: 4,
      finalized_rejected: 4,
    })
    expect(stats?.final_pending).toBe(1)
    expect(stats?.final_pending_queue).toHaveLength(1)
    expect(stats?.finalized_approved).toBe(4)
    expect(stats?.finalized_rejected).toBe(4)
  })
})

describe('CommitteeDirectorDashboard — queue ordering', () => {
  it('orders the FINAL queue oldest-first (earliest created_at leads)', () => {
    const newer = makeItem({ id: 2, reference_number: 'B', created_at: '2026-06-01T00:00:00Z' })
    const older = makeItem({ id: 1, reference_number: 'A', created_at: '2026-05-01T00:00:00Z' })
    const sorted = sortQueue([newer, older])
    expect(sorted.map((r) => r.id)).toEqual([1, 2])
  })
})

describe('CommitteeDirectorDashboard — amount formatting', () => {
  it('renders an em dash for a null amount', () => {
    expect(formatAmount(null, 'USD')).toBe('—')
  })

  it('formats a numeric amount (locale digits) rather than the empty dash', () => {
    const formatted = formatAmount(100000, 'USD')
    expect(formatted).not.toBe('—')
    expect(formatted.length).toBeGreaterThan(0)
  })
})
