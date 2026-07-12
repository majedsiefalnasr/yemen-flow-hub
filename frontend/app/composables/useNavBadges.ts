type RouteBadgeMap = Partial<Record<string, number>>

function asPositiveCount(value: unknown): number | undefined {
  if (typeof value !== 'number' || !Number.isFinite(value)) return undefined
  const normalized = Math.max(0, Math.trunc(value))
  return normalized > 0 ? normalized : undefined
}

/**
 * Operational nav badges (Phase D0). The `/workflows` badge is the user's
 * actionable-work count from the single shared actionable query (the same record
 * set the dashboard and /my-queue show) — never a per-role stats key. Analytics
 * users have no actionable work, so they get no fabricated workflow badge.
 * Executive voting is out of V1 and no longer contributes any badge.
 */
export function buildOperationalNavBadges(params: {
  actionableCount: number | null | undefined
  unreadCount: number
}): RouteBadgeMap {
  const badges: RouteBadgeMap = {}

  const notificationsBadge = asPositiveCount(params.unreadCount)
  if (notificationsBadge) {
    badges['/notifications'] = notificationsBadge
  }

  const workflowsBadge = asPositiveCount(params.actionableCount)
  if (workflowsBadge) {
    badges['/workflows'] = workflowsBadge
  }

  return badges
}
