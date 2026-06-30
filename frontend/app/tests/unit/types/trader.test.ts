import { describe, expect, it } from 'vitest'
import type { CreateTraderPayload, Trader, TraderCompany, TraderOwner } from '../../../types/trader'
import { traderFormSchema, isMajorOwner } from '../../../types/trader'

const trader: Trader = {
  id: 1,
  tax_number: 'TX-100',
  trader_name: 'شركة الاختبار',
  tax_card_expiry: '2027-01-01',
  commercial_registration_number: 'CR-100',
  commercial_registration_expiry: '2027-01-01',
  companies_count: 1,
  owners_count: 1,
  companies: [{ id: 11, company_name: 'شركة فرعية' }] satisfies TraderCompany[],
  owners: [
    {
      id: 21,
      full_name: 'مالك رئيسي',
      ownership_percentage: 25,
      nationality: 'Yemeni',
      identification_number: 'ID-1',
    },
  ] satisfies TraderOwner[],
  created_at: '2026-06-08T00:00:00.000000Z',
  updated_at: '2026-06-08T00:00:00.000000Z',
}

describe('trader types and schemas', () => {
  it('matches the TraderResource nested shape', () => {
    expect(trader.companies[0]?.company_name).toBe('شركة فرعية')
    expect(trader.owners[0]?.ownership_percentage).toBe(25)
  })

  it('accepts a create payload with companies and owners', () => {
    const payload: CreateTraderPayload = {
      tax_number: 'TX-101',
      trader_name: 'تاجر جديد',
      tax_card_expiry: '2027-02-01',
      commercial_registration_number: 'CR-101',
      commercial_registration_expiry: '2027-02-01',
      companies: [{ company_name: 'شركة مرتبطة' }],
      owners: [
        {
          full_name: 'مالك رئيسي',
          ownership_percentage: 25,
          nationality: 'Yemeni',
          identification_number: 'ID-2',
        },
      ],
    }

    expect(traderFormSchema.safeParse(payload).success).toBe(true)
  })

  it('requires nationality and identification number for owners at 25% or above', () => {
    const result = traderFormSchema.safeParse({
      tax_number: 'TX-102',
      trader_name: 'تاجر ناقص',
      tax_card_expiry: '2027-03-01',
      commercial_registration_number: 'CR-102',
      commercial_registration_expiry: '2027-03-01',
      companies: [],
      owners: [{ full_name: 'مالك', ownership_percentage: 25 }],
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
        expect.arrayContaining(['owners.0.nationality', 'owners.0.identification_number']),
      )
    }
  })

  it('classifies major owners from the locked 25% threshold', () => {
    expect(isMajorOwner({ ownership_percentage: 25 })).toBe(true)
    expect(isMajorOwner({ ownership_percentage: 24.99 })).toBe(false)
  })
})
