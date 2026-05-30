<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
import {
  useThemingStore,
  type LayoutMode,
  type SidebarCollapsible,
  type SidebarVariant,
  type ThemeMode,
} from '@/stores/theming.store'
import { useSettingsStore } from '@/stores/settings.store'
import ProfilePage from '@/pages/profile.vue'

definePageMeta({ middleware: ['auth'] })

useHead({ title: 'إعداداتي' })

const themingStore = useThemingStore()
const settingsStore = useSettingsStore()
const route = useRoute()
const router = useRouter()

// ── Nav items ──────────────────────────────────────────────────────────────────
const userTabs = [
  { value: 'profile', label: 'الملف الشخصي', icon: UserRound, dataTab: 'profile', testId: 'tab-profile' },
  { value: 'appearance', label: 'المظهر الشخصي', icon: Sliders, dataTab: 'appearance', testId: 'tab-appearance' },
  { value: 'notif', label: 'التنبيهات', icon: Bell, dataTab: 'notifications', testId: 'tab-notif' },
] as const

type UserTab = (typeof userTabs)[number]['value']

const activeTab = ref<UserTab>('profile')

function normalizeTab(raw: unknown): UserTab | null {
  if (typeof raw !== 'string') return null
  return userTabs.some(t => t.value === raw) ? (raw as UserTab) : null
}

// ── Appearance options ─────────────────────────────────────────────────────────
const fontPickerOpen = ref(false)
const lastClickEvent = ref<MouseEvent | undefined>(undefined)

const themeOptions: Array<{ value: ThemeMode, label: string, description: string, icon: typeof Sun }> = [
  { value: 'dark', label: 'داكن', description: 'واجهة منخفضة السطوع', icon: Moon },
  { value: 'light', label: 'فاتح', description: 'واجهة عالية الوضوح', icon: Sun },
  { value: 'system', label: 'النظام', description: 'حسب إعداد الجهاز', icon: Monitor },
]

const layoutOptions: Array<{ value: LayoutMode, label: string, description: string, icon: typeof Maximize2 }> = [
  { value: 'full', label: 'كامل العرض', description: 'يستخدم كامل مساحة الشاشة', icon: Maximize2 },
  { value: 'boxed', label: 'محدود العرض', description: 'محتوى مركزي للقراءة الهادئة', icon: Square },
]

const sidebarVariantOptions: Array<{
  value: SidebarVariant
  label: string
  description: string
  icon: typeof PanelLeft
}> = [
  { value: 'sidebar', label: 'ثابت', description: 'شريط جانبي أرضي مدمج', icon: PanelLeft },
  { value: 'floating', label: 'عائم', description: 'شريط مرتفع بظل خفيف', icon: PanelLeftDashed },
  { value: 'inset', label: 'مضمّن', description: 'محتوى غارق داخل الصفحة', icon: Columns2 },
]

const sidebarCollapsibleOptions: Array<{
  value: SidebarCollapsible
  label: string
  description: string
  icon: typeof PanelLeft
}> = [
  { value: 'offcanvas', label: 'خارج الشاشة', description: 'يختفي الشريط الجانبي تماماً عند الطي', icon: PanelLeftClose },
  { value: 'icon', label: 'أيقونات فقط', description: 'يتقلص إلى أيقونات مع الحفاظ على المساحة', icon: PanelLeft },
  { value: 'none', label: 'ثابت دائماً', description: 'لا يمكن طي الشريط الجانبي', icon: PanelLeft },
]

