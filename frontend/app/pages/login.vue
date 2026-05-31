<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { renderSVG } from 'uqr'
import {
  Building2,
  CheckCircle2,
  ChevronRight,
  Copy,
  KeyRound,
  Lock,
  Loader2,
  QrCode,
  Smartphone,
  ShieldCheck,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useRouter, useRoute } from 'vue-router'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Progress } from '@/components/ui/progress'
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { useAuthStore } from '../stores/auth.store'
import { useOrgStore } from '../stores/org.store'
import { ROLE_LABELS } from '../constants/workflow'
import { useSavedAccounts, getDeviceInfo } from '../composables/useSavedAccounts'
import { useProfile } from '../composables/useProfile'
import LoginSavedAccountCard from '../components/auth/LoginSavedAccountCard.vue'

definePageMeta({ layout: false, middleware: ['guest'] })

const orgStore = useOrgStore()
orgStore.loadSettings()

function copyToClipboard(text: string) {
  if (import.meta.client) {
    window.navigator.clipboard?.writeText(text)
    toast.success('تم النسخ إلى الحافظة')
  }
}

// ─── Step machine ──────────────────────────────────────────────────────────────
type LoginStep =
  | 'account-select'
  | 'pin'
  | 'pin-reset'
  | 'password'
  | 'authenticator'
  | 'authenticator-setup'
  | 'save-account'
  | 'create-pin'

const step = ref<LoginStep>('account-select')
const stepHistory = ref<LoginStep[]>([])

function pushStep(next: LoginStep) {
  stepHistory.value.push(step.value)
  step.value = next
}

function goBack() {
  const prev = stepHistory.value.pop()
  if (prev) step.value = prev
}

// ─── Shared state ─────────────────────────────────────────────────────────────
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const pageDir = computed<'rtl' | 'ltr'>(() => auth.preferredLanguage === 'en' ? 'ltr' : 'rtl')

const STEP_PROGRESS: Partial<Record<LoginStep, number>> = {
  'pin': 65,
  'pin-reset': 20,
  'password': 35,
  'authenticator': 75,
  'authenticator-setup': 70,
  'save-account': 88,
  'create-pin': 94,
}
const loginProgress = computed(() => STEP_PROGRESS[step.value] ?? 0)

const nextPath = computed(() => {
  const candidate = route.query.next
  if (typeof candidate !== 'string') return '/dashboard'
  if (!candidate.startsWith('/')) return '/dashboard'
  return candidate
})

const showInactivityBanner = computed(() => route.query.reason === 'inactivity')
const inactivityBannerDismissed = ref(false)
const serverError = ref<string | null>(null)
const pendingEmail = ref('')
const pendingChallengeId = ref('')
const otpRoleLabel = ref('')

// ─── Auto-focus ───────────────────────────────────────────────────────────────
const formPanelRef = ref<HTMLElement | null>(null)

function focusFirstInput() {
  nextTick(() => {
    const el = formPanelRef.value?.querySelector<HTMLInputElement>(
      'input:not([type=hidden]):not([disabled])',
    )
    el?.focus()
  })
}

watch(step, () => focusFirstInput())
onMounted(() => focusFirstInput())

// ─── Post-auth routing ────────────────────────────────────────────────────────
/**
 * Navigate to dashboard or show "save account" prompt if account not yet saved.
 * Called after full authentication is complete (password + TOTP if required).
 */
async function navigateAfterAuth() {
  const email = pendingEmail.value || selectedAccount.value?.email || auth.user?.email || ''
  const alreadySaved = accounts.value.some(a => a.email === email)
  if (!alreadySaved && email) {
    if (email) pendingEmail.value = email
    pushStep('save-account')
  }
  else {
    await router.push(nextPath.value)
  }
}

// ─── Saved accounts ───────────────────────────────────────────────────────────
const { accounts, addAccount, removeAccount, setPINStatus } = useSavedAccounts()
const { setupTotp, verifyTotpSetup, setPin } = useProfile()

const selectedAccountId = ref<string | null>(null)
const selectedAccount = computed(() =>
  accounts.value.find(a => a.id === selectedAccountId.value) ?? null,
)

function selectSavedAccount(id: string) {
  const account = accounts.value.find(a => a.id === id)
  if (!account) return
  selectedAccountId.value = id
  pendingEmail.value = account.email
  serverError.value = null
  // Always offer PIN first for saved accounts.
  // If PIN is not configured for this account, backend response guides user to password/reset.
  pushStep('pin')
}

function removeSavedAccount(id: string) {
  removeAccount(id)
  if (selectedAccountId.value === id) selectedAccountId.value = null
}

// ─── Step: account-select — email form ────────────────────────────────────────
const emailSubmitAttempted = ref(false)
const emailSchema = toTypedSchema(
  z.object({
    email: z.string().min(1, 'البريد الإلكتروني مطلوب').email('صيغة البريد الإلكتروني غير صحيحة'),
  }),
)
const emailForm = useForm({ validationSchema: emailSchema })
const [emailField, emailFieldAttrs] = emailForm.defineField('email')

