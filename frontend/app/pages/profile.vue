<script setup lang="ts">
import { computed, ref, onMounted, watch, nextTick } from 'vue'
import { renderSVG } from 'uqr'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Activity, AlertTriangle, BadgeCheck, Building2, CheckCircle2, Copy, KeyRound, Laptop, Lock, Mail, MonitorSmartphone, Phone, QrCode, Save, Shield, ShieldCheck, Smartphone, Tablet, Trash2, X } from 'lucide-vue-next'
import { Card, CardContent } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { ROLE_LABELS } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { useProfile } from '@/composables/useProfile'
import { useToast } from '@/composables/use-toast'
import { useSavedAccounts } from '@/composables/useSavedAccounts'

const props = withDefaults(defineProps<{ embedded?: boolean }>(), {
  embedded: false,
})

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { profile, loading, fetchProfile, updateProfile, toggleMfa: composableToggleMfa, setupTotp, verifyTotpSetup, disableTotp, disableTotpWithPassword, setPin: savePinOnServer, disablePin: disablePinOnServer, changePassword } = useProfile()
const { notify } = useToast()
const { accounts, removeAccount, getPINStatus, setPINStatus } = useSavedAccounts()

const name = ref(user.value?.name ?? '')
const email = ref(user.value?.email ?? '')
const phone = ref(user.value?.phone ?? '')

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const passwordSuccess = ref(false)
const mfaEnabled = ref(user.value?.mfa_enabled ?? false)
const totpEnabled = ref(user.value?.totp_enabled ?? false)

onMounted(async () => {
  if (!props.embedded) {
    await navigateTo('/settings?tab=profile', { replace: true })
    return
  }
  await fetchProfile()
})

watch(profile, (p) => {
  if (!p) return
  name.value = p.name
  email.value = p.email
  phone.value = p.phone ?? ''
  hasPIN.value = Boolean(p.pin_enabled)
  setPINStatus(p.email, Boolean(p.pin_enabled))
})

const stats = computed(() => {
  const s = profile.value?.stats
  if (!s) return []
  if (user.value?.role === UserRole.BANK_REVIEWER) {
    return [
      { label: 'مراجعات', value: s.reviews_performed ?? s.total ?? 0 },
      { label: 'اعتمادات', value: s.approvals ?? 0 },
      { label: 'إعادات', value: s.returns ?? 0 },
      { label: 'رفض نهائي', value: s.terminal_rejections ?? 0 },
    ]
  }
  if (user.value?.role === UserRole.BANK_ADMIN) {
    return [
      { label: 'موظفون', value: s.staff_managed ?? s.total ?? 0 },
      { label: 'تجار', value: s.merchants_managed ?? s.in_progress ?? 0 },
      { label: 'مكتمل', value: s.completed ?? 0 },
    ]
  }
  if (user.value?.role === UserRole.COMMITTEE_DIRECTOR) {
    return [
      { label: 'جلسات أُغلقت', value: s.sessions_closed ?? s.total ?? 0 },
      { label: 'قرارات مُصدرة', value: s.decisions_finalized ?? s.completed ?? 0 },
      { label: 'تأكيدات مصارفة', value: s.fx_confirmations_completed ?? 0 },
    ]
  }
  if (user.value?.role === UserRole.EXECUTIVE_MEMBER) {
    return [
      { label: 'جلسات شارك بها', value: s.sessions_participated ?? s.total ?? 0 },
      { label: 'متوسط وقت التصويت', value: s.avg_time_to_vote_hours != null ? `${s.avg_time_to_vote_hours}س` : '—' },
      { label: 'نسبة الاعتماد', value: s.approval_percentage != null ? `${s.approval_percentage}%` : '—' },
    ]
  }
  if (user.value?.role === UserRole.SWIFT_OFFICER) {
    return [
      { label: 'وثائق مرفوعة', value: s.swift_uploads ?? s.total ?? 0 },
      { label: 'متوسط وقت الرفع', value: s.avg_time_to_upload_hours != null ? `${s.avg_time_to_upload_hours}س` : '—' },
      { label: 'مكتمل', value: s.completed ?? 0 },
    ]
  }
  return [
    { label: 'إجمالي الطلبات', value: s.total },
    { label: 'قيد المعالجة', value: s.in_progress },
    { label: 'مكتمل', value: s.completed },
  ]
})

const myActivity = computed(() => profile.value?.recent_activity?.slice(0, 6) ?? [])

async function saveProfile() {
  const ok = await updateProfile({ name: name.value, email: email.value, phone: phone.value })
  if (ok) notify('تم حفظ التغييرات')
}

async function submitPasswordChange() {
  const ok = await changePassword({
    current_password: passwordForm.current_password,
    password: passwordForm.password,
    password_confirmation: passwordForm.password_confirmation,
  })
  if (ok) {
    passwordSuccess.value = true
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  }
}

