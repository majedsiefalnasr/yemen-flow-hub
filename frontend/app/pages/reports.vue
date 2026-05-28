<script setup lang="ts">
import type { ColumnDef, VisibilityState } from '@tanstack/vue-table'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { Calendar, ChevronLeft, ChevronRight, Download, FileSpreadsheet, FileText, Filter } from 'lucide-vue-next'
import { h } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useReports } from '@/composables/useReports'
import type { WorkflowReport } from '@/composables/useReports'
import { useTableKeyboard } from '@/composables/useTableKeyboard'
import { useTableExport } from '@/composables/useTableExport'
import { DataTableViewOptions } from '@/components/ui/data-table'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/reports'],
})

const { fetchWorkflowReport, exportReport } = useReports()
const { exportToCSV } = useTableExport()

const report = ref<WorkflowReport | null>(null)
const loading = ref(true)
const scheduleQuery = ref('')
const scheduleSearchRef = ref<HTMLInputElement | null>(null)
const scheduleColumnVisibility = ref<VisibilityState>({
  recipients: false,
})

useTableKeyboard(scheduleSearchRef, {
  onEscape: () => {
    scheduleQuery.value = ''
  },
})

onMounted(async () => {
  try {
    report.value = await fetchWorkflowReport()
  }
  finally {
    loading.value = false
  }
})

const total = computed(() => {
  if (!report.value) return 0
  return Object.values(report.value.counts_by_status).reduce((s, v) => s + v, 0)
})
const approved = computed(() => report.value?.throughput.approved ?? 0)
const rejected = computed(() => report.value?.throughput.rejected ?? 0)
const totalValue = computed(() => report.value?.total_financing_value ?? 0)
const approvalRate = computed(() => total.value > 0 ? Math.round((approved.value / total.value) * 100) : 0)

const monthly = computed(() => report.value?.monthly_trend ?? [])
const monthlyMax = computed(() => Math.max(...monthly.value.flatMap(m => [m.total, m.approved]), 1))

const categoryDist = computed(() => report.value?.category_distribution ?? [])
const categoryTotal = computed(() => categoryDist.value.reduce((s, item) => s + item.count, 0) || 1)

const heatRows = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس']
const heatCols = ['08', '10', '12', '14', '16', '18']

const kpis = computed(() => [
  { label: 'إجمالي الطلبات', value: total.value.toLocaleString('en-US'), summary: `${approved.value} مُعتمد` },
  { label: 'قيمة التمويل', value: `$${(totalValue.value / 1_000_000).toFixed(1)}M`, summary: '' },
  { label: 'متوسط زمن المعالجة', value: '—', summary: '' },
  { label: 'نسبة الاعتماد', value: `${approvalRate.value}%`, summary: `${rejected.value} مرفوض` },
  { label: 'الفواتير المكررة', value: (report.value?.duplicate_invoice_count ?? 0).toString(), summary: 'تنبيه' },
])

const amountByCurrency = computed(() => report.value?.amount_by_currency ?? [])
const maxAmount = computed(() => Math.max(...amountByCurrency.value.map(item => item.amount), 1))

type ScheduledReportRow = {
  name: string
  cadence: string
  recipients: string
  lastRun: string
  status: 'نشط' | 'متوقف'
}

const scheduledReports: ScheduledReportRow[] = [
  { name: 'تقرير أسبوعي للجنة التنفيذية', cadence: 'أسبوعي · الأحد 08:00', recipients: 'executive@cby.gov.ye', lastRun: '27 أكتوبر', status: 'نشط' },
  { name: 'تقرير الفواتير المكررة', cadence: 'يومي · 22:00', recipients: 'audit@cby.gov.ye', lastRun: 'اليوم 22:00', status: 'نشط' },
  { name: 'تحليل البنوك التجارية', cadence: 'شهري · 1 من الشهر', recipients: 'stats@cby.gov.ye', lastRun: '1 أكتوبر', status: 'نشط' },
  { name: 'تقرير الإفراج الجمركي', cadence: 'أسبوعي · الخميس', recipients: 'customs@customs.gov.ye', lastRun: '23 أكتوبر', status: 'متوقف' },
]

