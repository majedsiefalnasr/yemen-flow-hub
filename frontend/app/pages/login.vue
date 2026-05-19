<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAuthStore } from '../stores/auth.store'
import { useRouter } from 'vue-router'

definePageMeta({
  layout: false,
  middleware: ['guest'],
})

const router = useRouter()
const auth = useAuthStore()
const config = useRuntimeConfig()
const isDemoMode = config.public.demoMode as boolean

// ── Login form ────────────────────────────────────────────────────────────────
const schema = toTypedSchema(
  z.object({
    email: z.string().min(1, 'البريد الإلكتروني مطلوب').email('صيغة البريد الإلكتروني غير صحيحة'),
    password: z.string().min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'),
  }),
)

const { handleSubmit, defineField, errors } = useForm({ validationSchema: schema })
const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')

const isLoading = ref(false)
const serverError = ref<string | null>(null)

// ── OTP state ─────────────────────────────────────────────────────────────────
const otpStep = ref(false)
const pendingEmail = ref('')
const pendingChallengeId = ref('')
const otpCells = ref<string[]>(['', '', '', '', '', ''])
const otpCellRefs = ref<HTMLInputElement[]>([])
const otpError = ref<string | null>(null)
const isOtpLoading = ref(false)

// ── Login submit ──────────────────────────────────────────────────────────────
const onSubmit = handleSubmit(async (values) => {
  isLoading.value = true
  serverError.value = null

  try {
    const result = await auth.login(values.email, values.password)

    if (result?.requiresMfa) {
      pendingEmail.value = result.email
      pendingChallengeId.value = result.challengeId
      otpStep.value = true
      otpCells.value = ['', '', '', '', '', '']
      await nextTick()
      otpCellRefs.value[0]?.focus()
      return
    }

    await router.push('/dashboard')
  }
  catch (err: unknown) {
    const status = (err as { statusCode?: number })?.statusCode
    if (status === 429) {
      serverError.value = 'لقد تجاوزت الحد المسموح به من محاولات تسجيل الدخول. يرجى الانتظار دقيقة ثم حاول مرة أخرى.'
    }
    else if (
      typeof err === 'object' && err !== null &&
      'data' in err && typeof (err as { data?: { message?: string } }).data === 'object'
    ) {
      const data = (err as { data?: { message?: string } }).data
      serverError.value = data?.message ?? 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
    }
    else {
      serverError.value = 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
    }
  }
  finally {
    isLoading.value = false
  }
})

// ── OTP cell keyboard handling ────────────────────────────────────────────────
function onOtpKeydown(index: number, event: KeyboardEvent) {
  const key = event.key

  // Preserve keyboard shortcuts like paste (Ctrl/Cmd+V).
  if (event.ctrlKey || event.metaKey || event.altKey) {
    return
  }

  if (key === 'Backspace') {
    if (otpCells.value[index]) {
      otpCells.value[index] = ''
    }
    else if (index > 0) {
      otpCells.value[index - 1] = ''
      otpCellRefs.value[index - 1]?.focus()
    }
    event.preventDefault()
    return
  }

  if (/^\d$/.test(key)) {
    otpCells.value[index] = key
    if (index < 5) {
      otpCellRefs.value[index + 1]?.focus()
    }
    event.preventDefault()
  }
  else if (!['Tab', 'ArrowLeft', 'ArrowRight'].includes(key)) {
    event.preventDefault()
  }
}

function onOtpPaste(event: ClipboardEvent) {
  const pasted = event.clipboardData?.getData('text') ?? ''
  const digits = pasted.replace(/\D/g, '').slice(0, 6)
  if (digits.length === 0) return

  event.preventDefault()
  for (let i = 0; i < 6; i++) {
    otpCells.value[i] = digits[i] ?? ''
  }
  const lastFilled = Math.min(digits.length, 5)
  otpCellRefs.value[lastFilled]?.focus()
}

