<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Check,
  CheckCircle2,
  ChevronsUpDown,
  Columns2,
  Copy,
  KeyRound,
  Laptop,
  Loader2,
  Lock,
  Maximize2,
  Monitor,
  MonitorSmartphone,
  Moon,
  PanelLeft,
  PanelLeftClose,
  PanelLeftDashed,
  QrCode,
  Shield,
  ShieldAlert,
  ShieldCheck,
  Sliders,
  Smartphone,
  Square,
  Sun,
  Tablet,
  Trash2,
  UserRound,
  X,
} from 'lucide-vue-next'
import { renderSVG } from 'uqr'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp'
import { cn } from '@/lib/utils'
import { UserRole } from '@/types/enums'
import {
  useThemingStore,
  type DensityPreference,
  type LayoutMode,
  type RadiusPreference,
  type SidebarCollapsible,
  type SidebarVariant,
  type ThemeMode,
} from '@/stores/theming.store'
import { useSettingsStore } from '@/stores/settings.store'
import { useAuthStore } from '@/stores/auth.store'
import { useProfile } from '@/composables/useProfile'
import { useSavedAccounts } from '@/composables/useSavedAccounts'

definePageMeta({ middleware: ['auth'] })
useHead({ title: 'إعداداتي' })

const themingStore = useThemingStore()
const settingsStore = useSettingsStore()
const authStore = useAuthStore()
const route = useRoute()

const user = computed(() => authStore.user)

// ── Role labels ────────────────────────────────────────────────────────────────
const ROLE_LABELS: Record<string, string> = {
  [UserRole.DATA_ENTRY]: 'مدخل بيانات',
  [UserRole.BANK_REVIEWER]: 'مراجع بنكي',
  [UserRole.BANK_ADMIN]: 'مدير البنك',
  [UserRole.SWIFT_OFFICER]: 'مسؤول سويفت',
  [UserRole.SUPPORT_COMMITTEE]: 'لجنة الدعم',
  [UserRole.EXECUTIVE_MEMBER]: 'عضو تنفيذي',
  [UserRole.COMMITTEE_DIRECTOR]: 'مدير اللجنة',
  [UserRole.CBY_ADMIN]: 'مدير النظام',
}

// ── Nav items ──────────────────────────────────────────────────────────────────
const userTabs = [
  { value: 'profile', label: 'الملف الشخصي', icon: UserRound, dataTab: 'profile', testId: 'tab-profile' },
  { value: 'appearance', label: 'المظهر الشخصي', icon: Sliders, dataTab: 'appearance', testId: 'tab-appearance' },
  { value: 'notif', label: 'التنبيهات', icon: Bell, dataTab: 'notifications', testId: 'tab-notif' },
  { value: 'security', label: 'الأمان', icon: ShieldAlert, dataTab: 'security', testId: 'tab-security' },
] as const

type UserTab = (typeof userTabs)[number]['value']

const activeSection = computed<UserTab>(() => normalizeSection(route.query.section) ?? 'profile')

function normalizeSection(raw: unknown): UserTab | null {
  if (typeof raw !== 'string') return null
  return userTabs.some(t => t.value === raw) ? (raw as UserTab) : null
}

// ── Security (password / MFA / PIN) ───────────────────────────────────────────
const { toggleMfa: composableToggleMfa, setupTotp, verifyTotpSetup, disableTotp, disableTotpWithPassword, setPin: savePinOnServer, disablePin: disablePinOnServer, changePassword } = useProfile()
const { accounts: allAccounts, removeAccount, getPINStatus, setPINStatus } = useSavedAccounts()
const currentUserDevices = computed(() =>
  allAccounts.value.filter(a => a.email.trim().toLowerCase() === (user.value?.email ?? '').trim().toLowerCase()),
)

const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const passwordSuccess = ref(false)
const passwordError = ref<string | null>(null)
const passwordSaving = ref(false)

const mfaEnabled = ref(user.value?.mfa_enabled ?? false)
const totpEnabled = ref(user.value?.totp_enabled ?? false)

async function submitPasswordChange() {
  passwordError.value = null
  passwordSaving.value = true
  try {
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
      toast.success('تم تغيير كلمة المرور بنجاح')
    }
    else {
      passwordError.value = 'تعذر تغيير كلمة المرور. تحقق من كلمة المرور الحالية.'
    }
  }
  catch {
    passwordError.value = 'حدث خطأ غير متوقع. أعد المحاولة.'
  }
  finally {
    passwordSaving.value = false
  }
}

// ── PIN management ─────────────────────────────────────────────────────────────
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
  if ((contentRef as any).$el instanceof HTMLElement) return (contentRef as any).$el
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
  if (val.length === 6 && pinDialogStage.value === 'new') pinDialogStage.value = 'confirm'
})

watch(pinCurrent, (val) => {
  if (val.length === 6 && pinDialogStage.value === 'current') pinDialogStage.value = 'new'
})

watch(pinDialogStage, () => { focusPinInput() })

async function submitPinAction() {
  pinError.value = null
  if (pinDialogMode.value === 'disable') {
    if (pinCurrent.value.length < 6) { pinError.value = 'الرجاء إدخال رمز PIN الحالي'; return }
    isPinSaving.value = true
    try {
      const ok = await disablePinOnServer(pinCurrent.value)
      if (!ok) throw new Error('invalid-pin')
      setPINStatus(user.value?.email ?? '', false)
      hasPIN.value = false
      pinDialogOpen.value = false
      toast.success('تم تعطيل رمز PIN بنجاح')
    }
    catch { pinError.value = 'رمز PIN الحالي غير صحيح' }
    finally { isPinSaving.value = false }
    return
  }
  if (pinNew.value.length < 6 || pinConfirm.value.length < 6) {
    pinError.value = 'الرجاء إدخال رمز PIN المكوّن من 6 أرقام في كلا الحقلين'; return
  }
  if (pinNew.value !== pinConfirm.value) {
    pinError.value = 'رمز PIN غير متطابق. يرجى المحاولة مرة أخرى.'; pinConfirm.value = ''; return
  }
  isPinSaving.value = true
  try {
    const currentPin = pinDialogMode.value === 'change' ? pinCurrent.value : undefined
    const ok = await savePinOnServer(pinNew.value, currentPin)
    if (!ok) throw new Error('pin-save-failed')
    setPINStatus(user.value?.email ?? '', true)
    hasPIN.value = true
    pinDialogOpen.value = false
    toast.success(pinDialogMode.value === 'create' ? 'تم إنشاء رمز PIN بنجاح' : 'تم تغيير رمز PIN بنجاح')
  }
  catch { pinError.value = 'تعذر حفظ رمز PIN الآن. أعد المحاولة بعد قليل.' }
  finally { isPinSaving.value = false }
}

