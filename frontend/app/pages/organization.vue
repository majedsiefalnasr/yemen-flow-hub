<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Building2,
  Check,
  ChevronsUpDown,
  Columns2,
  Cog,
  Eye,
  EyeOff,
  Globe,
  Image,
  KeyRound,
  Loader2,
  Lock,
  Mail,
  Maximize2,
  Monitor,
  Moon,
  Network,
  PanelLeft,
  PanelLeftClose,
  PanelLeftDashed,
  Palette,
  Phone,
  Save,
  Server,
  ShieldAlert,
  Square,
  Sun,
  Workflow,
} from 'lucide-vue-next'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Field, FieldContent, FieldDescription, FieldGroup, FieldLabel, FieldTitle } from '@/components/ui/field'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
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
import { useOrgStore } from '@/stores/org.store'
import { useAuthStore } from '@/stores/auth.store'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
})

useHead({ title: 'المؤسسة' })

const themingStore = useThemingStore()
const settingsStore = useSettingsStore()
const orgStore = useOrgStore()
const authStore = useAuthStore()
const route = useRoute()

const isCBYAdmin = computed(() => authStore.user?.role === UserRole.CBY_ADMIN)
const isBankAdmin = computed(() => authStore.user?.role === UserRole.BANK_ADMIN)

// ── CBY Tab definitions ────────────────────────────────────────────────────────
const cbyTabs = [
  { value: 'general', label: 'عام', icon: Cog },
  { value: 'branding', label: 'العلامة التجارية', icon: Palette },
  { value: 'appearance', label: 'المظهر الافتراضي', icon: Monitor },
  { value: 'security', label: 'الأمن', icon: ShieldAlert },
  { value: 'notif', label: 'الإشعارات', icon: Bell },
  { value: 'email', label: 'البريد الإلكتروني', icon: Mail },
  { value: 'workflow', label: 'سير العمل', icon: Workflow },
] as const

// ── Bank Tab definitions ───────────────────────────────────────────────────────
const bankTabs = [
  { value: 'profile', label: 'معلومات البنك', icon: Building2 },
  { value: 'swift', label: 'إعداد SWIFT', icon: Network },
  { value: 'notif', label: 'الإشعارات', icon: Bell },
  { value: 'security', label: 'الأمان', icon: KeyRound },
] as const

const currentTabs = computed(() => isCBYAdmin.value ? cbyTabs : bankTabs)

type AnyTab = (typeof cbyTabs)[number]['value'] | (typeof bankTabs)[number]['value']

const activeSection = computed<AnyTab>(() => {
  const raw = route.query.section
  if (typeof raw !== 'string') return currentTabs.value[0].value as AnyTab
  const valid = currentTabs.value.some((t: { value: string }) => t.value === raw)
  return (valid ? raw : currentTabs.value[0].value) as AnyTab
})

// ── Theming helpers ────────────────────────────────────────────────────────────
const fontPickerOpen = ref(false)
const showEmailPassword = ref(false)

const themeOptions: Array<{ value: ThemeMode, label: string, description: string, icon: typeof Sun }> = [
  { value: 'dark', label: 'داكن', description: 'واجهة منخفضة السطوع', icon: Moon },
  { value: 'light', label: 'فاتح', description: 'واجهة عالية الوضوح', icon: Sun },
  { value: 'system', label: 'النظام', description: 'حسب إعداد الجهاز', icon: Monitor },
]

const layoutOptions: Array<{ value: LayoutMode, label: string, description: string, icon: typeof Maximize2 }> = [
  { value: 'full', label: 'كامل العرض', description: 'استخدام كامل مساحة العمل', icon: Maximize2 },
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

function selectTheme(mode: ThemeMode, event: MouseEvent) {
  themingStore.setMode(mode, event)
}

function updateLayout(layout: LayoutMode) {
  themingStore.setLayout(layout)
}

function selectFont(fontValue: string) {
  themingStore.setFont(fontValue)
  fontPickerOpen.value = false
}

// ── Brand color — local pending state, apply ONLY on save ─────────────────────
const pendingBrandColor = ref(themingStore.brandColor)
const pendingBrandColorText = ref(themingStore.brandColor)

watch(() => themingStore.brandColor, (v) => {
  pendingBrandColor.value = v
  pendingBrandColorText.value = v
})

function onBrandColorInput(event: Event) {
  const val = (event.target as HTMLInputElement).value
  pendingBrandColor.value = val
  pendingBrandColorText.value = val
}

function onBrandColorTextInput(val: string) {
  pendingBrandColorText.value = val
  if (/^#[0-9a-f]{6}$/i.test(val)) pendingBrandColor.value = val
}

// ── CBY: General settings ──────────────────────────────────────────────────────
const generalSettings = reactive({
  platformName: '',
  authority: '',
  language: 'ar',
  timeZone: 'GMT+3',
})

// ── CBY: Workflow settings ─────────────────────────────────────────────────────
const workflowSettings = reactive({
  supportMembers: '5',
  executiveMembers: '6',
  quorum: '4',
  reviewHours: '48',
  hiddenVoting: true,
  managerWeight: true,
})

// ── CBY: Email settings ────────────────────────────────────────────────────────
const emailSettings = reactive({
  host: 'smtp.cby.gov.ye',
  port: '587',
  username: 'noreply@cby.gov.ye',
  password: '************',
  approvalTemplate: 'عزيزي {{importer}}،\nنخبركم باعتماد طلب التمويل رقم {{ref}} بمبلغ {{amount}} {{currency}}.',
})

// ── CBY: Notification settings ────────────────────────────────────────────────
const cbySysNotifications = reactive([
  { label: 'البريد الإلكتروني عند تقديم طلب جديد', enabled: true },
  { label: 'إشعار داخل المنصة عند تغيير حالة طلب', enabled: true },
  { label: 'SMS عند اعتماد/رفض الطلب', enabled: true },
  { label: 'تنبيه فوري عند اكتشاف فاتورة مكررة', enabled: true },
  { label: 'تقرير يومي بإجمالي النشاط', enabled: true },
])

// ── CBY: Security settings ────────────────────────────────────────────────────
const cbySecuritySettings = reactive([
  { label: 'إلزام المصادقة الثنائية MFA', enabled: true },
  { label: 'انتهاء كلمة المرور كل 90 يوم', enabled: true },
  { label: 'قفل الحساب بعد 5 محاولات فاشلة', enabled: true },
  { label: 'تشفير الوثائق المرفوعة AES-256', enabled: true },
  { label: 'تسجيل كل عملية في سجل التدقيق', enabled: true },
  { label: 'السماح بالوصول من خارج الشبكة', enabled: false },
])

// ── Bank: Profile ─────────────────────────────────────────────────────────────
const bankProfile = reactive({
  nameAr: '',
  nameEn: '',
  code: '',
  email: '',
  phone: '',
  website: '',
  address: '',
  logoPreview: null as string | null,
})

function handleBankLogoFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    toast.error('حجم الشعار يجب ألا يتجاوز 2 ميجابايت')
    return
  }
  const reader = new FileReader()
  reader.onload = (e) => { bankProfile.logoPreview = e.target?.result as string }
  reader.readAsDataURL(file)
}

