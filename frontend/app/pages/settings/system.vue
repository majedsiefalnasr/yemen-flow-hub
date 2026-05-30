<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Check,
  ChevronsUpDown,
  Cog,
  Eye,
  EyeOff,
  Image,
  Loader2,
  Lock,
  Mail,
  Maximize2,
  Monitor,
  Moon,
  Palette,
  Server,
  ShieldAlert,
  Square,
  Sun,
  Workflow,
} from 'lucide-vue-next'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Field, FieldContent, FieldDescription, FieldGroup, FieldLabel, FieldTitle } from '@/components/ui/field'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
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
import { useThemingStore, type LayoutMode, type ThemeMode } from '@/stores/theming.store'
import { useSettingsStore } from '@/stores/settings.store'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN],
})

useHead({ title: 'إعدادات النظام' })

const themingStore = useThemingStore()
const settingsStore = useSettingsStore()
const route = useRoute()
const router = useRouter()

// ── Nav items ──────────────────────────────────────────────────────────────────
const systemTabs = [
  { value: 'general', label: 'عام', icon: Cog, dataTab: 'general', testId: 'tab-general' },
  { value: 'branding', label: 'العلامة التجارية', icon: Palette, dataTab: 'branding', testId: 'tab-branding' },
  { value: 'appearance', label: 'المظهر الافتراضي', icon: Monitor, dataTab: 'appearance', testId: 'tab-appearance' },
  { value: 'security', label: 'الأمن', icon: ShieldAlert, dataTab: 'security', testId: 'tab-security' },
  { value: 'notif', label: 'الإشعارات', icon: Bell, dataTab: 'notifications', testId: 'tab-notif' },
  { value: 'email', label: 'البريد الإلكتروني', icon: Mail, dataTab: 'email', testId: 'tab-email' },
  { value: 'workflow', label: 'سير العمل', icon: Workflow, dataTab: 'workflow', testId: 'tab-workflow' },
] as const

type SystemTab = (typeof systemTabs)[number]['value']

const activeTab = ref<SystemTab>('general')

function normalizeTab(raw: unknown): SystemTab | null {
  if (typeof raw !== 'string') return null
  return systemTabs.some(tab => tab.value === raw) ? (raw as SystemTab) : null
}

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

const lastClickEvent = ref<MouseEvent | undefined>(undefined)

function updateThemeMode(value: unknown) {
  if (value === 'system' || value === 'light' || value === 'dark')
    themingStore.setMode(value, lastClickEvent.value)
}

function updateLayout(layout: LayoutMode) {
  themingStore.setLayout(layout)
}

function selectFont(fontValue: string) {
  themingStore.setFont(fontValue)
  fontPickerOpen.value = false
}

function updateBrandColor(event: Event) {
  themingStore.setBrandColor((event.target as HTMLInputElement).value)
}

function handleLogoFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) themingStore.setBrandLogoName(file.name)
}

// ── Settings data ──────────────────────────────────────────────────────────────
const originalWorkflowSettings = {
  supportMembers: '5',
  executiveMembers: '6',
  quorum: '4',
  reviewHours: '48',
  hiddenVoting: true,
  managerWeight: true,
}

const originalEmailSettings = {
  host: 'smtp.cby.gov.ye',
  port: '587',
  username: 'noreply@cby.gov.ye',
  password: '************',
  approvalTemplate: 'عزيزي {{importer}}،\nنخبركم باعتماد طلب التمويل رقم {{ref}} بمبلغ {{amount}} {{currency}}.',
}

const originalNotificationSettings = [
  { label: 'البريد الإلكتروني عند تقديم طلب جديد', enabled: true },
  { label: 'إشعار داخل المنصة عند تغيير حالة طلب', enabled: true },
  { label: 'SMS عند اعتماد/رفض الطلب', enabled: true },
  { label: 'تنبيه فوري عند اكتشاف فاتورة مكررة', enabled: true },
  { label: 'تقرير يومي بإجمالي النشاط', enabled: true },
]

const originalSecuritySettings = [
  { label: 'إلزام المصادقة الثنائية MFA', enabled: true },
  { label: 'انتهاء كلمة المرور كل 90 يوم', enabled: true },
  { label: 'قفل الحساب بعد 5 محاولات فاشلة', enabled: true },
  { label: 'تشفير الوثائق المرفوعة AES-256', enabled: true },
  { label: 'تسجيل كل عملية في سجل التدقيق', enabled: true },
  { label: 'السماح بالوصول من خارج الشبكة', enabled: false },
]

