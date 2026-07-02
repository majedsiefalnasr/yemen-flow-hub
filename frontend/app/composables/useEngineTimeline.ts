import type { EngineHistoryEntry } from '@/types/models'

export interface TimelineItem {
  id: number
  fromLabel: string | null
  toLabel: string | null
  actionCode: string | null
  actorName: string
  timestamp: string
  comment: string | null
  isLast: boolean
}

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })

function sortKey(entry: EngineHistoryEntry): number {
  return entry.created_at ? Date.parse(entry.created_at) : Number.MAX_SAFE_INTEGER
}

export function buildTimeline(entries: EngineHistoryEntry[]): TimelineItem[] {
  const ordered = [...entries].sort((a, b) => sortKey(a) - sortKey(b) || a.id - b.id)
  return ordered.map((entry, index) => ({
    id: entry.id,
    fromLabel: entry.from_stage?.name ?? null,
    toLabel: entry.to_stage?.name ?? null,
    actionCode: entry.action_code,
    actorName: entry.performed_by?.name ?? 'النظام',
    timestamp: entry.created_at ? dateFormatter.format(new Date(entry.created_at)) : '—',
    comment: entry.comments,
    isLast: index === ordered.length - 1,
  }))
}