// ── Bank: SWIFT ───────────────────────────────────────────────────────────────
const swift = reactive({
  enabled: true,
  bic: '',
  correspondentBankName: '',
  correspondentBankBic: '',
  messageFormat: 'MT103',
  testMode: false,
})

// ── Bank: Notifications ───────────────────────────────────────────────────────
const bankNotifications = ref([
  { id: 'request_submitted', label: 'تقديم طلب جديد', description: 'إشعار عند تقديم أي طلب من داخل البنك', enabled: true },
  { id: 'bank_approved', label: 'موافقة البنك الداخلية', description: 'عند اعتماد المراجع البنكي للطلب', enabled: true },
  { id: 'cby_decision', label: 'قرار البنك المركزي', description: 'عند موافقة أو رفض لجنة الدعم', enabled: true },
  { id: 'executive_decision', label: 'قرار الهيئة التنفيذية', description: 'عند صدور نتيجة التصويت', enabled: true },
  { id: 'swift_uploaded', label: 'رفع SWIFT', description: 'عند رفع حوالة SWIFT لأحد الطلبات', enabled: false },
  { id: 'escalation', label: 'تنبيهات التصعيد', description: 'عند تجاوز مدة معالجة طلب الحد المحدد', enabled: true },
  { id: 'daily_digest', label: 'الملخص اليومي', description: 'تقرير يومي بحالة طلبات البنك', enabled: false },
])

// ── Bank: Security ────────────────────────────────────────────────────────────
const bankSecurity = reactive({
  passwordMinLength: 8,
  passwordRequireUppercase: true,
  passwordRequireNumbers: true,
  passwordRequireSpecial: false,
  passwordExpiryDays: 90,
  sessionTimeoutMinutes: 60,
  maxLoginAttempts: 5,
  lockoutDurationMinutes: 15,
  ipRestrictionEnabled: false,
  allowedIpRanges: '',
})

const generalPayload = computed(() => ({
  platformName: generalSettings.platformName.trim(),
  authority: generalSettings.authority.trim(),
  language: generalSettings.language,
  timeZone: generalSettings.timeZone,
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

const brandingPayload = computed(() => ({
  brandColor: pendingBrandColor.value,
  brandLogoName: orgStore.brandLogoName,
  brandLogoDataUrl: orgStore.brandLogoDataUrl,
  brandingPublished: themingStore.brandingPublished,
  brandingChannels: themingStore.brandingChannels,
}))

const securityPayload = computed(() => ({
  settings: cbySecuritySettings.map(item => ({ label: item.label, enabled: item.enabled })),
}))

const notifPayload = computed(() => ({
  settings: cbySysNotifications.map(item => ({ label: item.label, enabled: item.enabled })),
}))

const emailPayload = computed(() => ({
  host: emailSettings.host,
  port: emailSettings.port,
  username: emailSettings.username,
  password: emailSettings.password,
  approvalTemplate: emailSettings.approvalTemplate,
}))

const workflowPayload = computed(() => ({
  supportMembers: workflowSettings.supportMembers,
  executiveMembers: workflowSettings.executiveMembers,
  quorum: workflowSettings.quorum,
  reviewHours: workflowSettings.reviewHours,
  hiddenVoting: workflowSettings.hiddenVoting,
  managerWeight: workflowSettings.managerWeight,
}))

const bankProfilePayload = computed(() => ({
  nameAr: bankProfile.nameAr.trim(),
  nameEn: bankProfile.nameEn.trim(),
  code: bankProfile.code.trim(),
  email: bankProfile.email.trim(),
  phone: bankProfile.phone.trim(),
  website: bankProfile.website.trim(),
  address: bankProfile.address.trim(),
}))

const bankSwiftPayload = computed(() => ({ ...swift }))

const bankNotificationsPayload = computed(() => ({
  settings: bankNotifications.value.map(item => ({
    id: item.id,
    enabled: item.enabled,
  })),
}))

const bankSecurityPayload = computed(() => ({ ...bankSecurity }))

// ── Lifecycle ──────────────────────────────────────────────────────────────────
onMounted(async () => {
  orgStore.loadSettings()
  await themingStore.loadSettings()
  generalSettings.platformName = orgStore.platformName
  generalSettings.authority = orgStore.authority
  pendingBrandColor.value = themingStore.brandColor
  pendingBrandColorText.value = themingStore.brandColor
  settingsStore.markSectionClean('general', undefined, generalPayload.value)
  settingsStore.markSectionClean('workflow', undefined, workflowPayload.value)
  settingsStore.markSectionClean('email', undefined, emailPayload.value)
  settingsStore.markSectionClean('notif', undefined, notifPayload.value)
  settingsStore.markSectionClean('security', undefined, securityPayload.value)
  settingsStore.markSectionClean('theming', 'appearance', appearancePayload.value)
  settingsStore.markSectionClean('theming', 'branding', brandingPayload.value)
  settingsStore.markSectionClean('bankProfile', undefined, bankProfilePayload.value)
  settingsStore.markSectionClean('bankSwift', undefined, bankSwiftPayload.value)
  settingsStore.markSectionClean('bankNotifications', undefined, bankNotificationsPayload.value)
  settingsStore.markSectionClean('bankSecurity', undefined, bankSecurityPayload.value)
})

watch(fontPickerOpen, (opened) => {
  if (opened && themingStore.fontSource === 'fallback' && !themingStore.fontsLoading)
    themingStore.loadGoogleFonts()
})

// ── Dirty watchers ─────────────────────────────────────────────────────────────
watch(workflowPayload, value => settingsStore.trackSectionState('workflow', value), { deep: true })
watch(emailPayload, value => settingsStore.trackSectionState('email', value), { deep: true })
watch(notifPayload, value => settingsStore.trackSectionState('notif', value), { deep: true })
watch(securityPayload, value => settingsStore.trackSectionState('security', value), { deep: true })
watch(generalPayload, value => settingsStore.trackSectionState('general', value), { deep: true })
watch(appearancePayload, value => settingsStore.trackSectionState('theming', value, 'appearance'), { deep: true })
watch(brandingPayload, value => settingsStore.trackSectionState('theming', value, 'branding'), { deep: true })
watch(bankProfilePayload, value => settingsStore.trackSectionState('bankProfile', value), { deep: true })
watch(bankSwiftPayload, value => settingsStore.trackSectionState('bankSwift', value), { deep: true })
watch(bankNotificationsPayload, value => settingsStore.trackSectionState('bankNotifications', value), { deep: true })
watch(bankSecurityPayload, value => settingsStore.trackSectionState('bankSecurity', value), { deep: true })

// ── CBY: Save handlers ─────────────────────────────────────────────────────────
async function saveGeneralSettings() {
  const ok = await settingsStore.saveSection('general', generalPayload.value)
  if (ok) {
    orgStore.setPlatformName(generalSettings.platformName)
    orgStore.setAuthority(generalSettings.authority)
    await themingStore.loadFromServer()
    themingStore.publishSystemSettingsSync()
    toast.success('تم حفظ الإعدادات العامة بنجاح')
  }
  else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات. حاول مرة أخرى.')
  }
}

function handleCBYLogoFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    toast.error('حجم الشعار يجب ألا يتجاوز 2 ميجابايت')
    return
  }
  const supportedLogo = ['image/svg+xml', 'image/png', 'image/jpeg'].includes(file.type)
    || /\.(svg|png|jpe?g)$/i.test(file.name)
  if (!supportedLogo) {
    toast.error('صيغة الشعار يجب أن تكون SVG أو PNG أو JPG')
    return
  }
  const reader = new FileReader()
  reader.onload = (e) => {
    const dataUrl = e.target?.result as string
    orgStore.setBrandLogo(file.name, dataUrl)
  }
  reader.readAsDataURL(file)
}

