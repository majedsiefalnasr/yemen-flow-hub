import { describe, expect, it } from 'vitest'
import TraderForm from '../../../components/trader/TraderForm.vue'
import type { CreateTraderPayload } from '../../../types/trader'
import { buildTraderPayload, traderFormSchema } from '../../../types/trader'

describe('TraderForm', () => {
  it('is available as the shared create/edit form component', () => {
    expect(TraderForm).toBeTruthy()
  })

  it('normalizes empty optional sub-form fields before submit', () => {
    const payload = buildTraderPayload({
      tax_number: 'TX-100',
      trader_name: 'شركة الاختبار',
      tax_card_expiry: '2027-01-01',
      commercial_registration_number: 'CR-100',
      commercial_registration_expiry: '2027-01-01',
      companies: [{ company_name: '  شركة مرتبطة  ' }],
      owners: [
        {
          full_name: 'مالك',
          ownership_percentage: 10,
          nationality: '',
          identification_number: '',
        },
      ],
    }) as CreateTraderPayload

    expect(payload.companies[0]?.company_name).toBe('شركة مرتبطة')
    expect(payload.owners[0]?.nationality).toBeNull()
    expect(payload.owners[0]?.identification_number).toBeNull()
  })

  it('uses the trader form schema for create and edit validation', () => {
    const valid = traderFormSchema.safeParse({
      tax_number: 'TX-100',
      trader_name: 'شركة الاختبار',
      tax_card_expiry: '2027-01-01',
      commercial_registration_number: 'CR-100',
      commercial_registration_expiry: '2027-01-01',
      companies: [],
      owners: [],
    })

    expect(valid.success).toBe(true)
  })
})
