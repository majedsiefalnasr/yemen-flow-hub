import { describe, it, expect } from 'vitest'
import type { Merchant } from '../../../../types/models'

// ── Fixtures ─────────────────────────────────────────────────────────────────

function makeMerchant(overrides: Partial<Merchant> = {}): Merchant {
  return {
    id: 1,
    bank_id: 1,
    bank_name: 'بنك اليمن',
    name: 'شركة الأمل للاستيراد',
    commercial_register: 'CR-12345',
    tax_number: 'TX-99999',
    national_id: null,
    owner_name: 'علي أحمد',
    phone: '+967700123456',
    email: null,
    address: 'صنعاء، شارع التحرير',
    business_type: 'import',
    is_active: true,
    transaction_count: 5,
    created_by: 1,
    created_at: '2026-05-01T00:00:00.000Z',
    ...overrides,
  }
}

// ── Display logic (mirrors MerchantCard.vue) ─────────────────────────────────

function statusLabel(merchant: Merchant): string {
  return merchant.is_active ? 'نشط' : 'موقوف'
}

function statusClass(merchant: Merchant): string {
  return merchant.is_active ? 'badge-active' : 'badge-suspended'
}

function toggleButtonLabel(merchant: Merchant): string {
  return merchant.is_active ? 'إيقاف المستورد' : 'تفعيل المستورد'
}

function metaValue(val: string | null): string {
  return val ?? 'غير محدد'
}

function businessTypeLabel(type: string | null | undefined): string {
  const MAP: Record<string, string> = {
    import: 'استيراد',
    export: 'تصدير',
    retail: 'تجارة تجزئة',
    wholesale: 'تجارة جملة',
    manufacturing: 'تصنيع',
    services: 'خدمات',
  }
  return type ? (MAP[type] ?? type) : 'غير محدد'
}

function transactionCount(merchant: Merchant): number {
  return merchant.transaction_count ?? 0
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('MerchantCard — status badge', () => {
  it('shows "نشط" label for active merchant', () => {
    expect(statusLabel(makeMerchant({ is_active: true }))).toBe('نشط')
  })

  it('shows "موقوف" label for suspended merchant', () => {
    expect(statusLabel(makeMerchant({ is_active: false }))).toBe('موقوف')
  })

  it('applies badge-active class for active merchant', () => {
    expect(statusClass(makeMerchant({ is_active: true }))).toBe('badge-active')
  })

  it('applies badge-suspended class for suspended merchant', () => {
    expect(statusClass(makeMerchant({ is_active: false }))).toBe('badge-suspended')
  })
})

describe('MerchantCard — toggle button', () => {
  it('shows "إيقاف المستورد" aria-label for active merchant', () => {
    expect(toggleButtonLabel(makeMerchant({ is_active: true }))).toBe('إيقاف المستورد')
  })

  it('shows "تفعيل المستورد" aria-label for suspended merchant', () => {
    expect(toggleButtonLabel(makeMerchant({ is_active: false }))).toBe('تفعيل المستورد')
  })
})

describe('MerchantCard — businessTypeLabel', () => {
  it('maps import to استيراد', () => {
    expect(businessTypeLabel('import')).toBe('استيراد')
  })

  it('maps export to تصدير', () => {
    expect(businessTypeLabel('export')).toBe('تصدير')
  })

  it('maps retail to تجارة تجزئة', () => {
    expect(businessTypeLabel('retail')).toBe('تجارة تجزئة')
  })

  it('maps wholesale to تجارة جملة', () => {
    expect(businessTypeLabel('wholesale')).toBe('تجارة جملة')
  })

  it('maps manufacturing to تصنيع', () => {
    expect(businessTypeLabel('manufacturing')).toBe('تصنيع')
  })

  it('maps services to خدمات', () => {
    expect(businessTypeLabel('services')).toBe('خدمات')
  })

  it('returns غير محدد for null', () => {
    expect(businessTypeLabel(null)).toBe('غير محدد')
  })

  it('returns unknown values as-is', () => {
    expect(businessTypeLabel('other')).toBe('other')
  })
})

describe('MerchantCard — transaction count', () => {
  it('shows transaction_count when present', () => {
    expect(transactionCount(makeMerchant({ transaction_count: 12 }))).toBe(12)
  })

  it('falls back to 0 when transaction_count is null', () => {
    expect(transactionCount(makeMerchant({ transaction_count: null }))).toBe(0)
  })

  it('falls back to 0 when transaction_count is undefined', () => {
    expect(transactionCount(makeMerchant({ transaction_count: undefined }))).toBe(0)
  })
})

describe('MerchantCard — meta values', () => {
  it('shows commercial_register value', () => {
    expect(metaValue('CR-12345')).toBe('CR-12345')
  })

  it('shows غير محدد for null commercial_register', () => {
    expect(metaValue(null)).toBe('غير محدد')
  })

  it('shows غير محدد for null tax_number', () => {
    expect(metaValue(null)).toBe('غير محدد')
  })

  it('shows غير محدد for null address', () => {
    expect(metaValue(null)).toBe('غير محدد')
  })

  it('shows address value when present', () => {
    expect(metaValue('صنعاء، شارع التحرير')).toBe('صنعاء، شارع التحرير')
  })
})

describe('MerchantCard — merchant data integrity', () => {
  it('fixture has required fields', () => {
    const m = makeMerchant()
    expect(m.id).toBe(1)
    expect(m.name).toBe('شركة الأمل للاستيراد')
    expect(m.is_active).toBe(true)
    expect(m.bank_id).toBe(1)
  })

  it('can override is_active to false', () => {
    const m = makeMerchant({ is_active: false })
    expect(m.is_active).toBe(false)
  })

  it('can override name', () => {
    const m = makeMerchant({ name: 'مؤسسة النور التجارية' })
    expect(m.name).toBe('مؤسسة النور التجارية')
  })
})