async function handleEmailContinue() {
  emailSubmitAttempted.value = true
  const { valid } = await emailForm.validate()
  if (!valid) return
  pendingEmail.value = (emailField.value as string) ?? ''
  serverError.value = null
  pushStep('password')
}

// ─── Step: password ───────────────────────────────────────────────────────────
const showPassword = ref(false)
const isPasswordLoading = ref(false)
const passwordSubmitAttempted = ref(false)

const passwordSchema = toTypedSchema(
  z.object({
    password: z.string().min(1, 'كلمة المرور مطلوبة'),
  }),
)
const passwordForm = useForm({ validationSchema: passwordSchema })
const [pwField, pwFieldAttrs] = passwordForm.defineField('password')

const handlePasswordSubmit = passwordForm.handleSubmit(async (values) => {
  const email = pendingEmail.value || selectedAccount.value?.email || ''
  isPasswordLoading.value = true
  serverError.value = null
  inactivityBannerDismissed.value = true
  try {
    const result = await auth.login(email, values.password)
    if (result?.requiresMfa) {
      pendingEmail.value = result.email
      pendingChallengeId.value = result.challengeId
      otpRoleLabel.value = result.roleLabel ?? ''
      pushStep('authenticator')
      return
    }
    // TOTP not enabled on this account — offer setup before saving account, unless user already skipped
    if (!auth.user?.totp_enabled) {
      const skipKey = `yfh-skip-totp-${email}`
      if (process.client && localStorage.getItem(skipKey)) {
        await navigateAfterAuth()
        return
      }
      pushStep('authenticator-setup')
      return
    }
    if (email) {
      setPINStatus(email, auth.user?.pin_enabled === true)
    }
    await navigateAfterAuth()
  }
  catch (err: any) {
    serverError.value
      = err?.statusCode === 429
        ? 'لقد تجاوزت الحد المسموح به من محاولات تسجيل الدخول. يرجى الانتظار دقيقة ثم حاول مرة أخرى.'
        : err?.data?.message ?? 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'
  }
  finally {
    isPasswordLoading.value = false
  }
})

function onPasswordSubmit() {
  passwordSubmitAttempted.value = true
  handlePasswordSubmit()
}

// ─── Step: pin ────────────────────────────────────────────────────────────────
const pinValue = ref('')
const pinError = ref<string | null>(null)
const isPinLoading = ref(false)
const pinShake = ref(false)

async function handlePinSubmit() {
  if (pinValue.value.length < 6) {
    pinError.value = 'الرجاء إدخال رمز PIN المكوّن من 6 أرقام'
    return
  }
  isPinLoading.value = true
  pinError.value = null
  try {
    const email = selectedAccount.value?.email ?? ''
    await auth.loginWithPin(email, pinValue.value)
    setPINStatus(email, true)
    await router.push(nextPath.value)
  }
  catch (err: any) {
    const backendMessage = err?.data?.message ?? err?.data?.errors?.pin?.[0]
    pinError.value = backendMessage || 'رمز PIN غير صحيح. استخدم كلمة المرور الآن، ثم أعد تعيين PIN من الملف الشخصي إذا لزم.'
    pinValue.value = ''
    pinShake.value = true
    setTimeout(() => { pinShake.value = false }, 600)
    nextTick(() => focusFirstInput())
  }
  finally {
    isPinLoading.value = false
  }
}

function usePinFallbackPassword() {
  pendingEmail.value = selectedAccount.value?.email ?? ''
  serverError.value = null
  pushStep('password')
}

function openPinResetStep() {
  pendingEmail.value = selectedAccount.value?.email ?? pendingEmail.value
  serverError.value = null
  pushStep('pin-reset')
}

// ─── Step: authenticator ──────────────────────────────────────────────────────
const authenticatorCode = ref('')
const authenticatorError = ref<string | null>(null)
const isAuthenticatorLoading = ref(false)

async function handleAuthenticatorSubmit() {
  if (authenticatorCode.value.length < 6) {
    authenticatorError.value = 'الرجاء إدخال الرمز المكوّن من 6 أرقام'
    return
  }
  isAuthenticatorLoading.value = true
  authenticatorError.value = null
  try {
    await auth.verifyOtp(pendingEmail.value, authenticatorCode.value, pendingChallengeId.value)
    otpRoleLabel.value = auth.user ? (ROLE_LABELS[auth.user.role] ?? otpRoleLabel.value) : otpRoleLabel.value
    await navigateAfterAuth()
  }
  catch {
    authenticatorError.value = 'الرمز المدخل غير صحيح أو انتهت صلاحيته. يرجى المحاولة مرة أخرى.'
    authenticatorCode.value = ''
  }
  finally {
    isAuthenticatorLoading.value = false
  }
}

// ─── Step: create-pin ────────────────────────────────────────────────────────
const newPin = ref('')
const newPinConfirm = ref('')
const createPinError = ref<string | null>(null)
const isCreatePinLoading = ref(false)
const createPinStage = ref<'enter' | 'confirm'>('enter')

watch(newPin, (val) => {
  if (val.length === 6 && createPinStage.value === 'enter') {
    createPinStage.value = 'confirm'
  }
})

