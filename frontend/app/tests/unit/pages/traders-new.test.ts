import { describe, expect, it } from 'vitest'
import TradersNewPage from '../../../pages/traders/new.vue'
import { UserRole } from '../../../types/enums'
import { TRADER_MANAGEMENT_ROLES } from '../../../types/trader'

describe('/traders/new page', () => {
  it('loads the create page module', () => {
    expect(TradersNewPage).toBeTruthy()
  })

  it('declares the bank roles allowed to create traders', () => {
    expect(TRADER_MANAGEMENT_ROLES).toEqual([
      UserRole.DATA_ENTRY,
      UserRole.BANK_REVIEWER,
      UserRole.BANK_ADMIN,
    ])
  })
})
