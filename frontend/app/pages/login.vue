<script setup lang="ts">
import { nextTick, ref, computed } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'
import Icon from '../components/ui/Icon.vue'

definePageMeta({ layout: false, middleware: ['guest'] })

const router = useRouter()
const auth = useAuthStore()
const schema = toTypedSchema(z.object({
  email: z.string().min(1, 'البريد الإلكتروني مطلوب').email('صيغة البريد الإلكتروني غير صحيحة'),
  password: z.string().min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'),
}))
const { handleSubmit, defineField, errors } = useForm({ validationSchema: schema })
const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')
const isLoading = ref(false)
const serverError = ref<string | null>(null)
const otpStep = ref(false)
const pendingEmail = ref('')
const pendingChallengeId = ref('')
const otpRoleLabel = ref('')
const otpCells = ref<string[]>(['', '', '', '', '', ''])
const otpCellRefs = ref<HTMLInputElement[]>([])
const otpError = ref<string | null>(null)
const isOtpLoading = ref(false)
const otpDestination = computed(() => pendingEmail.value ? '••42' : '••42')

const onSubmit = handleSubmit(async (values) => {
  isLoading.value = true
  serverError.value = null
  try {
    const result = await auth.login(values.email, values.password)
    if (result?.requiresMfa) {
      pendingEmail.value = result.email
      pendingChallengeId.value = result.challengeId
      otpRoleLabel.value = result.roleLabel ?? 'مستخدم النظام'
      otpStep.value = true
      otpCells.value = ['', '', '', '', '', '']
      await nextTick()
      otpCellRefs.value[0]?.focus()
      return
    }
    await router.push('/dashboard')
  }
  catch (err: any) {
    serverError.value = err?.statusCode === 429
      ? 'لقد تجاوزت الحد المسموح به من محاولات تسجيل الدخول. يرجى الانتظار دقيقة ثم حاول مرة أخرى.'
      : err?.data?.message ?? 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
  }
  finally {
    isLoading.value = false
  }
})

function onOtpKeydown(index: number, event: KeyboardEvent) {
  if (event.ctrlKey || event.metaKey || event.altKey) return
  const key = event.key
  if (key === 'Backspace') {
    if (otpCells.value[index]) otpCells.value[index] = ''
    else if (index > 0) { otpCells.value[index - 1] = ''; otpCellRefs.value[index - 1]?.focus() }
    event.preventDefault()
    return
  }
  if (/^\d$/.test(key)) {
    otpCells.value[index] = key
    if (index < 5) otpCellRefs.value[index + 1]?.focus()
    event.preventDefault()
  }
  else if (!['Tab', 'ArrowLeft', 'ArrowRight'].includes(key)) {
    event.preventDefault()
  }
}

function onOtpPaste(event: ClipboardEvent) {
  const digits = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, 6)
  if (!digits.length) return
  event.preventDefault()
  for (let i = 0; i < 6; i++) otpCells.value[i] = digits[i] ?? ''
  otpCellRefs.value[Math.min(digits.length, 5)]?.focus()
}

async function onOtpSubmit() {
  const otp = otpCells.value.join('')
  if (otp.length < 6) return (otpError.value = 'الرجاء إدخال الرمز المكوّن من 6 أرقام.')
  if (!pendingChallengeId.value) { otpError.value = 'انتهت جلسة التحقق. يرجى تسجيل الدخول مرة أخرى.'; return backToLogin() }
  isOtpLoading.value = true
  otpError.value = null
  try {
    await auth.verifyOtp(pendingEmail.value, otp, pendingChallengeId.value)
    otpRoleLabel.value = auth.user ? (ROLE_LABELS[auth.user.role] ?? auth.user.role) : otpRoleLabel.value
    await router.push('/dashboard')
  }
  catch {
    otpError.value = 'الرمز المدخل غير صحيح. حاول مرة أخرى.'
  }
  finally {
    isOtpLoading.value = false
  }
}

function backToLogin() {
  otpStep.value = false
  otpCells.value = ['', '', '', '', '', '']
  otpError.value = null
  pendingEmail.value = ''
  pendingChallengeId.value = ''
}
</script>

