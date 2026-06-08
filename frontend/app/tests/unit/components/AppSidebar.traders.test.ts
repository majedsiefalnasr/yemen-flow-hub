import { describe, expect, it } from 'vitest'
import { NAV_ITEMS } from '../../../constants/workflow'
import { TRADER_MANAGEMENT_ROLES } from '../../../types/trader'

describe('AppSidebar trader nav entry', () => {
  const traderNav = () => NAV_ITEMS.find((item) => item.route === '/traders')

  it('registers إدارة التجار in the navigation config', () => {
    expect(traderNav()?.label).toBe('إدارة التجار')
  })

  it('is visible only to the permitted bank roles', () => {
    expect(traderNav()?.roles).toEqual([...TRADER_MANAGEMENT_ROLES])
  })
})