const radiusOptions: Array<{ value: string, label: string, class: string }> = [
  { value: 'none', label: 'بدون', class: 'rounded-none' },
  { value: 'sm', label: 'صغير', class: 'rounded-sm' },
  { value: 'md', label: 'متوسط', class: 'rounded-md' },
  { value: 'lg', label: 'كبير', class: 'rounded-lg' },
  { value: 'xl', label: 'كبير جداً', class: 'rounded-xl' },
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

watch(
  () => ({
    mode: themingStore.mode,
    font: themingStore.font,
    layout: themingStore.layout,
    sidebarVariant: themingStore.sidebarVariant,
    sidebarCollapsible: themingStore.sidebarCollapsible,
    radius: themingStore.radius,
    density: themingStore.density,
    reducedMotion: themingStore.reducedMotion,
  }),
  () => settingsStore.markSectionDirty('userAppearance'),
  { deep: true },
)

// ── Handlers ───────────────────────────────────────────────────────────────────
function updateThemeMode(value: unknown) {
  if (value === 'system' || value === 'light' || value === 'dark')
    themingStore.setMode(value, lastClickEvent.value)
}

function selectFont(fontValue: string) {
  themingStore.setFont(fontValue)
  fontPickerOpen.value = false
}

async function saveAppearance() {
  const ok = await settingsStore.saveSection('userAppearance', {
    mode: themingStore.mode,
    font: themingStore.font,
    layout: themingStore.layout,
    sidebarVariant: themingStore.sidebarVariant,
    sidebarCollapsible: themingStore.sidebarCollapsible,
    radius: themingStore.radius,
    density: themingStore.density,
    reducedMotion: themingStore.reducedMotion,
  })
  if (ok) {
    settingsStore.markSectionClean('userAppearance')
    toast.success('تم حفظ إعدادات المظهر الشخصي بنجاح')
  } else {
    toast.error(settingsStore.error || 'فشل حفظ الإعدادات')
  }
}

function savePersonalNotifications() {
  toast.promise(
    settingsStore.saveSection('userNotifications', { settings: personalNotifications.value }),
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

    <div dir="rtl" class="flex flex-col gap-6 lg:flex-row">
      <!-- ── Desktop: left sidebar nav ───────────────────────────────────── -->
      <aside class="hidden lg:block lg:w-56 lg:shrink-0">
        <nav class="flex flex-col gap-1 rounded-xl border border-border bg-card p-2">
          <Button
            v-for="tab in userTabs"
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
              <SelectItem v-for="tab in userTabs" :key="tab.value" :value="tab.value">
                {{ tab.label }}
              </SelectItem>
            </SelectContent>
          </Select>

          <!-- Tablet: horizontal tabs -->
          <TabsList class="hidden h-auto flex-wrap gap-1 p-1 sm:flex lg:hidden">
            <TabsTrigger
              v-for="tab in userTabs"
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

          <!-- ── Profile ──────────────────────────────────────────────────── -->
          <TabsContent value="profile" data-panel="profile">
            <ProfilePage :embedded="true" />
          </TabsContent>

          <!-- ── Appearance ───────────────────────────────────────────────── -->
          <TabsContent value="appearance" data-panel="appearance" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Sliders class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>المظهر الشخصي</CardTitle>
                    <CardDescription>تخصيص واجهة المنصة لتناسب تفضيلاتك — لا تؤثر على المستخدمين الآخرين</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="space-y-10 pt-6">

                <!-- Section: Theme mode -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">وضع الثيم</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">اختر وضع عرض الواجهة المناسب لبيئة عملك.</p>
                  </div>
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
                          :id="`theme-${option.value}`"
                          :value="option.value"
                          class="absolute inset-0 z-10 size-full cursor-pointer rounded-xl opacity-0"
                          :aria-label="option.label"
                        />
                        <div
                          class="flex h-[150px] items-end overflow-hidden rounded-xl border border-input bg-background transition-all"
                          :class="themingStore.mode === option.value ? 'border-2 border-primary ring-2 ring-primary/20' : ''"
                        >
                          <!-- Light preview -->
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
                          <!-- Dark preview -->
                          <div v-else-if="option.value === 'dark'" class="grid size-full grid-cols-[48px_1fr] bg-[#151820]">
                            <div class="flex flex-col gap-2 border-e border-white/10 bg-[#0f1218] p-3">
                              <div class="size-2.5 rounded-full bg-[#2c3340]" />
                              <div class="h-1.5 rounded bg-primary" /><div class="h-1.5 rounded bg-[#343a44]" /><div class="h-1.5 rounded bg-[#343a44]" />
                            </div>
                            <div class="flex flex-col gap-3 p-4">
                              <div class="h-8 rounded bg-[#2a2e3a]" />
                              <div class="grid grid-cols-2 gap-2"><div class="h-10 rounded bg-[#2a2e3a]" /><div class="h-10 rounded bg-[#2a2e3a]" /></div>
                            </div>
                          </div>
                          <!-- System preview -->
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
                </section>

                <Separator />

                <!-- Section: Font -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">الخط</h3>
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

                <!-- Section: Layout -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">تخطيط المحتوى</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">كيفية توزيع المحتوى الرئيسي على الشاشة.</p>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <button
                      v-for="option in layoutOptions"
                      :key="option.value"
                      type="button"
                      class="flex min-h-20 cursor-pointer items-center gap-3 rounded-lg border p-4 text-start transition-colors hover:bg-muted/50"
                      :class="themingStore.layout === option.value
                        ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                        : 'border-border'"
                      @click="themingStore.setLayout(option.value)"
                    >
                      <component :is="option.icon" class="h-5 w-5 shrink-0 text-primary" />
                      <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-medium">{{ option.label }}</span>
                        <span class="text-xs text-muted-foreground">{{ option.description }}</span>
                      </span>
                      <Check v-if="themingStore.layout === option.value" class="ms-auto h-4 w-4 shrink-0 text-primary" />
                    </button>
                  </div>
                </section>

                <Separator />

                <!-- Section: Sidebar variant -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">نمط الشريط الجانبي</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">كيف يظهر الشريط الجانبي بالنسبة لمنطقة المحتوى.</p>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-3">
                    <button
                      v-for="option in sidebarVariantOptions"
                      :key="option.value"
                      type="button"
                      class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                      :class="themingStore.sidebarVariant === option.value
                        ? 'border-primary ring-2 ring-primary/20'
                        : 'border-border'"
                      @click="themingStore.setSidebarVariant(option.value)"
                    >
                      <!-- Mini preview -->
                      <div class="relative h-24 w-full bg-muted/30">
                        <!-- Sidebar preview -->
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
                          class="absolute top-2 end-2 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
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

                <!-- Section: Sidebar collapsible -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">سلوك طي الشريط الجانبي</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">كيف يتصرف الشريط عند الضغط على زر الطي.</p>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-3">
                    <button
                      v-for="option in sidebarCollapsibleOptions"
                      :key="option.value"
                      type="button"
                      class="flex cursor-pointer flex-col overflow-hidden rounded-xl border text-start transition-all hover:shadow-sm"
                      :class="themingStore.sidebarCollapsible === option.value
                        ? 'border-primary ring-2 ring-primary/20'
                        : 'border-border'"
                      @click="themingStore.setSidebarCollapsible(option.value)"
                    >
                      <!-- Mini preview -->
                      <div class="relative h-24 w-full bg-muted/30">
                        <template v-if="option.value === 'offcanvas'">
                          <!-- No sidebar -->
                          <div class="absolute inset-2 rounded bg-card border border-border" />
                          <div class="absolute top-3 start-3 flex size-5 items-center justify-center rounded-sm bg-primary/10">
                            <PanelLeftClose class="h-3 w-3 text-primary/60" />
                          </div>
                        </template>
                        <template v-else-if="option.value === 'icon'">
                          <!-- Icon-only sidebar -->
                          <div class="absolute inset-y-0 start-0 w-6 bg-card border-e border-border flex flex-col items-center gap-1 py-2">
                            <div class="size-2 rounded-sm bg-primary/40" />
                            <div class="size-2 rounded-sm bg-muted" />
                            <div class="size-2 rounded-sm bg-muted" />
                          </div>
                          <div class="absolute inset-y-2 start-8 end-2 rounded bg-card border border-border" />
                        </template>
                        <template v-else>
                          <!-- Full sidebar always visible -->
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
                          class="absolute top-2 end-2 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
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

                <!-- Section: Radius -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">نصف قطر الحواف</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">درجة استدارة حواف المكونات كالأزرار والبطاقات.</p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="opt in radiusOptions"
                      :key="opt.value"
                      type="button"
                      class="flex h-16 w-16 cursor-pointer flex-col items-center justify-center gap-1.5 border bg-muted/20 transition-all hover:bg-muted/50"
                      :class="[opt.class, themingStore.radius === opt.value ? 'border-primary ring-1 ring-primary/30' : 'border-border']"
                      @click="themingStore.setRadius(opt.value as any)"
                    >
                      <div class="h-6 w-6 bg-primary/30" :class="opt.class" />
                      <span class="text-xs text-muted-foreground">{{ opt.label }}</span>
                    </button>
                  </div>
                </section>

                <Separator />

                <!-- Section: Density -->
                <section class="space-y-4">
                  <div>
                    <h3 class="text-sm font-semibold">كثافة العرض</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">التباعد بين عناصر الواجهة.</p>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <button
                      v-for="opt in [{ value: 'comfortable', label: 'مريح', description: 'تباعد واسع بين العناصر' }, { value: 'compact', label: 'مضغوط', description: 'عرض أكثر معلومات في مساحة أقل' }]"
                      :key="opt.value"
                      type="button"
                      class="flex min-h-16 cursor-pointer items-center gap-3 rounded-lg border p-4 text-start transition-colors hover:bg-muted/50"
                      :class="themingStore.density === opt.value ? 'border-primary bg-primary/5' : 'border-border'"
                      @click="themingStore.setDensity(opt.value as any)"
                    >
                      <div class="flex flex-col gap-1 shrink-0">
                        <div v-if="opt.value === 'comfortable'" class="flex flex-col gap-1.5">
                          <div class="h-1.5 w-10 rounded bg-muted-foreground/30" />
                          <div class="h-1.5 w-8 rounded bg-muted-foreground/30" />
                        </div>
                        <div v-else class="flex flex-col gap-0.5">
                          <div class="h-1 w-10 rounded bg-muted-foreground/30" />
                          <div class="h-1 w-8 rounded bg-muted-foreground/30" />
                          <div class="h-1 w-6 rounded bg-muted-foreground/30" />
                        </div>
                      </div>
                      <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-medium">{{ opt.label }}</span>
                        <span class="text-xs text-muted-foreground">{{ opt.description }}</span>
                      </span>
                      <Check v-if="themingStore.density === opt.value" class="ms-auto h-4 w-4 shrink-0 text-primary" />
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
              </CardContent>
            </Card>
          </TabsContent>

          <!-- ── Personal Notifications ────────────────────────────────────── -->
          <TabsContent value="notif" data-panel="notifications" class="space-y-6">
            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                    <Bell class="h-4 w-4 text-primary" />
                  </div>
                  <div>
                    <CardTitle>إشعاراتي الشخصية</CardTitle>
                    <CardDescription>اختر الأحداث التي تريد تلقّي إشعار عنها</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <Separator />
              <CardContent class="pt-6">
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
                <div class="flex justify-end pt-6">
                  <Button
                    :disabled="settingsStore.saving"
                    @click="savePersonalNotifications"
                  >
                    <Loader2 v-if="settingsStore.saving" class="ms-2 h-4 w-4 animate-spin" />
                    حفظ إعدادات التنبيهات
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
