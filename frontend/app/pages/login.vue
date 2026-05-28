<script setup lang="ts">
import { nextTick, ref, computed, watch, onBeforeUnmount } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useRouter, useRoute } from 'vue-router'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'
import Icon from '../components/shared/Icon.vue'

definePageMeta({ layout: false, middleware: ['guest'] })

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const nextPath = computed(() => {
  const candidate = route.query.next
  if (typeof candidate !== 'string') return '/dashboard'
  if (!candidate.startsWith('/')) return '/dashboard'
  return candidate
})

const showInactivityBanner = computed(() => route.query.reason === 'inactivity')
const inactivityBannerDismissed = ref(false)
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
  inactivityBannerDismissed.value = true
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
    await router.push(nextPath.value)
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

const OTP_TTL = 300
const otpSecondsLeft = ref(OTP_TTL)
let otpTimerHandle: ReturnType<typeof setInterval> | null = null

function startOtpTimer() {
  otpSecondsLeft.value = OTP_TTL
  clearOtpTimer()
  otpTimerHandle = setInterval(() => {
    if (otpSecondsLeft.value > 0) otpSecondsLeft.value--
    else clearOtpTimer()
  }, 1000)
}

function clearOtpTimer() {
  if (otpTimerHandle !== null) { clearInterval(otpTimerHandle); otpTimerHandle = null }
}

const otpTimerDisplay = computed(() => {
  const m = Math.floor(otpSecondsLeft.value / 60).toString().padStart(2, '0')
  const s = (otpSecondsLeft.value % 60).toString().padStart(2, '0')
  return `${m}:${s}`
})

watch(otpStep, (val) => { if (val) startOtpTimer(); else clearOtpTimer() })
onBeforeUnmount(clearOtpTimer)
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
          <Alert v-if="showInactivityBanner && !inactivityBannerDismissed" role="status" aria-live="polite" class="mb-5">
            <AlertDescription>
              تم تسجيل خروجك بسبب عدم النشاط — يرجى تسجيل الدخول مرة أخرى
            </AlertDescription>
          </Alert>
          <Alert v-if="serverError" variant="destructive" role="alert" aria-live="assertive" class="mb-5">
            <AlertDescription>
              {{ serverError }}
            </AlertDescription>
          </Alert>
          <form class="login-form" novalidate @submit.prevent="onSubmit">
            <div class="field-group">
              <Label for="email">البريد الإلكتروني</Label>
              <Input
                id="email"
                v-model="email"
                v-bind="emailAttrs"
                type="email"
                placeholder="user@example.com"
                autocomplete="email"
                dir="ltr"
                :aria-invalid="errors.email ? 'true' : undefined"
              />
              <p v-if="errors.email" class="mt-1 text-sm text-destructive">
                {{ errors.email }}
              </p>
            </div>
            <div class="field-group">
              <Label for="password">كلمة المرور</Label>
              <Input
                id="password"
                v-model="password"
                v-bind="passwordAttrs"
                type="password"
                placeholder="••••••••"
                autocomplete="current-password"
                :aria-invalid="errors.password ? 'true' : undefined"
              />
              <p v-if="errors.password" class="mt-1 text-sm text-destructive">
                {{ errors.password }}
              </p>
            </div>
            <div class="flex justify-start">
              <NuxtLink
                to="/reset-password"
                class="text-xs text-primary hover:underline"
              >
                نسيت كلمة المرور؟
              </NuxtLink>
            </div>
            <Button type="submit" size="lg" class="w-full" :disabled="isLoading">
              <Loader2 v-if="isLoading" class="size-4 animate-spin me-2" />
              {{ isLoading ? 'جارٍ تسجيل الدخول...' : 'متابعة' }}
            </Button>
          </form>
          <p class="mfa-footer-note">مصادقة متعددة العوامل (MFA) مفعّلة</p>
        </template>

        <template v-else>
          <div class="otp-heading">
            <h2 class="otp-title">رمز التحقق (OTP)</h2>
            <p class="otp-desc">أدخل الرمز المرسل إلى هاتفك المنتهي بـ {{ otpDestination }}</p>
          </div>
          <Alert v-if="otpError" variant="destructive" role="alert" aria-live="assertive" class="mb-5">
            <AlertDescription>
              {{ otpError }}
            </AlertDescription>
          </Alert>
          <div class="otp-cells" @paste.prevent="onOtpPaste">
            <input v-for="(_, i) in otpCells" :key="i" :ref="(el) => { if (el) otpCellRefs[i] = el as HTMLInputElement }" v-model="otpCells[i]" type="text" inputmode="numeric" maxlength="1" class="otp-cell" :class="{ 'otp-cell--error': otpError }" autocomplete="one-time-code" @keydown="onOtpKeydown(i, $event)">
          </div>
          <div class="otp-timer" :class="{ 'otp-timer--expiring': otpSecondsLeft <= 60 }" aria-live="polite">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
            <span>{{ otpSecondsLeft > 0 ? `الرمز صالح لمدة ${otpTimerDisplay}` : 'انتهت صلاحية الرمز' }}</span>
          </div>
          <div class="otp-role-card">
            <Icon name="lock" />
            <span>سيتم تسجيل دخولك بصلاحيات: <strong>{{ otpRoleLabel || 'مستخدم النظام' }}</strong></span>
          </div>
          <Button size="lg" class="w-full" :disabled="isOtpLoading" @click="onOtpSubmit">
            <Loader2 v-if="isOtpLoading" class="size-4 animate-spin me-2" />
            {{ isOtpLoading ? 'جارٍ التحقق...' : 'تأكيد ودخول' }}
          </Button>
          <Button type="button" variant="ghost" class="mt-3 w-full" @click="backToLogin">
            رجوع
          </Button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page { display: grid; grid-template-columns: 1fr 1fr; min-height: 100vh; background-color: var(--background); }
