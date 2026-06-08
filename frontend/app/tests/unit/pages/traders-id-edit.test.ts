import { describe, expect, it } from 'vitest'
import TraderEditPage from '../../../pages/traders/[id]/edit.vue'
import { UserRole } from '../../../types/enums'
import { canManageTraders } from '../../../types/trader'

describe('/traders/[id]/edit page', () => {
  it('loads the edit page module', () => {
    expect(TraderEditPage).toBeTruthy()
  })

  it('uses the same route-level write guard roles as create', () => {
    expect(canManageTraders(UserRole.DATA_ENTRY)).toBe(true)
    expect(canManageTraders(UserRole.BANK_REVIEWER)).toBe(true)
    expect(canManageTraders(UserRole.BANK_ADMIN)).toBe(true)
    expect(canManageTraders(UserRole.COMMITTEE_DIRECTOR)).toBe(false)
  })
})