<template>
  <div class="login-page" dir="rtl">
    <div class="login-hero" aria-hidden="true">
      <div class="hero-top">
        <div class="hero-brand">
          <div class="hero-monogram">ب.م</div>
          <div>
            <p class="hero-brand-ar">البنك المركزي اليمني</p>
            <p class="hero-brand-en">Central Bank of Yemen</p>
          </div>
        </div>
      </div>
      <div class="hero-main">
        <h1>منصة إدارة ومراجعة طلبات تمويل الواردات</h1>
        <p>نظام موحّد لإدارة التقديم، المراجعة البنكية، مراجعة الدعم، رفع SWIFT، التصويت التنفيذي، وإصدار التصريح الجمركي.</p>
      </div>
      <p class="hero-iso">ISO 27001 compliant | Secure institutional workflow</p>
    </div>

    <div class="login-form-col">
      <div class="login-form-wrap">
        <template v-if="!otpStep">
          <div class="login-heading">
            <h2>تسجيل الدخول</h2>
          </div>
          <div v-if="serverError" class="error-alert" role="alert" aria-live="assertive">{{ serverError }}</div>
          <form class="login-form" novalidate @submit.prevent="onSubmit">
            <div class="field-group">
              <label for="email" class="field-label">البريد الإلكتروني</label>
              <input id="email" v-model="email" v-bind="emailAttrs" type="email" class="field-input" :class="{ 'field-input--error': errors.email }" placeholder="user@example.com" autocomplete="email" dir="ltr">
              <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
            </div>
            <div class="field-group">
              <label for="password" class="field-label">كلمة المرور</label>
              <input id="password" v-model="password" v-bind="passwordAttrs" type="password" class="field-input" :class="{ 'field-input--error': errors.password }" placeholder="••••••••" autocomplete="current-password">
              <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
            </div>
            <button type="submit" class="submit-btn" :disabled="isLoading">{{ isLoading ? 'جارٍ تسجيل الدخول...' : 'متابعة' }}</button>
          </form>
          <p class="mfa-footer-note">مصادقة متعددة العوامل (MFA) مفعّلة</p>
        </template>

        <template v-else>
          <div class="otp-heading">
            <h2 class="otp-title">رمز التحقق (OTP)</h2>
            <p class="otp-desc">أدخل الرمز المرسل إلى هاتفك المنتهي بـ {{ otpDestination }}</p>
          </div>
          <div v-if="otpError" class="error-alert" role="alert" aria-live="assertive">{{ otpError }}</div>
          <div class="otp-cells" @paste.prevent="onOtpPaste">
            <input v-for="(_, i) in otpCells" :key="i" :ref="(el) => { if (el) otpCellRefs[i] = el as HTMLInputElement }" v-model="otpCells[i]" type="text" inputmode="numeric" maxlength="1" class="otp-cell" :class="{ 'otp-cell--error': otpError }" autocomplete="one-time-code" @keydown="onOtpKeydown(i, $event)">
          </div>
          <div class="otp-role-card">
            <Icon name="lock" />
            <span>سيتم تسجيل دخولك بصلاحيات: <strong>{{ otpRoleLabel || 'مستخدم النظام' }}</strong></span>
          </div>
          <button class="submit-btn" :disabled="isOtpLoading" @click="onOtpSubmit">{{ isOtpLoading ? 'جارٍ التحقق...' : 'تأكيد ودخول' }}</button>
          <button type="button" class="back-link" @click="backToLogin">رجوع</button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page { display: grid; grid-template-columns: 1fr 1fr; min-height: 100vh; background-color: #fff; }
.login-hero { background-color: #0066cc; color: #fff; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; }
.hero-brand { display: flex; align-items: center; gap: 12px; }
.hero-monogram { height: 56px; width: 56px; border-radius: 16px; background: rgb(255 255 255 / 10%); border: 1px solid rgb(255 255 255 / 20%); display: grid; place-items: center; font-size: 24px; font-weight: 700; }
.hero-brand-ar { margin: 0; font-weight: 600; color: #fff; }
.hero-brand-en { margin: 0; color: rgb(255 255 255 / 80%); font-size: 12px; }
.hero-main { max-width: 460px; }
.hero-main h1 { margin: 0 0 12px; color: #fff; font-size: 34px; font-weight: 800; line-height: 1.3; }
.hero-main p { margin: 0; color: rgb(255 255 255 / 85%); line-height: 1.8; font-size: 15px; }
.hero-iso { margin: 0; color: rgb(255 255 255 / 50%); font-size: 12px; }
.login-form-col { background: #fff; display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
.login-form-wrap { width: 100%; max-width: 420px; }
.login-heading h2 { margin: 0 0 20px; font-size: 30px; font-weight: 700; color: #1c222b; }
.error-alert { background-color: #fff5f5; border: 1px solid #c62828; border-radius: 8px; color: #c62828; font-size: 14px; padding: 12px 16px; margin-bottom: 20px; }
.login-form { display: flex; flex-direction: column; gap: 20px; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 14px; font-weight: 500; color: #1c222b; }
.field-input { min-height: 44px; padding: 0 14px; border: 1px solid #cccccc; border-radius: 12px; font-size: 15px; color: #1c222b; background-color: #fff; transition: border-color 120ms ease; width: 100%; box-sizing: border-box; }
.field-input:focus { outline: none; border-width: 1.5px; border-color: #0066cc; }
.field-input--error { border-color: #c62828; }
.field-error { font-size: 13px; color: #c62828; }
.submit-btn { display: flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 24px; background-color: #0066cc; color: #fff; border: none; border-radius: 16px; font-size: 15px; font-weight: 500; cursor: pointer; width: 100%; margin-top: 8px; transition: background-color 120ms ease; }
.submit-btn:hover:not(:disabled) { background-color: #0052a3; }
.submit-btn:disabled { opacity: 0.65; cursor: not-allowed; }
.mfa-footer-note { font-size: 12px; color: #6c757d; text-align: center; margin: 20px 0 0; }
.otp-heading { text-align: center; margin-bottom: 24px; }
.otp-title { font-size: 24px; font-weight: 700; color: #1c222b; margin: 0 0 8px; }
.otp-desc { font-size: 14px; color: #6c757d; margin: 0; }
.otp-cells { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; direction: ltr; }
.otp-cell { width: 48px; height: 56px; border: 2px solid #cccccc; border-radius: 12px; font-size: 24px; font-weight: 600; text-align: center; color: #1c222b; background-color: #fff; transition: border-color 120ms ease; outline: none; caret-color: transparent; }
.otp-cell:focus { border-color: #0066cc; }
.otp-cell--error { border-color: #c62828; }
.otp-role-card { display: flex; align-items: center; gap: 8px; border: 1px dashed #cccccc; border-radius: 12px; padding: 12px; background: color-mix(in srgb, var(--color-muted, #f5f5f7) 40%, #fff); font-size: 13px; margin-bottom: 12px; }
.back-link { display: block; width: 100%; margin-top: 12px; padding: 10px; background: none; border: none; color: #0066cc; font-size: 14px; text-align: center; cursor: pointer; text-decoration: underline; }
@media (max-width: 1023px) { .login-page { grid-template-columns: 1fr; } .login-hero { display: none; } .login-form-col { padding: 24px 16px; } }
</style>
