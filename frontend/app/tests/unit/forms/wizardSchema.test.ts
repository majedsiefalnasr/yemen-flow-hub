import { describe, it, expect } from 'vitest'
import { step1Schema, step2Schema, CUSTOMS_BY_PORT } from '../../../schemas/wizard.schema'

// ── Step 1 schema ─────────────────────────────────────────────────────────────

const VALID_STEP1 = {
  goods_type: 'مواد غذائية',
  amount: 50000,
  currency: 'USD' as const,
  payment_terms: 'LC',
  due_date: '',
  merchant_id: 5,
  notes: '',
}

describe('step1Schema — valid', () => {
  it('accepts fully valid step 1 data', () => {
    expect(step1Schema.safeParse(VALID_STEP1).success).toBe(true)
  })

  it('accepts optional due_date as null or empty', () => {
    expect(step1Schema.safeParse({ ...VALID_STEP1, due_date: null }).success).toBe(true)
    expect(step1Schema.safeParse({ ...VALID_STEP1, due_date: undefined }).success).toBe(true)
  })

  it('accepts a future due_date', () => {
    const future = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000)
    const iso = `${future.getFullYear()}-${String(future.getMonth() + 1).padStart(2, '0')}-${String(future.getDate()).padStart(2, '0')}`
    expect(step1Schema.safeParse({ ...VALID_STEP1, due_date: iso }).success).toBe(true)
  })

  it('rejects today as due_date (backend requires after:today)', () => {
    const today = new Date()
    const iso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
    expect(step1Schema.safeParse({ ...VALID_STEP1, due_date: iso }).success).toBe(false)
  })

  it('rejects a past due_date', () => {
    const past = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
    const iso = `${past.getFullYear()}-${String(past.getMonth() + 1).padStart(2, '0')}-${String(past.getDate()).padStart(2, '0')}`
    expect(step1Schema.safeParse({ ...VALID_STEP1, due_date: iso }).success).toBe(false)
  })

  it('accepts notes as null or omitted', () => {
    const r = step1Schema.safeParse({ ...VALID_STEP1, notes: null })
    expect(r.success).toBe(true)
  })
})

describe('step1Schema — required fields', () => {
  it('rejects missing goods_type', () => {
    const { goods_type: _, ...rest } = VALID_STEP1
    expect(step1Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects missing amount', () => {
    const { amount: _, ...rest } = VALID_STEP1
    expect(step1Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects amount below 1000', () => {
    const r = step1Schema.safeParse({ ...VALID_STEP1, amount: 500 })
    expect(r.success).toBe(false)
    if (!r.success) expect(r.error.issues[0]!.path).toContain('amount')
  })

  it('rejects missing payment_terms', () => {
    const { payment_terms: _, ...rest } = VALID_STEP1
    expect(step1Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects missing merchant_id', () => {
    const { merchant_id: _, ...rest } = VALID_STEP1
    expect(step1Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects non-positive merchant_id', () => {
    expect(step1Schema.safeParse({ ...VALID_STEP1, merchant_id: 0 }).success).toBe(false)
  })

  it('rejects notes exceeding 500 chars', () => {
    const r = step1Schema.safeParse({ ...VALID_STEP1, notes: 'a'.repeat(501) })
    expect(r.success).toBe(false)
  })
})

// ── Step 2 schema ─────────────────────────────────────────────────────────────

const VALID_STEP2 = {
  supplier_name: 'Cargill Trading Inc.',
  invoice_number: 'INV-2025-001',
  origin_country: 'الولايات المتحدة',
  invoice_date: '2025-12-01',
  arrival_port: 'ميناء عدن',
  shipping_port: '',
  customs_office: '',
  bl_number: '',
}

describe('step2Schema — valid', () => {
  it('accepts fully valid step 2 data', () => {
    expect(step2Schema.safeParse(VALID_STEP2).success).toBe(true)
  })

  it('accepts optional fields as empty string', () => {
    expect(
      step2Schema.safeParse({
        ...VALID_STEP2,
        shipping_port: '',
        customs_office: '',
        bl_number: '',
      }).success,
    ).toBe(true)
  })
})

describe('step2Schema — required fields', () => {
  it('rejects missing supplier_name', () => {
    const { supplier_name: _, ...rest } = VALID_STEP2
    expect(step2Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects missing invoice_number', () => {
    const { invoice_number: _, ...rest } = VALID_STEP2
    expect(step2Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects missing origin_country', () => {
    const { origin_country: _, ...rest } = VALID_STEP2
    expect(step2Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects missing invoice_date', () => {
    const { invoice_date: _, ...rest } = VALID_STEP2
    expect(step2Schema.safeParse(rest).success).toBe(false)
  })

  it('rejects missing arrival_port', () => {
    const { arrival_port: _, ...rest } = VALID_STEP2
    expect(step2Schema.safeParse(rest).success).toBe(false)
  })
})

// ── CUSTOMS_BY_PORT mapping ───────────────────────────────────────────────────

describe('CUSTOMS_BY_PORT auto-fill mapping', () => {
  it('maps ميناء عدن to جمارك عدن', () => {
    expect(CUSTOMS_BY_PORT['ميناء عدن']).toBe('جمارك عدن')
  })

  it('maps ميناء الحديدة to جمارك الحديدة', () => {
    expect(CUSTOMS_BY_PORT['ميناء الحديدة']).toBe('جمارك الحديدة')
  })

  it('maps ميناء المكلا to جمارك المكلا', () => {
    expect(CUSTOMS_BY_PORT['ميناء المكلا']).toBe('جمارك المكلا')
  })

  it('returns undefined for unknown port', () => {
    expect(CUSTOMS_BY_PORT['ميناء مجهول']).toBeUndefined()
  })
})
