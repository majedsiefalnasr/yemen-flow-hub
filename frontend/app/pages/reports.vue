<script setup lang="ts">
import type { ColumnDef, VisibilityState } from '@tanstack/vue-table'
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
  Download,
  FileSpreadsheet,
  FileText,
  Filter,
} from 'lucide-vue-next'
import { h } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useReports } from '@/composables/useReports'
import type { WorkflowReport } from '@/composables/useReports'
import { useTableKeyboard } from '@/composables/useTableKeyboard'
import { useTableExport } from '@/composables/useTableExport'
import { DataTableViewOptions } from '@/components/ui/data-table'
import DataTable from '@/components/ui/data-table/DataTable.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'
import { Skeleton } from '@/components/ui/skeleton'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/reports'],
})

const { fetchWorkflowReport, exportReport } = useReports()
const { exportToCSV } = useTableExport()

const report = ref<WorkflowReport | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)
const scheduleQuery = ref('')
const scheduleSearchRef = ref<HTMLInputElement | null>(null)
const scheduleColumnVisibility = ref<VisibilityState>({
  recipients: false,
})
const schedulePagination = ref({ pageIndex: 0, pageSize: 5 })

useTableKeyboard(scheduleSearchRef, {
  onEscape: () => {
    scheduleQuery.value = ''
  },
})

async function loadReport() {
  loading.value = true
  loadError.value = null
  try {
    report.value = await fetchWorkflowReport()
  } catch {
    loadError.value = 'تعذّر تحميل تقرير سير العمل. تحقق من الاتصال وأعد المحاولة.'
    report.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadReport()
})

const total = computed(() => {
  if (!report.value) return 0
  return Object.values(report.value.counts_by_status).reduce((s, v) => s + v, 0)
})
const approved = computed(() => report.value?.throughput.approved ?? 0)
const rejected = computed(() => report.value?.throughput.rejected ?? 0)
const totalValue = computed(() => report.value?.total_financing_value ?? 0)
const approvalRate = computed(() =>
  total.value > 0 ? Math.round((approved.value / total.value) * 100) : 0,
)

const monthly = computed(() => report.value?.monthly_trend ?? [])
const monthlyMax = computed(() =>
  Math.max(...monthly.value.flatMap((m) => [m.total, m.approved]), 1),
)

const categoryDist = computed(() => report.value?.category_distribution ?? [])
const categoryTotal = computed(() => categoryDist.value.reduce((s, item) => s + item.count, 0) || 1)

const heatRows = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس']
const heatCols = ['08', '10', '12', '14', '16', '18']

const kpis = computed(() => [
  {
    label: 'إجمالي الطلبات',
    value: total.value.toLocaleString('en-US'),
    summary: `${approved.value} مُعتمد`,
  },
  { label: 'قيمة التمويل', value: `$${(totalValue.value / 1_000_000).toFixed(1)}M`, summary: '' },
  { label: 'متوسط زمن المعالجة', value: '—', summary: '' },
  { label: 'نسبة الاعتماد', value: `${approvalRate.value}%`, summary: `${rejected.value} مرفوض` },
  {
    label: 'الفواتير المكررة',
    value: (report.value?.duplicate_invoice_count ?? 0).toString(),
    summary: 'تنبيه',
  },
])

const amountByCurrency = computed(() => report.value?.amount_by_currency ?? [])
const maxAmount = computed(() => Math.max(...amountByCurrency.value.map((item) => item.amount), 1))

type ScheduledReportRow = {
  name: string
  cadence: string
  recipients: string
  lastRun: string
  status: 'نشط' | 'متوقف'
}

const scheduledReports: ScheduledReportRow[] = [
  {
    name: 'تقرير أسبوعي للجنة التنفيذية',
    cadence: 'أسبوعي · الأحد 08:00',
    recipients: 'executive@cby.gov.ye',
    lastRun: '27 أكتوبر',
    status: 'نشط',
  },
  {
    name: 'تقرير الفواتير المكررة',
    cadence: 'يومي · 22:00',
    recipients: 'audit@cby.gov.ye',
    lastRun: 'اليوم 22:00',
    status: 'نشط',
  },
  {
    name: 'تحليل البنوك التجارية',
    cadence: 'شهري · 1 من الشهر',
    recipients: 'stats@cby.gov.ye',
    lastRun: '1 أكتوبر',
    status: 'نشط',
  },
  {
    name: 'تقرير الإفراج الجمركي',
    cadence: 'أسبوعي · الخميس',
    recipients: 'customs@customs.gov.ye',
    lastRun: '23 أكتوبر',
    status: 'متوقف',
  },
]