function removeTrustedDevice(id: string) {
  removeAccount(id)
  toast.success('تم إزالة الجهاز الموثوق')
}

// ── MFA / Authenticator setup ──────────────────────────────────────────────────
type MfaDialogStage = 'intro' | 'scan' | 'verify' | 'disable-verify' | 'disable-with-password'

const mfaDialogOpen = ref(false)
const mfaDialogStage = ref<MfaDialogStage>('intro')
const mfaVerifyCode = ref('')
const mfaSetupError = ref<string | null>(null)
const isMfaActionLoading = ref(false)
const liveMfaSecret = ref<string | null>(null)
const liveMfaUri = ref<string | null>(null)
const mfaDisablePassword = ref('')

const mfaQrSvg = computed(() => {
  if (!liveMfaUri.value) return null
  return renderSVG(liveMfaUri.value, { ecc: 'M' })
})

function openMfaSetup() {
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
  if (!mfaDisablePassword.value) { mfaSetupError.value = 'الرجاء إدخال كلمة المرور'; return }
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const ok = await disableTotpWithPassword(mfaDisablePassword.value)
    if (!ok) throw new Error('invalid')
    totpEnabled.value = false
    mfaEnabled.value = false
    mfaDialogOpen.value = false
    toast.success('تم تعطيل تطبيق المصادقة')
  }
  catch { mfaSetupError.value = 'كلمة المرور غير صحيحة.'; mfaDisablePassword.value = '' }
  finally { isMfaActionLoading.value = false }
}

function copyMfaSecret() {
  if (import.meta.client && liveMfaSecret.value)
    window.navigator.clipboard?.writeText(liveMfaSecret.value)
}

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
  catch { mfaSetupError.value = 'تعذر تحميل رمز QR الآن. أعد المحاولة بعد قليل.' }
  finally { isMfaActionLoading.value = false }
}

async function confirmMfaSetup() {
  if (mfaVerifyCode.value.length < 6) { mfaSetupError.value = 'الرجاء إدخال الرمز المكوّن من 6 أرقام'; return }
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const ok = await verifyTotpSetup(mfaVerifyCode.value)
    if (!ok) throw new Error('invalid')
    totpEnabled.value = true
    mfaEnabled.value = true
    mfaDialogOpen.value = false
    toast.success('تم تفعيل تطبيق المصادقة بنجاح')
  }
  catch { mfaSetupError.value = 'الرمز غير صحيح. تحقق من تطبيق المصادقة وحاول مجدداً.'; mfaVerifyCode.value = '' }
  finally { isMfaActionLoading.value = false }
}

async function confirmMfaDisable() {
  if (mfaVerifyCode.value.length < 6) { mfaSetupError.value = 'الرجاء إدخال رمز التحقق لتأكيد التعطيل'; return }
  isMfaActionLoading.value = true
  mfaSetupError.value = null
  try {
    const ok = await disableTotp(mfaVerifyCode.value)
    if (!ok) throw new Error('invalid')
    totpEnabled.value = false
    mfaEnabled.value = false
    mfaDialogOpen.value = false
    toast.success('تم تعطيل تطبيق المصادقة')
  }
  catch { mfaSetupError.value = 'رمز التحقق غير صحيح.'; mfaVerifyCode.value = '' }
  finally { isMfaActionLoading.value = false }
}

// ── Profile form ───────────────────────────────────────────────────────────────
const profileForm = reactive({ name: '', phone: '' })
const profileSaving = ref(false)

onMounted(async () => {
  profileForm.name = authStore.user?.name ?? ''
  profileForm.phone = authStore.user?.phone ?? ''
  await themingStore.loadSettings()
  settingsStore.markSectionClean('userProfile', undefined, profilePayload.value)
  settingsStore.markSectionClean('userAppearance', undefined, appearancePayload.value)
  settingsStore.markSectionClean('userNotifications', undefined, notificationsPayload.value)
})

async function saveProfile() {
  profileSaving.value = true
  const ok = await settingsStore.saveSection('userProfile', profilePayload.value)
  profileSaving.value = false
  if (ok) toast.success('تم حفظ الملف الشخصي بنجاح')
  else toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
}

// ── Appearance options ─────────────────────────────────────────────────────────
const fontPickerOpen = ref(false)

const themeOptions: Array<{ value: ThemeMode, label: string, description: string, icon: typeof Sun }> = [
  { value: 'dark', label: 'داكن', description: 'واجهة منخفضة السطوع', icon: Moon },
  { value: 'light', label: 'فاتح', description: 'واجهة عالية الوضوح', icon: Sun },
  { value: 'system', label: 'النظام', description: 'حسب إعداد الجهاز', icon: Monitor },
]

const layoutOptions: Array<{ value: LayoutMode, label: string, description: string, icon: typeof Maximize2 }> = [
  { value: 'full', label: 'كامل العرض', description: 'يستخدم كامل مساحة الشاشة', icon: Maximize2 },
  { value: 'boxed', label: 'محدود العرض', description: 'محتوى مركزي للقراءة الهادئة', icon: Square },
]

const sidebarVariantOptions: Array<{ value: SidebarVariant, label: string, description: string, icon: typeof PanelLeft }> = [
  { value: 'sidebar', label: 'ثابت', description: 'شريط جانبي أرضي مدمج', icon: PanelLeft },
  { value: 'floating', label: 'عائم', description: 'شريط مرتفع بظل خفيف', icon: PanelLeftDashed },
  { value: 'inset', label: 'مضمّن', description: 'محتوى غارق داخل الصفحة', icon: Columns2 },
]

const sidebarCollapsibleOptions: Array<{ value: SidebarCollapsible, label: string, description: string, icon: typeof PanelLeft }> = [
  { value: 'offcanvas', label: 'خارج الشاشة', description: 'يختفي الشريط الجانبي تماماً عند الطي', icon: PanelLeftClose },
  { value: 'icon', label: 'أيقونات فقط', description: 'يتقلص إلى أيقونات مع الحفاظ على المساحة', icon: PanelLeft },
  { value: 'none', label: 'ثابت دائماً', description: 'لا يمكن طي الشريط الجانبي', icon: PanelLeft },
]