async function toggleMfa() {
  if (composableToggleMfa) await composableToggleMfa()
  mfaEnabled.value = !mfaEnabled.value
  notify(mfaEnabled.value ? 'تم تفعيل المصادقة الثنائية' : 'تم إلغاء تفعيل المصادقة الثنائية')
}

// Executive Member: surface MFA enrollment prompt prominently when MFA not yet enabled
const showMfaEnrollmentPrompt = computed(() =>
  user.value?.role === UserRole.EXECUTIVE_MEMBER && !user.value?.mfa_enabled,
)

function requestMfaActivation() {
  navigateTo('/mfa-setup')
}

function userInitials(n?: string) {
  if (!n) return '?'
  return n.split(' ').map(p => p[0]).slice(0, 2).join('')
}

// ─── PIN management ───────────────────────────────────────────────────────────
/** PIN is account-level — reads from localStorage, synced with the composable */
const hasPIN = ref(Boolean(user.value?.pin_enabled) || getPINStatus(user.value?.email ?? ''))

type PinDialogMode = 'create' | 'change' | 'disable'
const pinDialogOpen = ref(false)
const pinDialogMode = ref<PinDialogMode>('create')
const pinDialogStage = ref<'current' | 'new' | 'confirm'>('new')
const pinCurrent = ref('')
const pinNew = ref('')
const pinConfirm = ref('')
const pinError = ref<string | null>(null)
const isPinSaving = ref(false)
type PinDialogContentRef = HTMLElement | { $el?: HTMLElement } | null
const pinDialogContentRef = ref<PinDialogContentRef>(null)

function resolvePinDialogRoot(contentRef: PinDialogContentRef): HTMLElement | null {
  if (!contentRef) return null
  if (contentRef instanceof HTMLElement) return contentRef
  if (contentRef.$el instanceof HTMLElement) return contentRef.$el
  return null
}

function focusPinInput() {
  nextTick(() => {
    requestAnimationFrame(() => {
      const root = resolvePinDialogRoot(pinDialogContentRef.value)
        ?? document.querySelector<HTMLElement>('[data-slot="dialog-content"][data-state="open"]')
      const input = root?.querySelector<HTMLInputElement>(
        'input[data-input-otp], input:not([type=hidden]):not([disabled])',
      )
      input?.focus()
    })
  })
}

function openPinDialog(mode: PinDialogMode) {
  pinDialogMode.value = mode
  pinDialogStage.value = mode === 'change' ? 'current' : 'new'
  pinCurrent.value = ''
  pinNew.value = ''
  pinConfirm.value = ''
  pinError.value = null
  pinDialogOpen.value = true
  focusPinInput()
}

watch(pinNew, (val) => {
  if (val.length === 6 && pinDialogStage.value === 'new') {
    pinDialogStage.value = 'confirm'
  }
})

watch(pinCurrent, (val) => {
  if (val.length === 6 && pinDialogStage.value === 'current') {
    pinDialogStage.value = 'new'
  }
})

watch(pinDialogStage, () => {
  focusPinInput()
})

async function submitPinAction() {
  pinError.value = null
  if (pinDialogMode.value === 'disable') {
    if (pinCurrent.value.length < 6) {
      pinError.value = 'الرجاء إدخال رمز PIN الحالي'
      return
    }
    isPinSaving.value = true
    try {
      const ok = await disablePinOnServer(pinCurrent.value)
      if (!ok) throw new Error('invalid-pin')
      const email = user.value?.email ?? ''
      setPINStatus(email, false)
      hasPIN.value = false
      pinDialogOpen.value = false
      notify('تم تعطيل رمز PIN بنجاح')
    }
    catch {
      pinError.value = 'رمز PIN الحالي غير صحيح'
    }
    finally {
      isPinSaving.value = false
    }
    return
  }
  if (pinNew.value.length < 6 || pinConfirm.value.length < 6) {
    pinError.value = 'الرجاء إدخال رمز PIN المكوّن من 6 أرقام في كلا الحقلين'
    return
  }
  if (pinNew.value !== pinConfirm.value) {
    pinError.value = 'رمز PIN غير متطابق. يرجى المحاولة مرة أخرى.'
    pinConfirm.value = ''
    return
  }
  isPinSaving.value = true
  try {
    const currentPin = pinDialogMode.value === 'change' ? pinCurrent.value : undefined
    const ok = await savePinOnServer(pinNew.value, currentPin)
    if (!ok) throw new Error('pin-save-failed')
    const email = user.value?.email ?? ''
    setPINStatus(email, true)
    hasPIN.value = true
    pinDialogOpen.value = false
    notify(pinDialogMode.value === 'create' ? 'تم إنشاء رمز PIN بنجاح' : 'تم تغيير رمز PIN بنجاح')
  }
  catch {
    pinError.value = 'حدث خطأ أثناء حفظ رمز PIN. يرجى المحاولة مرة أخرى.'
  }
  finally {
    isPinSaving.value = false
  }
}

