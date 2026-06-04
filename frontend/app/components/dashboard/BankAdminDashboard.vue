// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/bank-admin page
level
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, onMounted, ref, h } from 'vue'
import { useRouter } from 'vue-router'
import {
  FileText,
  Building2,
  Users,
  BarChart3,
  AlertCircle,
  AlertTriangle,
  ShieldAlert,
  Download,
  CalendarDays,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type {
  BankAdminDashboardStats,
  BankAdminDashboardStatsExtended,
  BankAdminMonthlyEntry,
} from '../../composables/useDashboard'
import type { DualEntry } from '../../utils/bank-admin-helpers'
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
import { Skeleton } from '../ui/skeleton'
import DataTable from '../ui/data-table/DataTable.vue'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'
import DashboardToolbar from '../shared/dashboard/DashboardToolbar.vue'

const router = useRouter()
const store = useDashboardStore()
const stats = computed(
  () => store.stats as (BankAdminDashboardStats & BankAdminDashboardStatsExtended) | null,
)
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
  return new Intl.NumberFormat('ar-YE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('ar-YE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(iso))
}

function monthLabel(ym: string): string {
  const [y, m] = ym.split('-')
  return new Intl.DateTimeFormat('ar-YE', { month: 'short' }).format(
    new Date(Number(y), Number(m) - 1, 1),
  )
}

const rejectionRate = computed(() => calcRejectionRate(stats.value))
const showHealthStrip = computed(() => calcShowHealthStrip(stats.value))
const healthIssues = computed(() => calcHealthIssues(stats.value))
const monthlyRequests = computed(() => stats.value?.monthly_requests ?? [])
const recentRequests = computed(() => stats.value?.recent_requests ?? [])

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

type BankRecentRow = NonNullable<BankAdminDashboardStatsExtended['recent_requests']>[number]

const bankRecentColumns: ColumnDef<BankRecentRow>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h(
        'a',
        {
          class: 'font-mono text-primary hover:underline',
          href: `/requests/${row.original.id}`,
          onClick: (event: MouseEvent) => {
            event.preventDefault()
            event.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        row.original.reference_number,
      ),
  },
  {
    id: 'merchant',
    header: 'التاجر',
    cell: ({ row }) => h('span', row.original.merchant?.name ?? row.original.supplier_name),
  },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'direction-ltr font-tabular-nums' },
        `${formatAmount(row.original.amount)} ${row.original.currency}`,
      ),
  },
  {
    id: 'status',
    header: 'الحالة',
    cell: ({ row }) => h(StatusBadge, { status: row.original.status, role: UserRole.BANK_ADMIN }),
  },
  {
    id: 'progress',
    header: 'التقدم',
    cell: ({ row }) =>
      h('div', { class: 'flex items-center gap-2 min-w-24' }, [
        h('div', { class: 'flex-1 h-1.5 bg-muted rounded-full overflow-hidden' }, [
          h('div', {
            class: 'h-full bg-primary transition-all',
            style: { width: `${getRequestProgress(row.original.status)}%` },
          }),
        ]),
        h(
          'span',
          { class: 'text-xs text-muted-foreground whitespace-nowrap' },
          `${getRequestProgress(row.original.status)}%`,
        ),
      ]),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) =>
      h(
        Button,
        {
          size: 'sm',
          variant: 'outline',
          'aria-label': `عرض الطلب ${row.original.reference_number}`,
          onClick: (event: MouseEvent) => {
            event.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        () => 'عرض',
      ),
  },
]

