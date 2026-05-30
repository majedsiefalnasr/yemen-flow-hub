<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Check,
  ChevronsUpDown,
  Columns2,
  Loader2,
  Maximize2,
  Monitor,
  Moon,
  PanelLeft,
  PanelLeftClose,
  PanelLeftDashed,
  Sliders,
  Square,
  Sun,
  UserRound,
} from 'lucide-vue-next'
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

definePageMeta({ middleware: ['auth'] })
useHead({ title: 'إعداداتي' })

const themingStore = useThemingStore()
const settingsStore = useSettingsStore()
const authStore = useAuthStore()
const route = useRoute()

const user = computed(() => authStore.user)

// ── Role labels ────────────────────────────────────────────────────────────────
const ROLE_LABELS: Record<string, string> = {
  [UserRole.DATA_ENTRY]: 'مُدخل بيانات',
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
] as const

type UserTab = (typeof userTabs)[number]['value']

const activeSection = computed<UserTab>(() => normalizeSection(route.query.section) ?? 'profile')

function normalizeSection(raw: unknown): UserTab | null {
  if (typeof raw !== 'string') return null
  return userTabs.some(t => t.value === raw) ? (raw as UserTab) : null
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
      subtitle="تفضيلاتك الشخصية — تؤثر على تجربتك فقط ولا تمسّ بقية المستخدمين"
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