function removeTrustedDevice(id: string) {
  removeAccount(id)
  notify('تم إزالة الجهاز الموثوق')
}

// ─── MFA / Authenticator setup ───────────────────────────────────────────────
type MfaDialogStage = 'intro' | 'scan' | 'verify' | 'disable-verify' | 'disable-with-password'

const mfaDialogOpen = ref(false)
const mfaDialogStage = ref<MfaDialogStage>('intro')
const mfaVerifyCode = ref('')
const mfaSetupError = ref<string | null>(null)
const isMfaActionLoading = ref(false)
/** Live TOTP secret received from backend during setup */
const liveMfaSecret = ref<string | null>(null)
/** Live provisioning URI from backend — rendered as QR code */
const liveMfaUri = ref<string | null>(null)
/** Password input for the "no authenticator access" fallback disable flow */
const mfaDisablePassword = ref('')

/** SVG QR rendered from the backend provisioning URI (otpauth://) */
const mfaQrSvg = computed(() => {
  if (!liveMfaUri.value) return null
  return renderSVG(liveMfaUri.value, { ecc: 'M' })
})

async function openMfaSetup() {
  mfaDialogStage.value = 'intro'
  mfaVerifyCode.value = ''
  mfaSetupError.value = null
  liveMfaSecret.value = null
  liveMfaUri.value = null
  mfaDialogOpen.value = true
}

function openMfaDisable() {
  mfaDialogStage.value = 'disable-verify'
  mfaVerifyCode.value = ''
  mfaDisablePassword.value = ''
  mfaSetupError.value = null
  mfaDialogOpen.value = true
}

async function confirmMfaDisableWithPassword() {
  if (!mfaDisablePassword.value) {
    mfaSetupError.value = 'الرجاء إدخال كلمة المرور'
    return
  }
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const ok = await disableTotpWithPassword(mfaDisablePassword.value)
    if (!ok) throw new Error('invalid')
    totpEnabled.value = false
    mfaEnabled.value = false
    mfaDialogOpen.value = false
    notify('تم تعطيل تطبيق المصادقة')
  }
  catch {
    mfaSetupError.value = 'كلمة المرور غير صحيحة.'
    mfaDisablePassword.value = ''
  }
  finally {
    isMfaActionLoading.value = false
  }
}

function copyMfaSecret() {
  if (import.meta.client && liveMfaSecret.value)
    window.navigator.clipboard?.writeText(liveMfaSecret.value)
}

/** Called when the user clicks "التالي" from the intro stage — fetches QR from backend */
async function loadTotpSetup() {
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const data = await setupTotp()
    if (!data) throw new Error()
    liveMfaSecret.value = data.secret
    liveMfaUri.value = data.provisioning_uri
    mfaDialogStage.value = 'scan'
  }
  catch {
    mfaSetupError.value = 'تعذر تحميل رمز QR. يرجى المحاولة مرة أخرى.'
  }
  finally {
    isMfaActionLoading.value = false
  }
}

async function confirmMfaSetup() {
  if (mfaVerifyCode.value.length < 6) {
    mfaSetupError.value = 'الرجاء إدخال الرمز المكوّن من 6 أرقام'
    return
  }
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const ok = await verifyTotpSetup(mfaVerifyCode.value)
    if (!ok) throw new Error('invalid')
    totpEnabled.value = true
    mfaEnabled.value = true
    mfaDialogOpen.value = false
    notify('تم تفعيل تطبيق المصادقة بنجاح')
  }
  catch {
    mfaSetupError.value = 'الرمز غير صحيح. تحقق من تطبيق المصادقة وحاول مجدداً.'
    mfaVerifyCode.value = ''
  }
  finally {
    isMfaActionLoading.value = false
  }
}

async function confirmMfaDisable() {
  if (mfaVerifyCode.value.length < 6) {
    mfaSetupError.value = 'الرجاء إدخال رمز التحقق لتأكيد التعطيل'
    return
  }
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const ok = await disableTotp(mfaVerifyCode.value)
    if (!ok) throw new Error('invalid')
    totpEnabled.value = false
    mfaEnabled.value = false
    mfaDialogOpen.value = false
    notify('تم تعطيل تطبيق المصادقة')
  }
  catch {
    mfaSetupError.value = 'رمز التحقق غير صحيح.'
    mfaVerifyCode.value = ''
  }
  finally {
    isMfaActionLoading.value = false
  }
}
</script>

