import { describe, expect, it } from 'vitest'
import {
  addTraderCompanyRow,
  removeTraderCompanyRow,
  traderCompanySchema,
} from '../../../types/trader'

describe('TraderCompaniesField', () => {
  it('validates required company names', () => {
    expect(traderCompanySchema.safeParse({ company_name: 'شركة مرتبطة' }).success).toBe(true)
    expect(traderCompanySchema.safeParse({ company_name: '' }).success).toBe(false)
  })

  it('adds and removes company rows immutably', () => {
    const rows = addTraderCompanyRow([])
    expect(rows).toEqual([{ company_name: '' }])

    const updated = addTraderCompanyRow([{ company_name: 'شركة أولى' }])
    expect(updated).toHaveLength(2)
    expect(removeTraderCompanyRow(updated, 0)).toEqual([{ company_name: '' }])
  })
})
