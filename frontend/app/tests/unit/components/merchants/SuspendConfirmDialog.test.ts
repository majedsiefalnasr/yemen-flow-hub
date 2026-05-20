import { describe, it, expect } from 'vitest'
import type { Merchant } from '../../../../types/models'

// ── Fixtures ─────────────────────────────────────────────────────────────────

function makeMerchant(overrides: Partial<Merchant> = {}): Merchant {
  return {
    id: 1,
    bank_id: 1,
    bank_name: 'بنك اليمن',
    name: 'شركة الأمل',
    commercial_register: 'CR-12345',
    tax_number: 'TX-99999',
    national_id: null,
    owner_name: null,
    phone: null,
    email: null,
    address: null,
    is_active: true,
    created_by: 1,
    created_at: '2026-05-01T00:00:00.000Z',
    ...overrides,
  }
}

// ── Dialog logic (mirrors SuspendConfirmDialog.vue) ──────────────────────────

function dialogTitle(merchant: Merchant): string {
  return merchant.is_active ? 'تأكيد تعليق التاجر' : 'تأكيد تفعيل التاجر'
}

function confirmButtonLabel(merchant: Merchant, submitting = false): string {
  if (submitting) {
    return 'جارٍ التحديث…'
  }
  return merchant.is_active ? 'تعليق' : 'تفعيل'
}

function confirmButtonClass(merchant: Merchant): string {
  return merchant.is_active ? 'btn-suspend' : 'btn-activate'
}

function iconClass(merchant: Merchant): string {
  return merchant.is_active ? 'icon-suspend' : 'icon-activate'
}

function dialogMessage(merchant: Merchant): string {
  if (merchant.is_active) {
    return `هل أنت متأكد من تعليق التاجر ${merchant.name}؟ لن يتمكن المستخدمون من اختياره في الطلبات الجديدة.`
  }
  return `هل أنت متأكد من تفعيل التاجر ${merchant.name}؟`
}

function canDismissDialog(submitting: boolean): boolean {
  return !submitting
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('SuspendConfirmDialog — title', () => {
  it('shows suspend title for active merchant', () => {
    expect(dialogTitle(makeMerchant({ is_active: true }))).toBe('تأكيد تعليق التاجر')
  })

  it('shows activate title for suspended merchant', () => {
    expect(dialogTitle(makeMerchant({ is_active: false }))).toBe('تأكيد تفعيل التاجر')
  })
})

describe('SuspendConfirmDialog — confirm button', () => {
  it('shows "تعليق" label for active merchant', () => {
    expect(confirmButtonLabel(makeMerchant({ is_active: true }))).toBe('تعليق')
  })

  it('shows "تفعيل" label for suspended merchant', () => {
    expect(confirmButtonLabel(makeMerchant({ is_active: false }))).toBe('تفعيل')
  })

  it('uses btn-suspend class for active merchant', () => {
    expect(confirmButtonClass(makeMerchant({ is_active: true }))).toBe('btn-suspend')
  })

  it('uses btn-activate class for suspended merchant', () => {
    expect(confirmButtonClass(makeMerchant({ is_active: false }))).toBe('btn-activate')
  })

  it('shows loading label while submitting', () => {
    expect(confirmButtonLabel(makeMerchant({ is_active: true }), true)).toBe('جارٍ التحديث…')
  })
})

describe('SuspendConfirmDialog — icon', () => {
  it('uses suspend icon for active merchant', () => {
    expect(iconClass(makeMerchant({ is_active: true }))).toBe('icon-suspend')
  })

  it('uses activate icon for suspended merchant', () => {
    expect(iconClass(makeMerchant({ is_active: false }))).toBe('icon-activate')
  })
})

describe('SuspendConfirmDialog — message', () => {
  it('suspend message contains merchant name', () => {
    const msg = dialogMessage(makeMerchant({ name: 'شركة النجاح', is_active: true }))
    expect(msg).toContain('شركة النجاح')
    expect(msg).toContain('تعليق')
    expect(msg).toContain('لن يتمكن المستخدمون')
  })

  it('activate message contains merchant name', () => {
    const msg = dialogMessage(makeMerchant({ name: 'مؤسسة الفجر', is_active: false }))
    expect(msg).toContain('مؤسسة الفجر')
    expect(msg).toContain('تفعيل')
  })

  it('suspend message differs from activate message', () => {
    const m = makeMerchant({ name: 'شركة' })
    const suspendMsg = dialogMessage({ ...m, is_active: true })
    const activateMsg = dialogMessage({ ...m, is_active: false })
    expect(suspendMsg).not.toBe(activateMsg)
  })
})

describe('SuspendConfirmDialog — interaction guard', () => {
  it('allows dismiss when not submitting', () => {
    expect(canDismissDialog(false)).toBe(true)
  })

  it('blocks dismiss while submitting', () => {
    expect(canDismissDialog(true)).toBe(false)
  })
})
