import { describe, expect, it } from 'vitest'
import { buildTimeline } from '@/composables/useEngineTimeline'
import type { EngineHistoryEntry } from '@/types/models'

const entry = (id: number, created_at: string | null, over: Partial<EngineHistoryEntry> = {}): EngineHistoryEntry => ({
  id,
  from_stage: { id: 1, code: 'S1', name: 'الإدخال' },
  to_stage: { id: 2, code: 'S2', name: 'المراجعة' },
  action_code: 'SUBMIT',
  performed_by: { id: 7, name: 'أحمد' },
  comments: null,
  created_at,
  ...over,
})

describe('buildTimeline', () => {
  it('sorts ascending by created_at and flags the last item', () => {
    const items = buildTimeline([
      entry(2, '2026-06-02T10:00:00Z'),
      entry(1, '2026-06-01T10:00:00Z'),
    ])
    expect(items.map((i) => i.id)).toEqual([1, 2])
    expect(items.at(-1)?.isLast).toBe(true)
    expect(items[0]!.isLast).toBe(false)
  })

  it('falls back to النظام actor and dash timestamp', () => {
    const items = buildTimeline([entry(1, null, { performed_by: null })])
    expect(items[0]!.actorName).toBe('النظام')
    expect(items[0]!.timestamp).toBe('—')
  })

  it('maps stage names and comment through', () => {
    const items = buildTimeline([entry(1, '2026-06-01T10:00:00Z', { comments: 'ملاحظة' })])
    expect(items[0]!.fromLabel).toBe('الإدخال')
    expect(items[0]!.toLabel).toBe('المراجعة')
    expect(items[0]!.comment).toBe('ملاحظة')
  })

  it('returns [] for no entries', () => {
    expect(buildTimeline([])).toEqual([])
  })
})