const filteredScheduledReports = computed(() => {
  const q = scheduleQuery.value.trim().toLowerCase()
  if (!q) return scheduledReports
  return scheduledReports.filter(
    (item) =>
      item.name.toLowerCase().includes(q) ||
      item.cadence.toLowerCase().includes(q) ||
      item.recipients.toLowerCase().includes(q),
  )
})

const scheduledReportColumns: ColumnDef<ScheduledReportRow>[] = [
  {
    accessorKey: 'name',
    header: 'اسم التقرير',
    cell: ({ row }) => h('span', { class: 'font-medium' }, row.original.name),
  },
  {
    accessorKey: 'cadence',
    header: 'الفترة',
    cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, row.original.cadence),
  },
  {
    accessorKey: 'recipients',
    header: 'المستلمون',
    cell: ({ row }) => h('span', { class: 'text-xs' }, row.original.recipients),
  },
  {
    accessorKey: 'lastRun',
    header: 'آخر تشغيل',
    cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, row.original.lastRun),
  },
  {
    accessorKey: 'status',
    header: 'الحالة',
    cell: ({ row }) =>
      h(
        Badge,
        { variant: row.original.status === 'نشط' ? 'secondary' : 'outline' },
        () => row.original.status,
      ),
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    cell: () =>
      h(Button, { size: 'sm', variant: 'ghost' }, () => h(Download, { class: 'h-3.5 w-3.5' })),
  },
]

const SCHEDULE_COLUMN_LABELS: Record<string, string> = {
  name: 'اسم التقرير',
  cadence: 'الفترة',
  recipients: 'المستلمون',
  lastRun: 'آخر تشغيل',
  status: 'الحالة',
}

function exportScheduledReports() {
  if (!filteredScheduledReports.value.length) return
  const stamp = new Date().toISOString().slice(0, 10)
  exportToCSV(
    filteredScheduledReports.value as any as Record<string, any>[],
    [
      { key: 'name', label: 'اسم التقرير' },
      { key: 'cadence', label: 'الفترة' },
      { key: 'recipients', label: 'المستلمون' },
      { key: 'lastRun', label: 'آخر تشغيل' },
      { key: 'status', label: 'الحالة' },
    ] as const,
    `scheduled-reports-${stamp}`,
  )
}

function heatValue(rowIndex: number, colIndex: number) {
  return Math.round(((Math.sin(rowIndex * 1.7 + colIndex * 1.3) + 1) / 2) * 80)
}

function heatOpacity(rowIndex: number, colIndex: number) {
  return (0.15 + ((Math.sin(rowIndex * 1.7 + colIndex * 1.3) + 1) / 2) * 0.85).toFixed(2)
}

const colors = [
  'bg-primary',
  'bg-info',
  'bg-[var(--color-surface-warning)]',
  'bg-[var(--severity-red)]',
  'bg-purple-600',
  'bg-emerald-600',
]

const monthlyChartConfig = { requests: { label: 'طلبات' }, approvals: { label: 'مُعتمد' } }
const categoryChartConfig = { value: { label: 'النسبة' } }

type BankBreakdownRow = {
  bank_id?: number
  bank_name: string
  total: number
  approved?: number
  rejected?: number
  total_value?: number
}