watch(createPinStage, (stage) => {
  if (stage === 'confirm') focusFirstInput()
})

async function handleCreatePinSubmit() {
  if (newPin.value.length < 6 || newPinConfirm.value.length < 6) {
    createPinError.value = 'الرجاء إدخال رمز PIN المكوّن من 6 أرقام في كلا الحقلين'
    return
  }
  if (newPin.value !== newPinConfirm.value) {
    createPinError.value = 'رمز PIN غير متطابق. يرجى المحاولة مرة أخرى.'
    newPinConfirm.value = ''
    return
  }
  isCreatePinLoading.value = true
  createPinError.value = null
  try {
    const email = pendingEmail.value || selectedAccount.value?.email || auth.user?.email || ''
    const ok = await setPin(newPin.value)
    if (!ok) throw new Error('pin-save-failed')
    if (email) setPINStatus(email, true)
    toast.success('تم إنشاء رمز PIN بنجاح — يمكنك الآن تسجيل الدخول بسرعة في المرات القادمة')
    await router.push(nextPath.value)
  }
  catch {
    createPinError.value = 'حدث خطأ أثناء حفظ رمز PIN. يرجى المحاولة مرة أخرى.'
  }
  finally {
    isCreatePinLoading.value = false
  }
}


// ─── Step: authenticator-setup ────────────────────────────────────────────────
const isAuthSetupLoading = ref(false)
const authSetupCode = ref('')
const authSetupError = ref<string | null>(null)
/** Live provisioning URI from backend — rendered as QR code */
const authSetupUri = ref<string | null>(null)
const authSetupSecret = ref<string | null>(null)

/** SVG QR rendered from the backend provisioning URI */
const authSetupQrSvg = computed(() =>
  authSetupUri.value ? renderSVG(authSetupUri.value, { ecc: 'M' }) : null,
)

/** Fetch TOTP setup data from backend when entering this step. */
async function loadAuthSetup() {
  if (authSetupUri.value) return // already loaded
  isAuthSetupLoading.value = true
  authSetupError.value = null
  try {
    const data = await setupTotp()
    if (!data) throw new Error('failed')
    authSetupUri.value = data.provisioning_uri
    authSetupSecret.value = data.secret
  }
  catch {
    authSetupError.value = 'تعذر تحميل رمز الإعداد. يرجى المحاولة مرة أخرى.'
  }
  finally {
    isAuthSetupLoading.value = false
  }
}

async function handleAuthenticatorSetupSubmit() {
  if (authSetupCode.value.length < 6) {
    authSetupError.value = 'الرجاء إدخال الرمز المكوّن من 6 أرقام من تطبيق المصادقة'
    return
  }
  isAuthSetupLoading.value = true
  authSetupError.value = null
  try {
    const ok = await verifyTotpSetup(authSetupCode.value)
    if (!ok) throw new Error('invalid')
    // TOTP is now set up — proceed to save-account offer
    toast.success('تم إعداد المصادقة الثنائية بنجاح — حسابك محمي الآن')
    await navigateAfterAuth()
  }
  catch {
    authSetupError.value = 'الرمز غير صحيح. تأكد من إدخال الرمز من تطبيق المصادقة وحاول مجدداً.'
    authSetupCode.value = ''
  }
  finally {
    isAuthSetupLoading.value = false
  }
}

function skipAuthenticatorSetup() {
  const email = auth.user?.email ?? pendingEmail.value ?? ''
  if (email && process.client) {
    localStorage.setItem(`yfh-skip-totp-${email}`, '1')
  }
  navigateAfterAuth()
}

// ─── Step: save-account ───────────────────────────────────────────────────────
const isSaveAccountLoading = ref(false)

async function handleSaveAccount(save: boolean) {
  isSaveAccountLoading.value = true
  try {
    if (save && auth.user) {
      addAccount({
        id: crypto.randomUUID(),
        name: auth.user.name,
        email: auth.user.email,
        role: auth.user.role,
        bankName: auth.user.bank_name_ar ?? auth.user.bank_name_en ?? 'البنك المركزي اليمني',
        trustedAt: new Date().toISOString(),
        deviceInfo: getDeviceInfo(),
      })
      setPINStatus(auth.user.email, auth.user.pin_enabled === true)
      if (auth.user.pin_enabled) {
        await router.push(nextPath.value)
        return
      }
      // Offer to create PIN only when this account does not have one yet.
      pushStep('create-pin')
    }
    else {
      await router.push(nextPath.value)
    }
  }
  finally {
    isSaveAccountLoading.value = false
  }
}

// Pre-fill email in password form whenever the step becomes 'password'
watch(step, (newStep) => {
  if (newStep === 'password') {
    const email = pendingEmail.value || selectedAccount.value?.email || ''
    passwordForm.setFieldValue('password', '')
    passwordSubmitAttempted.value = false
    serverError.value = null
    showPassword.value = false
    if (email) pendingEmail.value = email
  }
  if (newStep === 'account-select') {
    emailSubmitAttempted.value = false
    emailForm.setFieldValue('email', '')
    serverError.value = null
  }
  if (newStep === 'pin') {
    pinValue.value = ''
    pinError.value = null
  }
  if (newStep === 'authenticator') {
    authenticatorCode.value = ''
    authenticatorError.value = null
  }
  if (newStep === 'authenticator-setup') {
    authSetupCode.value = ''
    authSetupError.value = null
    authSetupUri.value = null
    authSetupSecret.value = null
    // Immediately fetch QR from backend
    nextTick(() => loadAuthSetup())
  }
  if (newStep === 'create-pin') {
    newPin.value = ''
    newPinConfirm.value = ''
    createPinError.value = null
    createPinStage.value = 'enter'
  }
})
</script>

