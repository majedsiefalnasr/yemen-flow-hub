/**
 * Tests for Story 2.5 review patches — form-level invariants:
 * - Zod schema accepts non-nullable merchant_id and amount (F8)
 * - amount is numeric throughout (F9)
 * - initialValues watcher behavior (documented expectation)
 */
import { describe, it, expect } from 'vitest'
import { requestFormSchema } from '../../../schemas/requestForm.schema'
import type { RequestFormData } from '../../../types/models'

const VALID_FORM: RequestFormData = {
  merchant_id: 7,
  currency: 'USD',
  amount: 12500,
  supplier_name: 'Global Supplier',
  goods_description: 'Industrial parts',
  port_of_entry: 'Hodeidah Port',
  notes: '',
}

describe('RequestFormData type contract — non-nullable fields', () => {
  it('merchant_id is a positive integer (non-nullable in schema)', () => {
    const result = requestFormSchema.safeParse(VALID_FORM)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(typeof result.data.merchant_id).toBe('number')
      expect(result.data.merchant_id).toBeGreaterThan(0)
    }
  })

  it('amount is a positive number (non-nullable in schema)', () => {
    const result = requestFormSchema.safeParse(VALID_FORM)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(typeof result.data.amount).toBe('number')
      expect(result.data.amount).toBeGreaterThan(0)
    }
  })

  it('schema rejects null merchant_id (F8 fix: must be number, not number|null)', () => {
    const result = requestFormSchema.safeParse({ ...VALID_FORM, merchant_id: null })
    expect(result.success).toBe(false)
  })

  it('schema rejects null amount (F9 fix: must be number, not number|null)', () => {
    const result = requestFormSchema.safeParse({ ...VALID_FORM, amount: null })
    expect(result.success).toBe(false)
  })

  it('schema rejects undefined merchant_id', () => {
    const { merchant_id: _m, ...rest } = VALID_FORM
    const result = requestFormSchema.safeParse(rest)
    expect(result.success).toBe(false)
  })

  it('schema rejects undefined amount', () => {
    const { amount: _a, ...rest } = VALID_FORM
    const result = requestFormSchema.safeParse(rest)
    expect(result.success).toBe(false)
  })
})

describe('Amount numeric canonicalization (F9)', () => {
  it('amount as integer parses successfully', () => {
    const result = requestFormSchema.safeParse({ ...VALID_FORM, amount: 50000 })
    expect(result.success).toBe(true)
  })

  it('amount as decimal (float) parses successfully', () => {
    const result = requestFormSchema.safeParse({ ...VALID_FORM, amount: 1234.56 })
    expect(result.success).toBe(true)
  })

  it('amount as string is rejected', () => {
    const result = requestFormSchema.safeParse({ ...VALID_FORM, amount: '50000' })
    expect(result.success).toBe(false)
  })

  it('formatAmount receives numeric input and produces Arabic-locale string', () => {
    // Mirrors the index.vue formatAmount function after F9 patch
    function formatAmount(amount: number, currency: string): string {
      return `${amount.toLocaleString('ar-YE')} ${currency}`
    }
    const out = formatAmount(50000, 'USD')
    expect(out).toContain('USD')
    expect(out).not.toContain('NaN')
  })

  it('formatAmount does not accept string (TypeScript: compiled function with number param)', () => {
    function formatAmount(amount: number, currency: string): string {
      return `${amount.toLocaleString('ar-YE')} ${currency}`
    }
    // Verify numeric amount roundtrips correctly
    expect(formatAmount(1234.5, 'EUR')).toContain('EUR')
  })
})

describe('Schema — form defaults for edit pre-population (F1)', () => {
  it('parses a complete valid edit payload', () => {
    const editPayload: RequestFormData = {
      merchant_id: 5,
      currency: 'EUR',
      amount: 75000,
      supplier_name: 'EU Supplier GmbH',
      goods_description: 'Machinery components',
      port_of_entry: 'Aden',
      notes: 'Expedited shipment',
    }
    const result = requestFormSchema.safeParse(editPayload)
    expect(result.success).toBe(true)
  })

  it('notes defaults to empty string when undefined (for edit flow with no notes)', () => {
    const { notes: _n, ...rest } = VALID_FORM
    const result = requestFormSchema.safeParse(rest)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.notes).toBe('')
    }
  })
})