const radiusOptions: Array<{ value: RadiusPreference, label: string, previewRadius: string }> = [
  { value: 'none', label: 'بدون', previewRadius: '0px' },
  { value: 'sm', label: 'صغير', previewRadius: '0.25rem' },
  { value: 'md', label: 'متوسط', previewRadius: '0.5rem' },
  { value: 'lg', label: 'كبير', previewRadius: '0.75rem' },
  { value: 'xl', label: 'كبير جداً', previewRadius: '1rem' },
]

const densityOptions: Array<{ value: DensityPreference, label: string, description: string }> = [
  { value: 'comfortable', label: 'مريح', description: 'تباعد واسع بين العناصر' },
  { value: 'compact', label: 'مضغوط', description: 'عرض أكثر معلومات في مساحة أقل' },
]

// ── Personal notification prefs ────────────────────────────────────────────────
const personalNotifications = ref([
  { id: 'status_change', label: 'تغييرات حالة طلباتي', description: 'عند تحديث حالة أي طلب أشارك فيه', enabled: true },
  { id: 'task_assigned', label: 'تكليف مهام جديدة', description: 'عند إسناد مهمة مراجعة أو تصويت إليّ', enabled: true },
  { id: 'comments', label: 'التعليقات والملاحظات', description: 'عند إضافة تعليق على طلب مرتبط بي', enabled: true },
  { id: 'deadlines', label: 'تذكيرات المواعيد النهائية', description: 'قبل 24 ساعة من انتهاء مهلة المراجعة', enabled: false },
  { id: 'session_open', label: 'فتح جلسات التصويت', description: 'عند فتح جلسة تصويت جديدة', enabled: true },
  { id: 'reports', label: 'التقارير الدورية', description: 'ملخص أسبوعي لنشاط العمل', enabled: false },
])

const profilePayload = computed(() => ({
  name: profileForm.name.trim(),
  phone: profileForm.phone.trim(),
}))

const appearancePayload = computed(() => ({
  mode: themingStore.mode,
  font: themingStore.font,
  layout: themingStore.layout,
  sidebarVariant: themingStore.sidebarVariant,
  sidebarCollapsible: themingStore.sidebarCollapsible,
  radius: themingStore.radius,
  density: themingStore.density,
  reducedMotion: themingStore.reducedMotion,
}))

const notificationsPayload = computed(() => ({
  settings: personalNotifications.value.map(item => ({
    id: item.id,
    enabled: item.enabled,
  })),
}))

watch(fontPickerOpen, (opened) => {
  if (opened && themingStore.fontSource === 'fallback' && !themingStore.fontsLoading)
    themingStore.loadGoogleFonts()
})

watch(
  profilePayload,
  value => settingsStore.trackSectionState('userProfile', value),
  { deep: true },
)

watch(
  appearancePayload,
  value => settingsStore.trackSectionState('userAppearance', value),
  { deep: true },
)

watch(
  notificationsPayload,
  value => settingsStore.trackSectionState('userNotifications', value),
  { deep: true },
)

// ── Handlers ───────────────────────────────────────────────────────────────────
function selectTheme(mode: ThemeMode, event: MouseEvent) {
  themingStore.setMode(mode, event)
}

function selectFont(fontValue: string) {
  themingStore.setFont(fontValue)
  fontPickerOpen.value = false
}

async function saveAppearance() {
  const ok = await settingsStore.saveSection('userAppearance', appearancePayload.value)
  if (ok) {
    toast.success('تم حفظ إعدادات المظهر الشخصي بنجاح')
  }
  else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
  }
}

function savePersonalNotifications() {
  toast.promise(
    settingsStore.saveSection('userNotifications', notificationsPayload.value),
    {
      loading: 'جاري حفظ إعدادات التنبيهات...',
      success: 'تم حفظ إعدادات التنبيهات بنجاح',
      error: () => settingsStore.error || 'فشل حفظ الإعدادات',
    },
  )
}
</script>

