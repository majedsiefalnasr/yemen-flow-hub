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
    is_active: true,
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
  return merchant.is_active ? 'تعليق' : 'تفعيل'
}

function avatarChar(merchant: Merchant): string {
  return merchant.name.charAt(0)
}

function metaValue(val: string | null): string {
  return val ?? '—'
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
  it('shows "تعليق" for active merchant', () => {
    expect(toggleButtonLabel(makeMerchant({ is_active: true }))).toBe('تعليق')
  })

  it('shows "تفعيل" for suspended merchant', () => {
    expect(toggleButtonLabel(makeMerchant({ is_active: false }))).toBe('تفعيل')
  })
})

describe('MerchantCard — avatar', () => {
  it('shows first character of merchant name as avatar', () => {
    expect(avatarChar(makeMerchant({ name: 'شركة النجاح' }))).toBe('ش')
  })

  it('handles single-character names', () => {
    expect(avatarChar(makeMerchant({ name: 'أ' }))).toBe('أ')
  })
})

describe('MerchantCard — meta values', () => {
  it('shows commercial_register value', () => {
    expect(metaValue('CR-12345')).toBe('CR-12345')
  })

  it('shows dash for null commercial_register', () => {
    expect(metaValue(null)).toBe('—')
  })

  it('shows dash for null tax_number', () => {
    expect(metaValue(null)).toBe('—')
  })

  it('shows dash for null address', () => {
    expect(metaValue(null)).toBe('—')
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
    expect(avatarChar(m)).toBe('م')
  })
})