onMounted(() => {
  store.loadStats()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Header toolbar -->
    <DashboardToolbar
      badge-label="إدارة وعرض"
      :badge-icon="ShieldAlert"
      :last-updated="formatTime(lastRefreshed)"
      @refresh="refresh"
    >
      <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs">
        <CalendarDays class="size-3.5" aria-hidden="true" />
        النطاق الزمني
      </Button>
      <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs">
        <Download class="size-3.5" aria-hidden="true" />
        تصدير ملخص البنك
      </Button>
    </DashboardToolbar>

    <!-- Skeleton -->
    <div
      v-if="store.loading"
      class="grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1"
      aria-busy="true"
      aria-label="جارٍ تحميل الإحصائيات"
    >
      <div v-for="n in 4" :key="n" class="rounded-xl border-0 p-4 shadow" aria-hidden="true">
        <Skeleton class="mb-3 h-3.5 w-3/5" />
        <Skeleton class="h-8 w-1/3" />
      </div>
    </div>

    <!-- Error -->
    <Card
      v-else-if="store.error"
      class="bg-background border-0 border-[var(--severity-red)]"
      role="alert"
    >
      <CardContent class="flex items-center gap-3 pt-6">
        <AlertCircle class="size-4.5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
        <span class="flex-1 text-[var(--severity-red)]">{{ store.error }}</span>
        <Button
          variant="outline"
          size="sm"
          class="text-destructive border-destructive"
          @click="store.loadStats()"
        >
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <template v-else-if="stats">
      <!-- 4-KPI grid -->
      <MetricGrid :columns="4" aria-label="مؤشرات أداء البنك">
        <MetricCard
          v-for="kpi in kpiGrid"
          :key="kpi.label"
          :label="kpi.label"
          :value="kpi.value"
          :tone="
            kpi.tab === 'completed'
              ? 'success'
              : kpi.tab === 'rejected' && rejectionRate > REJECTION_THRESHOLD
                ? 'danger'
                : kpi.tab === 'at_cby'
                  ? 'info'
                  : 'default'
          "
          :highlighted="kpi.tab === 'rejected' && rejectionRate > REJECTION_THRESHOLD"
          @click="router.push(`/requests?tab=${kpi.tab}`)"
        />
      </MetricGrid>

      <!-- Conditional Operational Health strip -->
      <Card
        v-if="showHealthStrip"
        class="border-0 border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
        aria-label="تنبيهات صحة التشغيل"
      >
        <CardContent>
          <div class="mb-2 flex items-center gap-2">
            <AlertTriangle
              class="size-4 flex-shrink-0 text-[var(--severity-amber)]"
              aria-hidden="true"
            />
            <span class="text-sm font-semibold text-[var(--severity-amber)]"
              >تنبيهات صحة التشغيل</span
            >
          </div>
          <ul class="flex flex-col gap-1 pe-6">
            <li
              v-for="issue in healthIssues"
              :key="issue"
              class="text-xs text-[var(--severity-amber)]"
            >
              • {{ issue }}
            </li>
          </ul>
        </CardContent>
      </Card>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="sr-only">إجراءات سريعة</h2>
        <div class="grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-md:grid-cols-1">
          <Card
            class="bg-background border-border text-foreground hover:border-primary focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-xl border p-4 transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="طلبات البنك"
            @click="router.push('/requests')"
            @keydown.enter="router.push('/requests')"
            @keydown.space.prevent="router.push('/requests')"
          >
            <FileText class="text-primary mb-1 size-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">طلبات البنك</span>
            <span class="text-muted-foreground text-xs">عرض جميع طلبات البنك</span>
          </Card>

          <Card
            class="bg-background border-border text-foreground hover:border-primary focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-xl border p-4 transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="التجار"
            @click="router.push('/merchants')"
            @keydown.enter="router.push('/merchants')"
            @keydown.space.prevent="router.push('/merchants')"
          >
            <Building2 class="text-primary mb-1 size-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">التجار</span>
            <span class="text-muted-foreground text-xs">إدارة بيانات التجار</span>
          </Card>

          <Card
            class="bg-background border-border text-foreground hover:border-primary focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-xl border p-4 transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="الموظفون"
            @click="router.push('/staff')"
            @keydown.enter="router.push('/staff')"
            @keydown.space.prevent="router.push('/staff')"
          >
            <Users class="text-primary mb-1 size-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">الموظفون</span>
            <span class="text-muted-foreground text-xs">إدارة موظفي البنك</span>
          </Card>

          <!-- Reports = primary blue per spec -->
          <Card
            class="bg-primary text-primary-foreground focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-xl p-4 transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="التقارير"
            @click="router.push('/reports')"
            @keydown.enter="router.push('/reports')"
            @keydown.space.prevent="router.push('/reports')"
          >
            <BarChart3 class="mb-1 size-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">التقارير</span>
            <span class="text-xs opacity-75">تقارير وتحليلات البنك</span>
          </Card>
        </div>
      </section>

      <!-- Monthly Trend SVG — dual lines: volume + approved-completed -->
      <Card v-if="monthlyRequests.length" class="border-0 shadow" aria-labelledby="chart-heading">
        <CardHeader class="pb-1">
          <CardTitle id="chart-heading" class="text-sm font-semibold"
            >حركة طلبات البنك الشهرية</CardTitle
          >
          <CardDescription class="text-xs"
            >الحجم الكلي والمكتمل ({{ monthlyRequests.length }} أشهر)</CardDescription
          >
        </CardHeader>
        <CardContent class="p-4 pt-2">
          <svg
            :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
            class="h-20 w-full"
            aria-label="مخطط الطلبات الشهرية"
            role="img"
            preserveAspectRatio="none"
          >
            <!-- Volume area fill (primary blue) -->
            <polygon
              :points="buildArea(monthlyRequests as DualEntry[], 'count')"
              fill="var(--brand-color)"
              opacity="0.08"
            />
            <!-- Volume line -->
            <polyline
              :points="buildLine(monthlyRequests as DualEntry[], 'count')"
              fill="none"
              stroke="var(--brand-color)"
              stroke-width="2"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
            <!-- Approved-completed line (dashed green) — only if field available -->
            <polyline
              v-if="(monthlyRequests as DualEntry[]).some((e) => e.approved !== undefined)"
              :points="buildLine(monthlyRequests as DualEntry[], 'approved')"
              fill="none"
              stroke="var(--severity-green)"
              stroke-width="1.5"
              stroke-dasharray="4 2"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
          </svg>
          <div class="mt-1 flex justify-between px-2">
            <span
              v-for="entry in monthlyRequests"
              :key="(entry as BankAdminMonthlyEntry).month"
              class="text-muted-foreground text-xs"
            >
              {{ monthLabel((entry as BankAdminMonthlyEntry).month) }}
            </span>
          </div>
          <div class="mt-2 flex gap-4">
            <div class="text-muted-foreground flex items-center gap-1.5 text-xs">
              <span class="bg-primary inline-block h-0.5 w-5 rounded-full" />
              الحجم الكلي
            </div>
            <div class="text-muted-foreground flex items-center gap-1.5 text-xs">
              <span
                class="inline-block h-0.5 w-5 rounded-full"
                style="
                  background: var(--severity-green);
                  border-top: 1px dashed var(--severity-green);
                "
              />
              مكتمل ومعتمد
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Recent Bank Requests — max 8 rows, read-only -->
      <Card class="border-0 shadow" aria-labelledby="recent-heading">
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle id="recent-heading" class="text-sm font-semibold"
              >أحدث طلبات البنك</CardTitle
            >
            <Button
              variant="link"
              size="sm"
              class="h-auto p-0 text-xs"
              @click="router.push('/requests')"
              >عرض الكل</Button
            >
          </div>
        </CardHeader>
        <CardContent class="p-0">
          <DataTable
            :data="recentRequests.slice(0, 8)"
            :columns="bankRecentColumns"
            @row-click="(row) => router.push(`/requests/${row.id}`)"
          >
            <template #empty>لا توجد طلبات بعد</template>
          </DataTable>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