<template>
  <div>
    <PageHeader
      title="إعداداتي"
      subtitle="تفضيلاتك الشخصية، وتؤثر على تجربتك فقط ولا تمس بقية المستخدمين"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'إعداداتي' }]"
    />

    <div  class="flex flex-col gap-6 lg:flex-row">
      <!-- ── Desktop: left sidebar nav ───────────────────────────────────── -->
      <aside class="hidden lg:block lg:w-56 lg:shrink-0">
        <nav class="flex flex-col gap-1">
          <NuxtLink
            v-for="tab in userTabs"
            :key="tab.value"
            :to="{ path: '/settings', query: { section: tab.value } }"
            :data-tab="tab.dataTab"
            :data-testid="tab.testId"
            :class="cn(
              'flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition-colors',
              activeSection === tab.value
                ? 'bg-muted font-medium text-foreground'
                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
            )"
            :aria-current="activeSection === tab.value ? 'page' : undefined"
          >
            <component :is="tab.icon" class="size-4 shrink-0" />
            {{ tab.label }}
          </NuxtLink>
        </nav>
      </aside>

      <div class="min-w-0 flex-1">
        <!-- Mobile / tablet: horizontal scrollable nav -->
        <div class="mb-6 flex gap-1 overflow-x-auto pb-1 lg:hidden">
          <NuxtLink
            v-for="tab in userTabs"
            :key="tab.value"
            :to="{ path: '/settings', query: { section: tab.value } }"
            :class="cn(
              'flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm transition-colors',
              activeSection === tab.value
                ? 'bg-muted font-medium text-foreground'
                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
            )"
          >
            <component :is="tab.icon" class="size-4 shrink-0" />
            {{ tab.label }}
          </NuxtLink>
        </div>

        <div class="space-y-6">

          <!-- ── Profile ──────────────────────────────────────────────────── -->
          <section v-if="activeSection === 'profile'" data-panel="profile" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">الملف الشخصي</h3>
              <p class="text-sm text-muted-foreground">معلوماتك الشخصية المرتبطة بحسابك في المنصة.</p>
            </div>
            <Separator />
            <div class="max-w-md space-y-4">
              <div class="space-y-2">
                <Label for="profile-name">الاسم الكامل</Label>
                <Input id="profile-name" v-model="profileForm.name" />
              </div>
              <div class="space-y-2">
                <Label for="profile-email">البريد الإلكتروني</Label>
                <Input id="profile-email" :model-value="user?.email ?? ''"  disabled />
                <p class="text-xs text-muted-foreground">لا يمكن تغيير البريد الإلكتروني من هنا.</p>
              </div>
              <div class="space-y-2">
                <Label for="profile-phone">رقم الهاتف</Label>
                <Input id="profile-phone" v-model="profileForm.phone"  placeholder="+9677..." />
              </div>
              <div class="space-y-2">
                <Label>الجهة</Label>
                <Input :model-value="user?.bank_name_ar ?? ''" disabled />
              </div>
              <div class="space-y-2">
                <Label>الدور الوظيفي</Label>
                <Input :model-value="ROLE_LABELS[user?.role ?? ''] ?? (user?.role ?? '')" disabled />
              </div>
              <div class="flex justify-end">
                <Button :disabled="!settingsStore.isSectionDirty('userProfile') || profileSaving || settingsStore.saving" @click="saveProfile">
                  <Loader2 v-if="profileSaving" class="ms-2 h-4 w-4 animate-spin" />
                  حفظ التغييرات
                </Button>
              </div>
            </div>
          </section>

          <!-- ── Appearance ───────────────────────────────────────────────── -->
          <section v-if="activeSection === 'appearance'" data-panel="appearance" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">المظهر الشخصي</h3>
              <p class="text-sm text-muted-foreground">تخصيص واجهة المنصة لتناسب تفضيلاتك — لا تؤثر على المستخدمين الآخرين.</p>
            </div>
            <Separator />

            <!-- Sub-section: Theme mode -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">وضع الثيم</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">اختر وضع عرض الواجهة المناسب لبيئة عملك.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-3">
                <button
                  v-for="option in themeOptions"
                  :key="option.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.mode === option.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="selectTheme(option.value, $event)"
                >
                  <div class="relative h-24 w-full" :class="option.value === 'dark' ? 'bg-[#111827]' : option.value === 'light' ? 'bg-[#f3f4f6]' : 'bg-[#d1d5db]'">
                    <template v-if="option.value === 'light'">
                      <div class="absolute inset-y-0 start-0 w-10 bg-[#ffffff] border-e border-[#e5e7eb] flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-[#e5e7eb]" />
                        <div class="h-1.5 w-3/4 rounded bg-[#0066cc]/40" />
                        <div class="h-1.5 w-full rounded bg-[#e5e7eb]" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-[#ffffff] border border-[#e5e7eb] p-2">
                        <div class="h-3 rounded bg-[#f1f3f5]" />
                        <div class="mt-2 h-2 rounded bg-[#f1f3f5]" />
                        <div class="mt-1.5 h-2 rounded bg-[#f1f3f5]" />
                      </div>
                    </template>
                    <template v-else-if="option.value === 'dark'">
                      <div class="absolute inset-y-0 start-0 w-10 bg-[#0f1218] border-e border-white/10 flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-[#343a44]" />
                        <div class="h-1.5 w-3/4 rounded bg-[#0066cc]/70" />
                        <div class="h-1.5 w-full rounded bg-[#343a44]" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-[#151820] border border-white/10 p-2">
                        <div class="h-3 rounded bg-[#2a2e3a]" />
                        <div class="mt-2 h-2 rounded bg-[#2a2e3a]" />
                        <div class="mt-1.5 h-2 rounded bg-[#2a2e3a]" />
                      </div>
                    </template>
                    <template v-else>
                      <!-- system: left half light, right half dark -->
                      <div class="absolute inset-y-0 start-0 w-10 overflow-hidden flex flex-col gap-1 p-1.5" style="background: linear-gradient(to left, #0f1218 50%, #ffffff 50%); border-inline-end: 1px solid #9ca3af;">
                        <div class="h-1.5 w-full rounded" style="background: linear-gradient(to left, #343a44 50%, #e5e7eb 50%)" />
                        <div class="h-1.5 w-3/4 rounded bg-[#0066cc]/50" />
                        <div class="h-1.5 w-full rounded" style="background: linear-gradient(to left, #343a44 50%, #e5e7eb 50%)" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 overflow-hidden rounded border border-[#9ca3af]">
                        <div class="absolute inset-y-0 start-0 w-1/2 bg-[#ffffff]" />
                        <div class="absolute inset-y-0 end-0 w-1/2 bg-[#151820]" />
                        <div class="relative p-2">
                          <div class="h-3 rounded" style="background: linear-gradient(to left, #2a2e3a 50%, #f1f3f5 50%)" />
                          <div class="mt-2 h-2 rounded" style="background: linear-gradient(to left, #2a2e3a 50%, #f1f3f5 50%)" />
                          <div class="mt-1.5 h-2 rounded" style="background: linear-gradient(to left, #2a2e3a 50%, #f1f3f5 50%)" />
                        </div>
                      </div>
                    </template>
                    <span
                      v-if="themingStore.mode === option.value"
                      class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                      <Check class="h-3 w-3" />
                    </span>
                  </div>
                  <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <component :is="option.icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                      <p class="text-sm font-medium">{{ option.label }}</p>
                      <p class="truncate text-xs text-muted-foreground">{{ option.description }}</p>
                    </div>
                  </div>
                </button>
              </div>
            </section>

            <Separator />

            <!-- Sub-section: Font -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">الخط</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">الخط المستخدم في واجهتك الشخصية.</p>
              </div>
              <Popover v-model:open="fontPickerOpen">
                <PopoverTrigger as-child>
                  <Button variant="outline" role="combobox" :aria-expanded="fontPickerOpen" class="h-10 w-full max-w-sm justify-between">
                    <span class="truncate">{{ themingStore.selectedFontLabel || 'اختر خطاً...' }}</span>
                    <ChevronsUpDown class="h-4 w-4 opacity-50" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[var(--reka-popover-trigger-width)] p-0">
                  <Command>
                    <CommandInput class="h-9" placeholder="ابحث عن خط..." />
                    <CommandList>
                      <CommandEmpty>لا توجد نتائج.</CommandEmpty>
                      <CommandGroup heading="الخطوط الأساسية">
                        <CommandItem
                          v-for="font in themingStore.pinnedFonts"
                          :key="font.value"
                          :value="font.value"
                          @select="(ev) => selectFont(ev.detail.value as string)"
                        >
                          <div class="flex min-w-0 flex-col">
                            <span class="truncate">{{ font.label }}</span>
                            <span class="truncate text-xs text-muted-foreground">{{ font.category }}</span>
                          </div>
                          <Check :class="cn('ms-auto h-4 w-4', themingStore.font === font.value ? 'opacity-100' : 'opacity-0')" />
                        </CommandItem>
                      </CommandGroup>
                      <CommandSeparator />
                      <CommandGroup heading="جميع الخطوط">
                        <div v-if="themingStore.fontsLoading" class="flex items-center gap-2 px-2 py-3 text-sm text-muted-foreground">
                          <Loader2 class="h-4 w-4 animate-spin" />جاري تحميل القائمة...
                        </div>
                        <template v-else>
                          <CommandItem
                            v-for="font in themingStore.searchableFonts"
                            :key="font.value"
                            :value="font.value"
                            @select="(ev) => selectFont(ev.detail.value as string)"
                          >
                            <div class="flex min-w-0 flex-col">
                              <span class="truncate">{{ font.label }}</span>
                              <span class="truncate text-xs text-muted-foreground">{{ font.category }}</span>
                            </div>
                            <Check :class="cn('ms-auto h-4 w-4', themingStore.font === font.value ? 'opacity-100' : 'opacity-0')" />
                          </CommandItem>
                        </template>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
            </section>

            <Separator />

            <!-- Sub-section: Layout -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">تخطيط المحتوى</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">كيفية توزيع المحتوى الرئيسي على الشاشة.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  v-for="option in layoutOptions"
                  :key="option.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.layout === option.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="themingStore.setLayout(option.value)"
                >
                  <div class="relative h-24 w-full bg-muted/30">
                    <template v-if="option.value === 'full'">
                      <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-card border border-border p-2">
                        <div class="h-full rounded bg-muted/60" />
                      </div>
                    </template>
                    <template v-else>
                      <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-card border border-border p-2 mx-6" />
                    </template>
                    <span
                      v-if="themingStore.layout === option.value"
                      class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                      <Check class="h-3 w-3" />
                    </span>
                  </div>
                  <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <component :is="option.icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                      <p class="text-sm font-medium">{{ option.label }}</p>
                      <p class="truncate text-xs text-muted-foreground">{{ option.description }}</p>
                    </div>
                  </div>
                </button>
              </div>
            </section>

            <Separator />

            <!-- Sub-section: Sidebar variant -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">نمط الشريط الجانبي</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">كيف يظهر الشريط الجانبي بالنسبة لمنطقة المحتوى.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-3">
                <button
                  v-for="option in sidebarVariantOptions"
                  :key="option.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.sidebarVariant === option.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="themingStore.setSidebarVariant(option.value)"
                >
                  <div class="relative h-24 w-full bg-muted/30">
                    <template v-if="option.value === 'sidebar'">
                      <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-card border border-border" />
                    </template>
                    <template v-else-if="option.value === 'floating'">
                      <div class="absolute inset-y-1.5 start-1.5 w-10 rounded-md bg-card shadow-md flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-14 end-2 rounded bg-card border border-border" />
                    </template>
                    <template v-else>
                      <div class="absolute inset-0 bg-card border-e border-b border-border/50">
                        <div class="absolute inset-y-0 start-0 w-10 flex flex-col gap-1 p-1.5 border-e border-border/30">
                          <div class="h-1.5 w-full rounded bg-muted" />
                          <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        </div>
                        <div class="absolute inset-2 start-12 rounded-md bg-muted/40 border border-border/30" />
                      </div>
                    </template>
                    <span
                      v-if="themingStore.sidebarVariant === option.value"
                      class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                      <Check class="h-3 w-3" />
                    </span>
                  </div>
                  <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <component :is="option.icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                      <p class="text-sm font-medium">{{ option.label }}</p>
                      <p class="truncate text-xs text-muted-foreground">{{ option.description }}</p>
                    </div>
                  </div>
                </button>
              </div>
            </section>

            <Separator />

            <!-- Sub-section: Sidebar collapsible -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">سلوك طي الشريط الجانبي</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">كيف يتصرف الشريط عند الضغط على زر الطي.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-3">
                <button
                  v-for="option in sidebarCollapsibleOptions"
                  :key="option.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.sidebarCollapsible === option.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="themingStore.setSidebarCollapsible(option.value)"
                >
                  <div class="relative h-24 w-full bg-muted/30">
                    <template v-if="option.value === 'offcanvas'">
                      <div class="absolute inset-2 rounded bg-card border border-border" />
                      <div class="absolute top-3 start-3 flex size-5 items-center justify-center rounded-sm bg-primary/10">
                        <PanelLeftClose class="h-3 w-3 text-primary/60" />
                      </div>
                    </template>
                    <template v-else-if="option.value === 'icon'">
                      <div class="absolute inset-y-0 start-0 w-6 bg-card border-e border-border flex flex-col items-center gap-1 py-2">
                        <div class="size-2 rounded-sm bg-primary/40" />
                        <div class="size-2 rounded-sm bg-muted" />
                        <div class="size-2 rounded-sm bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-8 end-2 rounded bg-card border border-border" />
                    </template>
                    <template v-else>
                      <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-card border border-border" />
                      <div class="absolute top-2 start-10 -ms-0.5 h-4 w-px bg-border" />
                    </template>
                    <span
                      v-if="themingStore.sidebarCollapsible === option.value"
                      class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                      <Check class="h-3 w-3" />
                    </span>
                  </div>
                  <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <component :is="option.icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                      <p class="text-sm font-medium">{{ option.label }}</p>
                      <p class="truncate text-xs text-muted-foreground">{{ option.description }}</p>
                    </div>
                  </div>
                </button>
              </div>
            </section>

            <Separator />

            <!-- Sub-section: Radius -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">نصف قطر الحواف</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">درجة استدارة حواف المكونات كالأزرار والبطاقات.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <button
                  v-for="opt in radiusOptions"
                  :key="opt.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.radius === opt.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="themingStore.setRadius(opt.value)"
                >
                  <div class="relative flex h-24 w-full items-center justify-center bg-muted/30">
                    <div class="h-10 w-14 border border-border bg-card" :style="{ borderRadius: opt.previewRadius }" />
                    <span
                      v-if="themingStore.radius === opt.value"
                      class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                      <Check class="h-3 w-3" />
                    </span>
                  </div>
                  <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <span class="text-sm font-medium">{{ opt.label }}</span>
                  </div>
                </button>
              </div>
            </section>

            <Separator />

            <!-- Sub-section: Density -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">كثافة العرض</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">التباعد بين عناصر الواجهة.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  v-for="opt in densityOptions"
                  :key="opt.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.density === opt.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="themingStore.setDensity(opt.value)"
                >
                  <div class="relative h-24 w-full bg-muted/30">
                    <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                      <div class="h-1.5 w-full rounded bg-muted" />
                      <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                      <div class="h-1.5 w-full rounded bg-muted" />
                    </div>
                    <div class="absolute inset-y-2 start-12 end-2 rounded bg-card border border-border p-2">
                      <div v-if="opt.value === 'comfortable'" class="flex flex-col gap-2">
                        <div class="h-2 rounded bg-muted/80" />
                        <div class="h-2 rounded bg-muted/80" />
                        <div class="h-2 rounded bg-muted/80" />
                      </div>
                      <div v-else class="flex flex-col gap-1">
                        <div class="h-2 rounded bg-muted/80" />
                        <div class="h-2 rounded bg-muted/80" />
                        <div class="h-2 rounded bg-muted/80" />
                        <div class="h-2 rounded bg-muted/80" />
                      </div>
                    </div>
                    <span
                      v-if="themingStore.density === opt.value"
                      class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                      <Check class="h-3 w-3" />
                    </span>
                  </div>
                  <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <div class="min-w-0">
                      <p class="text-sm font-medium">{{ opt.label }}</p>
                      <p class="truncate text-xs text-muted-foreground">{{ opt.description }}</p>
                    </div>
                  </div>
                </button>
              </div>
            </section>

            <div class="flex justify-end">
              <Button
                data-testid="save-appearance"
                :disabled="!settingsStore.isSectionDirty('userAppearance') || settingsStore.saving"
                @click="saveAppearance"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ المظهر الشخصي
              </Button>
            </div>
          </section>

          <!-- ── Security ───────────────────────────────────────────────────── -->
          <section v-if="activeSection === 'security'" data-panel="security" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">الأمان</h3>
              <p class="text-sm text-muted-foreground">إدارة كلمة المرور، المصادقة الثنائية، ورمز PIN.</p>
            </div>
            <Separator />

            <!-- Password change -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">تغيير كلمة المرور</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">يُنصح بتغيير كلمة المرور بانتظام لحماية حسابك.</p>
              </div>
              <div
                v-if="passwordSuccess"
                class="max-w-md rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5 p-3 text-sm text-[var(--severity-green)]"
              >
                تم تغيير كلمة المرور بنجاح
              </div>
              <div
                v-if="passwordError"
                class="max-w-md rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                role="alert"
              >
                {{ passwordError }}
              </div>
              <form class="max-w-md space-y-3" @submit.prevent="submitPasswordChange">
                <div class="space-y-1.5">
                  <Label for="sec-current-password">كلمة المرور الحالية</Label>
                  <Input id="sec-current-password" v-model="passwordForm.current_password" type="password" />
                </div>
                <div class="space-y-1.5">
                  <Label for="sec-new-password">كلمة المرور الجديدة</Label>
                  <Input id="sec-new-password" v-model="passwordForm.password" type="password" />
                </div>
                <div class="space-y-1.5">
                  <Label for="sec-confirm-password">تأكيد كلمة المرور</Label>
                  <Input id="sec-confirm-password" v-model="passwordForm.password_confirmation" type="password" />
                </div>
                <Button type="submit" variant="outline" size="sm" :disabled="passwordSaving">
                  <Loader2 v-if="passwordSaving" class="ms-2 h-4 w-4 animate-spin" />
                  <KeyRound v-else class="ms-2 h-4 w-4" />
                  حفظ كلمة المرور
                </Button>
              </form>
            </section>

            <Separator />

            <!-- MFA / Authenticator -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium flex items-center gap-2">
                  <Shield class="h-4 w-4" />
                  تطبيق المصادقة الثنائية
                </h4>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  يُستخدم تطبيق Microsoft Authenticator أو Google Authenticator لتوليد رموز تحقق مؤقتة.
                </p>
              </div>
              <div class="max-w-md">
                <div v-if="totpEnabled || mfaEnabled" class="flex items-center justify-between rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5 p-3">
                  <div class="flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4 text-[var(--severity-green)] shrink-0" />
                    <span class="text-sm font-medium text-[var(--severity-green)]">تطبيق المصادقة مفعّل</span>
                  </div>
                  <Button type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive text-xs" @click="openMfaDisable">
                    <X class="ms-1 h-3.5 w-3.5" />
                    تعطيل
                  </Button>
                </div>
                <div v-else class="rounded-lg border border-border bg-muted/20 p-3">
                  <div class="flex items-center gap-2 mb-2">
                    <Lock class="h-4 w-4 text-muted-foreground shrink-0" />
                    <span class="text-sm text-muted-foreground">تطبيق المصادقة غير مفعّل</span>
                  </div>
                  <Button type="button" variant="outline" size="sm" @click="openMfaSetup">
                    <QrCode class="ms-1 h-4 w-4" />
                    إعداد تطبيق المصادقة
                  </Button>
                </div>
              </div>
            </section>

            <Separator />

            <!-- PIN -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium flex items-center gap-2">
                  <KeyRound class="h-4 w-4" />
                  رمز PIN للدخول السريع
                </h4>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  رمز مكوّن من 6 أرقام يتيح لك تسجيل الدخول بسرعة من الأجهزة الموثوقة.
                </p>
              </div>
              <div class="max-w-md">
                <div v-if="hasPIN" class="flex items-center gap-2 mb-3">
                  <CheckCircle2 class="h-4 w-4 text-[var(--severity-green)] shrink-0" />
                  <span class="text-sm text-[var(--severity-green)]">رمز PIN مفعّل</span>
                </div>
                <div v-else class="flex items-center gap-2 mb-3">
                  <Lock class="h-4 w-4 text-muted-foreground shrink-0" />
                  <span class="text-sm text-muted-foreground">لم يتم إنشاء رمز PIN بعد</span>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button v-if="!hasPIN" type="button" variant="outline" size="sm" @click="openPinDialog('create')">
                    <KeyRound class="ms-1 h-4 w-4" />
                    إنشاء رمز PIN
                  </Button>
                  <template v-else>
                    <Button type="button" variant="outline" size="sm" @click="openPinDialog('change')">
                      <KeyRound class="ms-1 h-4 w-4" />
                      تغيير رمز PIN
                    </Button>
                    <Button type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="openPinDialog('disable')">
                      <X class="ms-1 h-4 w-4" />
                      تعطيل رمز PIN
                    </Button>
                  </template>
                </div>
              </div>
            </section>

            <Separator />

            <!-- Trusted Devices -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium flex items-center gap-2">
                  <MonitorSmartphone class="h-4 w-4" />
                  الأجهزة الموثوقة
                </h4>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  الأجهزة التي حفظت بيانات دخولك للوصول السريع برمز PIN.
                </p>
              </div>
              <div v-if="currentUserDevices.length === 0" class="text-sm text-muted-foreground py-2">
                لا توجد أجهزة موثوقة مسجّلة حتى الآن.
              </div>
              <ul v-else class="max-w-md space-y-2">
                <li v-for="device in currentUserDevices" :key="device.id" class="rounded-lg border border-border p-3">
                  <div class="flex items-start gap-3">
                    <div class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-muted">
                      <Smartphone v-if="device.deviceInfo?.deviceType === 'mobile'" class="h-4 w-4 text-muted-foreground" />
                      <Tablet v-else-if="device.deviceInfo?.deviceType === 'tablet'" class="h-4 w-4 text-muted-foreground" />
                      <Laptop v-else class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="flex-1 min-w-0 space-y-1">
                      <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                        <span v-if="device.deviceInfo?.browser" class="flex items-center gap-1 text-xs text-muted-foreground">
                          <MonitorSmartphone class="h-3 w-3" />
                          {{ device.deviceInfo.browser }}
                        </span>
                        <span v-if="device.deviceInfo?.os" class="text-xs text-muted-foreground">{{ device.deviceInfo.os }}</span>
                      </div>
                      <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                        <span v-if="device.deviceInfo?.lastLoginAt" class="text-[10px] text-muted-foreground/70">
                          آخر دخول: {{ new Date(device.deviceInfo.lastLoginAt).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                        </span>
                        <span class="text-[10px] text-muted-foreground/70">
                          الثقة منذ: {{ new Date(device.trustedAt).toLocaleDateString('ar-EG', { dateStyle: 'medium' }) }}
                        </span>
                      </div>
                    </div>
                    <Button type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive shrink-0" :aria-label="`إزالة جهاز ${device.name}`" @click="removeTrustedDevice(device.id)">
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </li>
              </ul>
            </section>

            <!-- MFA Dialog -->
            <Dialog v-model:open="mfaDialogOpen">
              <DialogContent class="max-w-sm">
                <template v-if="mfaDialogStage === 'disable-verify'">
                  <DialogHeader>
                    <DialogTitle>تعطيل تطبيق المصادقة</DialogTitle>
                    <DialogDescription>أدخل الرمز الحالي من تطبيق المصادقة للتأكيد</DialogDescription>
                  </DialogHeader>
                  <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">{{ mfaSetupError }}</div>
                  <div class="flex justify-center py-2" dir="ltr">
                    <InputOTP v-model="mfaVerifyCode" :maxlength="6" :disabled="isMfaActionLoading" @complete="confirmMfaDisable">
                      <InputOTPGroup>
                        <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                      </InputOTPGroup>
                    </InputOTP>
                  </div>
                  <DialogFooter class="flex-col gap-2 sm:flex-col">
                    <div class="flex gap-2">
                      <Button type="button" variant="destructive" :disabled="isMfaActionLoading || mfaVerifyCode.length < 6" @click="confirmMfaDisable">
                        <X class="ms-1 h-4 w-4" />تعطيل
                      </Button>
                      <Button type="button" variant="outline" :disabled="isMfaActionLoading" @click="mfaDialogOpen = false">إلغاء</Button>
                    </div>
                    <Button type="button" variant="ghost" size="sm" class="text-muted-foreground text-xs" @click="() => { mfaSetupError = null; mfaDialogStage = 'disable-with-password' }">
                      لا أملك وصولاً إلى تطبيق المصادقة
                    </Button>
                  </DialogFooter>
                </template>

                <template v-else-if="mfaDialogStage === 'disable-with-password'">
                  <DialogHeader>
                    <DialogTitle>تعطيل المصادقة بكلمة المرور</DialogTitle>
                    <DialogDescription>أدخل كلمة المرور الخاصة بحسابك لتعطيل تطبيق المصادقة</DialogDescription>
                  </DialogHeader>
                  <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">{{ mfaSetupError }}</div>
                  <div class="space-y-2 py-2">
                    <Label for="mfa-disable-pwd">كلمة المرور</Label>
                    <Input id="mfa-disable-pwd" v-model="mfaDisablePassword" type="password" :disabled="isMfaActionLoading" placeholder="أدخل كلمة مرورك" autofocus @keydown.enter="confirmMfaDisableWithPassword" />
                  </div>
                  <DialogFooter class="gap-2 sm:flex-row-reverse">
                    <Button type="button" variant="destructive" :disabled="isMfaActionLoading || !mfaDisablePassword" @click="confirmMfaDisableWithPassword">
                      <X class="ms-1 h-4 w-4" />تعطيل
                    </Button>
                    <Button type="button" variant="outline" :disabled="isMfaActionLoading" @click="() => { mfaSetupError = null; mfaDialogStage = 'disable-verify' }">رجوع</Button>
                  </DialogFooter>
                </template>

                <template v-else-if="mfaDialogStage === 'intro'">
                  <DialogHeader>
                    <DialogTitle>إعداد تطبيق المصادقة</DialogTitle>
                    <DialogDescription>سيضاف تطبيق مصادقة لحماية حسابك برمز تحقق مؤقت يتغير كل 30 ثانية</DialogDescription>
                  </DialogHeader>
                  <div class="space-y-3 py-2 text-sm text-muted-foreground">
                    <p>الخطوات:</p>
                    <ol class="list-decimal list-inside space-y-1 text-sm">
                      <li>افتح تطبيق <strong>Microsoft Authenticator</strong> أو <strong>Google Authenticator</strong></li>
                      <li>امسح رمز QR أو أدخل المفتاح يدوياً</li>
                      <li>أدخل الرمز المكوّن من 6 أرقام للتأكيد</li>
                    </ol>
                  </div>
                  <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">{{ mfaSetupError }}</div>
                  <DialogFooter class="gap-2 sm:flex-row-reverse">
                    <Button type="button" :disabled="isMfaActionLoading" @click="loadTotpSetup">
                      <span v-if="isMfaActionLoading" class="flex items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />جارٍ التحميل…
                      </span>
                      <span v-else>التالي</span>
                    </Button>
                    <Button type="button" variant="outline" @click="mfaDialogOpen = false">إلغاء</Button>
                  </DialogFooter>
                </template>

                <template v-else-if="mfaDialogStage === 'scan'">
                  <DialogHeader>
                    <DialogTitle>امسح رمز QR</DialogTitle>
                    <DialogDescription>امسح الرمز بتطبيق المصادقة أو أدخل المفتاح السري يدوياً</DialogDescription>
                  </DialogHeader>
                  <div class="flex flex-col items-center gap-3 py-2">
                    <div class="rounded-xl border-2 border-border bg-white p-2 shadow-sm" style="line-height:0">
                      <!-- eslint-disable-next-line vue/no-v-html -->
                      <div v-if="mfaQrSvg" v-html="mfaQrSvg" class="w-40 h-40 [&>svg]:w-full [&>svg]:h-full [&>svg]:block" />
                      <div v-else class="w-40 h-40 flex items-center justify-center text-muted-foreground text-xs">جارٍ تحميل الرمز…</div>
                    </div>
                    <div class="w-full rounded-lg border border-border bg-muted/30 px-3 py-2">
                      <p class="mb-1 text-[10px] text-muted-foreground">المفتاح السري (للإدخال اليدوي)</p>
                      <div class="flex items-center justify-between gap-2">
                        <code class="text-xs font-mono tracking-widest">{{ liveMfaSecret ?? '…' }}</code>
                        <Button type="button" variant="ghost" size="sm" class="h-7 px-2" :disabled="!liveMfaSecret" @click="copyMfaSecret">
                          <Copy class="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </div>
                  </div>
                  <DialogFooter class="gap-2 sm:flex-row-reverse">
                    <Button type="button" @click="mfaDialogStage = 'verify'">متابعة إدخال رمز التحقق</Button>
                    <Button type="button" variant="outline" @click="mfaDialogStage = 'intro'">رجوع</Button>
                  </DialogFooter>
                </template>

                <template v-else-if="mfaDialogStage === 'verify'">
                  <DialogHeader>
                    <DialogTitle>تأكيد الإعداد</DialogTitle>
                    <DialogDescription>أدخل الرمز المكوّن من 6 أرقام الظاهر في تطبيق المصادقة</DialogDescription>
                  </DialogHeader>
                  <div v-if="mfaSetupError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">{{ mfaSetupError }}</div>
                  <div class="flex justify-center py-2" dir="ltr">
                    <InputOTP v-model="mfaVerifyCode" :maxlength="6" :disabled="isMfaActionLoading" @complete="confirmMfaSetup">
                      <InputOTPGroup>
                        <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                      </InputOTPGroup>
                    </InputOTP>
                  </div>
                  <DialogFooter class="gap-2 sm:flex-row-reverse">
                    <Button type="button" :disabled="isMfaActionLoading || mfaVerifyCode.length < 6" @click="confirmMfaSetup">تفعيل</Button>
                    <Button type="button" variant="outline" :disabled="isMfaActionLoading" @click="mfaDialogStage = 'scan'">رجوع</Button>
                  </DialogFooter>
                </template>
              </DialogContent>
            </Dialog>

            <!-- PIN Dialog -->
            <Dialog v-model:open="pinDialogOpen">
              <DialogContent ref="pinDialogContentRef" class="max-w-sm">
                <DialogHeader>
                  <DialogTitle>
                    {{ pinDialogMode === 'create' ? 'إنشاء رمز PIN' : pinDialogMode === 'change' ? 'تغيير رمز PIN' : 'تعطيل رمز PIN' }}
                  </DialogTitle>
                  <DialogDescription>
                    {{
                      pinDialogMode === 'disable' ? 'أدخل رمز PIN الحالي للتأكيد على تعطيله'
                      : pinDialogMode === 'change' && pinDialogStage === 'current' ? 'أدخل رمز PIN الحالي أولاً'
                      : pinDialogStage === 'new' ? 'أدخل رمز PIN الجديد المكوّن من 6 أرقام'
                      : 'أعد إدخال رمز PIN للتأكيد'
                    }}
                  </DialogDescription>
                </DialogHeader>
                <div v-if="pinError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">{{ pinError }}</div>
                <div class="flex justify-center py-2" dir="ltr">
                  <InputOTP v-if="pinDialogMode === 'disable' || (pinDialogMode === 'change' && pinDialogStage === 'current')" v-model="pinCurrent" :maxlength="6" :disabled="isPinSaving">
                    <InputOTPGroup>
                      <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                    </InputOTPGroup>
                  </InputOTP>
                  <InputOTP v-else-if="pinDialogStage === 'new'" v-model="pinNew" :maxlength="6" :disabled="isPinSaving">
                    <InputOTPGroup>
                      <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                    </InputOTPGroup>
                  </InputOTP>
                  <InputOTP v-else v-model="pinConfirm" :maxlength="6" :disabled="isPinSaving" @complete="submitPinAction">
                    <InputOTPGroup>
                      <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="size-11 text-xl font-bold" />
                    </InputOTPGroup>
                  </InputOTP>
                </div>
                <DialogFooter class="gap-2 sm:flex-row-reverse">
                  <Button type="button" :disabled="isPinSaving" @click="submitPinAction">
                    <KeyRound class="ms-1 h-4 w-4" />
                    {{ pinDialogMode === 'disable' ? 'تعطيل' : pinDialogStage !== 'confirm' ? 'التالي' : 'حفظ' }}
                  </Button>
                  <Button type="button" variant="outline" :disabled="isPinSaving" @click="pinDialogOpen = false">إلغاء</Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </section>

          <!-- ── Personal Notifications ────────────────────────────────────────────────── -->
          <section v-if="activeSection === 'notif'" data-panel="notifications" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">إشعاراتي الشخصية</h3>
              <p class="text-sm text-muted-foreground">اختر الأحداث التي تريد تلقّي إشعار عنها.</p>
            </div>
            <Separator />
            <div class="space-y-2">
              <div
                v-for="item in personalNotifications"
                :key="item.id"
                class="flex items-start justify-between gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-muted/30"
              >
                <div class="flex flex-col gap-0.5">
                  <p class="text-sm font-medium">{{ item.label }}</p>
                  <p class="text-xs text-muted-foreground">{{ item.description }}</p>
                </div>
                <Switch v-model="item.enabled" :data-testid="`notif-switch-${item.id}`" class="shrink-0" />
              </div>
            </div>
            <div class="flex justify-end">
              <Button
                :disabled="!settingsStore.isSectionDirty('userNotifications') || settingsStore.saving"
                @click="savePersonalNotifications"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ إعدادات التنبيهات
              </Button>
            </div>
          </section>

        </div>
      </div>
    </div>
  </div>
</template>
