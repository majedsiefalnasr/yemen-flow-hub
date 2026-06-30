import { describe, expect, it } from 'vitest'
import {
  addTraderOwnerRow,
  removeTraderOwnerRow,
  traderOwnerSchema,
  traderOwnersSchema,
} from '../../../types/trader'

describe('TraderOwnersField', () => {
  it('validates ownership percentage between 0 and 100', () => {
    expect(
      traderOwnerSchema.safeParse({ full_name: 'مالك', ownership_percentage: 0 }).success,
    ).toBe(true)
    expect(
      traderOwnerSchema.safeParse({ full_name: 'مالك', ownership_percentage: 101 }).success,
    ).toBe(false)
  })

  it('enforces required-set fields for owners at 25% or above', () => {
    const result = traderOwnersSchema.safeParse([{ full_name: 'مالك', ownership_percentage: 30 }])

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
        expect.arrayContaining(['0.nationality', '0.identification_number']),
      )
    }
  })

  it('does not require optional owner identifiers below 25%', () => {
    expect(
      traderOwnersSchema.safeParse([{ full_name: 'مالك', ownership_percentage: 10 }]).success,
    ).toBe(true)
  })

  it('adds and removes owner rows immutably', () => {
    const rows = addTraderOwnerRow([])
    expect(rows).toEqual([{ full_name: '', ownership_percentage: 0 }])
    expect(removeTraderOwnerRow(rows, 0)).toEqual([])
  })
})
