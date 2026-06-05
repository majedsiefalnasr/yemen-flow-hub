// @vitest-environment jsdom

import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'

const requestPasswordRecovery = vi.hoisted(() => vi.fn())
const verifyPasswordRecoveryCode = vi.hoisted(() => vi.fn())
const resetPasswordWithOtp = vi.hoisted(() => vi.fn())
const toastSuccess = vi.hoisted(() => vi.fn())
const navigateTo = vi.hoisted(() => vi.fn())

vi.mock('@/stores/auth.store', () => ({
  useAuthStore: () => ({
    requestPasswordRecovery,
    verifyPasswordRecoveryCode,
    resetPasswordWithOtp,
  }),
}))

vi.mock('vue-sonner', () => ({
  toast: { success: toastSuccess },
}))

const ResetPasswordPage = (await import('../../../pages/reset-password.vue')).default

describe('reset-password page', () => {
  beforeEach(() => {
    vi.useRealTimers()
    requestPasswordRecovery.mockReset()
    verifyPasswordRecoveryCode.mockReset()
    resetPasswordWithOtp.mockReset()
    toastSuccess.mockReset()
    navigateTo.mockReset()
    vi.stubGlobal('navigateTo', navigateTo)
    requestPasswordRecovery.mockResolvedValue(
      'If this email exists, a recovery code has been sent.',
    )
    verifyPasswordRecoveryCode.mockResolvedValue(undefined)
    resetPasswordWithOtp.mockResolvedValue(undefined)
  })

  it('walks email, OTP, and new password steps without exposing account existence', async () => {
    const wrapper = mount(ResetPasswordPage, {
      global: {
        stubs: {
          PasswordRequirements: true,
        },
      },
    })

    const vm = wrapper.vm as unknown as {
      email: string
      otp: string
      password: string
      passwordConfirmation: string
      submitEmail: () => Promise<void>
      submitOtp: () => Promise<void>
      submitPassword: () => Promise<void>
      goBackFromRecoveryStep: () => void
      remainingSeconds: number
    }

    vm.email = 'unknown@bank.ye'
    await vm.submitEmail()
    await flushPromises()

    expect(requestPasswordRecovery).toHaveBeenCalledWith('unknown@bank.ye')
    expect(wrapper.text()).toContain('إذا كان البريد موجوداً')
    expect(wrapper.text()).toContain('البريد الإلكتروني')
    expect(wrapper.text()).toContain('unknown@bank.ye')
    expect(wrapper.text()).toContain('رجوع')
    expect(wrapper.text()).toContain('10:00')
    expect(wrapper.text()).toContain('ينتهي رمز الاستعادة خلال')
    expect(wrapper.text()).not.toContain('تعديل البريد')

    const text = wrapper.text()
    expect(text.indexOf('رجوع')).toBeLessThan(text.indexOf('استعادة كلمة المرور'))

    vm.goBackFromRecoveryStep()
    await flushPromises()

    expect(wrapper.text()).not.toContain('إذا كان البريد موجوداً')

    await vm.submitEmail()
    await flushPromises()

    vm.otp = '123456'
    await vm.submitOtp()
    await flushPromises()

    expect(verifyPasswordRecoveryCode).toHaveBeenCalledWith('unknown@bank.ye', '123456')

    vm.password = 'NewPassword123'
    vm.passwordConfirmation = 'NewPassword123'
    await vm.submitPassword()
    await flushPromises()

    expect(resetPasswordWithOtp).toHaveBeenCalledWith(
      'unknown@bank.ye',
      '123456',
      'NewPassword123',
      'NewPassword123',
    )
    expect(toastSuccess).toHaveBeenCalledWith(
      'تم تحديث كلمة المرور. استخدم كلمة المرور الجديدة لتسجيل الدخول.',
    )
    expect(navigateTo).toHaveBeenCalledWith('/login')
  })

  it('keeps a back-to-login action visible from the recovery page', async () => {
    const wrapper = mount(ResetPasswordPage, {
      global: {
        stubs: {
          PasswordRequirements: true,
        },
      },
    })

    const loginButton = wrapper.findAll('button').find((button) => {
      return button.text().includes('تسجيل الدخول')
    })

    expect(loginButton).toBeTruthy()
    await loginButton?.trigger('click')

    expect(navigateTo).toHaveBeenCalledWith('/login')
  })

  it('blocks OTP verification after the visible countdown expires', async () => {
    const wrapper = mount(ResetPasswordPage, {
      global: {
        stubs: {
          PasswordRequirements: true,
        },
      },
    })

    const vm = wrapper.vm as unknown as {
      email: string
      otp: string
      remainingSeconds: number
      submitEmail: () => Promise<void>
      submitOtp: () => Promise<void>
    }

    vm.email = 'expired@bank.ye'
    await vm.submitEmail()
    await flushPromises()

    vm.remainingSeconds = 0
    await flushPromises()

    expect(wrapper.text()).toContain('انتهت صلاحية رمز الاستعادة')
    expect(vm.remainingSeconds).toBe(0)

    vm.otp = '123456'
    await vm.submitOtp()
    await flushPromises()

    expect(verifyPasswordRecoveryCode).not.toHaveBeenCalled()
  })
})