async function saveBrandingSettings() {
  // Apply brand color to CSS vars only on save
  themingStore.setBrandColor(pendingBrandColor.value)
  const ok = await settingsStore.saveSection('theming', {
    ...brandingPayload.value,
  }, 'branding')
  if (ok) {
    await themingStore.loadFromServer()
    themingStore.publishSystemSettingsSync()
    toast.success('تم حفظ إعدادات الهوية البصرية بنجاح')
  }
  else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
  }
}

async function saveDefaultAppearance() {
  const ok = await settingsStore.saveSection('theming', appearancePayload.value, 'appearance')
  if (ok) {
    await themingStore.loadFromServer()
    themingStore.publishSystemSettingsSync()
    toast.success('تم حفظ إعدادات المظهر الافتراضي بنجاح')
  }
  else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
  }
}

function saveCBYSecuritySettings() {
  toast.promise(settingsStore.saveSection('security', securityPayload.value), {
    loading: 'جاري حفظ سياسات الأمن...',
    success: 'تم حفظ سياسات الأمن بنجاح',
    error: () => settingsStore.error || 'فشل حفظ الإعدادات.',
  })
}

function saveCBYNotifications() {
  toast.promise(settingsStore.saveSection('notif', notifPayload.value), {
    loading: 'جاري حفظ إعدادات الإشعارات...',
    success: 'تم حفظ إعدادات الإشعارات بنجاح',
    error: () => settingsStore.error || 'فشل حفظ الإعدادات.',
  })
}

function saveEmailSettings() {
  toast.promise(settingsStore.saveSection('email', emailPayload.value), {
    loading: 'جاري حفظ إعدادات البريد...',
    success: 'تم حفظ إعدادات البريد بنجاح',
    error: () => settingsStore.error || 'فشل حفظ الإعدادات.',
  })
}

function saveWorkflowSettings() {
  toast.promise(settingsStore.saveSection('workflow', workflowPayload.value), {
    loading: 'جاري حفظ إعدادات سير العمل...',
    success: 'تم حفظ إعدادات سير العمل بنجاح',
    error: () => settingsStore.error || 'فشل حفظ الإعدادات.',
  })
}

// ── Bank: Save handlers ────────────────────────────────────────────────────────
async function saveBankProfile() {
  const ok = await settingsStore.saveSection('bankProfile', bankProfilePayload.value)
  if (ok) toast.success('تم حفظ معلومات البنك بنجاح')
  else toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
}

async function saveBankSwift() {
  const ok = await settingsStore.saveSection('bankSwift', bankSwiftPayload.value)
  if (ok) toast.success('تم حفظ إعدادات SWIFT بنجاح')
  else toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
}

function saveBankNotifications() {
  toast.promise(settingsStore.saveSection('bankNotifications', bankNotificationsPayload.value), {
    loading: 'جاري حفظ إعدادات الإشعارات...',
    success: 'تم حفظ إعدادات الإشعارات بنجاح',
    error: () => settingsStore.error || 'فشل حفظ الإعدادات.',
  })
}

async function saveBankSecurity() {
  const ok = await settingsStore.saveSection('bankSecurity', bankSecurityPayload.value)
  if (ok) toast.success('تم حفظ إعدادات الأمان بنجاح')
  else toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
}
</script>

