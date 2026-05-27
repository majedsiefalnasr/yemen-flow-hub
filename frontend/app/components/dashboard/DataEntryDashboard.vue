<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, FileText, Zap, Bell, AlertCircle, AlertTriangle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { DataEntryDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getBusinessStatus } from '../../constants/workflow'
import { Card, CardContent } from '../ui/card'

const router = useRouter()
const store = useDashboardStore()

const stats = computed(() => store.stats as DataEntryDashboardStats | null)

const hasAnyRequests = computed(() =>
  stats.value !== null && (
    (stats.value.completed ?? 0) > 0
    || (stats.value.under_cby_processing ?? 0) > 0
    || returnedCount.value > 0
    || (stats.value.draft ?? 0) > 0
    || (stats.value.recent_requests?.length ?? 0) > 0
    || (stats.value.draft_requests?.length ?? 0) > 0
  ),
)

// Prefer the backend's aggregate `returned` count when present; only fall back
// to `returned_requests.length` (a sample slice, max 5) when the aggregate is
// unset. Adding the two double-counted the same items in the action strip.
const returnedCount = computed(() =>
  stats.value?.returned ?? stats.value?.returned_requests?.length ?? 0,
)

const actionRequiredCount = computed(() => returnedCount.value)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-green-700 bg-green-50/10',
    blue: 'text-primary bg-primary/10',
    amber: 'text-amber-600 bg-amber-50/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] ?? colors.gray!
}