const originalGeneralSettings = {
  platformName: 'منصة إدارة وتمويل الواردات',
  authority: 'البنك المركزي اليمني',
  timeZone: 'GMT+3',
  language: 'ar',
}

const workflowSettings = reactive({ ...originalWorkflowSettings })
const emailSettings = reactive({ ...originalEmailSettings })
const notificationSettings = reactive(JSON.parse(JSON.stringify(originalNotificationSettings)))
const securitySettings = reactive(JSON.parse(JSON.stringify(originalSecuritySettings)))
const generalSettings = reactive({ ...originalGeneralSettings })

// ── Lifecycle ──────────────────────────────────────────────────────────────────
onMounted(() => {
  themingStore.loadSettings()
  const urlTab = normalizeTab(route.query.tab)
  if (urlTab) activeTab.value = urlTab
})

watch(() => route.query.tab, (rawTab) => {
  const urlTab = normalizeTab(rawTab)
  if (urlTab && urlTab !== activeTab.value) activeTab.value = urlTab
})

watch(activeTab, async (tab) => {
  const current = normalizeTab(route.query.tab)
  if (current === tab) return
  await router.replace({ query: { ...route.query, tab } })
})

watch(fontPickerOpen, (opened) => {
  if (opened && themingStore.fontSource === 'fallback' && !themingStore.fontsLoading)
    themingStore.loadGoogleFonts()
})

// ── Dirty watchers ─────────────────────────────────────────────────────────────
watch(() => workflowSettings, () => settingsStore.markSectionDirty('workflow'), { deep: true })
watch(() => emailSettings, () => settingsStore.markSectionDirty('email'), { deep: true })
watch(() => notificationSettings, () => settingsStore.markSectionDirty('notif'), { deep: true })
watch(() => securitySettings, () => settingsStore.markSectionDirty('security'), { deep: true })
watch(() => generalSettings, () => settingsStore.markSectionDirty('general'), { deep: true })
watch(() => ({ mode: themingStore.mode, font: themingStore.font, layout: themingStore.layout }),
  () => settingsStore.markSectionDirty('theming', 'appearance'), { deep: true })
watch(() => ({ brandColor: themingStore.brandColor, brandLogoName: themingStore.brandLogoName }),
  () => settingsStore.markSectionDirty('theming', 'branding'), { deep: true })

// ── Save handlers ──────────────────────────────────────────────────────────────
function saveWorkflowSettings() {
  toast.promise(settingsStore.saveSection('workflow', workflowSettings), {
    loading: 'جاري حفظ إعدادات سير العمل...',
    success: () => { Object.assign(originalWorkflowSettings, workflowSettings); return 'تم حفظ إعدادات سير العمل بنجاح' },
    error: () => settingsStore.error || 'فشل حفظ الإعدادات. حاول مرة أخرى.',
  })
}

function saveEmailSettings() {
  toast.promise(settingsStore.saveSection('email', emailSettings), {
    loading: 'جاري حفظ إعدادات البريد...',
    success: () => { Object.assign(originalEmailSettings, emailSettings); return 'تم حفظ إعدادات البريد بنجاح' },
    error: () => settingsStore.error || 'فشل حفظ الإعدادات. حاول مرة أخرى.',
  })
}

function saveNotificationSettings() {
  toast.promise(settingsStore.saveSection('notif', { settings: notificationSettings }), {
    loading: 'جاري حفظ إعدادات الإشعارات...',
    success: () => { Object.assign(originalNotificationSettings, notificationSettings); return 'تم حفظ إعدادات الإشعارات بنجاح' },
    error: () => settingsStore.error || 'فشل حفظ الإعدادات. حاول مرة أخرى.',
  })
}

function saveSecuritySettings() {
  toast.promise(settingsStore.saveSection('security', { settings: securitySettings }), {
    loading: 'جاري حفظ إعدادات الأمن...',
    success: () => { Object.assign(originalSecuritySettings, securitySettings); return 'تم حفظ إعدادات الأمن بنجاح' },
    error: () => settingsStore.error || 'فشل حفظ الإعدادات. حاول مرة أخرى.',
  })
}

