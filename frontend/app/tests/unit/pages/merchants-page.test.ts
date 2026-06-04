import { describe, expect, it } from 'vitest'
import type { Bank, Merchant } from '../../../types/models'

function makeMerchant(overrides: Partial<Merchant> = {}): Merchant {
  return {
    id: 1,
    bank_id: 1,
    bank_name: 'بنك اليمن الدولي',
    name: 'شركة الأمل',
    commercial_register: 'CR-100',
    tax_number: 'TX-100',
    national_id: null,
    owner_name: null,
    phone: null,
    email: null,
    address: null,
    business_type: 'import',
    is_active: true,
    transaction_count: 4,
    created_by: 1,
    created_at: '2026-05-01T00:00:00.000Z',
    ...overrides,
  }
}

function mapBankOptions(banks: Bank[]) {
  return banks.map((bank) => ({
    id: bank.id,
    name: `${bank.name_ar || bank.name_en}${bank.is_active ? '' : ' (موقوف)'}`,
  }))
}

function filterMerchants(merchants: Merchant[], searchQuery: string, bankFilter = '') {
  let list = merchants
  if (searchQuery.trim()) {
    const q = searchQuery.trim().toLowerCase()
    list = list.filter(
      (m) =>
        m.name.toLowerCase().includes(q) ||
        (m.commercial_register ?? '').toLowerCase().includes(q) ||
        (m.tax_number ?? '').toLowerCase().includes(q) ||
        (m.bank_name ?? '').toLowerCase().includes(q),
    )
  }

  if (bankFilter) {
    list = list.filter((m) => String(m.bank_id) === bankFilter)
  }

  return list
}

describe('merchants page bank options', () => {
  it('keeps inactive banks in the CBY filter list', () => {
    const options = mapBankOptions([
      { id: 1, name_ar: 'بنك اليمن الدولي', name_en: 'YIB', code: 'YIB', is_active: true },
      { id: 2, name_ar: 'بنك السلام', name_en: 'Al Salam Bank', code: 'SLM', is_active: false },
    ])

    expect(options).toEqual([
      { id: 1, name: 'بنك اليمن الدولي' },
      { id: 2, name: 'بنك السلام (موقوف)' },
    ])
  })
})

describe('merchants page filtering', () => {
  it('matches bank_name in the search box', () => {
    const results = filterMerchants(
      [
        makeMerchant({ id: 1, bank_id: 1, bank_name: 'بنك اليمن الدولي', name: 'شركة الأمل' }),
        makeMerchant({ id: 2, bank_id: 2, bank_name: 'بنك السلام', name: 'مؤسسة النور' }),
      ],
      'السلام',
    )

    expect(results).toHaveLength(1)
    expect(results[0]?.bank_name).toBe('بنك السلام')
  })

  it('can filter down to merchants from an inactive bank by bank id', () => {
    const results = filterMerchants(
      [
        makeMerchant({ id: 1, bank_id: 1, bank_name: 'بنك اليمن الدولي' }),
        makeMerchant({ id: 2, bank_id: 2, bank_name: 'بنك السلام' }),
      ],
      '',
      '2',
    )

    expect(results).toHaveLength(1)
    expect(results[0]?.id).toBe(2)
  })
})