<template>
  <div>
    <PageHeader
      :title="isCBYAdmin ? 'إعدادات المؤسسة' : 'إعدادات البنك'"
      :subtitle="isCBYAdmin ? 'إعدادات المنصة التي تؤثر على جميع المستخدمين، ويقتصر الوصول إليها على مدير النظام' : 'إعدادات وتكوينات البنك، وتؤثر على جميع مستخدميه'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: isCBYAdmin ? 'إعدادات المؤسسة' : 'إعدادات البنك' }]"
    />

    <div  class="flex flex-col gap-6 lg:flex-row">
      <!-- ── Desktop: left sidebar nav ───────────────────────────────────── -->
      <aside class="hidden lg:block lg:w-56 lg:shrink-0">
        <nav class="flex flex-col gap-1">
          <NuxtLink
            v-for="tab in currentTabs"
            :key="tab.value"
            :to="{ query: { section: tab.value } }"
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
        <!-- Mobile: horizontal scrollable nav -->
        <div class="mb-6 flex gap-1 overflow-x-auto pb-1 lg:hidden">
          <NuxtLink
            v-for="tab in currentTabs"
            :key="tab.value"
            :to="{ query: { section: tab.value } }"
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

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: General                                                    -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'general'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">معلومات المنصة</h3>
              <p class="text-sm text-muted-foreground">الاسم الرسمي، الجهة المشغّلة، اللغة الافتراضية، والمنطقة الزمنية</p>
            </div>
            <Separator />
            <div class="grid gap-5 md:grid-cols-2">
              <FieldGroup>
                <FieldLabel>اسم المنصة</FieldLabel>
                <Input v-model="generalSettings.platformName" placeholder="منصة إدارة وتمويل الواردات" />
              </FieldGroup>
              <FieldGroup>
                <FieldLabel>الجهة المشغّلة</FieldLabel>
                <Input v-model="generalSettings.authority" placeholder="البنك المركزي اليمني" />
              </FieldGroup>
              <FieldGroup>
                <FieldLabel>اللغة الافتراضية للنظام</FieldLabel>
                <Select v-model="generalSettings.language" class="w-full">
                  <SelectTrigger class="w-full">
                    <SelectValue>
                      <span>{{ generalSettings.language === 'ar' ? 'العربية' : 'English' }}</span>
                    </SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ar">العربية</SelectItem>
                    <SelectItem value="en">English</SelectItem>
                  </SelectContent>
                </Select>
              </FieldGroup>
              <FieldGroup>
                <FieldLabel>المنطقة الزمنية</FieldLabel>
                <Select v-model="generalSettings.timeZone" class="w-full">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="اختر المنطقة الزمنية" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      <SelectLabel>الشرق الأوسط وأفريقيا</SelectLabel>
                      <SelectItem value="GMT+3">GMT+3 (Arabia)</SelectItem>
                      <SelectItem value="GMT+2">GMT+2 (East Africa)</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                      <SelectLabel>أوروبا</SelectLabel>
                      <SelectItem value="GMT">GMT (London)</SelectItem>
                      <SelectItem value="GMT+1">GMT+1 (Europe)</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                      <SelectLabel>آسيا</SelectLabel>
                      <SelectItem value="GMT+8">GMT+8 (Asia)</SelectItem>
                    </SelectGroup>
                  </SelectContent>
                </Select>
              </FieldGroup>
            </div>
            <div class="flex justify-end">
              <Button
                :disabled="!settingsStore.isSectionDirty('general') || settingsStore.saving"
                @click="saveGeneralSettings"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ الإعدادات العامة
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: Branding                                                   -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'branding'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">الهوية البصرية</h3>
              <p class="text-sm text-muted-foreground">الشعار ولون العلامة — تؤثر على مظهر المنصة لجميع المستخدمين</p>
            </div>
            <Separator />

            <!-- Logo upload -->
            <FieldGroup>
              <Field orientation="horizontal">
                <FieldContent>
                  <FieldTitle>شعار الجهة</FieldTitle>
                  <FieldDescription>SVG أو PNG أو JPG حتى 800×400 بكسل</FieldDescription>
                </FieldContent>
                <div class="flex items-center gap-4">
                  <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted">
                    <img
                      v-if="orgStore.brandLogoDataUrl"
                      :src="orgStore.brandLogoDataUrl"
                      alt="شعار المنصة"
                      class="h-full w-full object-contain"
                    />
                    <Image v-else class="h-8 w-8 text-muted-foreground" />
                  </div>
                  <div class="space-y-2">
                    <label class="cursor-pointer">
                      <div class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                        <Image class="h-4 w-4" />
                        {{ orgStore.brandLogoName || 'رفع شعار' }}
                      </div>
                      <input type="file" accept=".svg,.png,.jpg,.jpeg" class="sr-only" @change="handleCBYLogoFile">
                    </label>
                    <p class="text-xs text-muted-foreground">PNG، SVG، JPG — 2 MB كحد أقصى</p>
                    <button
                      v-if="orgStore.brandLogoDataUrl"
                      type="button"
                      class="text-xs text-destructive hover:underline"
                      @click="orgStore.clearBrandLogo()"
                    >
                      إزالة الشعار
                    </button>
                  </div>
                </div>
              </Field>
            </FieldGroup>

            <Separator />

            <!-- Brand color — Input Group pattern -->
            <FieldGroup>
              <Field orientation="horizontal">
                <FieldContent>
                  <FieldTitle>لون العلامة</FieldTitle>
                  <FieldDescription>يُحدَّث فقط عند الضغط على زر الحفظ</FieldDescription>
                </FieldContent>
                <!-- Combined Input Group: single outer border + aligned swatch -->
                <InputGroup class="h-9 w-full max-w-64">
                  <InputGroupInput
                    :model-value="pendingBrandColorText"
                    dir="ltr"
                    class="font-mono"
                    placeholder="#0066cc"
                    maxlength="7"
                    @update:model-value="onBrandColorTextInput(String($event))"
                  />
                  <InputGroupAddon align="inline-end" class="h-full pe-1">
                    <label
                      class="flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-md border border-input transition-colors hover:bg-muted"
                      :style="{ backgroundColor: /^#[0-9a-f]{6}$/i.test(pendingBrandColor) ? pendingBrandColor : undefined }"
                    >
                      <input
                        :value="pendingBrandColor"
                        type="color"
                        class="sr-only"
                        @input="onBrandColorInput"
                      />
                    </label>
                  </InputGroupAddon>
                </InputGroup>
              </Field>
            </FieldGroup>

            <div class="flex justify-end">
              <Button
                :disabled="!settingsStore.isSectionDirty('theming', 'branding') || settingsStore.saving"
                @click="saveBrandingSettings"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ الهوية البصرية
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: Default Appearance                                         -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'appearance'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">المظهر الافتراضي للنظام</h3>
              <p class="text-sm text-muted-foreground">القيم الافتراضية التي يرثها كل مستخدم لم يُخصّص مظهره بعد</p>
            </div>
            <Separator />

            <!-- Theme mode -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">وضع الثيم الافتراضي</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">يُطبَّق على المستخدمين الجدد أو من لم يختاروا وضعاً بعد.</p>
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
                  <div class="relative h-24 w-full" :class="option.value === 'dark' ? 'bg-[#111827]' : 'bg-muted/30'">
                    <template v-if="option.value === 'light'">
                      <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-white border border-[#e5e7eb] p-2">
                        <div class="h-3 rounded bg-[#f1f3f5]" />
                        <div class="mt-2 h-2 rounded bg-[#f1f3f5]" />
                        <div class="mt-1.5 h-2 rounded bg-[#f1f3f5]" />
                      </div>
                    </template>
                    <template v-else-if="option.value === 'dark'">
                      <div class="absolute inset-y-0 start-0 w-10 bg-[#0f1218] border-e border-white/10 flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-[#343a44]" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/70" />
                        <div class="h-1.5 w-full rounded bg-[#343a44]" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-[#151820] border border-white/10 p-2">
                        <div class="h-3 rounded bg-[#2a2e3a]" />
                        <div class="mt-2 h-2 rounded bg-[#2a2e3a]" />
                        <div class="mt-1.5 h-2 rounded bg-[#2a2e3a]" />
                      </div>
                    </template>
                    <template v-else>
                      <div class="absolute inset-y-0 start-0 w-10 bg-card border-e border-border flex flex-col gap-1 p-1.5">
                        <div class="h-1.5 w-full rounded bg-muted" />
                        <div class="h-1.5 w-3/4 rounded bg-primary/40" />
                        <div class="h-1.5 w-full rounded bg-muted" />
                      </div>
                      <div class="absolute inset-y-2 start-12 end-2 overflow-hidden rounded border border-[#e5e7eb] bg-white">
                        <div class="absolute inset-y-0 end-0 w-1/2 bg-[#151820]" />
                        <div class="relative p-2">
                          <div class="h-3 rounded bg-[#f1f3f5]" />
                          <div class="mt-2 h-2 rounded bg-[#2a2e3a]" />
                          <div class="mt-1.5 h-2 rounded bg-[#f1f3f5]" />
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

            <!-- Font picker -->
            <div class="space-y-3">
              <Label class="text-sm font-medium">الخط الافتراضي</Label>
              <Popover v-model:open="fontPickerOpen">
                <PopoverTrigger as-child>
                  <Button variant="outline" role="combobox" :aria-expanded="fontPickerOpen" class="h-10 w-full justify-between">
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
                          <Loader2 class="h-4 w-4 animate-spin" />جاري تحميل قائمة الخطوط...
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
            </div>

            <Separator />

            <!-- Layout mode -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">تخطيط العرض الافتراضي</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">كيفية توزيع المحتوى الرئيسي على الشاشة.</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  v-for="option in layoutOptions"
                  :key="option.value"
                  type="button"
                  class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                  :class="themingStore.layout === option.value ? 'border-2 border-primary ring-2 ring-primary/20' : 'border-border'"
                  @click="updateLayout(option.value)"
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
                      <div class="absolute inset-y-2 start-12 end-2 rounded bg-card border border-border p-2">
                        <div class="h-full rounded border border-dashed border-primary/50 bg-muted/40 mx-4" />
                      </div>
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

            <!-- Sidebar variant -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">نمط الشريط الجانبي الافتراضي</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">كيف يظهر الشريط الجانبي افتراضياً بالنسبة لمنطقة المحتوى.</p>
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

            <!-- Sidebar collapsible -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">سلوك طي الشريط الجانبي الافتراضي</h4>
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

            <!-- Radius -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">نصف قطر الحواف الافتراضي</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">درجة استدارة حواف المكونات مثل الأزرار والبطاقات.</p>
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

            <!-- Density -->
            <section class="space-y-4">
              <div>
                <h4 class="text-sm font-medium">كثافة العرض الافتراضية</h4>
                <p class="mt-0.5 text-xs text-muted-foreground">مقدار التباعد بين عناصر الواجهة.</p>
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
                :disabled="!settingsStore.isSectionDirty('theming', 'appearance') || settingsStore.saving"
                @click="saveDefaultAppearance"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ المظهر الافتراضي
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: Security                                                   -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'security'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">سياسات الأمن</h3>
              <p class="text-sm text-muted-foreground">معايير الحماية الإلزامية على مستوى المنصة لجميع المستخدمين</p>
            </div>
            <Separator />
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <div class="rounded-lg border bg-muted/20 p-3">
                <div class="text-xs text-muted-foreground">عتبة قفل الحساب</div>
                <div class="text-sm font-semibold">10 محاولات</div>
              </div>
              <div class="rounded-lg border bg-muted/20 p-3">
                <div class="text-xs text-muted-foreground">مدة القفل</div>
                <div class="text-sm font-semibold">15 دقيقة</div>
              </div>
              <div class="rounded-lg border bg-muted/20 p-3">
                <div class="text-xs text-muted-foreground">تقييد تسجيل الدخول</div>
                <div class="text-sm font-semibold">5 / دقيقة</div>
              </div>
              <div class="rounded-lg border bg-muted/20 p-3">
                <div class="text-xs text-muted-foreground">انتهاء الجلسة</div>
                <div class="text-sm font-semibold">8 ساعات</div>
              </div>
            </div>
            <div class="space-y-2">
              <div
                v-for="item in cbySecuritySettings"
                :key="item.label"
                class="flex items-center justify-between rounded-lg border border-border p-4 transition-colors hover:bg-muted/30"
              >
                <div class="flex items-center gap-3">
                  <Lock class="h-4 w-4 shrink-0 text-muted-foreground" />
                  <span class="text-sm">{{ item.label }}</span>
                </div>
                <Switch v-model="item.enabled" />
              </div>
            </div>
            <div class="flex justify-end pt-6">
              <Button
                :disabled="!settingsStore.isSectionDirty('security') || settingsStore.saving"
                @click="saveCBYSecuritySettings"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ سياسات الأمن
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: Notifications                                              -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'notif'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">قنوات الإشعارات النظامية</h3>
              <p class="text-sm text-muted-foreground">قنوات التنبيه المفعّلة لأحداث سير العمل على مستوى النظام</p>
            </div>
            <Separator />
            <div class="space-y-2">
              <div
                v-for="item in cbySysNotifications"
                :key="item.label"
                class="flex items-center justify-between rounded-lg border border-border p-4 transition-colors hover:bg-muted/30"
              >
                <div class="flex items-center gap-3">
                  <Bell class="h-4 w-4 shrink-0 text-muted-foreground" />
                  <span class="text-sm">{{ item.label }}</span>
                </div>
                <Switch v-model="item.enabled" />
              </div>
            </div>
            <div class="flex justify-end pt-6">
              <Button
                :disabled="!settingsStore.isSectionDirty('notif') || settingsStore.saving"
                @click="saveCBYNotifications"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ إعدادات الإشعارات
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: Email                                                      -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'email'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">إعدادات البريد الإلكتروني</h3>
              <p class="text-sm text-muted-foreground">تكوين خادم البريد الصادر للإشعارات والمراسلات الرسمية</p>
            </div>
            <Separator />
            <div class="space-y-4">
              <h3 class="text-sm font-semibold">إعدادات الخادم</h3>
              <div class="grid gap-5 md:grid-cols-2">
                <FieldGroup>
                  <FieldLabel>SMTP Host</FieldLabel>
                  <Input v-model="emailSettings.host"  placeholder="smtp.example.com" />
                </FieldGroup>
                <FieldGroup>
                  <FieldLabel>المنفذ (Port)</FieldLabel>
                  <Input v-model="emailSettings.port"  type="number" placeholder="587" />
                </FieldGroup>
              </div>
            </div>
            <Separator />
            <div class="space-y-4">
              <h3 class="text-sm font-semibold">بيانات الاعتماد</h3>
              <div class="grid gap-5 md:grid-cols-2">
                <FieldGroup>
                  <FieldLabel>اسم المستخدم</FieldLabel>
                  <Input v-model="emailSettings.username"  />
                </FieldGroup>
                <FieldGroup>
                  <FieldLabel>كلمة المرور</FieldLabel>
                  <div class="relative">
                    <Input
                      v-model="emailSettings.password"
                      
                      :type="showEmailPassword ? 'text' : 'password'"
                      placeholder="••••••••"
                      class="pe-10"
                    />
                    <button
                      type="button"
                      class="absolute inset-y-0 end-0 flex cursor-pointer items-center px-3 text-muted-foreground transition-colors hover:text-foreground"
                      @click="showEmailPassword = !showEmailPassword"
                    >
                      <EyeOff v-if="showEmailPassword" class="h-4 w-4" />
                      <Eye v-else class="h-4 w-4" />
                    </button>
                  </div>
                </FieldGroup>
              </div>
            </div>
            <Separator />
            <div class="space-y-4">
              <h3 class="text-sm font-semibold">قوالب البريد</h3>
              <FieldGroup>
                <FieldLabel>قالب إشعار اعتماد الطلب</FieldLabel>
                <Textarea v-model="emailSettings.approvalTemplate" rows="5" class="font-mono text-sm" />
                <p class="text-xs text-muted-foreground">
                  المتغيرات المتاحة:
                  <code class="rounded bg-muted px-1">&#123;&#123;importer&#125;&#125;</code>
                  <code class="rounded bg-muted px-1">&#123;&#123;ref&#125;&#125;</code>
                  <code class="rounded bg-muted px-1">&#123;&#123;amount&#125;&#125;</code>
                  <code class="rounded bg-muted px-1">&#123;&#123;currency&#125;&#125;</code>
                </p>
              </FieldGroup>
            </div>
            <div class="flex justify-end">
              <Button
                :disabled="!settingsStore.isSectionDirty('email') || settingsStore.saving"
                @click="saveEmailSettings"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ إعدادات البريد
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- CBY: Workflow                                                   -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isCBYAdmin && activeSection === 'workflow'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">إعدادات سير العمل</h3>
              <p class="text-sm text-muted-foreground">تكوين معاملات الموافقة، اللجان، وقواعد التصويت</p>
            </div>
            <Separator />
            <div class="space-y-4">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold">هيكل اللجان</h3>
                <Badge variant="secondary" class="text-xs">تنظيمي</Badge>
              </div>
              <div class="grid gap-5 md:grid-cols-2">
                <FieldGroup>
                  <FieldLabel>عدد أعضاء اللجنة المساندة</FieldLabel>
                  <Input v-model="workflowSettings.supportMembers" type="number" min="1" />
                </FieldGroup>
                <FieldGroup>
                  <FieldLabel>عدد أعضاء اللجنة التنفيذية</FieldLabel>
                  <Input v-model="workflowSettings.executiveMembers" type="number" min="1" />
                </FieldGroup>
              </div>
            </div>
            <Separator />
            <div class="space-y-4">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold">النصاب والمهل</h3>
                <Badge variant="secondary" class="text-xs">اجتماعات</Badge>
              </div>
              <div class="grid gap-5 md:grid-cols-2">
                <FieldGroup>
                  <FieldLabel>الحد الأدنى للنصاب القانوني</FieldLabel>
                  <Input v-model="workflowSettings.quorum" type="number" min="1" />
                </FieldGroup>
                <FieldGroup>
                  <FieldLabel>مهلة المراجعة (ساعات)</FieldLabel>
                  <Input v-model="workflowSettings.reviewHours" type="number" min="1" />
                </FieldGroup>
              </div>
            </div>
            <Separator />
            <div class="space-y-4">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold">قواعد التصويت</h3>
                <Badge variant="secondary" class="text-xs">صلاحيات</Badge>
              </div>
              <div class="space-y-3">
                <div class="flex items-center justify-between rounded-lg border border-border p-4">
                  <div>
                    <p class="text-sm font-medium">تصويت سري</p>
                    <p class="text-xs text-muted-foreground">إخفاء أصوات الأعضاء قبل إغلاق الجلسة</p>
                  </div>
                  <Switch v-model="workflowSettings.hiddenVoting" />
                </div>
                <div class="flex items-center justify-between rounded-lg border border-border p-4">
                  <div>
                    <p class="text-sm font-medium">ترجيح صوت المدير عند التعادل</p>
                    <p class="text-xs text-muted-foreground">يملك مدير اللجنة صلاحية كسر التعادل</p>
                  </div>
                  <Switch v-model="workflowSettings.managerWeight" />
                </div>
              </div>
            </div>
            <div class="flex justify-end">
              <Button
                :disabled="!settingsStore.isSectionDirty('workflow') || settingsStore.saving"
                @click="saveWorkflowSettings"
              >
                <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                حفظ إعدادات سير العمل
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- Bank: Profile                                                   -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isBankAdmin && activeSection === 'profile'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">معلومات البنك</h3>
              <p class="text-sm text-muted-foreground">البيانات الرسمية للبنك المسجلة لدى البنك المركزي اليمني</p>
            </div>
            <Separator />

            <!-- Logo -->
            <div class="flex items-center gap-4">
              <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted">
                <img v-if="bankProfile.logoPreview" :src="bankProfile.logoPreview" alt="شعار البنك" class="h-full w-full object-contain" />
                <Building2 v-else class="h-8 w-8 text-muted-foreground" />
              </div>
              <div class="space-y-1">
                <label class="cursor-pointer">
                  <div class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                    <Image class="h-4 w-4" />
                    رفع شعار
                  </div>
                  <Input type="file" accept=".png,.svg,.jpg,.jpeg" class="hidden" @change="handleBankLogoFile" />
                </label>
                <p class="text-xs text-muted-foreground">PNG، SVG، JPG — 2 MB كحد أقصى</p>
              </div>
            </div>

            <Separator />

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label>الاسم بالعربية</Label>
                <Input v-model="bankProfile.nameAr"  placeholder="البنك اليمني للتجارة" />
              </div>
              <div class="space-y-2">
                <Label>الاسم بالإنجليزية</Label>
                <Input v-model="bankProfile.nameEn"  placeholder="Yemen Commercial Bank" />
              </div>
              <div class="space-y-2">
                <Label>رمز البنك (CBY)</Label>
                <Input v-model="bankProfile.code"  placeholder="YCB" class="font-mono uppercase" maxlength="10" />
              </div>
              <div class="space-y-2">
                <Label>البريد الإلكتروني</Label>
                <Input v-model="bankProfile.email" type="email"  placeholder="info@bank.ye" />
              </div>
              <div class="space-y-2">
                <Label>رقم الهاتف</Label>
                <Input v-model="bankProfile.phone"  placeholder="+967 1 000 000" />
              </div>
              <div class="space-y-2">
                <Label>الموقع الإلكتروني</Label>
                <Input v-model="bankProfile.website"  placeholder="https://bank.ye" />
              </div>
              <div class="space-y-2 sm:col-span-2">
                <Label>العنوان</Label>
                <Input v-model="bankProfile.address"  placeholder="صنعاء، اليمن" />
              </div>
            </div>
            <div class="flex justify-end">
              <Button :disabled="!settingsStore.isSectionDirty('bankProfile') || settingsStore.saving" @click="saveBankProfile">
                <Save class="h-4 w-4" />
                حفظ معلومات البنك
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- Bank: SWIFT                                                     -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isBankAdmin && activeSection === 'swift'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">إعداد SWIFT</h3>
              <p class="text-sm text-muted-foreground">تكوين رموز وإعدادات شبكة التحويلات المالية الدولية SWIFT</p>
            </div>
            <Separator />
            <FieldGroup>
              <Field>
                <FieldLabel>تفعيل SWIFT</FieldLabel>
                <FieldContent>
                  <div class="flex items-center gap-3">
                    <Switch id="swift-enabled" v-model="swift.enabled" />
                    <Label for="swift-enabled" class="cursor-pointer text-sm">
                      {{ swift.enabled ? 'مُفعَّل' : 'معطَّل' }}
                    </Label>
                    <Badge :variant="swift.enabled ? 'default' : 'secondary'">
                      {{ swift.enabled ? 'نشط' : 'غير نشط' }}
                    </Badge>
                  </div>
                </FieldContent>
                <FieldDescription>تفعيل أو تعطيل إمكانية إصدار تحويلات SWIFT من هذا البنك</FieldDescription>
              </Field>
            </FieldGroup>
            <Separator />
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label>رمز BIC الخاص بالبنك</Label>
                <Input v-model="swift.bic"  placeholder="YCBBYEYE" maxlength="11" class="font-mono uppercase" :disabled="!swift.enabled" />
                <p class="text-xs text-muted-foreground">8 أو 11 حرفاً — رمز SWIFT/BIC المعتمد</p>
              </div>
              <div class="space-y-2">
                <Label>صيغة رسائل SWIFT</Label>
                <Select v-model="swift.messageFormat" :disabled="!swift.enabled">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="MT103">MT103 — تحويل عميل</SelectItem>
                    <SelectItem value="MT202">MT202 — تحويل بين بنوك</SelectItem>
                    <SelectItem value="MT199">MT199 — رسالة حرة</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>اسم البنك المراسل</Label>
                <Input v-model="swift.correspondentBankName"  placeholder="International Bank" :disabled="!swift.enabled" />
              </div>
              <div class="space-y-2">
                <Label>BIC البنك المراسل</Label>
                <Input v-model="swift.correspondentBankBic"  placeholder="IBKNUS33" maxlength="11" class="font-mono uppercase" :disabled="!swift.enabled" />
              </div>
            </div>
            <Separator />
            <FieldGroup>
              <Field>
                <FieldLabel>وضع الاختبار (Test Mode)</FieldLabel>
                <FieldContent>
                  <div class="flex items-center gap-3">
                    <Switch id="swift-test" v-model="swift.testMode" :disabled="!swift.enabled" />
                    <Label for="swift-test" class="cursor-pointer text-sm">
                      {{ swift.testMode ? 'في وضع الاختبار' : 'في وضع الإنتاج' }}
                    </Label>
                  </div>
                </FieldContent>
                <FieldDescription>في وضع الاختبار لا تُرسَل رسائل SWIFT الحقيقية — للتطوير والاختبار فقط</FieldDescription>
              </Field>
            </FieldGroup>
            <div class="flex justify-end">
              <Button :disabled="!settingsStore.isSectionDirty('bankSwift') || settingsStore.saving" @click="saveBankSwift">
                <Save class="h-4 w-4" />
                حفظ إعدادات SWIFT
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- Bank: Notifications                                             -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isBankAdmin && activeSection === 'notif'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">إشعارات البنك</h3>
              <p class="text-sm text-muted-foreground">تحديد الأحداث التي يتلقى عنها مديرو البنك إشعارات بريدية ومنصة</p>
            </div>
            <Separator />
            <div
              v-for="notif in bankNotifications"
              :key="notif.id"
              class="flex items-center justify-between gap-4 rounded-lg px-3 py-3 transition-colors hover:bg-muted/40"
            >
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ notif.label }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ notif.description }}</p>
              </div>
              <Switch :id="`notif-${notif.id}`" v-model="notif.enabled" class="shrink-0" />
            </div>
            <div class="flex justify-end">
              <Button :disabled="!settingsStore.isSectionDirty('bankNotifications') || settingsStore.saving" @click="saveBankNotifications">
                <Save class="h-4 w-4" />
                حفظ إعدادات الإشعارات
              </Button>
            </div>
          </section>

          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- Bank: Security                                                  -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <section v-if="isBankAdmin && activeSection === 'security'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium">أمان البنك</h3>
              <p class="text-sm text-muted-foreground">سياسات كلمات المرور وإدارة الجلسات وقيود الوصول لمستخدمي بنكك</p>
            </div>
            <Separator />

            <!-- Password policy -->
            <div class="space-y-4">
              <div class="flex items-center gap-2">
                <Lock class="h-4 w-4 text-muted-foreground" />
                <h3 class="text-sm font-semibold">سياسة كلمة المرور</h3>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                  <Label>الحد الأدنى لطول كلمة المرور</Label>
                  <div class="flex items-center gap-3">
                    <Input v-model.number="bankSecurity.passwordMinLength" type="number" min="6" max="32" class="w-24"  />
                    <span class="text-sm text-muted-foreground">حرف على الأقل</span>
                  </div>
                </div>
                <div class="space-y-2">
                  <Label>انتهاء صلاحية كلمة المرور</Label>
                  <div class="flex items-center gap-3">
                    <Input v-model.number="bankSecurity.passwordExpiryDays" type="number" min="0" max="365" class="w-24"  />
                    <span class="text-sm text-muted-foreground">يوم (0 = بدون انتهاء)</span>
                  </div>
                </div>
              </div>
              <div class="space-y-3">
                <div
                  v-for="(item, key) in ({
                    passwordRequireUppercase: 'تتضمن حروفاً كبيرة (A–Z)',
                    passwordRequireNumbers: 'تتضمن أرقاماً (0–9)',
                    passwordRequireSpecial: 'تتضمن رموزاً خاصة (!@#$...)',
                  } as const)"
                  :key="key"
                  class="flex items-center justify-between gap-4"
                >
                  <Label :for="`pw-${key}`" class="cursor-pointer text-sm text-muted-foreground">{{ item }}</Label>
                  <Switch
                    :id="`pw-${key}`"
                    :model-value="bankSecurity[key as keyof typeof bankSecurity] as boolean"
                    @update:model-value="(v) => ((bankSecurity[key as keyof typeof bankSecurity] as boolean) = v)"
                  />
                </div>
              </div>
            </div>

            <Separator />

            <!-- Session & lockout -->
            <div class="space-y-4">
              <h3 class="text-sm font-semibold">الجلسات والقيود</h3>
              <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                  <Label>مهلة انتهاء الجلسة</Label>
                  <div class="flex items-center gap-3">
                    <Input v-model.number="bankSecurity.sessionTimeoutMinutes" type="number" min="5" max="480" class="w-24"  />
                    <span class="text-sm text-muted-foreground">دقيقة</span>
                  </div>
                </div>
                <div class="space-y-2">
                  <Label>الحد الأقصى لمحاولات تسجيل الدخول</Label>
                  <div class="flex items-center gap-3">
                    <Input v-model.number="bankSecurity.maxLoginAttempts" type="number" min="3" max="20" class="w-24"  />
                    <span class="text-sm text-muted-foreground">محاولة</span>
                  </div>
                </div>
                <div class="space-y-2">
                  <Label>مدة الإغلاق بعد تجاوز المحاولات</Label>
                  <div class="flex items-center gap-3">
                    <Input v-model.number="bankSecurity.lockoutDurationMinutes" type="number" min="5" max="1440" class="w-24"  />
                    <span class="text-sm text-muted-foreground">دقيقة</span>
                  </div>
                </div>
              </div>
            </div>

            <Separator />

            <!-- IP restriction -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold">قيود عناوين IP</h3>
                  <p class="mt-0.5 text-xs text-muted-foreground">السماح بالوصول من نطاقات IP محددة فقط</p>
                </div>
                <Switch id="ip-restriction" v-model="bankSecurity.ipRestrictionEnabled" />
              </div>
              <div v-if="bankSecurity.ipRestrictionEnabled" class="space-y-2">
                <Label>نطاقات IP المسموح بها</Label>
                <textarea
                  v-model="bankSecurity.allowedIpRanges"
                  
                  rows="4"
                  placeholder="192.168.1.0/24&#10;10.0.0.1"
                  class="w-full rounded-lg border border-border bg-background p-3 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
                />
                <p class="text-xs text-muted-foreground">أدخل عنواناً واحداً أو نطاق CIDR في كل سطر</p>
              </div>
            </div>

            <div class="flex justify-end">
              <Button :disabled="!settingsStore.isSectionDirty('bankSecurity') || settingsStore.saving" @click="saveBankSecurity">
                <Save class="h-4 w-4" />
                حفظ إعدادات الأمان
              </Button>
            </div>
          </section>

        </div>
      </div>
    </div>
  </div>
</template>
