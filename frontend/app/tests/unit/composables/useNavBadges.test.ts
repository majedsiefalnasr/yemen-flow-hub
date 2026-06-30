import { describe, expect, it } from 'vitest'
import { buildOperationalNavBadges } from '../../../composables/useNavBadges'
import { UserRole } from '../../../types/enums'

describe('buildOperationalNavBadges', () => {
  it('maps unread notifications count to /notifications badge', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.DATA_ENTRY,
      stats: null,
      unreadCount: 7,
    })

    expect(badges['/notifications']).toBe(7)
  })

  it('uses DATA_ENTRY returned count for /requests badge', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.DATA_ENTRY,
      stats: { returned: 4 } as any,
      unreadCount: 0,
    })

    expect(badges['/requests']).toBe(4)
  })

  it('sums support waiting + active for SUPPORT_COMMITTEE /requests badge', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.SUPPORT_COMMITTEE,
      stats: { waiting_for_claim: 6, active_by_me: 2 } as any,
      unreadCount: 0,
    })

    expect(badges['/requests']).toBe(8)
  })

  it('uses pending_my_vote for EXECUTIVE_MEMBER /requests badge', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.EXECUTIVE_MEMBER,
      stats: { pending_my_vote: 3, active_voting_sessions: 9 } as any,
      unreadCount: 0,
    })

    expect(badges['/requests']).toBe(3)
  })

  it('sets director /requests and /customs badges from real FX/voting counts', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.COMMITTEE_DIRECTOR,
      stats: {
        sessions_ready_to_close: 2,
        sessions_with_tie: 1,
        fx_confirmation_pending: 5,
      } as any,
      unreadCount: 0,
    })

    expect(badges['/requests']).toBe(8)
    expect(badges['/customs']).toBe(5)
  })

  it('uses active_workflow_requests KPI value for CBY_ADMIN /requests badge', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.CBY_ADMIN,
      stats: { active_workflow_requests: { value: 11 } } as any,
      unreadCount: 0,
    })

    expect(badges['/requests']).toBe(11)
  })

  it('omits zero/negative badges', () => {
    const badges = buildOperationalNavBadges({
      role: UserRole.SWIFT_OFFICER,
      stats: { pending_swift_upload: 0 } as any,
      unreadCount: -3,
    })

    expect(badges['/requests']).toBeUndefined()
    expect(badges['/notifications']).toBeUndefined()
  })
})