<template>
  <div v-if="user" class="mx-auto w-full max-w-6xl px-4">
    <h1 class="page-title sr-only">الملف الشخصي</h1>
    <PageHeader
      v-if="!props.embedded"
      title="الملف الشخصي"
      subtitle="معلومات الحساب وإعدادات الأمان"
    />

    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="border border-border p-6 text-center">
        <Avatar class="mx-auto h-24 w-24">
          <AvatarFallback data-testid="avatar-initials" class="bg-primary text-3xl font-bold text-primary-foreground">
            {{ userInitials(user.name) }}
          </AvatarFallback>
        </Avatar>
        <div data-testid="profile-name" class="mt-4 flex items-center justify-center gap-1.5 text-lg font-bold">
          {{ user.name }}
          <BadgeCheck class="h-4 w-4 text-primary" />
        </div>
        <Badge
          variant="secondary"
          class="badge-role mt-1"
        >
          {{ ROLE_LABELS[user.role] }}
        </Badge>
        <div class="mt-2 text-xs text-muted-foreground">
          {{ user.bank_name_ar }}
        </div>

        <div
          v-if="stats.length > 0"
          data-testid="stats-strip"
          class="mt-6 grid gap-3 border-t border-border pt-6"
          :class="stats.length === 4 ? 'grid-cols-4' : 'grid-cols-3'"
        >
          <div
            v-for="(stat, idx) in stats"
            :key="stat.label"
            :data-testid="idx === 0 ? 'stats-total' : idx === 1 ? 'stats-in-progress' : 'stats-completed'"
          >
            <div class="font-bold tabular-nums">
              {{ stat.value }}
            </div>
            <div class="text-[10px] text-muted-foreground">
              {{ stat.label }}
            </div>
          </div>
        </div>

        <!-- Quick stat rows -->
        <div class="mt-4 space-y-1.5 border-t border-border pt-4 text-sm">
          <div data-testid="stat-last-login" class="flex items-center justify-between text-xs text-muted-foreground">
            <span>آخر دخول</span>
            <span>—</span>
          </div>
          <div data-testid="stat-total-actions" class="flex items-center justify-between text-xs text-muted-foreground">
            <span>إجمالي الإجراءات</span>
            <span>{{ profile?.stats?.total ?? 0 }}</span>
          </div>
        </div>

        <div class="mt-6 space-y-2 border-t border-border pt-6 text-end">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Mail class="h-3.5 w-3.5 shrink-0" />
            <span data-testid="profile-email" class="truncate">{{ user.email }}</span>
          </div>
          <div
            v-if="phone"
            class="flex items-center gap-2 text-xs text-muted-foreground"
          >
            <Phone class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ phone }}</span>
          </div>
          <div
            v-if="user.bank_name_ar"
            class="flex items-center gap-2 text-xs text-muted-foreground"
          >
            <Building2 class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ user.bank_name_ar }}</span>
          </div>
        </div>
      </Card>

      <Card class="space-y-5 border border-border p-6 lg:col-span-2">
        <div>
          <h3 class="font-semibold">
            المعلومات الأساسية
          </h3>
          <p class="mt-0.5 text-xs text-muted-foreground">
            حدّث بياناتك الشخصية وطرق التواصل
          </p>
        </div>

        <form class="space-y-5" @submit.prevent="saveProfile">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label>الاسم الكامل</Label>
            <Input v-model="name" />
          </div>
          <div class="space-y-2">
            <Label>البريد الإلكتروني</Label>
            <Input
              v-model="email"
              type="email"
            />
          </div>
          <div class="space-y-2">
            <Label>رقم الهاتف</Label>
            <Input
              v-model="phone"
              placeholder="+9677..."
            />
          </div>
          <div class="space-y-2">
            <Label>الجهة</Label>
            <Input
              :model-value="user.bank_name_ar ?? ''"
              disabled
            />
          </div>
          <div class="space-y-2">
            <Label>الدور</Label>
            <Input
              :model-value="ROLE_LABELS[user.role]"
              disabled
            />
          </div>
          <div class="space-y-2">
            <Label>المعرّف</Label>
            <Input
              :model-value="user.id.toString()"
              disabled
              class="font-mono text-xs"
            />
          </div>
        </div>

        <!-- Executive Member: prominent MFA enrollment prompt when MFA not enabled -->
        <div
          v-if="showMfaEnrollmentPrompt"
          class="flex items-start gap-3 rounded-md border border-[var(--severity-amber)]/50 bg-[var(--severity-amber)]/8 p-4"
          role="alert"
          data-testid="mfa-enrollment-prompt"
        >
          <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[var(--severity-amber)]">
              المصادقة الثنائية غير مفعّلة
            </p>
            <p class="mt-1 text-xs text-foreground leading-relaxed">
              كعضو في اللجنة التنفيذية، يُلزمك النظام بتفعيل المصادقة الثنائية (MFA) لحماية جلسات التصويت.
              لن تتمكن من التصويت في الجلسات القادمة حتى تُفعّل هذه الميزة.
            </p>
            <Button
              size="sm"
              class="mt-2 bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
              @click="requestMfaActivation"
            >
              <Shield class="ms-1 h-4 w-4" />
              تفعيل المصادقة الثنائية الآن
            </Button>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 border-t border-border pt-4">
          <Button type="submit" :disabled="loading">
            <Save class="ms-1 h-4 w-4" />
            حفظ التغييرات
          </Button>
        </div>
        </form>

        <!-- Authenticator (MFA) management -->
        <div class="border-t border-border pt-4">
          <h3 class="mb-1 font-semibold flex items-center gap-2">
            <Shield class="h-4 w-4" />
            تطبيق المصادقة الثنائية
          </h3>
          <p class="mb-3 text-xs text-muted-foreground">
            يُستخدم تطبيق Microsoft Authenticator أو Google Authenticator لتوليد رموز تحقق مؤقتة
          </p>

          <div v-if="totpEnabled || mfaEnabled" class="flex items-center justify-between rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5 p-3">
            <div class="flex items-center gap-2">
              <ShieldCheck class="h-4 w-4 text-[var(--severity-green)] shrink-0" />
              <span class="text-sm font-medium text-[var(--severity-green)]">تطبيق المصادقة مفعّل</span>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="text-destructive hover:text-destructive text-xs"
              @click="openMfaDisable"
            >
              <X class="ms-1 h-3.5 w-3.5" />
              تعطيل
            </Button>
          </div>
          <div v-else class="rounded-lg border border-border bg-muted/20 p-3">
            <div class="flex items-center gap-2 mb-2">
              <Lock class="h-4 w-4 text-muted-foreground shrink-0" />
              <span class="text-sm text-muted-foreground">تطبيق المصادقة غير مفعّل</span>
            </div>
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="openMfaSetup"
            >
              <QrCode class="ms-1 h-4 w-4" />
              إعداد تطبيق المصادقة
            </Button>
          </div>
        </div>

        <!-- Password change form -->
        <div class="border-t border-border pt-4">
          <h3 class="mb-3 font-semibold">تغيير كلمة المرور</h3>
          <div
            v-if="passwordSuccess"
            class="success-banner mb-3 rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5 p-3 text-sm text-[var(--severity-green)]"
          >
            تم تغيير كلمة المرور بنجاح
          </div>
          <form data-testid="password-form" class="space-y-3" @submit.prevent="submitPasswordChange">
            <div class="space-y-1.5">
              <Label>كلمة المرور الحالية</Label>
              <Input v-model="passwordForm.current_password" type="password" />
            </div>
            <div class="space-y-1.5">
              <Label>كلمة المرور الجديدة</Label>
              <Input v-model="passwordForm.password" type="password" />
            </div>
            <div class="space-y-1.5">
              <Label>تأكيد كلمة المرور</Label>
              <Input v-model="passwordForm.password_confirmation" type="password" />
            </div>
            <Button type="submit" variant="outline" size="sm">
              <KeyRound class="ms-1 h-4 w-4" />
              حفظ كلمة المرور
            </Button>
          </form>
        </div>

        <!-- PIN Management -->
        <div class="border-t border-border pt-4">
          <h3 class="mb-1 font-semibold flex items-center gap-2">
            <KeyRound class="h-4 w-4" />
            رمز PIN للدخول السريع
          </h3>
          <p class="mb-3 text-xs text-muted-foreground">
            رمز مكوّن من 6 أرقام يتيح لك تسجيل الدخول بسرعة من الأجهزة الموثوقة
          </p>

          <div v-if="hasPIN" class="flex items-center gap-2 mb-3">
            <CheckCircle2 class="h-4 w-4 text-[var(--severity-green)] shrink-0" />
            <span class="text-sm text-[var(--severity-green)]">رمز PIN مفعّل</span>
          </div>
          <div v-else class="flex items-center gap-2 mb-3">
            <Lock class="h-4 w-4 text-muted-foreground shrink-0" />
            <span class="text-sm text-muted-foreground">لم يتم إنشاء رمز PIN بعد</span>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="!hasPIN"
              type="button"
              variant="outline"
              size="sm"
              @click="openPinDialog('create')"
            >
              <KeyRound class="ms-1 h-4 w-4" />
              إنشاء رمز PIN
            </Button>
            <template v-else>
              <Button
                type="button"
                variant="outline"
                size="sm"
                @click="openPinDialog('change')"
              >
                <KeyRound class="ms-1 h-4 w-4" />
                تغيير رمز PIN
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                @click="openPinDialog('disable')"
              >
                <X class="ms-1 h-4 w-4" />
                تعطيل رمز PIN
              </Button>
            </template>
          </div>
        </div>

        <!-- Trusted Devices -->
        <div class="border-t border-border pt-4">
          <h3 class="mb-1 font-semibold flex items-center gap-2">
            <MonitorSmartphone class="h-4 w-4" />
            الأجهزة الموثوقة
          </h3>
          <p class="mb-3 text-xs text-muted-foreground">
            الأجهزة التي حفظت بيانات دخولك للوصول السريع برمز PIN
          </p>

          <div v-if="accounts.length === 0" class="text-sm text-muted-foreground py-2">
            لا توجد أجهزة موثوقة مسجّلة حتى الآن.
          </div>
          <ul v-else class="space-y-2">
            <li
              v-for="device in accounts"
              :key="device.id"
              class="rounded-lg border border-border p-3"
            >
              <div class="flex items-start gap-3">
                <!-- Device type icon -->
                <div class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-muted">
                  <Smartphone v-if="device.deviceInfo?.deviceType === 'mobile'" class="h-4 w-4 text-muted-foreground" />
                  <Tablet v-else-if="device.deviceInfo?.deviceType === 'tablet'" class="h-4 w-4 text-muted-foreground" />
                  <Laptop v-else class="h-4 w-4 text-muted-foreground" />
                </div>

                <div class="flex-1 min-w-0 space-y-1">
                  <p class="text-sm font-medium truncate">{{ device.name }}</p>
                  <!-- Browser + OS -->
                  <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                    <span v-if="device.deviceInfo?.browser" class="flex items-center gap-1 text-xs text-muted-foreground">
                      <MonitorSmartphone class="h-3 w-3" />
                      {{ device.deviceInfo.browser }}
                    </span>
                    <span v-if="device.deviceInfo?.os" class="text-xs text-muted-foreground">
                      {{ device.deviceInfo.os }}
                    </span>
                  </div>
                  <!-- Last login + trust date -->
                  <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                    <span v-if="device.deviceInfo?.lastLoginAt" class="text-[10px] text-muted-foreground/70">
                      آخر دخول:
                      {{ new Date(device.deviceInfo.lastLoginAt).toLocaleString('ar-EG', {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                      }) }}
                    </span>
                    <span class="text-[10px] text-muted-foreground/70">
                      الثقة منذ: {{ new Date(device.trustedAt).toLocaleDateString('ar-EG', { dateStyle: 'medium' }) }}
                    </span>
                  </div>
                </div>

                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="text-destructive hover:text-destructive shrink-0"
                  :aria-label="`إزالة جهاز ${device.name}`"
                  @click="removeTrustedDevice(device.id)"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </li>
          </ul>
        </div>

        <!-- MFA Setup / Disable Dialog -->
        <Dialog v-model:open="mfaDialogOpen">
          <DialogContent class="max-w-sm" dir="rtl">
            <!-- ── Disable verify ── -->
            <template v-if="mfaDialogStage === 'disable-verify'">
              <DialogHeader>
                <DialogTitle>تعطيل تطبيق المصادقة</DialogTitle>
                <DialogDescription>
                  أدخل الرمز الحالي من تطبيق المصادقة للتأكيد
                </DialogDescription>
              </DialogHeader>
              <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                {{ mfaSetupError }}
              </div>
              <div class="flex justify-center py-2" dir="ltr">
                <InputOTP v-model="mfaVerifyCode" :maxlength="6" :disabled="isMfaActionLoading" @complete="confirmMfaDisable">
                  <InputOTPGroup>
                    <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                  </InputOTPGroup>
                </InputOTP>
              </div>
              <DialogFooter class="flex-col gap-2 sm:flex-col">
                <div class="flex flex-row-reverse gap-2">
                  <Button type="button" variant="destructive" :disabled="isMfaActionLoading || mfaVerifyCode.length < 6" @click="confirmMfaDisable">
                    <X class="ms-1 h-4 w-4" />
                    تعطيل
                  </Button>
                  <Button type="button" variant="outline" :disabled="isMfaActionLoading" @click="mfaDialogOpen = false">
                    إلغاء
                  </Button>
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="text-muted-foreground text-xs"
                  @click="() => { mfaSetupError = null; mfaDialogStage = 'disable-with-password' }"
                >
                  لا أملك وصولاً إلى تطبيق المصادقة
                </Button>
              </DialogFooter>
            </template>

            <!-- ── Disable with password fallback ── -->
            <template v-else-if="mfaDialogStage === 'disable-with-password'">
              <DialogHeader>
                <DialogTitle>تعطيل المصادقة بكلمة المرور</DialogTitle>
                <DialogDescription>
                  أدخل كلمة المرور الخاصة بحسابك لتعطيل تطبيق المصادقة
                </DialogDescription>
              </DialogHeader>
              <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                {{ mfaSetupError }}
              </div>
              <div class="space-y-2 py-2">
                <Label for="mfa-disable-pwd">كلمة المرور</Label>
                <Input
                  id="mfa-disable-pwd"
                  v-model="mfaDisablePassword"
                  type="password"
                  :disabled="isMfaActionLoading"
                  placeholder="أدخل كلمة مرورك"
                  autofocus
                  @keydown.enter="confirmMfaDisableWithPassword"
                />
              </div>
              <DialogFooter class="flex-row-reverse gap-2 sm:flex-row-reverse">
                <Button
                  type="button"
                  variant="destructive"
                  :disabled="isMfaActionLoading || !mfaDisablePassword"
                  @click="confirmMfaDisableWithPassword"
                >
                  <X class="ms-1 h-4 w-4" />
                  تعطيل
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  :disabled="isMfaActionLoading"
                  @click="() => { mfaSetupError = null; mfaDialogStage = 'disable-verify' }"
                >
                  رجوع
                </Button>
              </DialogFooter>
            </template>

            <!-- ── Setup: intro ── -->
            <template v-else-if="mfaDialogStage === 'intro'">
              <DialogHeader>
                <DialogTitle>إعداد تطبيق المصادقة</DialogTitle>
                <DialogDescription>
                  سيُضاف طبق مصادقة لحماية حسابك برمز تحقق مؤقت يتغير كل 30 ثانية
                </DialogDescription>
              </DialogHeader>
              <div class="space-y-3 py-2 text-sm text-muted-foreground">
                <p>الخطوات:</p>
                <ol class="list-decimal list-inside space-y-1 text-sm">
                  <li>افتح تطبيق <strong>Microsoft Authenticator</strong> أو <strong>Google Authenticator</strong></li>
                  <li>امسح رمز QR أو أدخل المفتاح يدوياً</li>
                  <li>أدخل الرمز المكوّن من 6 أرقام للتأكيد</li>
                </ol>
              </div>
              <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                {{ mfaSetupError }}
              </div>
              <DialogFooter class="flex-row-reverse gap-2 sm:flex-row-reverse">
                <Button type="button" :disabled="isMfaActionLoading" @click="loadTotpSetup">
                  <span v-if="isMfaActionLoading" class="flex items-center gap-2">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    جارٍ التحميل…
                  </span>
                  <span v-else>التالي</span>
                </Button>
                <Button type="button" variant="outline" @click="mfaDialogOpen = false">
                  إلغاء
                </Button>
              </DialogFooter>
            </template>

            <!-- ── Setup: scan QR ── -->
            <template v-else-if="mfaDialogStage === 'scan'">
              <DialogHeader>
                <DialogTitle>امسح رمز QR</DialogTitle>
                <DialogDescription>
                  امسح الرمز بتطبيق المصادقة أو أدخل المفتاح السري يدوياً
                </DialogDescription>
              </DialogHeader>

              <!-- QR code generated client-side from live TOTP provisioning URI using uqr -->
              <div class="flex flex-col items-center gap-3 py-2">
                <div class="rounded-xl border-2 border-border bg-white p-2 shadow-sm" style="line-height:0">
                  <!-- eslint-disable-next-line vue/no-v-html -->
                  <div v-if="mfaQrSvg" v-html="mfaQrSvg" class="w-40 h-40 [&>svg]:w-full [&>svg]:h-full [&>svg]:block" />
                  <div v-else class="w-40 h-40 flex items-center justify-center text-muted-foreground text-xs">جارٍ تحميل الرمز…</div>
                </div>

                <div class="w-full rounded-lg border border-border bg-muted/30 px-3 py-2">
                  <p class="mb-1 text-[10px] text-muted-foreground">المفتاح السري (للإدخال اليدوي)</p>
                  <div class="flex items-center justify-between gap-2">
                    <code class="text-xs font-mono tracking-widest" dir="ltr">{{ liveMfaSecret ?? '…' }}</code>
                    <Button type="button" variant="ghost" size="sm" class="h-7 px-2" :disabled="!liveMfaSecret" @click="copyMfaSecret">
                      <Copy class="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
              </div>

              <DialogFooter class="flex-row-reverse gap-2 sm:flex-row-reverse">
                <Button type="button" @click="mfaDialogStage = 'verify'">
                  تم — إدخال رمز التحقق
                </Button>
                <Button type="button" variant="outline" @click="mfaDialogStage = 'intro'">
                  رجوع
                </Button>
              </DialogFooter>
            </template>

            <!-- ── Setup: verify ── -->
            <template v-else-if="mfaDialogStage === 'verify'">
              <DialogHeader>
                <DialogTitle>تأكيد الإعداد</DialogTitle>
                <DialogDescription>
                  أدخل الرمز المكوّن من 6 أرقام الظاهر في تطبيق المصادقة
                </DialogDescription>
              </DialogHeader>
              <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                {{ mfaSetupError }}
              </div>
              <div class="flex justify-center py-2" dir="ltr">
                <InputOTP v-model="mfaVerifyCode" :maxlength="6" :disabled="isMfaActionLoading" @complete="confirmMfaSetup">
                  <InputOTPGroup>
                    <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                  </InputOTPGroup>
                </InputOTP>
              </div>
              <DialogFooter class="flex-row-reverse gap-2 sm:flex-row-reverse">
                <Button type="button" :disabled="isMfaActionLoading || mfaVerifyCode.length < 6" @click="confirmMfaSetup">
                  <BadgeCheck class="ms-1 h-4 w-4" />
                  تفعيل
                </Button>
                <Button type="button" variant="outline" :disabled="isMfaActionLoading" @click="mfaDialogStage = 'scan'">
                  رجوع
                </Button>
              </DialogFooter>
            </template>
          </DialogContent>
        </Dialog>

        <!-- PIN Dialog -->
        <Dialog v-model:open="pinDialogOpen">
          <DialogContent ref="pinDialogContentRef" class="max-w-sm" dir="rtl">
            <DialogHeader>
              <DialogTitle>
                {{
                  pinDialogMode === 'create' ? 'إنشاء رمز PIN'
                  : pinDialogMode === 'change' ? 'تغيير رمز PIN'
                  : 'تعطيل رمز PIN'
                }}
              </DialogTitle>
              <DialogDescription>
                {{
                  pinDialogMode === 'disable'
                    ? 'أدخل رمز PIN الحالي للتأكيد على تعطيله'
                    : pinDialogMode === 'change' && pinDialogStage === 'current'
                      ? 'أدخل رمز PIN الحالي أولاً'
                      : pinDialogStage === 'new'
                        ? 'أدخل رمز PIN الجديد المكوّن من 6 أرقام'
                        : 'أعد إدخال رمز PIN للتأكيد'
                }}
              </DialogDescription>
            </DialogHeader>

            <div
              v-if="pinError"
              class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
              role="alert"
            >
              {{ pinError }}
            </div>

            <div class="flex justify-center py-2" dir="ltr">
              <!-- Current PIN (for change/disable) -->
              <InputOTP
                v-if="pinDialogMode === 'disable' || (pinDialogMode === 'change' && pinDialogStage === 'current')"
                v-model="pinCurrent"
                :maxlength="6"
                :disabled="isPinSaving"
              >
                <InputOTPGroup>
                  <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                </InputOTPGroup>
              </InputOTP>

              <!-- New PIN -->
              <InputOTP
                v-else-if="pinDialogStage === 'new'"
                v-model="pinNew"
                :maxlength="6"
                :disabled="isPinSaving"
              >
                <InputOTPGroup>
                  <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                </InputOTPGroup>
              </InputOTP>

              <!-- Confirm PIN -->
              <InputOTP
                v-else
                v-model="pinConfirm"
                :maxlength="6"
                :disabled="isPinSaving"
                @complete="submitPinAction"
              >
                <InputOTPGroup>
                  <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                </InputOTPGroup>
              </InputOTP>
            </div>

            <DialogFooter class="flex-row-reverse gap-2 sm:flex-row-reverse">
              <Button
                type="button"
                :disabled="isPinSaving"
                @click="submitPinAction"
              >
                <KeyRound class="ms-1 h-4 w-4" />
                {{
                  pinDialogMode === 'disable' ? 'تعطيل'
                  : pinDialogStage !== 'confirm' ? 'التالي'
                  : 'حفظ'
                }}
              </Button>
              <Button type="button" variant="outline" :disabled="isPinSaving" @click="pinDialogOpen = false">
                إلغاء
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <div data-testid="recent-activity" class="border-t border-border pt-4">
          <h3 class="mb-3 flex items-center gap-2 font-semibold">
            <Activity class="h-4 w-4" />
            آخر نشاطي
          </h3>
          <div
            v-if="loading"
            class="space-y-2"
          >
            <div v-for="i in 3" :key="i" class="flex items-center gap-3 p-2.5">
              <Skeleton class="h-8 w-8 rounded-lg" />
              <div class="flex-1 space-y-1">
                <Skeleton class="h-4 w-32" />
                <Skeleton class="h-3 w-24" />
              </div>
            </div>
          </div>
          <div
            v-else-if="myActivity.length === 0"
            data-testid="activity-empty"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            لا يوجد نشاط مسجل بعد.
          </div>
          <ul
            v-else
            data-testid="recent-activity-list"
            class="space-y-1.5"
          >
            <li
              v-for="entry in myActivity"
              :key="entry.id"
              class="flex items-center gap-3 rounded-lg p-2.5 transition-colors hover:bg-muted/50"
            >
              <div class="grid h-8 w-8 place-items-center rounded-lg bg-muted">
                <Activity class="h-4 w-4" />
              </div>
              <div class="flex-1 text-sm">
                <div class="font-medium">
                  {{ entry.action }}
                </div>
                <div
                  v-if="entry.ref"
                  class="font-mono text-[11px] text-muted-foreground"
                >
                  {{ entry.ref }}
                </div>
              </div>
              <div class="text-xs text-muted-foreground">
                {{ new Date(entry.ts).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
              </div>
            </li>
          </ul>
        </div>
      </Card>
    </div>
  </div>
</template>