const filteredScheduledReports = computed(() => {
  const q = scheduleQuery.value.trim().toLowerCase()
  if (!q) return scheduledReports
  return scheduledReports.filter(item =>
    item.name.toLowerCase().includes(q)
    || item.cadence.toLowerCase().includes(q)
    || item.recipients.toLowerCase().includes(q),
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
      h(Badge, { variant: row.original.status === 'نشط' ? 'secondary' : 'outline' }, () => row.original.status),
  },
  {
    id: 'actions',
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

const scheduledReportsTable = useVueTable({
  get data() { return filteredScheduledReports.value },
  columns: scheduledReportColumns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onColumnVisibilityChange: updater =>
    scheduleColumnVisibility.value = typeof updater === 'function' ? updater(scheduleColumnVisibility.value) : updater,
  state: {
    get columnVisibility() { return scheduleColumnVisibility.value },
  },
  initialState: {
    pagination: { pageSize: 5 },
  },
})

function exportScheduledReports() {
  if (!filteredScheduledReports.value.length) return
  const stamp = new Date().toISOString().slice(0, 10)
  exportToCSV(
    filteredScheduledReports.value as unknown as Record<string, unknown>[],
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

const colors = ['bg-primary', 'bg-info', 'bg-amber-50', 'bg-red-700', 'bg-purple-600', 'bg-emerald-600']

const monthlyChartConfig = { requests: { label: 'طلبات' }, approvals: { label: 'مُعتمد' } }
const categoryChartConfig = { value: { label: 'النسبة' } }
</script>

<template>
  <div>
    <PageHeader
      title="التقارير والتحليلات المتقدمة"
      subtitle="مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التقارير' }]"
    >
      <template #actions>
        <Button variant="outline">
          <Calendar class="ms-1 h-4 w-4" />
          الفترة: الكل
        </Button>
        <Button
          variant="outline"
          @click="exportReport('workflow', 'pdf')"
        >
          <FileText class="ms-1 h-4 w-4" />
          PDF
        </Button>
        <Button @click="exportReport('workflow', 'excel')">
          <FileSpreadsheet class="ms-1 h-4 w-4" />
          Excel
        </Button>
      </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
      <Card
        v-for="kpi in kpis"
        :key="kpi.label"
        class="border-0 p-4 shadow"
      >
        <div class="text-xs text-muted-foreground">
          {{ kpi.label }}
        </div>
        <div class="mt-1 flex items-end justify-between">
          <div class="text-xl font-bold">
            {{ loading ? '—' : kpi.value }}
          </div>
          <Badge
            v-if="kpi.summary"
            variant="secondary"
            class="text-[10px]"
          >
            {{ kpi.summary }}
          </Badge>
        </div>
      </Card>
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
        <h3 class="mb-4 font-semibold">
          تطور أحجام الطلبات
        </h3>
        <ChartContainer
          :config="monthlyChartConfig"
          class="h-[300px] rounded-lg border bg-muted/10 p-4"
        >
          <div
            v-if="monthly.length > 0"
            class="flex h-full items-end gap-4"
          >
            <div
              v-for="month in monthly"
              :key="month.month"
              class="flex h-full flex-1 flex-col justify-end gap-2"
            >
              <div class="flex flex-1 items-end justify-center gap-2">
                <div
                  class="w-5 rounded-t-md bg-primary"
                  :style="{ height: `${(month.total / monthlyMax) * 100}%` }"
                  :title="`طلبات: ${month.total}`"
                />
                <div
                  class="w-5 rounded-t-md bg-green-50"
                  :style="{ height: `${(month.approved / monthlyMax) * 100}%` }"
                  :title="`مُعتمد: ${month.approved}`"
                />
              </div>
              <div class="text-center text-[11px] text-muted-foreground">
                {{ month.month }}
              </div>
            </div>
          </div>
          <div
            v-else
            class="flex h-full items-center justify-center text-sm text-muted-foreground"
          >
            لا توجد بيانات
          </div>
        </ChartContainer>
        <div class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
          <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-primary" />طلبات</span>
          <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-green-50" />مُعتمد</span>
        </div>
      </Card>

      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 font-semibold">
          حسب الفئة
        </h3>
        <ChartContainer
          :config="categoryChartConfig"
          class="space-y-3"
        >
          <div
            v-for="(item, index) in categoryDist"
            :key="item.category"
            class="space-y-1.5"
          >
            <div class="flex justify-between text-xs">
              <span>{{ item.category }}</span>
              <span class="font-semibold">{{ Math.round((item.count / categoryTotal) * 100) }}%</span>
            </div>
            <Progress
              :class="['h-2', colors[index % colors.length] === 'bg-primary' ? '[&_[data-slot=progress-indicator]]:bg-primary' : '[&_[data-slot=progress-indicator]]:bg-info']"
              :model-value="(item.count / categoryTotal) * 100"
            />
          </div>
          <div
            v-if="categoryDist.length === 0"
            class="py-4 text-center text-xs text-muted-foreground"
          >
            لا توجد بيانات
          </div>
        </ChartContainer>
      </Card>

      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 font-semibold">
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
              class="h-3 [&_[data-slot=progress-indicator]]:bg-info"
              :model-value="(item.amount / maxAmount) * 100"
            />
            <span class="text-start text-xs text-muted-foreground">{{ item.amount.toLocaleString('en-US') }}</span>
          </div>
          <div
            v-if="amountByCurrency.length === 0"
            class="py-4 text-center text-xs text-muted-foreground"
          >
            لا توجد بيانات
          </div>
        </div>
      </Card>

      <Card class="border-0 p-5 shadow lg:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-semibold">
            خريطة حرارية: كثافة التقديم خلال الأسبوع
          </h3>
          <Badge variant="secondary">
            آخر 12 أسبوع
          </Badge>
        </div>
        <div class="overflow-x-auto">
          <div
            class="inline-grid gap-1"
            :style="{ gridTemplateColumns: `auto repeat(${heatCols.length}, minmax(50px, 1fr))` }"
          >
            <div />
            <div
              v-for="col in heatCols"
              :key="col"
              class="text-center text-[10px] text-muted-foreground"
            >
              {{ col }}:00
            </div>

            <template
              v-for="(row, rowIndex) in heatRows"
              :key="row"
            >
              <div class="py-2 ps-2 text-[11px] text-muted-foreground">
                {{ row }}
              </div>
              <div
                v-for="(col, colIndex) in heatCols"
                :key="`${row}-${col}`"
                class="grid aspect-square place-items-center rounded text-[10px] font-semibold text-white"
                :style="{ backgroundColor: `oklch(0.4 0.13 220 / ${heatOpacity(rowIndex, colIndex)})` }"
              >
                {{ heatValue(rowIndex, colIndex) }}
              </div>
            </template>
          </div>
        </div>
        <div class="mt-3 flex items-center gap-2 text-[10px] text-muted-foreground">
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
            <h3 class="font-semibold">
              تقارير مجدولة
            </h3>
            <div class="flex items-center gap-2">
              <Button variant="outline" size="sm" @click="exportScheduledReports">
                <Filter class="ms-1 h-3.5 w-3.5" />
                تصدير
              </Button>
              <DataTableViewOptions
                :table="scheduledReportsTable"
                :column-labels="SCHEDULE_COLUMN_LABELS"
              />
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

          <div class="overflow-x-auto">
            <Table class="w-full min-w-[720px] text-sm">
              <TableHeader class="border-b text-end text-xs text-muted-foreground">
                <TableRow
                  v-for="headerGroup in scheduledReportsTable.getHeaderGroups()"
                  :key="headerGroup.id"
                >
                  <TableHead
                    v-for="header in headerGroup.headers"
                    :key="header.id"
                    class="py-2.5"
                  >
                    <FlexRender
                      v-if="!header.isPlaceholder"
                      :render="header.column.columnDef.header"
                      :props="header.getContext()"
                    />
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="row in scheduledReportsTable.getRowModel().rows"
                  :key="row.id"
                  class="border-b last:border-0 hover:bg-muted/30"
                >
                  <TableCell
                    v-for="cell in row.getVisibleCells()"
                    :key="cell.id"
                    class="py-3"
                  >
                    <FlexRender
                      :render="cell.column.columnDef.cell"
                      :props="cell.getContext()"
                    />
                  </TableCell>
                </TableRow>
                <TableRow v-if="scheduledReportsTable.getRowModel().rows.length === 0">
                  <TableCell :col-span="scheduledReportColumns.length" class="py-8 text-center text-muted-foreground">
                    لا توجد نتائج مطابقة
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <div class="mt-3 flex items-center justify-between">
            <p class="text-xs text-muted-foreground">{{ scheduledReportsTable.getFilteredRowModel().rows.length }} تقرير</p>
            <div class="flex items-center gap-2 text-xs">
              <Button
                variant="outline"
                size="icon"
                class="h-7 w-7"
                :disabled="!scheduledReportsTable.getCanPreviousPage()"
                @click="scheduledReportsTable.previousPage()"
              >
                <span class="sr-only">الصفحة السابقة</span>
                <ChevronRight class="h-3.5 w-3.5" />
              </Button>
              <span>صفحة {{ scheduledReportsTable.getState().pagination.pageIndex + 1 }} من {{ scheduledReportsTable.getPageCount() }}</span>
              <Button
                variant="outline"
                size="icon"
                class="h-7 w-7"
                :disabled="!scheduledReportsTable.getCanNextPage()"
                @click="scheduledReportsTable.nextPage()"
              >
                <span class="sr-only">الصفحة التالية</span>
                <ChevronLeft class="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        </Card>
      </TabsContent>

      <!-- Bank Performance -->
      <TabsContent value="bank_performance" class="mt-4">
        <Card class="border-0 p-5 shadow">
          <h3 class="mb-4 font-semibold">أداء البنوك</h3>
          <div class="overflow-x-auto">
            <Table class="w-full min-w-[600px] text-sm">
              <TableHeader>
                <TableRow>
                  <TableHead>البنك</TableHead>
                  <TableHead>إجمالي الطلبات</TableHead>
                  <TableHead>مُعتمد</TableHead>
                  <TableHead>مرفوض</TableHead>
                  <TableHead>نسبة الاعتماد</TableHead>
                  <TableHead>قيمة التمويل</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loading">
                  <TableRow v-for="i in 5" :key="i">
                    <TableCell v-for="j in 6" :key="j"><Skeleton class="h-4 w-full" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="(report?.bank_breakdown ?? []).length > 0">
                  <TableRow v-for="bank in (report?.bank_breakdown ?? [])" :key="bank.bank_id ?? bank.bank_name">
                    <TableCell class="font-medium">{{ bank.bank_name }}</TableCell>
                    <TableCell class="tabular-nums">{{ bank.total }}</TableCell>
                    <TableCell class="text-[var(--severity-green)] tabular-nums">{{ bank.approved ?? '—' }}</TableCell>
                    <TableCell class="text-[var(--severity-red)] tabular-nums">{{ bank.rejected ?? '—' }}</TableCell>
                    <TableCell class="tabular-nums">{{ bank.total > 0 && bank.approved != null ? Math.round((bank.approved / bank.total) * 100) + '%' : '—' }}</TableCell>
                    <TableCell class="tabular-nums text-muted-foreground">{{ (bank.total_value ?? 0).toLocaleString('en-US') }}</TableCell>
                  </TableRow>
                </template>
                <template v-else>
                  <TableRow>
                    <TableCell colspan="6" class="py-8 text-center text-muted-foreground">لا توجد بيانات</TableCell>
                  </TableRow>
                </template>
              </TableBody>
            </Table>
          </div>
        </Card>
      </TabsContent>

      <!-- Workflow SLA -->
      <TabsContent value="workflow_sla" class="mt-4">
        <Card class="border-0 p-5 shadow">
          <h3 class="mb-4 font-semibold">أداء SLA لمراحل سير العمل</h3>
          <div class="space-y-4">
            <div v-if="loading" class="space-y-3">
              <Skeleton v-for="i in 5" :key="i" class="h-12 w-full rounded-lg" />
            </div>
            <div v-else-if="(report?.sla_performance ?? []).length > 0" class="space-y-3">
              <div
                v-for="stage in (report?.sla_performance ?? [])"
                :key="stage.stage"
                class="flex items-center gap-4 rounded-lg border p-3"
              >
                <div class="w-40 shrink-0 text-sm font-medium">{{ stage.stage }}</div>
                <div class="flex-1">
                  <div class="mb-1 flex justify-between text-xs text-muted-foreground">
                    <span>متوسط: {{ stage.avg_hours != null ? stage.avg_hours + 'س' : '—' }}</span>
                    <span :class="(stage.breach_rate ?? 0) > 20 ? 'text-[var(--severity-red)]' : 'text-[var(--severity-green)]'">
                      انتهاك: {{ stage.breach_rate != null ? stage.breach_rate + '%' : '—' }}
                    </span>
                  </div>
                  <Progress
                    :model-value="stage.breach_rate ?? 0"
                    class="h-2"
                  />
                </div>
              </div>
            </div>
            <div v-else class="py-8 text-center text-sm text-muted-foreground">لا توجد بيانات SLA</div>
          </div>
        </Card>
      </TabsContent>

      <!-- Decisions & Outcomes -->
      <TabsContent value="decisions" class="mt-4">
        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-0 p-5 shadow">
            <h3 class="mb-4 font-semibold">توزيع القرارات</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between rounded-lg border p-3">
                <span class="text-sm">مُعتمد</span>
                <span class="text-lg font-bold text-[var(--severity-green)] tabular-nums">{{ loading ? '—' : approved }}</span>
              </div>
              <div class="flex items-center justify-between rounded-lg border p-3">
                <span class="text-sm">مرفوض</span>
                <span class="text-lg font-bold text-[var(--severity-red)] tabular-nums">{{ loading ? '—' : rejected }}</span>
              </div>
              <div class="flex items-center justify-between rounded-lg border p-3">
                <span class="text-sm">نسبة الاعتماد</span>
                <span class="text-lg font-bold tabular-nums">{{ loading ? '—' : approvalRate + '%' }}</span>
              </div>
            </div>
          </Card>
          <Card class="border-0 p-5 shadow">
            <h3 class="mb-4 font-semibold">قيمة التمويل الإجمالية</h3>
            <div class="space-y-3">
              <div v-for="item in amountByCurrency" :key="item.currency" class="flex items-center justify-between rounded-lg border p-3">
                <span class="text-sm font-semibold">{{ item.currency }}</span>
                <span class="tabular-nums text-sm text-muted-foreground">{{ item.amount.toLocaleString('en-US') }}</span>
              </div>
              <div v-if="amountByCurrency.length === 0" class="py-4 text-center text-xs text-muted-foreground">لا توجد بيانات</div>
            </div>
          </Card>
        </div>
      </TabsContent>

      <!-- Executive Voting -->
      <TabsContent value="voting" class="mt-4">
        <Card class="border-0 p-5 shadow">
          <h3 class="mb-4 font-semibold">تحليل التصويت التنفيذي</h3>
          <div class="overflow-x-auto">
            <Table class="w-full text-sm">
              <TableHeader>
                <TableRow>
                  <TableHead>العضو</TableHead>
                  <TableHead>جلسات شارك بها</TableHead>
                  <TableHead>أصوات الاعتماد</TableHead>
                  <TableHead>أصوات الرفض</TableHead>
                  <TableHead>نسبة الاعتماد</TableHead>
                  <TableHead>متوسط وقت التصويت</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loading">
                  <TableRow v-for="i in 4" :key="i">
                    <TableCell v-for="j in 6" :key="j"><Skeleton class="h-4 w-full" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="(report?.voting_analytics ?? []).length > 0">
                  <TableRow v-for="member in (report?.voting_analytics ?? [])" :key="member.user_id">
                    <TableCell class="font-medium">{{ member.name }}</TableCell>
                    <TableCell class="tabular-nums">{{ member.sessions }}</TableCell>
                    <TableCell class="text-[var(--severity-green)] tabular-nums">{{ member.approvals }}</TableCell>
                    <TableCell class="text-[var(--severity-red)] tabular-nums">{{ member.rejections }}</TableCell>
                    <TableCell class="tabular-nums">{{ member.sessions > 0 ? Math.round((member.approvals / member.sessions) * 100) + '%' : '—' }}</TableCell>
                    <TableCell class="tabular-nums text-muted-foreground">{{ member.avg_hours != null ? member.avg_hours + 'س' : '—' }}</TableCell>
                  </TableRow>
                </template>
                <template v-else>
                  <TableRow>
                    <TableCell colspan="6" class="py-8 text-center text-muted-foreground">لا توجد بيانات تصويت</TableCell>
                  </TableRow>
                </template>
              </TableBody>
            </Table>
          </div>
        </Card>
      </TabsContent>

      <!-- SWIFT & FX -->
      <TabsContent value="swift_fx" class="mt-4">
        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-0 p-5 shadow">
            <h3 class="mb-4 font-semibold">إحصاءات SWIFT</h3>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between rounded-lg border p-3">
                <span>رفع SWIFT مكتمل</span>
                <span class="font-bold tabular-nums text-[var(--info)]">{{ loading ? '—' : (report?.swift_stats?.uploaded ?? '—') }}</span>
              </div>
              <div class="flex justify-between rounded-lg border p-3">
                <span>متوسط وقت الرفع</span>
                <span class="font-bold tabular-nums">{{ loading ? '—' : (report?.swift_stats?.avg_upload_hours != null ? report.swift_stats.avg_upload_hours + 'س' : '—') }}</span>
              </div>
              <div class="flex justify-between rounded-lg border p-3">
                <span>قيد الانتظار</span>
                <span class="font-bold tabular-nums text-[var(--severity-amber)]">{{ loading ? '—' : (report?.swift_stats?.pending ?? '—') }}</span>
              </div>
            </div>
          </Card>
          <Card class="border-0 p-5 shadow">
            <h3 class="mb-4 font-semibold">تأكيد المصارفة الخارجية</h3>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between rounded-lg border p-3">
                <span>مكتمل</span>
                <span class="font-bold tabular-nums text-[var(--severity-green)]">{{ loading ? '—' : (report?.fx_stats?.completed ?? '—') }}</span>
              </div>
              <div class="flex justify-between rounded-lg border p-3">
                <span>قيد الانتظار</span>
                <span class="font-bold tabular-nums text-[var(--severity-amber)]">{{ loading ? '—' : (report?.fx_stats?.pending ?? '—') }}</span>
              </div>
            </div>
          </Card>
        </div>
      </TabsContent>

      <!-- Compliance & Risk -->
      <TabsContent value="compliance" class="mt-4">
        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-0 p-5 shadow">
            <h3 class="mb-4 font-semibold">مؤشرات الامتثال</h3>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between rounded-lg border p-3">
                <span>معدل الإنجاز في الموعد</span>
                <span class="font-bold tabular-nums text-[var(--severity-green)]">{{ loading ? '—' : (report?.compliance?.on_time_rate != null ? report.compliance.on_time_rate + '%' : '—') }}</span>
              </div>
              <div class="flex justify-between rounded-lg border p-3">
                <span>انتهاكات SLA</span>
                <span class="font-bold tabular-nums text-[var(--severity-red)]">{{ loading ? '—' : (report?.compliance?.sla_violations ?? '—') }}</span>
              </div>
              <div class="flex justify-between rounded-lg border p-3">
                <span>طلبات عادت للبنك</span>
                <span class="font-bold tabular-nums text-[var(--severity-amber)]">{{ loading ? '—' : (report?.compliance?.returned_count ?? '—') }}</span>
              </div>
            </div>
          </Card>
          <Card class="border-0 p-5 shadow">
            <h3 class="mb-4 font-semibold">نشاط التدقيق</h3>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between rounded-lg border p-3">
                <span>إجمالي السجلات</span>
                <span class="font-bold tabular-nums">{{ loading ? '—' : (report?.audit_summary?.total_events ?? '—') }}</span>
              </div>
              <div class="flex justify-between rounded-lg border p-3">
                <span>رفض صلاحيات</span>
                <span class="font-bold tabular-nums text-[var(--severity-red)]">{{ loading ? '—' : (report?.audit_summary?.auth_failures ?? '—') }}</span>
              </div>
            </div>
          </Card>
        </div>
      </TabsContent>
    </Tabs>
  </div>
</template>
