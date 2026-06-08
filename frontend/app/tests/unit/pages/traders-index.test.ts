import { describe, expect, it } from 'vitest'
import TradersIndexPage from '../../../pages/traders/index.vue'
import { UserRole } from '../../../types/enums'
import { canManageTraders } from '../../../types/trader'

describe('/traders page', () => {
  it('loads the trader list page module', () => {
    expect(TradersIndexPage).toBeTruthy()
  })

  it('allows bank operational roles to access trader management', () => {
    expect(canManageTraders(UserRole.DATA_ENTRY)).toBe(true)
    expect(canManageTraders(UserRole.BANK_REVIEWER)).toBe(true)
    expect(canManageTraders(UserRole.BANK_ADMIN)).toBe(true)
  })

  it('denies CBY-only roles from trader management', () => {
    expect(canManageTraders(UserRole.CBY_ADMIN)).toBe(false)
    expect(canManageTraders(UserRole.SUPPORT_COMMITTEE)).toBe(false)
  })
})
