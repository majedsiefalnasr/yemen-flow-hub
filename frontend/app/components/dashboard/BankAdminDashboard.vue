// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/bank-admin page level
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  FileText,
  Building2,
  Users,
  BarChart3,
  AlertCircle,
  AlertTriangle,
  ShieldAlert,
  RefreshCw,
  Download,
  CalendarDays,
  Clock,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { BankAdminDashboardStats, BankAdminDashboardStatsExtended, BankAdminMonthlyEntry } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import {
  CHART_W,
  CHART_H,
  REJECTION_THRESHOLD,
  buildLine,
  buildArea,
  calcRejectionRate,
  calcShowHealthStrip,
  calcHealthIssues,
} from '../../utils/bank-admin-helpers'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../ui/card'
import { Badge } from '../ui/badge'
import { Button } from '../ui/button'

const router = useRouter()
const store = useDashboardStore()
const stats = computed(() => store.stats as (BankAdminDashboardStats & BankAdminDashboardStatsExtended) | null)
const lastRefreshed = ref(new Date())

// Only mark "last refreshed" once the fetch resolves so the timestamp tracks
// the data the user actually sees, not the moment they clicked refresh.
async function refresh() {
  await store.loadStats()
  if (!store.error) lastRefreshed.value = new Date()
}

function formatTime(d: Date): string {
  return new Intl.DateTimeFormat('ar-YE', { hour: '2-digit', minute: '2-digit' }).format(d)
}

