import { describe, it, expect } from 'vitest'
import { requestFormSchema } from '../../../schemas/requestForm.schema'

const VALID = {
  merchant_id: 5,
  currency: 'USD' as const,
  amount: 50000,
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics and hardware',
  port_of_entry: 'Aden Port',
  notes: 'Optional notes',
}

describe('requestFormSchema — valid inputs', () => {
  it('accepts a fully valid object', () => {
    const result = requestFormSchema.safeParse(VALID)
    expect(result.success).toBe(true)
  })

  it('accepts all valid currency values', () => {
    const currencies = ['USD', 'EUR', 'SAR', 'AED', 'CNY'] as const
    for (const currency of currencies) {
      const result = requestFormSchema.safeParse({ ...VALID, currency })
      expect(result.success).toBe(true)
    }
  })

  it('accepts notes as undefined (optional)', () => {
    const { notes: _notes, ...withoutNotes } = VALID
    const result = requestFormSchema.safeParse(withoutNotes)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.notes).toBe('')
    }
  })

  it('defaults notes to empty string when omitted', () => {
    const result = requestFormSchema.safeParse({ ...VALID, notes: undefined })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.notes).toBe('')
    }
  })
})

describe('requestFormSchema — merchant_id', () => {
  it('rejects missing merchant_id', () => {
    const { merchant_id: _m, ...rest } = VALID
    const result = requestFormSchema.safeParse(rest)
    expect(result.success).toBe(false)
  })

  it('rejects non-positive merchant_id', () => {
    const result = requestFormSchema.safeParse({ ...VALID, merchant_id: 0 })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]!.path).toContain('merchant_id')
    }
  })

  it('rejects negative merchant_id', () => {
    const result = requestFormSchema.safeParse({ ...VALID, merchant_id: -1 })
    expect(result.success).toBe(false)
  })

  it('rejects string merchant_id', () => {
    const result = requestFormSchema.safeParse({ ...VALID, merchant_id: 'five' })
    expect(result.success).toBe(false)
  })
})

describe('requestFormSchema — currency', () => {
  it('rejects invalid currency', () => {
    const result = requestFormSchema.safeParse({ ...VALID, currency: 'GBP' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]!.path).toContain('currency')
    }
  })

  it('rejects missing currency', () => {
    const { currency: _c, ...rest } = VALID
    const result = requestFormSchema.safeParse(rest)
    expect(result.success).toBe(false)
  })
})

describe('requestFormSchema — amount', () => {
  it('rejects zero amount', () => {
    const result = requestFormSchema.safeParse({ ...VALID, amount: 0 })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]!.path).toContain('amount')
    }
  })

  it('rejects negative amount', () => {
    const result = requestFormSchema.safeParse({ ...VALID, amount: -100 })
    expect(result.success).toBe(false)
  })

  it('accepts fractional amounts', () => {
    const result = requestFormSchema.safeParse({ ...VALID, amount: 0.01 })
    expect(result.success).toBe(true)
  })

  it('rejects string amount', () => {
    const result = requestFormSchema.safeParse({ ...VALID, amount: '50000' })
    expect(result.success).toBe(false)
  })
})

describe('requestFormSchema — supplier_name', () => {
  it('rejects empty supplier_name', () => {
    const result = requestFormSchema.safeParse({ ...VALID, supplier_name: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]!.path).toContain('supplier_name')
    }
  })

  it('rejects supplier_name over 255 chars', () => {
    const result = requestFormSchema.safeParse({ ...VALID, supplier_name: 'A'.repeat(256) })
    expect(result.success).toBe(false)
  })

  it('accepts supplier_name at exactly 255 chars', () => {
    const result = requestFormSchema.safeParse({ ...VALID, supplier_name: 'A'.repeat(255) })
    expect(result.success).toBe(true)
  })
})

describe('requestFormSchema — goods_description', () => {
  it('rejects empty goods_description', () => {
    const result = requestFormSchema.safeParse({ ...VALID, goods_description: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]!.path).toContain('goods_description')
    }
  })

  it('accepts multi-line goods description', () => {
    const result = requestFormSchema.safeParse({ ...VALID, goods_description: 'Line 1\nLine 2\nLine 3' })
    expect(result.success).toBe(true)
  })
})

describe('requestFormSchema — port_of_entry', () => {
  it('rejects empty port_of_entry', () => {
    const result = requestFormSchema.safeParse({ ...VALID, port_of_entry: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]!.path).toContain('port_of_entry')
    }
  })

  it('rejects port_of_entry over 255 chars', () => {
    const result = requestFormSchema.safeParse({ ...VALID, port_of_entry: 'A'.repeat(256) })
    expect(result.success).toBe(false)
  })
})
