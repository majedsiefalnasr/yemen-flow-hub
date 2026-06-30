// @vitest-environment jsdom

import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AccountRecoveryDialog from '@/components/security/AccountRecoveryDialog.vue'
import { UserRole } from '@/types/enums'

const { resetUserPassword, resetUserMfa, resetUserPin, toastSuccess, toastError } = vi.hoisted(
  () => ({
    resetUserPassword: vi.fn(),
    resetUserMfa: vi.fn(),
    resetUserPin: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
  }),
)

vi.mock('@/composables/useUsers', () => ({
  useUsers: () => ({ resetUserPassword, resetUserMfa, resetUserPin }),
}))

vi.mock('vue-sonner', () => ({
  toast: {
    success: toastSuccess,
    error: toastError,
  },
}))

const target = {
  id: 11,
  name: 'مستخدم تجريبي',
  email: 'user@example.gov.ye',
  role: UserRole.DATA_ENTRY,
  role_label: 'إدخال البيانات',
  bank_id: 1,
  bank_name_ar: 'بنك تجريبي',
  bank_name_en: 'Test Bank',
  is_active: true,
}

describe('AccountRecoveryDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetUserPassword.mockResolvedValue(target)
    resetUserMfa.mockResolvedValue(target)
    resetUserPin.mockResolvedValue(target)
  })

  it('keeps password, authenticator, and PIN as separate actions', async () => {
    const wrapper = mount(AccountRecoveryDialog, {
      props: { target },
      global: {
        stubs: {
          Teleport: true,
          Dialog: { template: '<div><slot /></div>' },
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
          DialogFooter: { template: '<div><slot /></div>' },
        },
      },
    })

    expect(wrapper.text()).toContain('إعادة تعيين كلمة المرور لا تعيد ضبط المصادقة أو PIN')
    expect(wrapper.text()).toContain('كلمة المرور')
    expect(wrapper.text()).toContain('تطبيق المصادقة')
    expect(wrapper.text()).toContain('رمز PIN')

    const buttons = wrapper.findAll('button')
    await buttons.find((button) => button.text() === 'إعادة ضبط')?.trigger('click')
    await flushPromises()

    expect(resetUserPassword).not.toHaveBeenCalled()
    expect(resetUserMfa).not.toHaveBeenCalled()
    expect(resetUserPin).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('تأكيد إعادة الضبط')
    expect(wrapper.findAll('[data-testid="account-recovery-dialog"]')).toHaveLength(1)

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('رجوع'))
      ?.trigger('click')
    expect(wrapper.text()).toContain('استعادة الوصول للحساب')
    expect(wrapper.text()).not.toContain('تأكيد إعادة الضبط')
  })

  it('shows precise Sonner feedback after a successful reset', async () => {
    const wrapper = mount(AccountRecoveryDialog, {
      props: { target },
      global: {
        stubs: {
          Teleport: true,
          Dialog: { template: '<div><slot /></div>' },
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
          DialogFooter: { template: '<div><slot /></div>' },
        },
      },
    })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'إعادة ضبط')
      ?.trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'تأكيد إعادة الضبط')
      ?.trigger('click')
    await flushPromises()

    expect(resetUserMfa).toHaveBeenCalledWith(target.id)
    expect(toastSuccess).toHaveBeenCalledWith('تمت إعادة ضبط تطبيق المصادقة بنجاح.')
    expect(toastError).not.toHaveBeenCalled()
  })

  it('shows Sonner error feedback when a reset fails', async () => {
    resetUserPin.mockRejectedValueOnce(new Error('Forbidden'))
    const wrapper = mount(AccountRecoveryDialog, {
      props: { target },
      global: {
        stubs: {
          Teleport: true,
          Dialog: { template: '<div><slot /></div>' },
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
          DialogFooter: { template: '<div><slot /></div>' },
        },
      },
    })

    const resetButtons = wrapper.findAll('button').filter((button) => button.text() === 'إعادة ضبط')
    await resetButtons[1]?.trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'تأكيد إعادة الضبط')
      ?.trigger('click')
    await flushPromises()

    expect(resetUserPin).toHaveBeenCalledWith(target.id)
    expect(toastError).toHaveBeenCalledWith(
      'تعذّر تنفيذ إعادة الضبط. تحقق من الصلاحية ثم أعد المحاولة.',
    )
    expect(wrapper.text()).toContain('تعذّر تنفيذ إعادة الضبط')
  })
})