function saveGeneralSettings() {
  toast.promise(settingsStore.saveSection('general', generalSettings), {
    loading: 'جاري حفظ الإعدادات العامة...',
    success: () => { Object.assign(originalGeneralSettings, generalSettings); return 'تم حفظ الإعدادات العامة بنجاح' },
    error: () => settingsStore.error || 'فشل حفظ الإعدادات. حاول مرة أخرى.',
  })
}

async function saveBrandingSettings() {
  const success = await settingsStore.saveSection('theming', {
    brandColor: themingStore.brandColor,
    brandLogoName: themingStore.brandLogoName,
  }, 'branding')
  if (success) {
    settingsStore.markSectionClean('theming', 'branding')
    toast.success('تم حفظ إعدادات العلامة التجارية بنجاح')
  } else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
  }
}

async function saveDefaultAppearance() {
  const success = await settingsStore.saveSection('theming', {
    mode: themingStore.mode,
    font: themingStore.font,
    layout: themingStore.layout,
  }, 'appearance')
  if (success) {
    settingsStore.markSectionClean('theming', 'appearance')
    toast.success('تم حفظ إعدادات المظهر الافتراضي بنجاح')
  } else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="إعدادات النظام"
      subtitle="إعدادات المنصة التي تؤثر على جميع المستخدمين — يقتصر الوصول على مدير النظام"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'إعدادات النظام' }]"
    />

    <div dir="rtl" class="flex flex-col gap-6 lg:flex-row">
      <!-- ── Desktop: left sidebar nav ───────────────────────────────────── -->
      <aside class="hidden lg:block lg:w-56 lg:shrink-0">
        <nav class="flex flex-col gap-1 rounded-xl border border-border bg-card p-2">
          <Button
            v-for="tab in systemTabs"
            :key="tab.value"
            type="button"
            variant="ghost"
            :data-tab="tab.dataTab"
            :data-testid="tab.testId"
            :class="cn(
              'h-auto w-full justify-start gap-2 rounded-lg px-3 py-2.5 text-start',
              activeTab === tab.value
                ? 'bg-muted font-medium text-foreground hover:bg-muted'
                : 'text-muted-foreground hover:text-foreground',
            )"
            :aria-current="activeTab === tab.value ? 'page' : undefined"
            @click="activeTab = tab.value"
          >
            <component :is="tab.icon" class="size-4 shrink-0" />
            {{ tab.label }}
          </Button>
        </nav>
      </aside>

      <div class="min-w-0 flex-1">
        <Tabs v-model="activeTab" class="space-y-6">
          <!-- Mobile: select dropdown -->
          <Select v-model="activeTab" class="sm:hidden">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="اختر القسم" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="tab in systemTabs" :key="tab.value" :value="tab.value">
                {{ tab.label }}
              </SelectItem>
            </SelectContent>
          </Select>

          <!-- Tablet: horizontal tabs -->
          <TabsList class="hidden h-auto flex-wrap gap-1 p-1 sm:flex lg:hidden">
            <TabsTrigger
              v-for="tab in systemTabs"
              :key="tab.value"
              :value="tab.value"
              :data-tab="tab.dataTab"
              :data-testid="tab.testId"
              class="gap-1.5"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </TabsTrigger>
          </TabsList>

          <!-- ── General ──────────────────────────────────────────────────── -->
          <TabsContent value="general" data-panel="general" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Cog class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>معلومات المنصة</CardTitle>
                    <CardDescription>الاسم الرسمي، الجهة المشغّلة، اللغة الافتراضية، والمنطقة الزمنية</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="space-y-6 pt-6">
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
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Branding ─────────────────────────────────────────────────── -->
          <TabsContent value="branding" data-panel="branding" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Palette class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>الهوية البصرية</CardTitle>
                    <CardDescription>الشعار ولون العلامة — تؤثر على مظهر المنصة لجميع المستخدمين</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="space-y-6 pt-6">
                <FieldGroup>
                  <Field orientation="horizontal">
                    <FieldContent>
                      <FieldTitle>شعار الجهة</FieldTitle>
                      <FieldDescription>SVG أو PNG أو JPG حتى 800×400 بكسل</FieldDescription>
                    </FieldContent>
                    <label class="flex min-h-24 w-full max-w-64 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/20 px-4 text-center text-sm text-muted-foreground transition-colors hover:bg-muted/40">
                      <Image class="h-5 w-5 text-primary" />
                      <span>{{ themingStore.brandLogoName || 'اختيار شعار' }}</span>
                      <input type="file" accept=".svg,.png,.jpg,.jpeg" class="sr-only" @change="handleLogoFile">
                    </label>
                  </Field>

                  <Separator />

                  <Field orientation="horizontal">
                    <FieldContent>
                      <FieldTitle>لون العلامة</FieldTitle>
                      <FieldDescription>يُحدّث متغيرات اللون الأساسية في جميع واجهات المنصة</FieldDescription>
                    </FieldContent>
                    <div class="flex w-full max-w-64 items-center gap-2">
                      <input
                        :value="themingStore.brandColor"
                        type="color"
                        class="size-10 cursor-pointer rounded-md border bg-background"
                        @input="updateBrandColor"
                      >
                      <Input
                        :model-value="themingStore.brandColor"
                        dir="ltr"
                        class="font-mono"
                        @update:model-value="themingStore.setBrandColor(String($event))"
                      />
                    </div>
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
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Default Appearance ───────────────────────────────────────── -->
          <TabsContent value="appearance" data-panel="appearance" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Monitor class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>المظهر الافتراضي للنظام</CardTitle>
                    <CardDescription>القيم الافتراضية التي يرثها كل مستخدم لم يُخصّص مظهره بعد</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="space-y-8 pt-6">
                <!-- Theme mode -->
                <div class="space-y-3">
                  <Label class="text-sm font-medium">وضع الثيم الافتراضي</Label>
                  <p class="text-xs text-muted-foreground">يُطبَّق على المستخدمين الجدد أو من لم يختاروا وضعاً بعد.</p>
                  <RadioGroup
                    :model-value="themingStore.mode"
                    class="grid gap-4 sm:grid-cols-3"
                    @update:model-value="updateThemeMode"
                  >
                    <div
                      v-for="(option, index) in themeOptions"
                      :key="option.value"
                      class="min-w-0"
                    >
                      <div class="relative" @click="lastClickEvent = $event as MouseEvent">
                        <RadioGroupItem
                          :id="`sys-theme-${option.value}`"
                          :value="option.value"
                          class="absolute inset-0 z-10 size-full cursor-pointer rounded-xl opacity-0"
                          :aria-label="option.label"
                        />
                        <div class="flex h-[150px] items-end overflow-hidden rounded-xl border border-input bg-background transition-all peer-data-checked:border-2 peer-data-checked:border-primary">
                          <div v-if="option.value === 'light'" class="grid size-full grid-cols-[48px_1fr] bg-white">
                            <div class="flex flex-col gap-2 border-e border-[#e5e7eb] bg-[#f8fafc] p-3">
                              <div class="size-2.5 rounded-full bg-[#e5e7eb]" />
                              <div class="h-1.5 rounded bg-[#d5dbe5]" /><div class="h-1.5 rounded bg-[#d5dbe5]" /><div class="h-1.5 rounded bg-[#d5dbe5]" />
                            </div>
                            <div class="flex flex-col gap-3 p-4">
                              <div class="h-8 rounded bg-[#f1f3f5]" />
                              <div class="grid grid-cols-2 gap-2"><div class="h-10 rounded bg-[#f1f3f5]" /><div class="h-10 rounded bg-[#f1f3f5]" /></div>
                            </div>
                          </div>
                          <div v-else-if="option.value === 'dark'" class="grid size-full grid-cols-[48px_1fr] bg-[#151820]">
                            <div class="flex flex-col gap-2 border-e border-white/10 bg-[#0f1218] p-3">
                              <div class="size-2.5 rounded-full bg-[#2c3340]" />
                              <div class="h-1.5 rounded bg-[var(--color-primary)]" /><div class="h-1.5 rounded bg-[#343a44]" /><div class="h-1.5 rounded bg-[#343a44]" />
                            </div>
                            <div class="flex flex-col gap-3 p-4">
                              <div class="h-8 rounded bg-[#2a2e3a]" />
                              <div class="grid grid-cols-2 gap-2"><div class="h-10 rounded bg-[#2a2e3a]" /><div class="h-10 rounded bg-[#2a2e3a]" /></div>
                            </div>
                          </div>
                          <div v-else class="grid size-full grid-cols-[48px_1fr] bg-white">
                            <div class="flex flex-col gap-2 border-e border-[#e5e7eb] bg-[#f8fafc] p-3">
                              <div class="size-2.5 rounded-full bg-[#e5e7eb]" />
                              <div class="h-1.5 rounded bg-[#d5dbe5]" /><div class="h-1.5 rounded bg-[#d5dbe5]" />
                            </div>
                            <div class="relative flex flex-col gap-3 overflow-hidden p-4">
                              <div class="absolute inset-y-0 end-0 w-1/2 rounded-s-[42%] bg-[#151820]" />
                              <div class="relative h-8 rounded bg-[#f1f3f5]" />
                              <div class="relative grid grid-cols-2 gap-2"><div class="h-10 rounded bg-[#f1f3f5]" /><div class="h-10 rounded bg-[#2a2e3a]" /></div>
                            </div>
                          </div>
                          <span
                            v-if="themingStore.mode === option.value"
                            class="absolute bottom-3 start-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                          >
                            <Check class="h-3 w-3" />
                          </span>
                        </div>
                      </div>
                      <div class="mt-2 flex items-center gap-2">
                        <component :is="option.icon" class="h-4 w-4 text-muted-foreground" />
                        <div class="min-w-0">
                          <p class="text-sm font-medium">{{ option.label }}</p>
                          <p class="truncate text-xs text-muted-foreground">{{ option.description }}</p>
                        </div>
                      </div>
                      <Separator v-if="index < themeOptions.length - 1" class="mt-4 sm:hidden" />
                    </div>
                  </RadioGroup>
                </div>

                <Separator />

                <!-- Font picker -->
                <div class="space-y-3">
                  <Label class="text-sm font-medium">الخط الافتراضي</Label>
                  <p class="text-xs text-muted-foreground">الخط المستخدم افتراضياً لجميع واجهات المنصة.</p>
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
                <div class="space-y-3">
                  <Label class="text-sm font-medium">تخطيط العرض الافتراضي</Label>
                  <p class="text-xs text-muted-foreground">تخطيط صفحة المحتوى الرئيسي الافتراضي.</p>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <button
                      v-for="option in layoutOptions"
                      :key="option.value"
                      type="button"
                      class="flex min-h-20 cursor-pointer items-center gap-3 rounded-lg border p-4 text-start transition-colors hover:bg-muted/50"
                      :class="themingStore.layout === option.value ? 'border-primary bg-primary/5' : 'border-border'"
                      @click="updateLayout(option.value)"
                    >
                      <component :is="option.icon" class="h-5 w-5 shrink-0 text-primary" />
                      <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-medium">{{ option.label }}</span>
                        <span class="text-xs text-muted-foreground">{{ option.description }}</span>
                      </span>
                    </button>
                  </div>
                </div>

                <div class="flex justify-end">
                  <Button
                    :disabled="!settingsStore.isSectionDirty('theming', 'appearance') || settingsStore.saving"
                    @click="saveDefaultAppearance"
                  >
                    <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                    حفظ المظهر الافتراضي
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Security ─────────────────────────────────────────────────── -->
          <TabsContent value="security" data-panel="security" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-destructive/10">
                    <ShieldAlert class="h-4 w-4 text-destructive" />
                  </div>
                  <div>
                    <CardTitle>سياسات الأمن</CardTitle>
                    <CardDescription>معايير الحماية الإلزامية على مستوى المنصة لجميع المستخدمين</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="pt-6">
                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                  <div class="rounded-lg border bg-muted/20 p-3">
                    <div class="text-xs text-muted-foreground">عتبة قفل الحساب</div>
                    <div data-testid="lockout-threshold" class="text-sm font-semibold">10 محاولات</div>
                  </div>
                  <div class="rounded-lg border bg-muted/20 p-3">
                    <div class="text-xs text-muted-foreground">مدة القفل</div>
                    <div data-testid="lockout-duration" class="text-sm font-semibold">15 دقيقة</div>
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
                    v-for="item in securitySettings"
                    :key="item.label"
                    class="flex items-center justify-between rounded-lg border border-border p-4 transition-colors hover:bg-muted/30"
                  >
                    <div class="flex items-center gap-3">
                      <Lock class="h-4 w-4 shrink-0 text-muted-foreground" />
                      <span class="text-sm">{{ item.label }}</span>
                    </div>
                    <Switch
                      v-model="item.enabled"
                      :data-testid="item.label.includes('MFA') || item.label.includes('مصادقة') ? 'security-switch-mfa' : undefined"
                    />
                  </div>
                </div>
                <div class="flex justify-end pt-6">
                  <Button
                    :disabled="!settingsStore.isSectionDirty('security') || settingsStore.saving"
                    @click="saveSecuritySettings"
                  >
                    <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                    حفظ سياسات الأمن
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Notifications ────────────────────────────────────────────── -->
          <TabsContent value="notif" data-panel="notifications" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Bell class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>قنوات الإشعارات النظامية</CardTitle>
                    <CardDescription>قنوات التنبيه المفعّلة لأحداث سير العمل على مستوى النظام</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="pt-6">
                <div class="space-y-2">
                  <div
                    v-for="item in notificationSettings"
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
                    @click="saveNotificationSettings"
                  >
                    <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                    حفظ إعدادات الإشعارات
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Email ────────────────────────────────────────────────────── -->
          <TabsContent value="email" data-panel="email" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Server class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>إعدادات البريد الإلكتروني</CardTitle>
                    <CardDescription>تكوين خادم البريد الصادر للإشعارات والمراسلات الرسمية</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="space-y-8 pt-6">
                <div class="space-y-4">
                  <h3 class="text-sm font-semibold">إعدادات الخادم</h3>
                  <div class="grid gap-5 md:grid-cols-2">
                    <FieldGroup>
                      <FieldLabel>SMTP Host</FieldLabel>
                      <Input v-model="emailSettings.host" dir="ltr" placeholder="smtp.example.com" />
                    </FieldGroup>
                    <FieldGroup>
                      <FieldLabel>المنفذ (Port)</FieldLabel>
                      <Input v-model="emailSettings.port" dir="ltr" type="number" placeholder="587" />
                    </FieldGroup>
                  </div>
                </div>
                <Separator />
                <div class="space-y-4">
                  <h3 class="text-sm font-semibold">بيانات الاعتماد</h3>
                  <div class="grid gap-5 md:grid-cols-2">
                    <FieldGroup>
                      <FieldLabel>اسم المستخدم</FieldLabel>
                      <Input v-model="emailSettings.username" dir="ltr" />
                    </FieldGroup>
                    <FieldGroup>
                      <FieldLabel>كلمة المرور</FieldLabel>
                      <div class="relative">
                        <Input
                          v-model="emailSettings.password"
                          dir="ltr"
                          :type="showEmailPassword ? 'text' : 'password'"
                          placeholder="••••••••"
                          class="pe-10"
                        />
                        <button
                          type="button"
                          class="absolute inset-y-0 end-0 flex cursor-pointer items-center px-3 text-muted-foreground transition-colors hover:text-foreground"
                          :aria-label="showEmailPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
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
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Workflow ──────────────────────────────────────────────────── -->
          <TabsContent value="workflow" data-panel="workflow" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Workflow class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>إعدادات سير العمل</CardTitle>
                    <CardDescription>تكوين معاملات الموافقة، اللجان، وقواعد التصويت</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="space-y-8 pt-6">
                <div class="space-y-4">
                  <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold">هيكل اللجان</h3>
                    <Badge variant="secondary" class="text-xs">تنظيمي</Badge>
                  </div>
                  <div class="grid gap-5 md:grid-cols-2">
                    <FieldGroup>
                      <FieldLabel>عدد أعضاء اللجنة المساندة</FieldLabel>
                      <Input v-model="workflowSettings.supportMembers" type="number" min="1" data-testid="input-support-members" />
                    </FieldGroup>
                    <FieldGroup>
                      <FieldLabel>عدد أعضاء اللجنة التنفيذية</FieldLabel>
                      <Input v-model="workflowSettings.executiveMembers" type="number" min="1" data-testid="input-executive-members" />
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
                      <Input v-model="workflowSettings.quorum" type="number" min="1" data-testid="input-quorum" />
                    </FieldGroup>
                    <FieldGroup>
                      <FieldLabel>مهلة المراجعة (ساعات)</FieldLabel>
                      <Input v-model="workflowSettings.reviewHours" type="number" min="1" data-testid="input-review-hours" />
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
                      <Switch v-model="workflowSettings.hiddenVoting" data-testid="switch-hidden-voting" />
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-border p-4">
                      <div>
                        <p class="text-sm font-medium">ترجيح صوت المدير عند التعادل</p>
                        <p class="text-xs text-muted-foreground">يملك مدير اللجنة صلاحية كسر التعادل</p>
                      </div>
                      <Switch v-model="workflowSettings.managerWeight" data-testid="switch-manager-weight" />
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
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  </div>
</template>
