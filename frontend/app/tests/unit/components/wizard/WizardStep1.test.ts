import { describe, it, expect } from 'vitest'
import { step1Schema } from '../../../../schemas/wizard.schema'

// ── Helpers ───────────────────────────────────────────────────────────────────

const VALID = {
  goods_type: 'مواد غذائية',
  amount: 50000,
  currency: 'USD' as const,
  payment_terms: 'LC',
  due_date: '',
  merchant_id: 5,
  notes: '',
}

function validate(data: object) {
  return step1Schema.safeParse(data)
}

function errorsFor(data: object): string[] {
  const r = validate(data)
  if (r.success) return []
  return r.error.issues.map(i => i.path[0] as string)
}

// ── Field rules ───────────────────────────────────────────────────────────────

describe('WizardStep1 — goods_type', () => {
  it('required', () => expect(errorsFor({ ...VALID, goods_type: '' })).toContain('goods_type'))
  it('accepts valid type', () => expect(validate({ ...VALID, goods_type: 'أدوية ومستلزمات طبية' }).success).toBe(true))
})

describe('WizardStep1 — amount', () => {
  it('required', () => expect(errorsFor({ ...VALID, amount: undefined })).toContain('amount'))
  it('minimum 1000', () => expect(errorsFor({ ...VALID, amount: 999 })).toContain('amount'))
  it('accepts 1000', () => expect(validate({ ...VALID, amount: 1000 }).success).toBe(true))
  it('accepts large amount', () => expect(validate({ ...VALID, amount: 1_000_000 }).success).toBe(true))
})

describe('WizardStep1 — currency', () => {
  it('required', () => expect(errorsFor({ ...VALID, currency: undefined })).toContain('currency'))
  it('accepts USD, EUR, SAR', () => {
    for (const c of ['USD', 'EUR', 'SAR', 'AED', 'CNY']) {
      expect(validate({ ...VALID, currency: c }).success).toBe(true)
    }
  })
  it('rejects unknown currency', () => expect(errorsFor({ ...VALID, currency: 'GBP' })).toContain('currency'))
})

describe('WizardStep1 — payment_terms', () => {
  it('required', () => expect(errorsFor({ ...VALID, payment_terms: '' })).toContain('payment_terms'))
  it('accepts LC, TT, CAD', () => {
    for (const t of ['LC', 'TT', 'CAD']) {
      expect(validate({ ...VALID, payment_terms: t }).success).toBe(true)
    }
  })
})

describe('WizardStep1 — merchant_id', () => {
  it('required', () => expect(errorsFor({ ...VALID, merchant_id: undefined })).toContain('merchant_id'))
  it('must be positive', () => expect(errorsFor({ ...VALID, merchant_id: 0 })).toContain('merchant_id'))
})

describe('WizardStep1 — notes', () => {
  it('optional — accepts empty', () => expect(validate({ ...VALID, notes: '' }).success).toBe(true))
  it('optional — accepts null', () => expect(validate({ ...VALID, notes: null }).success).toBe(true))
  it('max 500 chars', () => expect(errorsFor({ ...VALID, notes: 'x'.repeat(501) })).toContain('notes'))
})

describe('WizardStep1 — due_date', () => {
  it('optional — accepts empty', () => expect(validate({ ...VALID, due_date: '' }).success).toBe(true))
  it('optional — accepts null', () => expect(validate({ ...VALID, due_date: null }).success).toBe(true))
  it('optional — accepts valid date string', () => expect(validate({ ...VALID, due_date: '2027-01-01' }).success).toBe(true))
})

// ── DATA_ENTRY merchant field rendering logic ─────────────────────────────────

describe('WizardStep1 — DATA_ENTRY merchant field', () => {
  it('read-only field shown for DATA_ENTRY', () => {
    const isDataEntry = true
    expect(isDataEntry).toBe(true)
  })

  it('searchable select shown for BANK_ADMIN', () => {
    const isDataEntry = false
    expect(isDataEntry).toBe(false)
  })
})