function formatAmount(amount: number): string {
  return new Intl.NumberFormat('ar-YE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('ar-YE', { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(iso))
}

function monthLabel(ym: string): string {
  const [y, m] = ym.split('-')
  return new Intl.DateTimeFormat('ar-YE', { month: 'short' }).format(new Date(Number(y), Number(m) - 1, 1))
}

const rejectionRate = computed(() => calcRejectionRate(stats.value))
const showHealthStrip = computed(() => calcShowHealthStrip(stats.value))
const healthIssues = computed(() => calcHealthIssues(stats.value))

// KPI grid — spec order: Total / In Process / Approved-Completed / Rejected
const kpiGrid = computed(() => {
  if (!stats.value) return []
  return [
    {
      value: stats.value.total,
      label: 'إجمالي الطلبات',
      color: 'var(--locked)',
      bg: 'bg-muted/60',
      border: '',
      tab: 'all',
    },
    {
      value: stats.value.pending ?? stats.value.total - stats.value.approved - stats.value.rejected,
      label: 'قيد المعالجة',
      color: 'var(--brand-color)',
      bg: 'bg-primary/5',
      border: '',
      tab: 'at_cby',
    },
    {
      value: stats.value.approved,
      label: 'مُعتمد ومكتمل',
      color: 'var(--severity-green)',
      bg: 'bg-[var(--severity-green)]/5',
      border: '',
      tab: 'completed',
    },
    {
      value: stats.value.rejected,
      label: 'مرفوض',
      color: 'var(--severity-red)',
      bg: rejectionRate.value > REJECTION_THRESHOLD ? 'bg-[var(--severity-red)]/5' : 'bg-muted/40',
      border: rejectionRate.value > REJECTION_THRESHOLD ? 'border-s-[3px]' : '',
      tab: 'rejected',
    },
  ]
})

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Header toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-muted/30 px-4 py-3">
      <div class="flex items-center gap-2">
        <Badge variant="outline" class="gap-1 rounded-full px-3 py-1 text-xs font-medium text-muted-foreground border-border">
          <ShieldAlert class="size-3" aria-hidden="true" />
          إدارة وعرض
        </Badge>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs" @click="refresh">
          <RefreshCw class="size-3.5" aria-hidden="true" />
          تحديث
        </Button>
        <span class="text-xs text-muted-foreground">
          <Clock class="inline size-3 me-1" aria-hidden="true" />
          آخر تحديث: {{ formatTime(lastRefreshed) }}
        </span>
        <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs">
          <CalendarDays class="size-3.5" aria-hidden="true" />
          النطاق الزمني
        </Button>
        <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs">
          <Download class="size-3.5" aria-hidden="true" />
          تصدير ملخص البنك
        </Button>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow animate-pulse rounded-xl bg-muted/30" aria-hidden="true">
        <div class="h-3.5 w-3/5 bg-muted rounded mb-3" />
        <div class="h-8 w-1/3 bg-muted rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-0 border-s-4 border-s-[var(--severity-red)] bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="size-4.5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
        <span class="text-[var(--severity-red)] flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-background border border-[var(--severity-red)] rounded-lg text-[var(--severity-red)] text-sm cursor-pointer hover:bg-[var(--severity-red)]/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- 4-KPI grid -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" role="list" aria-label="مؤشرات أداء البنك">
        <button
          v-for="kpi in kpiGrid"
          :key="kpi.label"
          class="flex flex-col items-start gap-1.5 p-4 rounded-xl border border-border shadow transition-shadow hover:shadow-md cursor-pointer text-start"
          :class="[kpi.bg, kpi.border]"
          :style="kpi.border ? { borderInlineStartColor: 'var(--severity-red)' } : {}"
          role="listitem"
          :aria-label="`${kpi.label}: ${kpi.value}`"
          @click="router.push(`/requests?tab=${kpi.tab}`)"
        >
          <span class="text-2xl font-bold" :style="{ color: kpi.color }">{{ kpi.value }}</span>
          <span class="text-xs text-muted-foreground">{{ kpi.label }}</span>
        </button>
      </div>

      <!-- Conditional Operational Health strip -->
      <div
        v-if="showHealthStrip"
        class="flex flex-col gap-2 rounded-xl border border-[var(--severity-amber)]/40 bg-[var(--severity-amber)]/5 px-4 py-3"
        role="alert"
        aria-label="تنبيهات صحة التشغيل"
      >
        <div class="flex items-center gap-2">
          <AlertTriangle class="size-4 text-[var(--severity-amber)] flex-shrink-0" aria-hidden="true" />
          <span class="text-sm font-semibold text-[var(--severity-amber)]">تنبيهات صحة التشغيل</span>
        </div>
        <ul class="flex flex-col gap-1 pe-6">
          <li v-for="issue in healthIssues" :key="issue" class="text-xs text-[var(--severity-amber)]">• {{ issue }}</li>
        </ul>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground mb-3 sr-only">إجراءات سريعة</h2>
        <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-3">
          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/requests')">
            <FileText class="size-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طلبات البنك</span>
            <span class="text-xs text-muted-foreground">عرض جميع طلبات البنك</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/merchants')">
            <Building2 class="size-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">التجار</span>
            <span class="text-xs text-muted-foreground">إدارة بيانات التجار</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/staff')">
            <Users class="size-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">الموظفون</span>
            <span class="text-xs text-muted-foreground">إدارة موظفي البنك</span>
          </button>

          <!-- Reports = primary blue per spec -->
          <button class="flex flex-col items-start gap-1 p-4 bg-primary text-primary-foreground rounded-xl cursor-pointer hover:opacity-90 transition-opacity" @click="router.push('/reports')">
            <BarChart3 class="size-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">التقارير</span>
            <span class="text-xs opacity-75">تقارير وتحليلات البنك</span>
          </button>
        </div>
      </section>

      <!-- Monthly Trend SVG — dual lines: volume + approved-completed -->
      <Card v-if="stats.monthly_requests.length" class="border-0 shadow" aria-labelledby="chart-heading">
        <CardHeader class="pb-1">
          <CardTitle id="chart-heading" class="text-sm font-semibold">حركة طلبات البنك الشهرية</CardTitle>
          <CardDescription class="text-xs">الحجم الكلي والمكتمل — {{ stats.monthly_requests.length }} أشهر</CardDescription>
        </CardHeader>
        <CardContent class="p-4 pt-2">
          <svg
            :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
            class="w-full h-20"
            aria-label="مخطط الطلبات الشهرية"
            role="img"
            preserveAspectRatio="none"
          >
            <!-- Volume area fill (primary blue) -->
            <polygon :points="buildArea(stats.monthly_requests as DualEntry[], 'count')" fill="var(--brand-color)" opacity="0.08" />
            <!-- Volume line -->
            <polyline
              :points="buildLine(stats.monthly_requests as DualEntry[], 'count')"
              fill="none"
              stroke="var(--brand-color)"
              stroke-width="2"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
            <!-- Approved-completed line (dashed green) — only if field available -->
            <polyline
              v-if="(stats.monthly_requests as DualEntry[]).some(e => e.approved !== undefined)"
              :points="buildLine(stats.monthly_requests as DualEntry[], 'approved')"
              fill="none"
              stroke="var(--severity-green)"
              stroke-width="1.5"
              stroke-dasharray="4 2"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
          </svg>
          <div class="flex justify-between px-2 mt-1">
            <span v-for="entry in stats.monthly_requests" :key="(entry as BankAdminMonthlyEntry).month" class="text-xs text-muted-foreground">
              {{ monthLabel((entry as BankAdminMonthlyEntry).month) }}
            </span>
          </div>
          <div class="flex gap-4 mt-2">
            <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
              <span class="inline-block h-0.5 w-5 bg-primary rounded-full" />
              الحجم الكلي
            </div>
            <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
              <span class="inline-block h-0.5 w-5 rounded-full" style="background:var(--severity-green);border-top:1px dashed var(--severity-green)" />
              مكتمل ومعتمد
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Recent Bank Requests — max 8 rows, read-only -->
      <Card class="border-0 shadow" aria-labelledby="recent-heading">
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle id="recent-heading" class="text-sm font-semibold">أحدث طلبات البنك</CardTitle>
            <button class="text-xs text-primary hover:underline cursor-pointer" @click="router.push('/requests')">عرض الكل</button>
          </div>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="!stats.recent_requests.length" class="py-8 text-center text-sm text-muted-foreground" role="status">
            لا توجد طلبات بعد
          </div>
          <table v-else class="w-full border-collapse text-xs" role="table" aria-label="أحدث طلبات البنك">
            <thead>
              <tr class="border-b border-border">
                <th scope="col" class="py-2 px-3 text-right font-medium text-muted-foreground">المرجع</th>
                <th scope="col" class="py-2 px-3 text-right font-medium text-muted-foreground">التاجر</th>
                <th scope="col" class="py-2 px-3 text-right font-medium text-muted-foreground">المبلغ</th>
                <th scope="col" class="py-2 px-3 text-right font-medium text-muted-foreground">الحالة</th>
                <th scope="col" class="py-2 px-3 text-right font-medium text-muted-foreground">التقدم</th>
                <th scope="col" class="py-2 px-3 text-right font-medium text-muted-foreground">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="req in stats.recent_requests.slice(0, 8)"
                :key="req.id"
                class="border-t border-muted hover:bg-muted/50 cursor-pointer transition-colors"
                @click="router.push(`/requests/${req.id}`)"
              >
                <td class="py-2 px-3">
                  <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a>
                </td>
                <td class="py-2 px-3 text-foreground">{{ req.merchant?.name ?? req.supplier_name }}</td>
                <td class="py-2 px-3 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount) }} {{ req.currency }}</td>
                <td class="py-2 px-3"><StatusBadge :status="req.status" :role="UserRole.BANK_ADMIN" /></td>
                <td class="py-2 px-3">
                  <div class="flex items-center gap-2 min-w-24">
                    <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                      <div class="h-full bg-primary transition-all" :style="{ width: `${getRequestProgress(req.status)}%` }" />
                    </div>
                    <span class="text-xs text-muted-foreground whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                  </div>
                </td>
                <td class="py-2 px-3">
                  <!-- read-only: no decision buttons -->
                  <button
                    class="px-2 py-1 bg-background border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors cursor-pointer"
                    :aria-label="`عرض الطلب ${req.reference_number}`"
                    @click.stop="router.push(`/requests/${req.id}`)"
                  >
                    عرض
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>

    </template>
  </div>
</template>
