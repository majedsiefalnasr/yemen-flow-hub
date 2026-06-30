import { UserRole } from '../types/enums'
import type { DashboardStats } from './useDashboard'

type RouteBadgeMap = Partial<Record<string, number>>

function asPositiveCount(value: any): number | undefined {
  if (typeof value !== 'number' || !Number.isFinite(value)) return undefined
  const normalized = Math.max(0, Math.trunc(value))
  return normalized > 0 ? normalized : undefined
}

function sumPositiveCounts(...values: any[]): number | undefined {
  let total = 0
  for (const value of values) {
    total += asPositiveCount(value) ?? 0
  }
  return total > 0 ? total : undefined
}

function requestsBadgeForRole(role: UserRole, stats: DashboardStats | null): number | undefined {
  if (!stats) return undefined

  switch (role) {
    case UserRole.DATA_ENTRY:
      return asPositiveCount('returned' in stats ? stats.returned : undefined)
    case UserRole.BANK_REVIEWER:
      return asPositiveCount('pending_review' in stats ? stats.pending_review : undefined)
    case UserRole.BANK_ADMIN:
      return asPositiveCount('pending' in stats ? stats.pending : undefined)
    case UserRole.SUPPORT_COMMITTEE:
      return sumPositiveCounts(
        'waiting_for_claim' in stats ? stats.waiting_for_claim : undefined,
        'active_by_me' in stats ? stats.active_by_me : undefined,
      )
    case UserRole.SWIFT_OFFICER:
      return asPositiveCount(
        'pending_swift_upload' in stats ? stats.pending_swift_upload : undefined,
      )
    case UserRole.EXECUTIVE_MEMBER:
      return asPositiveCount(
        'pending_my_vote' in stats && stats.pending_my_vote != null
          ? stats.pending_my_vote
          : 'active_voting_sessions' in stats
            ? stats.active_voting_sessions
            : undefined,
      )
    case UserRole.COMMITTEE_DIRECTOR:
      return sumPositiveCounts(
        'sessions_ready_to_close' in stats ? stats.sessions_ready_to_close : undefined,
        'sessions_with_tie' in stats ? stats.sessions_with_tie : undefined,
        typeof ('fx_confirmation_pending' in stats ? stats.fx_confirmation_pending : undefined) ===
          'number'
          ? 'fx_confirmation_pending' in stats
            ? stats.fx_confirmation_pending
            : undefined
          : undefined,
      )
    case UserRole.CBY_ADMIN:
      return asPositiveCount(
        'active_workflow_requests' in stats ? stats.active_workflow_requests?.value : undefined,
      )
    default:
      return undefined
  }
}

function customsBadgeForDirector(stats: DashboardStats | null): number | undefined {
  if (!stats || !('fx_confirmation_pending' in stats)) return undefined
  if (typeof stats.fx_confirmation_pending !== 'number') return undefined
  return asPositiveCount(stats.fx_confirmation_pending)
}

export function buildOperationalNavBadges(params: {
  role: UserRole | null | undefined
  stats: DashboardStats | null
  unreadCount: number
}): RouteBadgeMap {
  const badges: RouteBadgeMap = {}

  const notificationsBadge = asPositiveCount(params.unreadCount)
  if (notificationsBadge) {
    badges['/notifications'] = notificationsBadge
  }

  if (!params.role) return badges

  const requestsBadge = requestsBadgeForRole(params.role, params.stats)
  if (requestsBadge) {
    badges['/requests'] = requestsBadge
  }

  if (params.role === UserRole.COMMITTEE_DIRECTOR) {
    const customsBadge = customsBadgeForDirector(params.stats)
    if (customsBadge) {
      badges['/customs'] = customsBadge
    }
  }

  return badges
}
