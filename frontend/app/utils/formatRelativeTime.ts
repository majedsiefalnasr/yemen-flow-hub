export function formatRelativeTime(iso: string): string {
  const now = Date.now()
  const target = new Date(iso).getTime()
  const diffSeconds = Math.round((target - now) / 1000)
  const absSeconds = Math.abs(diffSeconds)
  const rtf = new Intl.RelativeTimeFormat('ar', { numeric: 'auto' })

  if (absSeconds < 60) return rtf.format(diffSeconds, 'second')

  const diffMinutes = Math.round(diffSeconds / 60)
  if (Math.abs(diffMinutes) < 60) return rtf.format(diffMinutes, 'minute')

  const diffHours = Math.round(diffMinutes / 60)
  if (Math.abs(diffHours) < 24) return rtf.format(diffHours, 'hour')

  const diffDays = Math.round(diffHours / 24)
  if (Math.abs(diffDays) < 30) return rtf.format(diffDays, 'day')

  const diffMonths = Math.round(diffDays / 30)
  if (Math.abs(diffMonths) < 12) return rtf.format(diffMonths, 'month')

  const diffYears = Math.round(diffMonths / 12)
  return rtf.format(diffYears, 'year')
}