const bankBreakdownRows = computed<BankBreakdownRow[]>(() => report.value?.bank_breakdown ?? [])
const bankBreakdownColumns: ColumnDef<BankBreakdownRow>[] = [
  {
    accessorKey: 'bank_name',
    header: 'البنك',
    cell: ({ row }) => h('span', { class: 'font-medium' }, row.original.bank_name),
  },
  {
    accessorKey: 'total',
    header: 'إجمالي الطلبات',
    cell: ({ row }) => h('span', { class: 'tabular-nums' }, row.original.total),
  },
  {
    accessorKey: 'approved',
    header: 'مُعتمد',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'tabular-nums text-[var(--severity-green)]' },
        row.original.approved ?? '—',
      ),
  },
  {
    accessorKey: 'rejected',
    header: 'مرفوض',
    cell: ({ row }) =>
      h('span', { class: 'tabular-nums text-[var(--severity-red)]' }, row.original.rejected ?? '—'),
  },
  {
    id: 'approval_rate',
    header: 'نسبة الاعتماد',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'tabular-nums' },
        row.original.total > 0 && row.original.approved != null
          ? `${Math.round((row.original.approved / row.original.total) * 100)}%`
          : '—',
      ),
  },
  {
    accessorKey: 'total_value',
    header: 'قيمة التمويل',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'tabular-nums text-muted-foreground' },
        (row.original.total_value ?? 0).toLocaleString('en-US'),
      ),
  },
]

type VotingAnalyticsRow = {
  user_id: number
  name: string
  sessions: number
  approvals: number
  rejections: number
  avg_hours?: number | null
}

const votingAnalyticsRows = computed<VotingAnalyticsRow[]>(
  () => report.value?.voting_analytics ?? [],
)
const votingAnalyticsColumns: ColumnDef<VotingAnalyticsRow>[] = [
  {
    accessorKey: 'name',
    header: 'العضو',
    cell: ({ row }) => h('span', { class: 'font-medium' }, row.original.name),
  },
  {
    accessorKey: 'sessions',
    header: 'جلسات شارك بها',
    cell: ({ row }) => h('span', { class: 'tabular-nums' }, row.original.sessions),
  },
  {
    accessorKey: 'approvals',
    header: 'أصوات الاعتماد',
    cell: ({ row }) =>
      h('span', { class: 'tabular-nums text-[var(--severity-green)]' }, row.original.approvals),
  },
  {
    accessorKey: 'rejections',
    header: 'أصوات الرفض',
    cell: ({ row }) =>
      h('span', { class: 'tabular-nums text-[var(--severity-red)]' }, row.original.rejections),
  },
  {
    id: 'approval_rate',
    header: 'نسبة الاعتماد',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'tabular-nums' },
        row.original.sessions > 0
          ? `${Math.round((row.original.approvals / row.original.sessions) * 100)}%`
          : '—',
      ),
  },
  {
    accessorKey: 'avg_hours',
    header: 'متوسط وقت التصويت',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'tabular-nums text-muted-foreground' },
        row.original.avg_hours != null ? `${row.original.avg_hours}س` : '—',
      ),
  },
]
</script>

