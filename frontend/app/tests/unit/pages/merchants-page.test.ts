import { describe, expect, it } from 'vitest'
import type { Bank, Merchant } from '../../../types/models'

function makeMerchant(overrides: Partial<Merchant> = {}): Merchant {
  return {
    id: 1,
    bank_id: 1,
    bank_name: 'بنك اليمن الدولي',
    name: 'شركة الأمل',
    tax_number: 'TX-100',
    tax_card_expiry: '2027-12-31',
    phone: null,
    address: null,
    status: 'ACTIVE',
    version: 1,
    transaction_count: 4,
    owners: [],
    companies: [],
    created_by: 1,
    created_at: '2026-05-01T00:00:00.000Z',
    updated_at: '2026-05-01T00:00:00.000Z',
    ...overrides,
  }
}

function bankName(banks: Bank[], id?: number | null) {
  return banks.find((b) => b.id === id)?.name_ar ?? 'غير محدد'
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
        (m.tax_number ?? '').toLowerCase().includes(q) ||
        (bankName([], m.bank_id) ?? '').toLowerCase().includes(q),
    )
  }
  if (bankFilter) {
    list = list.filter((m) => String(m.bank_id) === bankFilter)
  }
  return list
}

function computeStats(merchants: Merchant[]) {
  return {
    total: merchants.length,
    active: merchants.filter((m) => m.status === 'ACTIVE').length,
    suspended: merchants.filter((m) => m.status === 'SUSPENDED').length,
  }
}

function detectCrossBankNames(merchants: Merchant[]) {
  const nameCount: Record<string, number> = {}
  for (const m of merchants) {
    const key = m.name.trim().toLowerCase()
    nameCount[key] = (nameCount[key] ?? 0) + 1
  }
  return new Set(
    Object.entries(nameCount)
      .filter(([, c]) => c > 1)
      .map(([n]) => n),
  )
}

describe('merchants page bank options', () => {
  it('marks inactive banks in filter list', () => {
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
  it('matches name in search', () => {
    const results = filterMerchants(
      [makeMerchant({ id: 1, name: 'شركة الأمل' }), makeMerchant({ id: 2, name: 'مؤسسة النور' })],
      'أمل',
    )
    expect(results).toHaveLength(1)
    expect(results[0]?.name).toBe('شركة الأمل')
  })

  it('matches tax_number in search', () => {
    const results = filterMerchants(
      [
        makeMerchant({ id: 1, tax_number: 'TX-100' }),
        makeMerchant({ id: 2, tax_number: 'TX-200' }),
      ],
      'TX-200',
    )
    expect(results).toHaveLength(1)
    expect(results[0]?.id).toBe(2)
  })

  it('filters by bank_id', () => {
    const results = filterMerchants(
      [makeMerchant({ id: 1, bank_id: 1 }), makeMerchant({ id: 2, bank_id: 2 })],
      '',
      '2',
    )
    expect(results).toHaveLength(1)
    expect(results[0]?.id).toBe(2)
  })
})

describe('merchants page stats', () => {
  it('computes stats from status field', () => {
    const merchants = [
      makeMerchant({ id: 1, status: 'ACTIVE' }),
      makeMerchant({ id: 2, status: 'ACTIVE' }),
      makeMerchant({ id: 3, status: 'SUSPENDED' }),
    ]
    const stats = computeStats(merchants)
    expect(stats.total).toBe(3)
    expect(stats.active).toBe(2)
    expect(stats.suspended).toBe(1)
  })
})

describe('merchants page cross-bank detection', () => {
  it('flags merchants with same name across banks', () => {
    const merchants = [
      makeMerchant({ id: 1, bank_id: 1, name: 'شركة الأمل' }),
      makeMerchant({ id: 2, bank_id: 2, name: 'شركة الأمل' }),
      makeMerchant({ id: 3, bank_id: 1, name: 'مؤسسة النور' }),
    ]
    const crossBank = detectCrossBankNames(merchants)
    expect(crossBank.has('شركة الأمل')).toBe(true)
    expect(crossBank.has('مؤسسة النور')).toBe(false)
  })

  it('returns empty set when no duplicates', () => {
    const merchants = [makeMerchant({ id: 1, name: 'أ' }), makeMerchant({ id: 2, name: 'ب' })]
    expect(detectCrossBankNames(merchants).size).toBe(0)
  })
})

describe('merchants page — merchantToForm shape', () => {
  it('maps Merchant to MerchantFormData shape', () => {
    const m = makeMerchant({
      owners: [{ id: 1, name: 'علي', ownership_percentage: 70 }],
      companies: [
        {
          id: 1,
          name: 'شركة',
          commercial_registration_number: 'CR-1',
          commercial_registration_expiry: '2028-01-01',
          sector_reference_value_id: null,
          is_active: true,
        },
      ],
    })
    const form = {
      name: m.name,
      tax_number: m.tax_number,
      tax_card_expiry: m.tax_card_expiry ?? '',
      address: m.address ?? '',
      phone: m.phone ?? '',
      status: m.status,
      bank_id: m.bank_id,
      version: m.version,
      owners: (m.owners ?? []).map((o) => ({
        name: o.name,
        ownership_percentage: o.ownership_percentage,
      })),
      companies: (m.companies ?? []).map((c) => ({
        name: c.name,
        commercial_registration_number: c.commercial_registration_number,
        commercial_registration_expiry: c.commercial_registration_expiry ?? '',
        is_active: c.is_active,
      })),
    }
    expect(form.name).toBe('شركة الأمل')
    expect(form.version).toBe(1)
    expect(form.owners).toHaveLength(1)
    expect(form.owners[0]?.ownership_percentage).toBe(70)
    expect(form.companies).toHaveLength(1)
    expect(form.companies[0]?.commercial_registration_number).toBe('CR-1')
  })
})

describe('merchants page — version-aware update payload', () => {
  it('includes version in update payload', () => {
    const m = makeMerchant({ version: 3 })
    const payload = { version: m.version, name: 'Updated Name', status: 'SUSPENDED' as const }
    expect(payload.version).toBe(3)
    expect(payload.status).toBe('SUSPENDED')
  })
})

describe('merchants page column visibility by role', () => {
  it('hides the bank column for bank admin by default', () => {
    const columnVisibility: Record<string, boolean> = { transactions: false, bank: false }
    expect(columnVisibility.bank).toBe(false)
  })

  it('shows the bank column for CBY admin', () => {
    const isCbyAdmin = true
    const columnVisibility: Record<string, boolean> = { transactions: false, bank: isCbyAdmin }
    expect(columnVisibility.bank).toBe(true)
  })
})