// ── OTP submit ────────────────────────────────────────────────────────────────
async function onOtpSubmit() {
  const otp = otpCells.value.join('')
  if (otp.length < 6) {
    otpError.value = 'الرجاء إدخال الرمز المكوّن من 6 أرقام.'
    return
  }

  if (!pendingChallengeId.value) {
    otpError.value = 'انتهت جلسة التحقق. يرجى تسجيل الدخول مرة أخرى.'
    backToLogin()
    return
  }

  isOtpLoading.value = true
  otpError.value = null

  try {
    await auth.verifyOtp(pendingEmail.value, otp, pendingChallengeId.value)
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
    <!-- Form column -->
    <div class="login-form-col">
      <div class="login-card">
        <!-- Brand -->
        <div class="login-brand">
          <div class="brand-icon" aria-hidden="true">🏦</div>
          <h1 class="brand-title">منصة إدارة طلبات التمويل</h1>
          <p class="brand-subtitle">البنك المركزي اليمني</p>
        </div>

        <!-- ── Login step ─────────────────────────────────────────────────── -->
        <template v-if="!otpStep">
          <!-- Error alert -->
          <div v-if="serverError" class="error-alert" role="alert">
            {{ serverError }}
          </div>

          <form class="login-form" novalidate @submit.prevent="onSubmit">
            <div class="field-group">
              <label for="email" class="field-label">البريد الإلكتروني</label>
              <input
                id="email"
                v-model="email"
                v-bind="emailAttrs"
                type="email"
                class="field-input"
                :class="{ 'field-input--error': errors.email }"
                placeholder="user@example.com"
                autocomplete="email"
                dir="ltr"
              />
              <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
            </div>

            <div class="field-group">
              <label for="password" class="field-label">كلمة المرور</label>
              <input
                id="password"
                v-model="password"
                v-bind="passwordAttrs"
                type="password"
                class="field-input"
                :class="{ 'field-input--error': errors.password }"
                placeholder="••••••••"
                autocomplete="current-password"
              />
              <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
            </div>

            <button type="submit" class="submit-btn" :disabled="isLoading">
              <span v-if="isLoading" class="btn-spinner" aria-hidden="true" />
              <span>{{ isLoading ? 'جارٍ تسجيل الدخول...' : 'تسجيل الدخول' }}</span>
            </button>
          </form>

          <!-- Demo role switcher — hidden in production -->
          <div v-if="isDemoMode" class="demo-switcher">
            <!-- demo-only persona picker placeholder -->
          </div>

          <p class="mfa-footer-note">مصادقة متعددة العوامل (MFA) مفعّلة</p>
        </template>

        <!-- ── OTP step ───────────────────────────────────────────────────── -->
        <template v-else>
          <div class="otp-heading">
            <h2 class="otp-title">التحقق بخطوتين</h2>
            <p class="otp-desc">أدخل الرمز المكوّن من 6 أرقام الذي تم إرساله إليك.</p>
          </div>

          <!-- OTP error -->
          <div v-if="otpError" class="error-alert" role="alert">
            {{ otpError }}
          </div>

          <!-- 6 OTP cells -->
          <div class="otp-cells" @paste.prevent="onOtpPaste">
            <input
              v-for="(_, i) in otpCells"
              :key="i"
              :ref="(el) => { if (el) otpCellRefs[i] = el as HTMLInputElement }"
              v-model="otpCells[i]"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="otp-cell"
              :class="{ 'otp-cell--error': otpError }"
              autocomplete="one-time-code"
              @keydown="onOtpKeydown(i, $event)"
            />
          </div>

          <button class="submit-btn" :disabled="isOtpLoading" @click="onOtpSubmit">
            <span v-if="isOtpLoading" class="btn-spinner" aria-hidden="true" />
            <span>{{ isOtpLoading ? 'جارٍ التحقق...' : 'تأكيد ودخول' }}</span>
          </button>

          <button type="button" class="back-link" @click="backToLogin">
            رجوع
          </button>
        </template>
      </div>
    </div>

    <!-- Hero column (hidden on mobile) -->
    <div class="login-hero" aria-hidden="true">
      <div class="hero-content">
        <div class="hero-icon">🏦</div>
        <h2 class="hero-title">البنك المركزي اليمني</h2>
        <p class="hero-tagline">منصة إدارة طلبات التمويل</p>
        <p class="hero-sub">نظام متكامل لإدارة سير العمل والطلبات المالية</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Page layout ─────────────────────────────────────────────────────────── */
.login-page {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100vh;
  background-color: #ffffff;
}

/* ── Form column ─────────────────────────────────────────────────────────── */
.login-form-col {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
  background-color: #ffffff;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background-color: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 24px;
  padding: 40px 32px;
  box-shadow: 0 2px 8px rgba(29, 29, 31, 0.04);
}

/* ── Brand ───────────────────────────────────────────────────────────────── */
.login-brand {
  text-align: center;
  margin-bottom: 32px;
}

.brand-icon {
  font-size: 40px;
  margin-bottom: 12px;
}

.brand-title {
  font-size: 20px;
  font-weight: 500;
  color: #1c222b;
  margin: 0 0 4px;
}

.brand-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

/* ── Error alert ─────────────────────────────────────────────────────────── */
.error-alert {
  background-color: #fff5f5;
  border: 1px solid #c62828;
  border-radius: 8px;
  color: #c62828;
  font-size: 14px;
  padding: 12px 16px;
  margin-bottom: 20px;
}

/* ── Login form ──────────────────────────────────────────────────────────── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 14px;
  font-weight: 500;
  color: #1c222b;
}

.field-input {
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 15px;
  color: #1c222b;
  background-color: #ffffff;
  transition: border-color 120ms ease;
  width: 100%;
  box-sizing: border-box;
}

.field-input:focus {
  outline: none;
  border-width: 1.5px;
  border-color: #0066cc;
}

.field-input--error {
  border-color: #c62828;
}

.field-error {
  font-size: 13px;
  color: #c62828;
}

/* ── Submit button ───────────────────────────────────────────────────────── */
.submit-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 24px;
  background-color: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  width: 100%;
  margin-top: 8px;
  transition: background-color 120ms ease;
}

.submit-btn:hover:not(:disabled) {
  background-color: #0052a3;
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.btn-spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── MFA footer note ─────────────────────────────────────────────────────── */
.mfa-footer-note {
  font-size: 12px;
  color: #6c757d;
  text-align: center;
  margin: 20px 0 0;
}

/* ── OTP step ────────────────────────────────────────────────────────────── */
.otp-heading {
  text-align: center;
  margin-bottom: 24px;
}

.otp-title {
  font-size: 18px;
  font-weight: 600;
  color: #1c222b;
  margin: 0 0 8px;
}

.otp-desc {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

.otp-cells {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-bottom: 24px;
  direction: ltr;
}

.otp-cell {
  width: 48px;
  height: 56px;
  border: 1.5px solid #cccccc;
  border-radius: 12px;
  font-size: 24px;
  font-weight: 600;
  text-align: center;
  color: #1c222b;
  background-color: #ffffff;
  transition: border-color 120ms ease;
  outline: none;
  caret-color: transparent;
}

.otp-cell:focus {
  border-color: #0066cc;
  border-width: 2px;
}

.otp-cell--error {
  border-color: #c62828;
}

.back-link {
  display: block;
  width: 100%;
  margin-top: 12px;
  padding: 10px;
  background: none;
  border: none;
  color: #0066cc;
  font-size: 14px;
  text-align: center;
  cursor: pointer;
  text-decoration: underline;
}

.back-link:hover {
  color: #0052a3;
}

/* ── Hero column ─────────────────────────────────────────────────────────── */
.login-hero {
  background-color: #0066cc;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.hero-content {
  text-align: center;
  color: #ffffff;
  max-width: 360px;
}

.hero-icon {
  font-size: 64px;
  margin-bottom: 24px;
}

.hero-title {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 12px;
  color: #ffffff;
}

.hero-tagline {
  font-size: 18px;
  font-weight: 500;
  margin: 0 0 16px;
  color: rgba(255, 255, 255, 0.9);
}

.hero-sub {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.75);
  line-height: 1.6;
  margin: 0;
}

/* ── Mobile: hide hero, full-width form ──────────────────────────────────── */
@media (max-width: 600px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .login-hero {
    display: none;
  }

  .login-form-col {
    padding: 24px 16px;
  }
}

/* ── Tablet breakpoint ───────────────────────────────────────────────────── */
@media (min-width: 601px) and (max-width: 1023px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .login-hero {
    display: none;
  }
}
</style>