.login-hero { background-color: var(--primary); color: var(--primary-foreground); padding: 40px; display: flex; flex-direction: column; justify-content: space-between; }
.hero-brand { display: flex; align-items: center; gap: 12px; }
.hero-monogram { height: 56px; width: 56px; border-radius: 16px; background: rgb(255 255 255 / 10%); border: 1px solid rgb(255 255 255 / 20%); display: grid; place-items: center; font-size: 24px; font-weight: 700; }
.hero-brand-ar { margin: 0; font-weight: 600; color: var(--primary-foreground); }
.hero-brand-en { margin: 0; color: rgb(255 255 255 / 80%); font-size: 12px; }
.hero-main { max-width: 460px; }
.hero-main h1 { margin: 0 0 12px; color: var(--primary-foreground); font-size: 34px; font-weight: 800; line-height: 1.3; }
.hero-main p { margin: 0; color: rgb(255 255 255 / 85%); line-height: 1.8; font-size: 15px; }
.hero-iso { margin: 0; color: rgb(255 255 255 / 50%); font-size: 12px; }
.login-form-col { background: var(--background); display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
.login-form-wrap { width: 100%; max-width: 420px; }
.login-heading h2 { margin: 0 0 20px; font-size: 30px; font-weight: 700; color: var(--foreground); }
.login-form { display: flex; flex-direction: column; gap: 20px; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.mfa-footer-note { font-size: 12px; color: var(--muted-foreground); text-align: center; margin: 20px 0 0; }
.otp-heading { text-align: center; margin-bottom: 24px; }
.otp-title { font-size: 24px; font-weight: 700; color: var(--foreground); margin: 0 0 8px; }
.otp-desc { font-size: 14px; color: var(--muted-foreground); margin: 0; }
.otp-cells { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; direction: ltr; }
.otp-cell { width: 48px; height: 56px; border: 2px solid var(--border); border-radius: 12px; font-size: 24px; font-weight: 600; text-align: center; color: var(--foreground); background-color: var(--background); transition: border-color 120ms ease; outline: none; caret-color: transparent; }
.otp-cell:focus { border-color: var(--primary); }
.otp-cell--error { border-color: var(--destructive); }
.otp-timer { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted-foreground); margin-bottom: 12px; justify-content: center; }
.otp-timer--expiring { color: var(--destructive); font-weight: 500; }
.otp-role-card { display: flex; align-items: center; gap: 8px; border: 1px dashed var(--border); border-radius: 12px; padding: 12px; background: color-mix(in srgb, var(--muted) 40%, var(--background)); font-size: 13px; margin-bottom: 12px; }
@media (max-width: 1023px) { .login-page { grid-template-columns: 1fr; } .login-hero { display: none; } .login-form-col { padding: 24px 16px; } }
</style>