<template>
  <div>
    <PageHeader
      title="التقارير والتحليلات المتقدمة"
      subtitle="مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التقارير' }]"
    >
      <template #actions>
        <Button variant="outline" :disabled="loading || !!loadError">
          <Calendar class="ms-1 h-4 w-4" />
          الفترة: الكل
        </Button>
        <Button
          variant="outline"
          :disabled="loading || !!loadError"
          @click="exportReport('workflow', 'pdf')"
        >
          <FileText class="ms-1 h-4 w-4" />
          PDF
        </Button>
        <Button :disabled="loading || !!loadError" @click="exportReport('workflow', 'excel')">
          <FileSpreadsheet class="ms-1 h-4 w-4" />
          Excel
        </Button>
      </template>
    </PageHeader>

    <LoadErrorAlert
      v-if="loadError"
      class="mb-4"
      :message="loadError"
      title="تعذّر تحميل التقرير"
      @retry="loadReport()"
    />

    <div
      v-else-if="loading"
      class="mb-6 space-y-4"
      aria-busy="true"
      aria-label="جارٍ تحميل التقرير"
    >
      <div class="grid gap-4 md:grid-cols-5">
        <Skeleton v-for="n in 5" :key="n" class="h-24 rounded-xl" />
      </div>
      <Skeleton class="h-64 w-full rounded-xl" />
    </div>

    <template v-else>
      <div class="mb-6">
        <MetricGrid :columns="5">
          <MetricCard
            v-for="kpi in kpis"
            :key="kpi.label"
            :label="kpi.label"
            :value="kpi.value"
            :previous-label="kpi.summary || undefined"
            :clickable="false"
          />
        </MetricGrid>
      </div>

      <Tabs default-value="executive_summary" class="mb-4">
        <TabsList>
          <TabsTrigger value="executive_summary">الملخص التنفيذي</TabsTrigger>
          <TabsTrigger value="bank_performance">أداء البنوك</TabsTrigger>
          <TabsTrigger value="workflow_sla">SLA سير العمل</TabsTrigger>
          <TabsTrigger value="decisions">القرارات والنتائج</TabsTrigger>
          <TabsTrigger value="voting">التصويت التنفيذي</TabsTrigger>
          <TabsTrigger value="swift_fx">SWIFT والمصارفة</TabsTrigger>
          <TabsTrigger value="compliance">الامتثال والمخاطر</TabsTrigger>
        </TabsList>

        <!-- Executive Summary -->
        <TabsContent value="executive_summary" class="mt-4">
          <div class="grid gap-4 lg:grid-cols-3">
            <Card class="border-0 p-5 shadow lg:col-span-2">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                تطور أحجام الطلبات
              </h3>
              <ChartContainer
                :config="monthlyChartConfig"
                class="bg-muted/10 h-[300px] rounded-lg border p-4"
              >
                <div v-if="monthly.length > 0" class="flex h-full items-end gap-4">
                  <div
                    v-for="month in monthly"
                    :key="month.month"
                    class="flex h-full flex-1 flex-col justify-end gap-2"
                  >
                    <div class="flex flex-1 items-end justify-center gap-2">
                      <div
                        class="bg-primary w-5 rounded-t-md"
                        :style="{ height: `${(month.total / monthlyMax) * 100}%` }"
                        :title="`طلبات: ${month.total}`"
                      />
                      <div
                        class="w-5 rounded-t-md bg-[var(--color-surface-success)]"
                        :style="{ height: `${(month.approved / monthlyMax) * 100}%` }"
                        :title="`مُعتمد: ${month.approved}`"
                      />
                    </div>
                    <div class="text-muted-foreground text-center text-[11px]">
                      {{ month.month }}
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="text-muted-foreground flex h-full items-center justify-center text-sm"
                >
                  لا توجد بيانات
                </div>
              </ChartContainer>
              <div class="text-muted-foreground mt-3 flex items-center gap-4 text-xs">
                <span class="inline-flex items-center gap-1"
                  ><span class="bg-primary h-2.5 w-2.5 rounded" />طلبات</span
                >
                <span class="inline-flex items-center gap-1"
                  ><span
                    class="h-2.5 w-2.5 rounded bg-[var(--color-surface-success)]"
                  />مُعتمد</span
                >
              </div>
            </Card>

            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                حسب الفئة
              </h3>
              <ChartContainer :config="categoryChartConfig" class="space-y-3">
                <div v-for="(item, index) in categoryDist" :key="item.category" class="space-y-1.5">
                  <div class="flex justify-between text-xs">
                    <span>{{ item.category }}</span>
                    <span class="font-semibold"
                      >{{ Math.round((item.count / categoryTotal) * 100) }}%</span
                    >
                  </div>
                  <Progress
                    :class="[
                      'h-2',
                      colors[index % colors.length] === 'bg-primary'
                        ? '[&_[data-slot=progress-indicator]]:bg-primary'
                        : '[&_[data-slot=progress-indicator]]:bg-info',
                    ]"
                    :model-value="(item.count / categoryTotal) * 100"
                  />
                </div>
                <div
                  v-if="categoryDist.length === 0"
                  class="text-muted-foreground py-4 text-center text-xs"
                >
                  لا توجد بيانات
                </div>
              </ChartContainer>
            </Card>

            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                قيمة التمويل بالعملة
              </h3>
              <div class="space-y-3">
                <div
                  v-for="item in amountByCurrency"
                  :key="item.currency"
                  class="grid grid-cols-[48px_1fr_64px] items-center gap-3 text-sm"
                >
                  <span class="font-semibold">{{ item.currency }}</span>
                  <Progress
                    class="[&_[data-slot=progress-indicator]]:bg-info h-3"
                    :model-value="(item.amount / maxAmount) * 100"
                  />
                  <span class="text-muted-foreground text-start text-xs">{{
                    item.amount.toLocaleString('en-US')
                  }}</span>
                </div>
                <div
                  v-if="amountByCurrency.length === 0"
                  class="text-muted-foreground py-4 text-center text-xs"
                >
                  لا توجد بيانات
                </div>
              </div>
            </Card>

            <Card class="border-0 p-5 shadow lg:col-span-2">
              <div class="mb-4 flex items-center justify-between">
                <h3 class="font-semibold">خريطة حرارية: كثافة التقديم خلال الأسبوع</h3>
                <Badge variant="secondary"> آخر 12 أسبوع </Badge>
              </div>
              <div class="overflow-x-auto">
                <div
                  class="inline-grid gap-1"
                  :style="{
                    gridTemplateColumns: `auto repeat(${heatCols.length}, minmax(50px, 1fr))`,
                  }"
                >
                  <div />
                  <div
                    v-for="col in heatCols"
                    :key="col"
                    class="text-muted-foreground text-center text-[10px]"
                  >
                    {{ col }}:00
                  </div>

                  <template v-for="(row, rowIndex) in heatRows" :key="row">
                    <div class="text-muted-foreground py-2 ps-2 text-[11px]">
                      {{ row }}
                    </div>
                    <div
                      v-for="(col, colIndex) in heatCols"
                      :key="`${row}-${col}`"
                      class="grid aspect-square place-items-center rounded text-[10px] font-semibold text-white"
                      :style="{
                        backgroundColor: `oklch(0.4 0.13 220 / ${heatOpacity(rowIndex, colIndex)})`,
                      }"
                    >
                      {{ heatValue(rowIndex, colIndex) }}
                    </div>
                  </template>
                </div>
              </div>
              <div class="text-muted-foreground mt-3 flex items-center gap-2 text-[10px]">
                أقل
                <div
                  v-for="opacity in [0.15, 0.35, 0.55, 0.75, 0.95]"
                  :key="opacity"
                  class="h-3 w-6 rounded"
                  :style="{ backgroundColor: `oklch(0.4 0.13 220 / ${opacity})` }"
                />
                أكثر
              </div>
            </Card>
          </div>

          <Card class="mt-4 border-0 p-5 shadow">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="font-semibold">تقارير مجدولة</h3>
              <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="exportScheduledReports">
                  <Filter class="ms-1 h-3.5 w-3.5" />
                  تصدير
                </Button>
              </div>
            </div>

            <div class="mb-3 flex items-center gap-2">
              <Input
                ref="scheduleSearchRef"
                v-model="scheduleQuery"
                class="h-8 max-w-sm"
                placeholder="بحث في التقارير المجدولة..."
              />
            </div>

            <DataTable
              :data="filteredScheduledReports"
              :columns="scheduledReportColumns"
              :column-visibility="scheduleColumnVisibility"
              :pagination="schedulePagination"
              @update:column-visibility="(v) => (scheduleColumnVisibility = v)"
              @update:pagination="(v) => (schedulePagination = v)"
            >
              <template #toolbar="{ table }">
                <div class="mb-3 flex items-center justify-between gap-2">
                  <p class="text-muted-foreground text-xs">
                    {{ filteredScheduledReports.length }} تقرير
                  </p>
                  <DataTableViewOptions :table="table" :column-labels="SCHEDULE_COLUMN_LABELS" />
                </div>
              </template>
              <template #empty> لا توجد نتائج مطابقة </template>
              <template #pagination="{ table }">
                <div class="mt-3 flex items-center justify-end gap-2 text-xs">
                  <Button
                    variant="outline"
                    size="icon"
                    class="h-7 w-7"
                    :disabled="!table.getCanPreviousPage()"
                    @click="table.previousPage()"
                  >
                    <span class="sr-only">الصفحة السابقة</span>
                    <ChevronRight class="h-3.5 w-3.5" />
                  </Button>
                  <span
                    >صفحة {{ table.getState().pagination.pageIndex + 1 }} من
                    {{ table.getPageCount() }}</span
                  >
                  <Button
                    variant="outline"
                    size="icon"
                    class="h-7 w-7"
                    :disabled="!table.getCanNextPage()"
                    @click="table.nextPage()"
                  >
                    <span class="sr-only">الصفحة التالية</span>
                    <ChevronLeft class="h-3.5 w-3.5" />
                  </Button>
                </div>
              </template>
            </DataTable>
          </Card>
        </TabsContent>

        <!-- Bank Performance -->
        <TabsContent value="bank_performance" class="mt-4">
          <Card class="border-0 p-5 shadow">
            <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
              أداء البنوك
            </h3>
            <DataTable
              :data="bankBreakdownRows"
              :columns="bankBreakdownColumns"
              :loading="loading"
            />
          </Card>
        </TabsContent>

        <!-- Workflow SLA -->
        <TabsContent value="workflow_sla" class="mt-4">
          <Card class="border-0 p-5 shadow">
            <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
              أداء SLA لمراحل سير العمل
            </h3>
            <div class="space-y-4">
              <div v-if="loading" class="space-y-3">
                <Skeleton v-for="i in 5" :key="i" class="h-12 w-full rounded-lg" />
              </div>
              <div v-else-if="(report?.sla_performance ?? []).length > 0" class="space-y-3">
                <div
                  v-for="stage in report?.sla_performance ?? []"
                  :key="stage.stage"
                  class="flex items-center gap-4 rounded-lg border p-3"
                >
                  <div
                    class="font-section text-foreground w-40 shrink-0 text-sm leading-5 font-medium"
                  >
                    {{ stage.stage }}
                  </div>
                  <div class="flex-1">
                    <div
                      class="font-section text-muted-foreground mb-1 flex justify-between text-xs leading-5"
                    >
                      <span class="tabular-nums"
                        >متوسط: {{ stage.avg_hours != null ? stage.avg_hours + 'س' : '—' }}</span
                      >
                      <span
                        class="tabular-nums"
                        :class="
                          (stage.breach_rate ?? 0) > 20
                            ? 'text-[var(--severity-red)]'
                            : 'text-[var(--severity-green)]'
                        "
                      >
                        انتهاك: {{ stage.breach_rate != null ? stage.breach_rate + '%' : '—' }}
                      </span>
                    </div>
                    <Progress :model-value="stage.breach_rate ?? 0" class="h-2" />
                  </div>
                </div>
              </div>
              <div v-else class="text-muted-foreground py-8 text-center text-sm">
                لا توجد بيانات SLA
              </div>
            </div>
          </Card>
        </TabsContent>

        <!-- Decisions & Outcomes -->
        <TabsContent value="decisions" class="mt-4">
          <div class="grid gap-4 lg:grid-cols-2">
            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                توزيع القرارات
              </h3>
              <div class="space-y-3">
                <div class="flex items-center justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground text-sm leading-5 font-medium"
                    >مُعتمد</span
                  >
                  <span
                    class="text-xl leading-7 font-semibold text-[var(--severity-green)] tabular-nums"
                    >{{ loading ? '—' : approved }}</span
                  >
                </div>
                <div class="flex items-center justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground text-sm leading-5 font-medium"
                    >مرفوض</span
                  >
                  <span
                    class="text-xl leading-7 font-semibold text-[var(--severity-red)] tabular-nums"
                    >{{ loading ? '—' : rejected }}</span
                  >
                </div>
                <div class="flex items-center justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground text-sm leading-5 font-medium"
                    >نسبة الاعتماد</span
                  >
                  <span class="text-foreground text-xl leading-7 font-semibold tabular-nums">{{
                    loading ? '—' : approvalRate + '%'
                  }}</span>
                </div>
              </div>
            </Card>
            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                قيمة التمويل الإجمالية
              </h3>
              <div class="space-y-3">
                <div
                  v-for="item in amountByCurrency"
                  :key="item.currency"
                  class="flex items-center justify-between rounded-lg border p-3"
                >
                  <span class="font-section text-foreground text-sm leading-5 font-semibold">{{
                    item.currency
                  }}</span>
                  <span class="text-muted-foreground text-sm leading-5 font-medium tabular-nums">{{
                    item.amount.toLocaleString('en-US')
                  }}</span>
                </div>
                <div
                  v-if="amountByCurrency.length === 0"
                  class="text-muted-foreground py-4 text-center text-xs"
                >
                  لا توجد بيانات
                </div>
              </div>
            </Card>
          </div>
        </TabsContent>

        <!-- Executive Voting -->
        <TabsContent value="voting" class="mt-4">
          <Card class="border-0 p-5 shadow">
            <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
              تحليل التصويت التنفيذي
            </h3>
            <DataTable
              :data="votingAnalyticsRows"
              :columns="votingAnalyticsColumns"
              :loading="loading"
            >
              <template #empty>لا توجد بيانات تصويت</template>
            </DataTable>
          </Card>
        </TabsContent>

        <!-- SWIFT & FX -->
        <TabsContent value="swift_fx" class="mt-4">
          <div class="grid gap-4 lg:grid-cols-2">
            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                إحصاءات SWIFT
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >رفع SWIFT مكتمل</span
                  >
                  <span class="leading-5 font-semibold text-[var(--info)] tabular-nums">{{
                    loading ? '—' : (report?.swift_stats?.uploaded ?? '—')
                  }}</span>
                </div>
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >متوسط وقت الرفع</span
                  >
                  <span class="text-foreground leading-5 font-semibold tabular-nums">{{
                    loading
                      ? '—'
                      : report?.swift_stats?.avg_upload_hours != null
                        ? report.swift_stats.avg_upload_hours + 'س'
                        : '—'
                  }}</span>
                </div>
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >قيد الانتظار</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-amber)] tabular-nums">{{
                    loading ? '—' : (report?.swift_stats?.pending ?? '—')
                  }}</span>
                </div>
              </div>
            </Card>
            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                تأكيد المصارفة الخارجية
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >مكتمل</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-green)] tabular-nums">{{
                    loading ? '—' : (report?.fx_stats?.completed ?? '—')
                  }}</span>
                </div>
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >قيد الانتظار</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-amber)] tabular-nums">{{
                    loading ? '—' : (report?.fx_stats?.pending ?? '—')
                  }}</span>
                </div>
              </div>
            </Card>
          </div>
        </TabsContent>

        <!-- Compliance & Risk -->
        <TabsContent value="compliance" class="mt-4">
          <div class="grid gap-4 lg:grid-cols-2">
            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                مؤشرات الامتثال
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >معدل الإنجاز في الموعد</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-green)] tabular-nums">{{
                    loading
                      ? '—'
                      : report?.compliance?.on_time_rate != null
                        ? report.compliance.on_time_rate + '%'
                        : '—'
                  }}</span>
                </div>
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >انتهاكات SLA</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-red)] tabular-nums">{{
                    loading ? '—' : (report?.compliance?.sla_violations ?? '—')
                  }}</span>
                </div>
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >طلبات عادت للبنك</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-amber)] tabular-nums">{{
                    loading ? '—' : (report?.compliance?.returned_count ?? '—')
                  }}</span>
                </div>
              </div>
            </Card>
            <Card class="border-0 p-5 shadow">
              <h3 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
                نشاط التدقيق
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >إجمالي السجلات</span
                  >
                  <span class="text-foreground leading-5 font-semibold tabular-nums">{{
                    loading ? '—' : (report?.audit_summary?.total_events ?? '—')
                  }}</span>
                </div>
                <div class="flex justify-between rounded-lg border p-3">
                  <span class="font-section text-muted-foreground leading-5 font-medium"
                    >رفض صلاحيات</span
                  >
                  <span class="leading-5 font-semibold text-[var(--severity-red)] tabular-nums">{{
                    loading ? '—' : (report?.audit_summary?.auth_failures ?? '—')
                  }}</span>
                </div>
              </div>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