const kpiConfig = computed(() => [
  {
    icon: CheckCircle2,
    value: stats.value?.completed ?? 0,
    label: 'مكتمل / صدر التأكيد',
    variant: 'green',
    tab: 'completed',
  },
  {
    icon: Clock,
    value: stats.value?.under_cby_processing ?? 0,
    label: 'قيد معالجة CBY',
    variant: 'blue',
    tab: 'processing',
  },
  {
    icon: RotateCcw,
    value: returnedCount.value,
    label: 'بحاجة تعديل',
    variant: returnedCount.value > 0 ? 'amber' : 'gray',
    tab: 'returned',
  },
  {
    icon: FileText,
    value: stats.value?.draft ?? 0,
    label: 'مسودات',
    variant: 'gray',
    tab: 'draft',
  },
])

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-15 bg-muted rounded mb-3" />
        <div class="h-8 w-10 bg-muted rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-l-4 border-destructive border-b border-border border-r bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-red-700" aria-hidden="true" />
        <span class="text-red-700 flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-background border border-destructive rounded-lg text-red-700 text-sm cursor-pointer hover:bg-red-700/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <!-- Empty state — no requests at all: hide KPI grid entirely -->
    <template v-else-if="stats && !hasAnyRequests">
      <div class="flex flex-col items-center justify-center py-20 gap-4 text-center">
        <FileText class="h-12 w-12 text-muted-foreground" aria-hidden="true" />
        <p class="text-sm text-muted-foreground">لم تبدأ بعد. ابدأ بأول طلب تمويل واردات.</p>
        <button class="px-5 py-2.5 bg-primary text-primary-foreground rounded-2xl text-sm font-semibold hover:opacity-90 transition-opacity" @click="router.push('/requests/new')">+ طلب جديد</button>
      </div>
    </template>

    <template v-else-if="stats">

      <!-- Action-required strip (above KPI grid, hidden when count = 0) -->
      <Card
        v-if="actionRequiredCount > 0"
        class="border-0 border-s-4 border-s-amber-600 bg-amber-50/30 shadow-sm"
        role="alert"
        aria-label="طلبات تحتاج تعديل"
      >
        <CardContent class="pt-4 pb-4 flex items-center gap-3">
          <AlertTriangle class="h-5 w-5 flex-shrink-0 text-amber-600" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <span class="font-semibold text-foreground text-sm">{{ actionRequiredCount }} طلبات تحتاج تعديل</span>
            <p v-if="stats.returned_requests?.length" class="text-xs text-muted-foreground mt-0.5 truncate">
              {{ stats.returned_requests[0]?.reference_number }}
            </p>
          </div>
          <button
            class="flex-shrink-0 px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-xl hover:bg-amber-700 transition-colors"
            @click="router.push('/requests?tab=returned')"
          >
            ابدأ التعديل
          </button>
        </CardContent>
      </Card>

      <!-- KPI grid: 4 clickable cards -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card
            class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow"
            :class="{ 'border-s-4 border-s-amber-600': kpi.variant === 'amber' }"
            role="button"
            tabindex="0"
            :aria-label="`${kpi.label}: ${kpi.value}`"
            @click="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.enter="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.space.prevent="router.push(`/requests?tab=${kpi.tab}`)"
          >
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span class="text-2xl font-semibold leading-none" :class="kpi.variant === 'amber' && kpi.value > 0 ? 'text-amber-600' : kpi.variant === 'green' ? 'text-green-700' : 'text-foreground'">
              {{ kpi.value }}
            </span>
            <span class="text-xs text-muted-foreground">{{ kpi.label }}</span>
          </Card>
        </template>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground mb-3">
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-3 max-md:grid-cols-1 gap-3">
          <!-- إنشاء طلب جديد -->
          <button class="flex flex-col items-start gap-1 p-4 bg-primary text-primary-foreground border-0 rounded-2xl cursor-pointer hover:opacity-90 transition-colors" @click="router.push('/requests/new')">
            <FileText class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">إنشاء طلب جديد</span>
            <span class="text-xs opacity-75">لبدء طلب تمويل جديد</span>
          </button>

          <!-- متابعة طلباتي -->
          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/requests')">
            <FileText class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">متابعة طلباتي</span>
            <span class="text-xs text-muted-foreground">كل ما قدّمت رأيناه</span>
          </button>

          <!-- الإشعارات -->
          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/notifications')">
            <Bell class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر التحديثات على طلباتك</span>
          </button>
        </div>
      </section>

      <!-- Two-column: مسوداتي | آخر نشاطي -->
      <div class="grid grid-cols-2 max-lg:grid-cols-1 gap-4">

        <!-- مسوداتي (draft requests — hidden when empty, no placeholder) -->
        <Card v-if="stats.draft_requests?.length > 0" class="border-0 shadow" aria-labelledby="drafts-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="drafts-heading" class="text-sm font-semibold text-foreground">مسوداتي</h2>
              <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests?tab=draft')">عرض الكل</a>
            </div>
            <table class="w-full border-collapse text-xs" role="table" aria-label="مسوداتي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">التاجر</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in stats.draft_requests.slice(0, 5)"
                  :key="req.id"
                  class="border-t border-muted hover:bg-muted/50 cursor-pointer transition-colors"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><button class="px-2 py-1 bg-background border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors" @click.stop="router.push(`/requests/${req.id}`)">متابعة</button></td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

        <!-- آخر نشاطي (recent requests) -->
        <Card class="border-0 shadow" :class="{ 'col-span-2 max-lg:col-span-1': !stats.draft_requests?.length }" aria-labelledby="recent-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="recent-heading" class="text-sm font-semibold text-foreground">آخر نشاطي</h2>
              <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests')">عرض الكل</a>
            </div>
            <div v-if="!stats.recent_requests?.length" class="py-6 text-center text-sm text-muted-foreground" role="status">لا توجد طلبات بعد</div>
            <table v-else class="w-full border-collapse text-xs" role="table" aria-label="آخر نشاطي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">التاجر</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in stats.recent_requests.slice(0, 5)"
                  :key="req.id"
                  class="border-t border-muted hover:bg-muted/50 cursor-pointer transition-colors"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.DATA_ENTRY" /></td>
                  <td class="py-2 px-2"><button class="px-2 py-1 bg-background border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

      </div>

    </template>
  </div>
</template>