<template>
  <div class="login-page" :dir="pageDir">
    <!-- ─── Form panel (right on desktop) ─────────────────────────────────── -->
    <div class="login-form-col">
      <div ref="formPanelRef" class="login-form-wrap">
        <!-- Step progress — hidden on first step -->
        <div v-if="step !== 'account-select'" class="mb-6">
          <Progress :model-value="loginProgress" class="h-1" />
        </div>

        <!-- Inactivity banner -->
        <Alert
          v-if="showInactivityBanner && !inactivityBannerDismissed"
          role="status"
          aria-live="polite"
          class="mb-5"
        >
          <AlertDescription>
            تم تسجيل خروجك بسبب عدم النشاط — يرجى تسجيل الدخول مرة أخرى
          </AlertDescription>
        </Alert>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: account-select
             ══════════════════════════════════════════════════════════════════ -->
        <template v-if="step === 'account-select'">
          <div class="step-header">
            <h2 class="step-title">تسجيل الدخول</h2>
            <p class="step-desc">{{ orgStore.authority }} — {{ orgStore.platformName }}</p>
          </div>

          <!-- Email form — always visible and auto-focused -->
          <form novalidate class="space-y-4" @submit.prevent="handleEmailContinue">
            <div class="space-y-2">
              <Label for="email-field">البريد الإلكتروني المؤسسي</Label>
              <Input
                id="email-field"
                v-model="emailField"
                v-bind="emailFieldAttrs"
                type="email"
                placeholder="user@cby.gov.ye"
                autocomplete="email"
                
                class="h-11 bg-muted/30"
                :aria-invalid="emailSubmitAttempted && emailForm.errors.value.email ? 'true' : undefined"
              />
              <p
                v-if="emailSubmitAttempted && emailForm.errors.value.email"
                role="alert"
                class="text-sm text-destructive"
              >
                {{ emailForm.errors.value.email }}
              </p>
            </div>
            <Button type="submit" size="lg" class="w-full" :disabled="!emailField">
              متابعة
            </Button>
          </form>

          <!-- Saved accounts below — with OR separator -->
          <template v-if="accounts.length > 0">
            <div class="relative my-6">
              <div class="absolute inset-0 flex items-center">
                <Separator class="w-full" />
              </div>
              <div class="relative flex justify-center text-xs">
                <span class="bg-background px-3 text-muted-foreground">
                  أو اختر حساباً محفوظاً
                </span>
              </div>
            </div>
            <div class="space-y-2">
              <LoginSavedAccountCard
                v-for="account in accounts"
                :key="account.id"
                :account="account"
                @select="selectSavedAccount(account.id)"
                @remove="removeSavedAccount(account.id)"
              />
            </div>
          </template>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: pin
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'pin'">
          <Button type="button" variant="ghost" size="sm" class="-ms-2 mb-5 gap-1 text-muted-foreground" @click="goBack">
            <ChevronRight class="size-4" />
            رجوع
          </Button>

          <div class="step-header">
            <h2 class="step-title">رمز PIN</h2>
            <p class="step-desc">أدخل رمز PIN المكوّن من 6 أرقام للدخول السريع</p>
          </div>

          <!-- Compact selected account display -->
          <LoginSavedAccountCard
            v-if="selectedAccount"
            :account="selectedAccount"
            :compact="true"
            class="mb-6"
          />

          <Alert v-if="pinError" variant="destructive" role="alert" aria-live="assertive" class="mb-4">
            <AlertDescription>{{ pinError }}</AlertDescription>
          </Alert>

          <div class="otp-wrap" :class="{ 'pin-shake': pinShake }" >
            <InputOTP
              v-model="pinValue"
              :maxlength="6"
              :disabled="isPinLoading"
              @complete="handlePinSubmit"
            >
              <InputOTPGroup>
                <InputOTPSlot
                  v-for="i in 6"
                  :key="i"
                  :index="i - 1"
                  class="size-12 text-2xl font-bold"
                />
              </InputOTPGroup>
            </InputOTP>
          </div>

          <Button
            type="button"
            size="lg"
            class="w-full mt-5"
            :disabled="isPinLoading || pinValue.length < 6"
            @click="handlePinSubmit"
          >
            <Loader2 v-if="isPinLoading" class="size-4 animate-spin me-2" />
            {{ isPinLoading ? 'جارٍ التحقق...' : 'دخول' }}
          </Button>

          <!-- Fallback options -->
          <div class="mt-4 flex flex-col items-center gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="text-xs text-muted-foreground hover:text-foreground"
              @click="usePinFallbackPassword"
            >
              <Lock class="me-1.5 size-3.5" />
              استخدام كلمة المرور بدلاً من PIN
            </Button>
            <Separator class="my-1 w-32" />
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="text-xs text-destructive/70 hover:text-destructive"
              @click="openPinResetStep"
            >
              نسيت رمز PIN؟
            </Button>
          </div>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: password
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'password'">
          <Button type="button" variant="ghost" size="sm" class="-ms-2 mb-5 gap-1 text-muted-foreground" @click="goBack">
            <ChevronRight class="size-4" />
            رجوع
          </Button>

          <div class="step-header">
            <h2 class="step-title">كلمة المرور</h2>
            <p class="step-desc">أدخل كلمة مرور حسابك المؤسسي للمتابعة</p>
          </div>

          <!-- Show who is logging in -->
          <LoginSavedAccountCard
            v-if="selectedAccount"
            :account="selectedAccount"
            :compact="true"
            class="mb-5"
          />
          <div v-else-if="pendingEmail" class="email-chip mb-5">
            <span class="text-xs text-muted-foreground">البريد الإلكتروني</span>
            <span class="text-sm font-medium" >{{ pendingEmail }}</span>
          </div>

          <Alert v-if="serverError" variant="destructive" role="alert" aria-live="assertive" class="mb-4">
            <AlertDescription>{{ serverError }}</AlertDescription>
          </Alert>

          <form novalidate class="space-y-4" @submit.prevent="onPasswordSubmit">
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label for="pw-password">كلمة المرور</Label>
                <Button
                  type="button"
                  variant="link"
                  size="sm"
                  class="h-auto p-0 text-xs text-muted-foreground"
                  @click="navigateTo('/reset-password')"
                >
                  نسيت كلمة المرور؟
                </Button>
              </div>
              <div class="relative">
                <Input
                  id="pw-password"
                  v-model="pwField"
                  v-bind="pwFieldAttrs"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  class="h-11 bg-muted/30 ps-10"
                  :aria-invalid="passwordSubmitAttempted && passwordForm.errors.value.password ? 'true' : undefined"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="absolute start-3 top-1/2 h-7 w-7 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                  :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                  @click="showPassword = !showPassword"
                >
                  <!-- Eye-off -->
                  <svg v-if="showPassword" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                    <line x1="1" y1="1" x2="23" y2="23" />
                  </svg>
                  <!-- Eye -->
                  <svg v-else class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                </Button>
              </div>
              <p
                v-if="passwordSubmitAttempted && passwordForm.errors.value.password"
                role="alert"
                class="text-sm text-destructive"
              >
                {{ passwordForm.errors.value.password }}
              </p>
            </div>

            <Button type="submit" size="lg" class="w-full" :disabled="isPasswordLoading || !pwField">
              <Loader2 v-if="isPasswordLoading" class="size-4 animate-spin me-2" />
              {{ isPasswordLoading ? 'جارٍ تسجيل الدخول...' : 'تسجيل الدخول' }}
            </Button>
          </form>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: authenticator
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'authenticator'">
          <Button type="button" variant="ghost" size="sm" class="-ms-2 mb-5 gap-1 text-muted-foreground" @click="goBack">
            <ChevronRight class="size-4" />
            رجوع
          </Button>

          <div class="step-header">
            <h2 class="step-title">رمز التحقق</h2>
            <p class="step-desc">
              افتح تطبيق المصادقة وأدخل الرمز المكوّن من 6 أرقام
            </p>
          </div>

          <Alert class="mb-5">
            <Smartphone class="h-4 w-4" />
            <AlertDescription>الرمز يتجدد كل 30 ثانية — أدخله فور ظهوره لضمان صلاحيته</AlertDescription>
          </Alert>

          <Alert
            v-if="authenticatorError"
            variant="destructive"
            role="alert"
            aria-live="assertive"
            class="mb-4"
          >
            <AlertDescription>{{ authenticatorError }}</AlertDescription>
          </Alert>

          <div class="otp-wrap" >
            <InputOTP
              v-model="authenticatorCode"
              :maxlength="6"
              :disabled="isAuthenticatorLoading"
              @complete="handleAuthenticatorSubmit"
            >
              <InputOTPGroup>
                <InputOTPSlot
                  v-for="i in 6"
                  :key="i"
                  :index="i - 1"
                  class="size-12 text-2xl font-bold"
                />
              </InputOTPGroup>
            </InputOTP>
          </div>

          <p v-if="otpRoleLabel" class="role-note mt-4">
            <Lock class="size-3.5 shrink-0" aria-hidden="true" />
            سيتم تسجيل دخولك بصلاحيات: <strong>{{ otpRoleLabel }}</strong>
          </p>

          <Button
            type="button"
            size="lg"
            class="w-full mt-5"
            :disabled="isAuthenticatorLoading || authenticatorCode.length < 6"
            @click="handleAuthenticatorSubmit"
          >
            <Loader2 v-if="isAuthenticatorLoading" class="size-4 animate-spin me-2" />
            {{ isAuthenticatorLoading ? 'جارٍ التحقق...' : 'تأكيد ودخول' }}
          </Button>

          <div class="mt-4 text-center">
            <p class="text-xs text-muted-foreground leading-6">
              إذا لم تتمكن من استخدام تطبيق المصادقة:
              تأكد من ضبط الوقت تلقائياً في الهاتف، ثم جرّب الرمز الجديد.
              إذا استمرت المشكلة، استخدم الدخول من جهاز موثوق أو تواصل مع مسؤول النظام لإعادة ضبط المصادقة.
            </p>
          </div>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: create-pin
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'create-pin'">
          <div class="step-header">
            <h2 class="step-title">إنشاء رمز PIN</h2>
            <p class="step-desc">
              أنشئ رمز PIN مكوّن من 6 أرقام لتسجيل دخول سريع في المرات القادمة
            </p>
          </div>

          <Alert class="mb-6">
            <KeyRound class="h-4 w-4" />
            <AlertDescription>بعد إنشاء رمز PIN يمكنك تسجيل الدخول بسرعة دون الحاجة إلى كلمة المرور أو تطبيق المصادقة في كل مرة.</AlertDescription>
          </Alert>

          <Alert v-if="createPinError" variant="destructive" role="alert" aria-live="assertive" class="mb-4">
            <AlertDescription>{{ createPinError }}</AlertDescription>
          </Alert>

          <div class="space-y-5">
            <div class="space-y-2">
              <Label class="text-sm font-medium">
                {{ createPinStage === 'enter' ? 'رمز PIN الجديد' : 'تأكيد رمز PIN' }}
              </Label>
              <div class="otp-wrap" >
                <InputOTP
                  v-if="createPinStage === 'enter'"
                  v-model="newPin"
                  :maxlength="6"
                  :disabled="isCreatePinLoading"
                >
                  <InputOTPGroup>
                    <InputOTPSlot
                      v-for="i in 6"
                      :key="i"
                      :index="i - 1"
                      class="size-12 text-2xl font-bold"
                    />
                  </InputOTPGroup>
                </InputOTP>
                <InputOTP
                  v-else
                  v-model="newPinConfirm"
                  :maxlength="6"
                  :disabled="isCreatePinLoading"
                  @complete="handleCreatePinSubmit"
                >
                  <InputOTPGroup>
                    <InputOTPSlot
                      v-for="i in 6"
                      :key="i"
                      :index="i - 1"
                      class="size-12 text-2xl font-bold"
                    />
                  </InputOTPGroup>
                </InputOTP>
              </div>
              <p v-if="createPinStage === 'confirm'" class="text-xs text-muted-foreground text-center">
                أعد إدخال رمز PIN للتأكيد
              </p>
            </div>
          </div>

          <div class="mt-6 flex flex-col gap-2">
            <Button
              type="button"
              size="lg"
              class="w-full"
              :disabled="isCreatePinLoading || (createPinStage === 'enter' ? newPin.length < 6 : newPinConfirm.length < 6)"
              @click="handleCreatePinSubmit"
            >
              <Loader2 v-if="isCreatePinLoading" class="size-4 animate-spin me-2" />
              {{ isCreatePinLoading ? 'جارٍ الحفظ...' : 'إنشاء رمز PIN' }}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="text-xs text-muted-foreground"
              :disabled="isCreatePinLoading"
              @click="router.push(nextPath.value)"
            >
              تخطي في الوقت الحالي
            </Button>
          </div>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: authenticator-setup (first-time TOTP onboarding)
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'authenticator-setup'">
          <div class="step-header">
            <h2 class="step-title">ربط تطبيق المصادقة</h2>
            <p class="step-desc">
              خطوة أمان إضافية — امسح الرمز لربط حسابك بتطبيق المصادقة
            </p>
          </div>

          <Alert class="mb-5">
            <ShieldCheck class="h-4 w-4" />
            <AlertDescription>استخدم Microsoft Authenticator أو Google Authenticator — ستحتاجه عند كل تسجيل دخول بكلمة المرور</AlertDescription>
          </Alert>

          <!-- QR code from backend TOTP provisioning URI -->
          <div class="flex flex-col items-center gap-3 mb-5">
            <div class="rounded-xl border-2 border-border bg-white p-2 shadow-sm" style="line-height:0">
              <div
                v-if="authSetupQrSvg"
                v-html="authSetupQrSvg"
                class="w-44 h-44 [&>svg]:w-full [&>svg]:h-full [&>svg]:block"
                aria-label="رمز QR لإعداد المصادقة الثنائية"
              />
              <div v-else class="w-44 h-44 flex flex-col items-center justify-center gap-2 text-muted-foreground">
                <Loader2 v-if="isAuthSetupLoading" class="size-8 animate-spin" />
                <QrCode v-else class="size-16 opacity-30" />
                <span class="text-xs">{{ isAuthSetupLoading ? 'جارٍ التحميل…' : 'تعذر تحميل الرمز' }}</span>
              </div>
            </div>

            <div v-if="authSetupSecret" class="flex items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 w-full max-w-xs">
              <code class="text-xs font-mono text-foreground/70 tracking-widest select-all flex-1 text-center" >
                {{ authSetupSecret }}
              </code>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-7 w-7 text-muted-foreground"
                aria-label="نسخ المفتاح السري"
                @click="copyToClipboard(authSetupSecret!)"
              >
                <Copy class="size-3.5" />
              </Button>
            </div>
            <p class="text-xs text-muted-foreground text-center">
              لا يمكنك مسح الرمز؟ أدخل المفتاح يدوياً في التطبيق
            </p>
          </div>

          <Alert
            v-if="authSetupError"
            variant="destructive"
            role="alert"
            aria-live="assertive"
            class="mb-4"
          >
            <AlertDescription>{{ authSetupError }}</AlertDescription>
          </Alert>

          <div class="space-y-2 mb-4">
            <Label class="text-sm font-medium">
              أدخل الرمز من تطبيق المصادقة للتحقق
            </Label>
            <div class="otp-wrap" >
              <InputOTP
                v-model="authSetupCode"
                :maxlength="6"
                :disabled="isAuthSetupLoading || !authSetupQrSvg"
                @complete="handleAuthenticatorSetupSubmit"
              >
                <InputOTPGroup>
                  <InputOTPSlot
                    v-for="i in 6"
                    :key="i"
                    :index="i - 1"
                    class="size-12 text-2xl font-bold"
                  />
                </InputOTPGroup>
              </InputOTP>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <Button
              type="button"
              size="lg"
              class="w-full"
              :disabled="isAuthSetupLoading || authSetupCode.length < 6"
              @click="handleAuthenticatorSetupSubmit"
            >
              <Loader2 v-if="isAuthSetupLoading" class="size-4 animate-spin me-2" />
              {{ isAuthSetupLoading ? 'جارٍ التحقق...' : 'تأكيد الإعداد' }}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="text-xs text-muted-foreground"
              :disabled="isAuthSetupLoading"
              @click="skipAuthenticatorSetup"
            >
              تخطي — سأقوم بالإعداد لاحقاً
            </Button>
          </div>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: save-account
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'save-account'">
          <div class="step-header">
            <CheckCircle2 class="size-8 text-[var(--severity-green)] mb-2" />
            <h2 class="step-title">تم تسجيل الدخول بنجاح</h2>
            <p class="step-desc">
              هل تريد حفظ بيانات الدخول على هذا الجهاز للدخول السريع في المرات القادمة؟
            </p>
          </div>

          <Alert class="mb-5">
            <ShieldCheck class="h-4 w-4" />
            <AlertDescription class="space-y-1">
              <p>سيظهر اسمك في قائمة الحسابات عند فتح النظام على هذا الجهاز.</p>
              <p class="text-xs text-muted-foreground">يمكنك إنشاء رمز PIN في الخطوة التالية للدخول بدون كلمة مرور.</p>
            </AlertDescription>
          </Alert>

          <!-- Account preview -->
          <div v-if="auth.user" class="email-chip mb-5 flex-col items-start gap-0.5 py-3">
            <span class="text-xs text-muted-foreground">الحساب الذي سيتم حفظه</span>
            <span class="text-sm font-semibold">{{ auth.user.name }}</span>
            <span class="text-xs text-muted-foreground" >{{ auth.user.email }}</span>
          </div>

          <div class="flex flex-col gap-3">
            <Button
              type="button"
              size="lg"
              class="w-full"
              :disabled="isSaveAccountLoading"
              @click="handleSaveAccount(true)"
            >
              <Loader2 v-if="isSaveAccountLoading" class="size-4 animate-spin me-2" />
              <CheckCircle2 v-else class="size-4 me-2" />
              {{ auth.user?.pin_enabled ? 'نعم، حفظ الحساب والمتابعة' : 'نعم، حفظ الحساب وإنشاء رمز PIN' }}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="lg"
              class="w-full"
              :disabled="isSaveAccountLoading"
              @click="handleSaveAccount(false)"
            >
              لا، الدخول فقط هذه المرة
            </Button>
            <p class="text-xs text-center text-muted-foreground/70">
              يمكنك تغيير هذا الإعداد لاحقاً من صفحة الملف الشخصي
            </p>
          </div>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════
             STEP: pin-reset
             ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="step === 'pin-reset'">
          <Button type="button" variant="ghost" size="sm" class="-ms-2 mb-5 gap-1 text-muted-foreground" @click="goBack">
            <ChevronRight class="size-4" />
            رجوع
          </Button>

          <div class="step-header">
            <h2 class="step-title">إعادة تعيين رمز PIN</h2>
            <p class="step-desc">أكمل الدخول بكلمة المرور، ثم أعد تعيين PIN من ملفك الشخصي</p>
          </div>

          <div class="space-y-4">
            <Alert>
              <KeyRound class="h-4 w-4" />
              <AlertDescription>إذا نسيت PIN: استخدم كلمة المرور الآن، وبعد الدخول افتح الملف الشخصي وأعد تعيين رمز PIN.</AlertDescription>
            </Alert>
            <Button
              type="button"
              size="lg"
              class="w-full"
              @click="usePinFallbackPassword"
            >
              استخدام كلمة المرور
            </Button>
            <p class="text-xs text-center text-muted-foreground/80">
              إذا نسيت PIN فأكمل الدخول بكلمة المرور ثم أعد ضبط PIN من الإعدادات.
              إذا نسيت كلمة المرور أو لا تستطيع الوصول إلى تطبيق المصادقة، تواصل مع مسؤول النظام.
            </p>
          </div>
        </template>

        <!-- Footer -->
        <div class="login-footer">
          <Building2 class="size-3.5 shrink-0" aria-hidden="true" />
          البنك المركزي اليمني — {{ orgStore.platformName }} v3.0
        </div>
      </div>
    </div>

    <!-- ─── Hero panel (left on desktop) ──────────────────────────────────── -->
    <div class="login-hero" aria-hidden="true">
      <div>
        <div class="hero-brand">
          <div v-if="orgStore.brandLogoDataUrl" class="hero-monogram-logo">
            <img :src="orgStore.brandLogoDataUrl" alt="Logo" class="h-full w-full object-contain" />
          </div>
          <div v-else class="hero-monogram">ب.م</div>
          <div>
            <p class="hero-brand-ar">{{ orgStore.authority }}</p>
            <p class="hero-brand-en">Central Bank of Yemen</p>
          </div>
        </div>
      </div>
      <div class="hero-main">
        <h1>منصة إدارة ومراجعة طلبات تمويل الواردات</h1>
        <p>
          نظام موحّد لإدارة التقديم، المراجعة البنكية، مراجعة الدعم، رفع SWIFT،
          التصويت التنفيذي، وإصدار تأكيد الصرف الخارجي.
        </p>
      </div>
      <p class="hero-iso">ISO 27001 compliant | Secure institutional workflow</p>
    </div>
  </div>
