import { describe, expect, it } from 'vitest'
import { buildOperationalNavBadges } from '../../../composables/useNavBadges'

describe('buildOperationalNavBadges (Phase D0)', () => {
  it('maps unread notifications count to /notifications badge', () => {
    const badges = buildOperationalNavBadges({ actionableCount: null, unreadCount: 7 })
    expect(badges['/notifications']).toBe(7)
  })

  it('sets the /workflows badge to the shared actionable-work count', () => {
    const badges = buildOperationalNavBadges({ actionableCount: 6, unreadCount: 0 })
    expect(badges['/workflows']).toBe(6)
  })

  it('omits the /workflows badge for a user with no actionable work (analytics user)', () => {
    const badges = buildOperationalNavBadges({ actionableCount: 0, unreadCount: 0 })
    expect(badges['/workflows']).toBeUndefined()
  })

  it('omits the /workflows badge when the count is unavailable', () => {
    const badges = buildOperationalNavBadges({ actionableCount: null, unreadCount: 0 })
    expect(badges['/workflows']).toBeUndefined()
  })

  it('never fabricates a /customs badge (no voting/FX remnants)', () => {
    const badges = buildOperationalNavBadges({ actionableCount: 5, unreadCount: 0 })
    expect(badges['/customs']).toBeUndefined()
  })

  it('omits zero/negative badges', () => {
    const badges = buildOperationalNavBadges({ actionableCount: -3, unreadCount: -3 })
    expect(badges['/workflows']).toBeUndefined()
    expect(badges['/notifications']).toBeUndefined()
  })
})
