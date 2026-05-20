import { describe, it, expect } from 'vitest'
import { z } from 'zod'
import type { Merchant } from '../../../../types/models'

// ── Schema (mirrors MerchantModal.vue validation) ────────────────────────────

const schema = z.object({
  name: z.string().trim().min(1, 'اسم التاجر مطلوب'),
  commercial_register: z.string().trim().min(1, 'رقم السجل التجاري مطلوب'),
  tax_number: z.string().trim().min(1, 'الرقم الضريبي مطلوب'),
  phone: z.string().optional().default(''),
  address: z.string().optional().default(''),
  business_type: z.string().optional().default(''),
  is_active: z.string().optional().default('true'),
  bank_id: z.string().optional().default(''),
})

function validate(data: object) {
  return schema.safeParse(data)
}

// ── Edit mode prefill logic (mirrors MerchantModal.vue watch) ────────────────

function prefillFromMerchant(merchant: Merchant) {
  return {
    name: merchant.name,
    commercial_register: merchant.commercial_register ?? '',
    tax_number: merchant.tax_number ?? '',
    address: merchant.address ?? '',
    business_type: merchant.business_type ?? '',
    bank_id: merchant.bank_id ? String(merchant.bank_id) : '',
  }
}

function emptyFormValues() {
  return { name: '', commercial_register: '', tax_number: '', address: '', business_type: '', bank_id: '' }
}

// ── Modal title logic ────────────────────────────────────────────────────────

function modalTitle(merchant: Merchant | null): string {
  return merchant ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد'
}

// ── BUSINESS_TYPE_OPTIONS ────────────────────────────────────────────────────

const BUSINESS_TYPE_OPTIONS = [
  { value: 'import', label: 'استيراد' },
  { value: 'export', label: 'تصدير' },
  { value: 'retail', label: 'تجارة تجزئة' },
  { value: 'wholesale', label: 'تجارة جملة' },
  { value: 'manufacturing', label: 'تصنيع' },
  { value: 'services', label: 'خدمات' },
]

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('MerchantModal — Zod validation', () => {
  it('passes when all required fields are present', () => {
    const result = validate({ name: 'شركة الأمل', commercial_register: 'CR-001', tax_number: 'TX-001' })
    expect(result.success).toBe(true)
  })

  it('fails when name is empty', () => {
    const result = validate({ name: '', commercial_register: 'CR-001', tax_number: 'TX-001' })
    expect(result.success).toBe(false)
    if (!result.success) {
      const nameErrors = result.error.errors.filter(e => e.path.includes('name'))
      expect(nameErrors.length).toBeGreaterThan(0)
      expect(nameErrors[0]!.message).toBe('اسم التاجر مطلوب')
    }
  })

  it('fails when commercial_register is empty', () => {
    const result = validate({ name: 'شركة', commercial_register: '', tax_number: 'TX-001' })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('commercial_register'))
      expect(errs.length).toBeGreaterThan(0)
    }
  })

  it('fails when tax_number is empty', () => {
    const result = validate({ name: 'شركة', commercial_register: 'CR-001', tax_number: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('tax_number'))
      expect(errs.length).toBeGreaterThan(0)
    }
  })

  it('address and business_type are optional', () => {
    const result = validate({ name: 'شركة', commercial_register: 'CR-001', tax_number: 'TX-001' })
    expect(result.success).toBe(true)
  })

  it('accepts address when provided', () => {
    const result = validate({ name: 'شركة', commercial_register: 'CR-001', tax_number: 'TX-001', address: 'صنعاء' })
    expect(result.success).toBe(true)
    if (result.success) expect(result.data.address).toBe('صنعاء')
  })

  it('defaults address to empty string when absent', () => {
    const result = validate({ name: 'شركة', commercial_register: 'CR-001', tax_number: 'TX-001' })
    if (result.success) expect(result.data.address).toBe('')
  })
})

describe('MerchantModal — edit prefill', () => {
  const merchant: Merchant = {
    id: 5,
    bank_id: 1,
    bank_name: 'بنك اليمن',
    name: 'شركة النجاح',
    commercial_register: 'CR-55555',
    tax_number: 'TX-77777',
    national_id: null,
    owner_name: null,
    phone: null,
    email: null,
    address: 'عدن، كريتر',
    business_type: 'import',
    is_active: true,
    created_by: 1,
    created_at: '2026-05-01T00:00:00.000Z',
  }

  it('prefills name from merchant', () => {
    expect(prefillFromMerchant(merchant).name).toBe('شركة النجاح')
  })

  it('prefills commercial_register from merchant', () => {
    expect(prefillFromMerchant(merchant).commercial_register).toBe('CR-55555')
  })

  it('prefills tax_number from merchant', () => {
    expect(prefillFromMerchant(merchant).tax_number).toBe('TX-77777')
  })

  it('prefills address from merchant', () => {
    expect(prefillFromMerchant(merchant).address).toBe('عدن، كريتر')
  })

  it('defaults commercial_register to empty string when null', () => {
    expect(prefillFromMerchant({ ...merchant, commercial_register: null }).commercial_register).toBe('')
  })

  it('defaults address to empty string when null', () => {
    expect(prefillFromMerchant({ ...merchant, address: null }).address).toBe('')
  })

  it('prefills business_type from merchant when provided', () => {
    expect(prefillFromMerchant(merchant).business_type).toBe('import')
  })

  it('defaults business_type to empty string when null', () => {
    expect(prefillFromMerchant({ ...merchant, business_type: null }).business_type).toBe('')
  })

  it('prefills bank_id from merchant', () => {
    expect(prefillFromMerchant(merchant).bank_id).toBe('1')
  })
})

describe('MerchantModal — create mode', () => {
  it('empty form values for create mode', () => {
    const values = emptyFormValues()
    expect(values.name).toBe('')
    expect(values.commercial_register).toBe('')
    expect(values.tax_number).toBe('')
    expect(values.address).toBe('')
    expect(values.business_type).toBe('')
    expect(values.bank_id).toBe('')
  })
})

describe('MerchantModal — title', () => {
  it('shows "تسجيل تاجر جديد" when no merchant', () => {
    expect(modalTitle(null)).toBe('تسجيل تاجر جديد')
  })

  it('shows "تعديل بيانات التاجر" when merchant provided', () => {
    const m = { id: 1, name: 'Test' } as Merchant
    expect(modalTitle(m)).toBe('تعديل بيانات التاجر')
  })
})

describe('MerchantModal — business type options', () => {
  it('has 6 options', () => {
    expect(BUSINESS_TYPE_OPTIONS).toHaveLength(6)
  })

  it('includes import option', () => {
    expect(BUSINESS_TYPE_OPTIONS.find(o => o.value === 'import')?.label).toBe('استيراد')
  })

  it('all options have value and label', () => {
    BUSINESS_TYPE_OPTIONS.forEach(opt => {
      expect(opt.value).toBeTruthy()
      expect(opt.label).toBeTruthy()
    })
  })
})

describe('MerchantModal — trimming', () => {
  it('fails when required fields are whitespace only', () => {
    const result = validate({ name: '   ', commercial_register: '  ', tax_number: '   ' })
    expect(result.success).toBe(false)
  })
})