</template>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.login-page {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100vh;
  background-color: var(--background);
}

/* ── Hero panel ──────────────────────────────────────────────────────────── */
.login-hero {
  background-color: var(--primary);
  color: var(--primary-foreground);
  padding: 40px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.hero-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hero-monogram {
  height: 56px;
  width: 56px;
  border-radius: 16px;
  background: rgb(255 255 255 / 10%);
  border: 1px solid rgb(255 255 255 / 20%);
  display: grid;
  place-items: center;
  font-size: 24px;
  font-weight: 700;
  flex-shrink: 0;
}

.hero-monogram-logo {
  height: 56px;
  width: 56px;
  border-radius: 16px;
  background: rgb(255 255 255 / 10%);
  border: 1px solid rgb(255 255 255 / 20%);
  overflow: hidden;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.hero-brand-ar {
  margin: 0;
  font-weight: 600;
  color: var(--primary-foreground);
}

.hero-brand-en {
  margin: 0;
  color: rgb(255 255 255 / 80%);
  font-size: 12px;
}

.hero-main {
  max-width: 460px;
}

.hero-main h1 {
  margin: 0 0 12px;
  color: var(--primary-foreground);
  font-size: 32px;
  font-weight: 800;
  line-height: 1.35;
}

.hero-main p {
  margin: 0;
  color: rgb(255 255 255 / 80%);
  line-height: 1.8;
  font-size: 15px;
}

.hero-iso {
  margin: 0;
  color: rgb(255 255 255 / 50%);
  font-size: 11px;
}

/* ── Form panel ──────────────────────────────────────────────────────────── */
.login-form-col {
  background: var(--background);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
  overflow-y: auto;
}

.login-form-wrap {
  width: 100%;
  max-width: 420px;
}

/* ── Step header ─────────────────────────────────────────────────────────── */
.step-header {
  margin-bottom: 24px;
}

.step-title {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 700;
  color: var(--foreground);
}

.step-desc {
  margin: 0;
  font-size: 14px;
  color: var(--muted-foreground);
  line-height: 1.5;
}


/* ── OTP / PIN wrapper (forces LTR slot order) ───────────────────────────── */
.otp-wrap {
  display: flex;
  justify-content: center;
  direction: ltr;
}

/* ── Role note under authenticator input ─────────────────────────────────── */
.role-note {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted-foreground);
  justify-content: center;
}


/* ── Email chip (shows email during password step) ───────────────────────── */
.email-chip {
  display: flex;
  flex-direction: column;
  gap: 2px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 10px 14px;
  background: color-mix(in srgb, var(--muted) 25%, var(--background));
}



/* ── Page footer ─────────────────────────────────────────────────────────── */
.login-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 32px;
  font-size: 11px;
  color: var(--muted-foreground);
  text-align: center;
}


/* ── PIN shake animation (wrong PIN feedback) ────────────────────────────── */
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  15%       { transform: translateX(-8px); }
  30%       { transform: translateX(8px); }
  45%       { transform: translateX(-6px); }
  60%       { transform: translateX(6px); }
  75%       { transform: translateX(-3px); }
  90%       { transform: translateX(3px); }
}

.pin-shake {
  animation: shake 0.6s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
  .login-page { grid-template-columns: 1fr; }
  .login-hero { display: none; }
}

@media (max-width: 480px) {
  .login-form-col { padding: 20px 12px; }
  .step-title { font-size: 22px; }
}
</style>
