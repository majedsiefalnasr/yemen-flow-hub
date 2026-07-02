<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, VisibilityState } from '@tanstack/vue-table'
import { h } from 'vue'
import {
  AlertCircle,
  AlertTriangle,
  Building2,
  BriefcaseBusiness,
  Check,
  CheckCircle2,
  ClipboardList,
  FileText,
  Layers,
  ListFilter,
  RefreshCw,
  SearchX,
  ShieldAlert,
  Timer,
  UserCheck,
} from 'lucide-vue-next'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import type { EngineRequest } from '@/types/models'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Command,
  CommandGroup,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Separator } from '@/components/ui/separator'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import PageHeader from '@/components/layout/PageHeader.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import { useTableExport } from '@/composables/useTableExport'
import {
  DataTable,
  DataTableColumnHeader,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const router = useRouter()
const store = useEngineRequestsStore()
const authStore = useAuthStore()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()

// Supervisor = oversight role (CBY admin). They monitor every request across the
// platform but cannot create requests or execute stage actions, so their view
// drops the personal queue and the "new request" affordance and gains SLA /
// claim oversight instead.
const isSupervisor = computed(() => authStore.isCbyAdmin)
const canCreateRequest = computed(() => authStore.user?.role === UserRole.DATA_ENTRY)

// Scope selects the data source: "queue" = requests waiting on the current user
// (server-side my-queue), "all" = every instance the user may see. Supervisors
// have no personal queue, so they are pinned to "all" and the scope filter is
// hidden for them; workflow participants keep the queue/all switch.
const view = ref<'queue' | 'all'>('queue')
const scopeOptions = [
  { label: 'طابوري', value: 'queue' as const },
  { label: 'جميع الطلبات', value: 'all' as const },
]
const scopeLabel = computed(
  () => scopeOptions.find((o) => o.value === view.value)?.label ?? 'النطاق',
)
const query = ref('')
const columnFilters = ref<ColumnFiltersState>([])
// SLA and claim columns are oversight-only; hidden for participants, shown for
// supervisors (toggled on in onMounted once the role is known).
const columnVisibility = ref<VisibilityState>({
  bank: true,
  merchant: true,
  amount: true,
  sla: false,
  claimed: false,
})

function load() {
  if (view.value === 'queue') store.loadQueue()
  else store.loadList()
}

onMounted(() => {
  // Pin supervisors to the platform-wide list; they have no personal queue, and
  // surface the oversight columns for them.
  if (isSupervisor.value) {
    view.value = 'all'
    columnVisibility.value = { ...columnVisibility.value, sla: true, claimed: true }
  }
  load()
})
watch(view, () => {
  columnFilters.value = []
  load()
})

const rows = computed<EngineRequest[]>(() => (view.value === 'queue' ? store.queue : store.instances))

// Client-side search across the fields most people scan by.
const filteredRows = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter((r) =>
    [r.reference, r.current_stage?.name, r.bank?.name, r.merchant?.name, r.invoice_number].some(
      (v) => (v ?? '').toString().toLowerCase().includes(q),
    ),
  )
})

const stats = computed(() => ({
  queue: store.queue.length,
  all: store.instances.length,
  total: rows.value.length,
  waiting: rows.value.filter((r) => r.status === 'ACTIVE').length,
  breached: rows.value.filter((r) => r.sla_status === 'breached').length,
  unclaimed: rows.value.filter((r) => r.status === 'ACTIVE' && r.claimed_by == null).length,
}))

function statusLabel(status: string): string {
  if (status === 'ACTIVE') return 'نشط'
  if (status === 'CLOSED') return 'مكتمل'
  if (status === 'REJECTED') return 'مرفوض'
  return status
}

function slaLabel(sla: string | null): string {
  if (sla === 'breached') return 'متجاوز'
  if (sla === 'nearing') return 'يقترب'
  if (sla === 'ok') return 'ضمن الوقت'
  return '—'
}

function slaTone(sla: string | null): { fg: string; bg: string } | null {
  if (sla === 'breached')
    return { fg: 'var(--color-text-error)', bg: 'var(--color-surface-error)' }
  if (sla === 'nearing')
    return {
      fg: 'var(--severity-amber)',
      bg: 'color-mix(in oklab, var(--severity-amber) 12%, transparent)',
    }
  if (sla === 'ok')
    return { fg: 'var(--color-text-success)', bg: 'var(--color-surface-success)' }
  return null
}

function statusTone(status: string): { fg: string; bg: string } {
  if (status === 'CLOSED')
    return { fg: 'var(--color-text-success)', bg: 'var(--color-surface-success)' }
  if (status === 'REJECTED')
    return { fg: 'var(--color-text-error)', bg: 'var(--color-surface-error)' }
  return { fg: 'var(--severity-amber)', bg: 'color-mix(in oklab, var(--severity-amber) 12%, transparent)' }
}

const currencyFmt = new Intl.NumberFormat('ar-EG')
function formatAmount(amount: number | null, currency: string | null): string {
  if (amount == null) return '—'
  return `${currencyFmt.format(amount)}${currency ? ` ${currency}` : ''}`
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(new Date(iso))
}

function openInstance(id: number) {
  router.push(`/workflows/instances/${id}`)
}

// ── Filter option lists, derived from the currently loaded rows ───────────────
const statusFilterOptions = [
  { label: 'نشط', value: 'ACTIVE' },
  { label: 'مكتمل', value: 'CLOSED' },
  { label: 'مرفوض', value: 'REJECTED' },
]
const stageFilterOptions = computed(() => {
  const names = new Set<string>()
  for (const r of rows.value) if (r.current_stage?.name) names.add(r.current_stage.name)
  return [...names].sort().map((n) => ({ label: n, value: n }))
})
const bankFilterOptions = computed(() => {
  const names = new Set<string>()
  for (const r of rows.value) if (r.bank?.name) names.add(r.bank.name)
  return [...names].sort().map((n) => ({ label: n, value: n }))
})

// Supervisor-only oversight filters.
const slaFilterOptions = [
  { label: 'متجاوز', value: 'breached' },
  { label: 'يقترب', value: 'nearing' },
  { label: 'ضمن الوقت', value: 'ok' },
]
const claimFilterOptions = [
  { label: 'مُطالب بها', value: 'claimed' },
  { label: 'غير مُطالب بها', value: 'unclaimed' },
]

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)

const COLUMN_LABELS: Record<string, string> = {
  reference: 'المرجع',
  stage: 'المرحلة الحالية',
  bank: 'البنك',
  merchant: 'المستورد',
  amount: 'القيمة',
  status: 'الحالة',
  sla: 'مؤشر SLA',
  claimed: 'المسؤول',
  created: 'تاريخ الإنشاء',
}

const columns: ColumnDef<EngineRequest>[] = [
  {
    accessorKey: 'reference',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'المرجع' }),
    enableHiding: false,
    cell: ({ row }) =>
      h(
        'button',
        {
          type: 'button',
          class:
            'text-primary font-mono text-sm text-start hover:underline underline-offset-2 focus-visible:outline-none focus-visible:underline',
          onClick: (e: Event) => {
            e.stopPropagation()
            openInstance(row.original.id)
          },
        },
        row.original.reference,
      ),
  },
  {
    id: 'stage',
    header: 'المرحلة الحالية',
    accessorFn: (row) => row.current_stage?.name ?? '—',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.current_stage?.name ?? '—'),
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.current_stage?.name ?? '—'),
  },
  {
    id: 'bank',
    header: 'البنك',
    accessorFn: (row) => row.bank?.name ?? '—',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.bank?.name ?? '—'),
    cell: ({ row }) =>
      row.original.bank?.name
        ? h(Badge, { variant: 'outline', class: 'font-normal' }, () => [
            h(Building2, { class: 'ms-1 h-3 w-3' }),
            row.original.bank!.name,
          ])
        : h('span', { class: 'text-muted-foreground text-sm' }, '—'),
  },
  {
    id: 'merchant',
    header: 'المستورد',
    accessorFn: (row) => row.merchant?.name ?? '—',
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.merchant?.name ?? '—'),
  },
  {
    id: 'amount',
    header: 'القيمة',
    accessorFn: (row) => row.amount ?? 0,
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm tabular-nums text-foreground' },
        formatAmount(row.original.amount, row.original.currency),
      ),
  },
  {
    accessorKey: 'status',
    header: 'الحالة',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.status),
    cell: ({ row }) => {
      const tone = statusTone(row.original.status)
      return h(
        'span',
        {
          class: 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
          style: { color: tone.fg, background: tone.bg },
        },
        statusLabel(row.original.status),
      )
    },
  },
  {
    id: 'sla',
    header: 'مؤشر SLA',
    accessorFn: (row) => row.sla_status ?? '',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.sla_status ?? ''),
    cell: ({ row }) => {
      const tone = slaTone(row.original.sla_status)
      if (!tone) return h('span', { class: 'text-muted-foreground text-sm' }, '—')
      return h(
        'span',
        {
          class: 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
          style: { color: tone.fg, background: tone.bg },
        },
        slaLabel(row.original.sla_status),
      )
    },
  },
  {
    id: 'claimed',
    header: 'المسؤول',
    accessorFn: (row) => (row.claimed_by == null ? 'unclaimed' : 'claimed'),
    filterFn: (row, _id, value: string[]) =>
      value.includes(row.original.claimed_by == null ? 'unclaimed' : 'claimed'),
    cell: ({ row }) =>
      row.original.claimed_by_user
        ? h('span', { class: 'text-sm text-foreground' }, row.original.claimed_by_user.name)
        : h('span', { class: 'text-muted-foreground text-sm' }, 'غير مُطالب بها'),
  },
  {
    id: 'created',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'تاريخ الإنشاء' }),
    accessorFn: (row) => row.created_at ?? '',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm text-muted-foreground tabular-nums' },
        formatDate(row.original.created_at),
      ),
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    cell: ({ row }) =>
      h(
        Button,
        {
          size: 'sm',
          variant: 'outline',
          class: 'h-8',
          onClick: (e: Event) => {
            e.stopPropagation()
            openInstance(row.original.id)
          },
        },
        () => 'عرض',
      ),
  },
]

// ── Export ────────────────────────────────────────────────────────────────────
const exportCols = [
  { key: 'reference', label: 'المرجع' },
  {
    key: 'stage',
    label: 'المرحلة الحالية',
    format: (_v: unknown, row: EngineRequest) => row.current_stage?.name ?? '—',
  },
  {
    key: 'bank',
    label: 'البنك',
    format: (_v: unknown, row: EngineRequest) => row.bank?.name ?? '—',
  },
  {
    key: 'merchant',
    label: 'المستورد',
    format: (_v: unknown, row: EngineRequest) => row.merchant?.name ?? '—',
  },
  {
    key: 'amount',
    label: 'القيمة',
    format: (_v: unknown, row: EngineRequest) => formatAmount(row.amount, row.currency),
  },
  {
    key: 'status',
    label: 'الحالة',
    format: (_v: unknown, row: EngineRequest) => statusLabel(row.status),
  },
  {
    key: 'sla_status',
    label: 'مؤشر SLA',
    format: (_v: unknown, row: EngineRequest) => slaLabel(row.sla_status),
  },
  {
    key: 'claimed',
    label: 'المسؤول',
    format: (_v: unknown, row: EngineRequest) => row.claimed_by_user?.name ?? 'غير مُطالب بها',
  },
  {
    key: 'created_at',
    label: 'تاريخ الإنشاء',
    format: (_v: unknown, row: EngineRequest) => formatDate(row.created_at),
  },
] as const

function buildExportFilename(): string {
  return `workflow-requests-${view.value}-${new Date().toISOString().slice(0, 10)}`
}

function setStatusFilter(status: EngineRequest['status']) {
  columnFilters.value = [{ id: 'status', value: [status] }]
}

function isStatusActive(status: EngineRequest['status']): boolean {
  const f = columnFilters.value.find((cf) => cf.id === 'status')
  return Array.isArray(f?.value) && (f!.value as string[]).length === 1 && f!.value[0] === status
}

function setColumnFilter(id: string, value: string) {
  columnFilters.value = [{ id, value: [value] }]
}

function isColumnFilterActive(id: string, value: string): boolean {
  const f = columnFilters.value.find((cf) => cf.id === id)
  return Array.isArray(f?.value) && (f!.value as string[]).length === 1 && f!.value[0] === value
}

function resetAllFilters() {
  columnFilters.value = []
}

function handleReset() {
  query.value = ''
  columnFilters.value = []
}
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-6 p-6" dir="rtl">
    <PageHeader
      title="طلبات التمويل"
      subtitle="متابعة طلبات تمويل الواردات وإدارة ما ينتظر إجراءك عبر مراحل سير العمل"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'طلبات التمويل' }]"
    >
      <template #actions>
        <Button variant="outline" size="sm" :disabled="store.loading" @click="load">
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': store.loading }" />
          تحديث
        </Button>
        <!-- Only request creators (data entry) may start a request; supervisors
             and reviewers never see this. -->
        <Button v-if="canCreateRequest" size="sm" @click="router.push('/workflows/new')">
          <FileText class="h-4 w-4" aria-hidden="true" />
          طلب جديد
        </Button>
      </template>
    </PageHeader>

    <!-- Supervisor KPIs: platform-wide oversight metrics that double as filters
         (total, active, SLA-breached, unclaimed). -->
    <MetricGrid v-if="isSupervisor" :columns="4">
      <MetricCard
        label="إجمالي الطلبات"
        :value="stats.total"
        :icon="ClipboardList"
        :active="columnFilters.length === 0"
        @click="resetAllFilters"
      />
      <MetricCard
        label="نشطة"
        :value="stats.waiting"
        :icon="Timer"
        tone="warning"
        :active="isStatusActive('ACTIVE')"
        @click="setStatusFilter('ACTIVE')"
      />
      <MetricCard
        label="متجاوزة SLA"
        :value="stats.breached"
        :icon="ShieldAlert"
        tone="danger"
        :active="isColumnFilterActive('sla', 'breached')"
        @click="setColumnFilter('sla', 'breached')"
      />
      <MetricCard
        label="غير مُطالب بها"
        :value="stats.unclaimed"
        :icon="UserCheck"
        :active="isColumnFilterActive('claimed', 'unclaimed')"
        @click="setColumnFilter('claimed', 'unclaimed')"
      />
    </MetricGrid>

    <!-- Participant KPIs: personal queue / all / pending action — the first two
         switch scope, the third filters to active. -->
    <MetricGrid v-else :columns="3">
      <MetricCard
        label="طابوري"
        :value="stats.queue"
        :icon="BriefcaseBusiness"
        :active="view === 'queue'"
        @click="view = 'queue'"
      />
      <MetricCard
        label="جميع الطلبات"
        :value="stats.all"
        :icon="FileText"
        tone="info"
        :active="view === 'all'"
        @click="view = 'all'"
      />
      <MetricCard
        label="بانتظار الإجراء"
        :value="stats.waiting"
        :icon="Timer"
        tone="warning"
        :active="isStatusActive('ACTIVE')"
        @click="setStatusFilter('ACTIVE')"
      />
    </MetricGrid>

    <Alert v-if="store.error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في التحميل</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="load">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <div v-else class="relative flex flex-col gap-4">
      <DataTable
        :data="filteredRows"
        :columns="columns"
        :loading="store.loading"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        row-class="cursor-pointer"
        @update:column-filters="(v) => (columnFilters = v)"
        @update:column-visibility="(v) => (columnVisibility = v)"
        @row-click="(row: EngineRequest) => openInstance(row.id)"
      >
        <template #toolbar="{ table }">
          <DataTableToolbar
            :table="table"
            search-placeholder="بحث بالمرجع، المرحلة، البنك، أو المستورد"
            :has-filters="hasActiveFilters"
            @update:search="(v) => (query = v)"
            @reset="handleReset"
          >
            <template #filters>
              <!-- Scope: single-select data-source filter that replaces the old
                   queue/all tabs. Hidden for supervisors, who have no personal
                   queue and always view the whole platform. -->
              <Popover v-if="!isSupervisor">
                <PopoverTrigger as-child>
                  <Button variant="outline" size="sm" class="h-8 border-dashed">
                    <ListFilter class="me-2 h-4 w-4" />
                    النطاق
                    <Separator orientation="vertical" class="mx-2 h-4" />
                    <Badge variant="secondary" class="rounded-sm px-1 font-normal">
                      {{ scopeLabel }}
                    </Badge>
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[200px] p-0" align="start">
                  <Command>
                    <CommandList>
                      <CommandGroup>
                        <CommandItem
                          v-for="option in scopeOptions"
                          :key="option.value"
                          :value="option.value"
                          class="flex items-center gap-2"
                          @select="view = option.value"
                        >
                          <div
                            class="border-primary flex h-4 w-4 items-center justify-center rounded-full border"
                            :class="
                              view === option.value
                                ? 'bg-primary text-primary-foreground'
                                : 'opacity-50'
                            "
                          >
                            <Check v-if="view === option.value" class="h-3 w-3" />
                          </div>
                          <span>{{ option.label }}</span>
                        </CommandItem>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
              <DataTableFacetedFilter
                v-if="table.getColumn('status')"
                :column="table.getColumn('status')!"
                title="الحالة"
                :options="statusFilterOptions"
              />
              <DataTableFacetedFilter
                v-if="table.getColumn('stage') && stageFilterOptions.length > 1"
                :column="table.getColumn('stage')!"
                title="المرحلة"
                :options="stageFilterOptions"
              />
              <DataTableFacetedFilter
                v-if="table.getColumn('bank') && bankFilterOptions.length > 1"
                :column="table.getColumn('bank')!"
                title="البنك"
                :options="bankFilterOptions"
              />
              <!-- Oversight filters, supervisor only. -->
              <DataTableFacetedFilter
                v-if="isSupervisor && table.getColumn('sla')"
                :column="table.getColumn('sla')!"
                title="مؤشر SLA"
                :options="slaFilterOptions"
              />
              <DataTableFacetedFilter
                v-if="isSupervisor && table.getColumn('claimed')"
                :column="table.getColumn('claimed')!"
                title="المسؤول"
                :options="claimFilterOptions"
              />
            </template>
            <template #actions>
              <DataTableViewOptions :table="table" :column-labels="COLUMN_LABELS" />
              <DataTableExport
                :table="table as any"
                :export-columns="exportCols as any"
                :filename="buildExportFilename()"
                :formats="['csv', 'tsv', 'json', 'excel', 'pdf']"
                :respect-column-visibility="true"
              />
            </template>
          </DataTableToolbar>
        </template>
        <template #empty>
          <Empty class="bg-muted/20 min-h-[280px] rounded-xl border border-dashed">
            <EmptyHeader>
              <div
                class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
              >
                <component
                  :is="
                    hasActiveFilters
                      ? SearchX
                      : isSupervisor
                        ? Layers
                        : view === 'queue'
                          ? CheckCircle2
                          : AlertTriangle
                  "
                  class="size-5"
                />
              </div>
              <EmptyTitle>
                {{
                  hasActiveFilters
                    ? 'لا توجد طلبات مطابقة'
                    : isSupervisor
                      ? 'لا توجد طلبات في النظام'
                      : view === 'queue'
                        ? 'الطابور فارغ'
                        : 'لا توجد طلبات معروضة لك'
                }}
              </EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                <template v-if="hasActiveFilters">
                  جرّب تخفيف معايير البحث أو إعادة ضبط الفلاتر.
                </template>
                <template v-else-if="isSupervisor">
                  لم يتم إنشاء أي طلبات تمويل عبر المنصة حتى الآن. ستظهر هنا للرقابة والمتابعة فور
                  تقديم أول طلب.
                </template>
                <template v-else-if="view === 'queue'">
                  لا توجد طلبات في انتظار إجرائك حالياً. اعرض جميع الطلبات لمتابعة ما هو متاح لك في
                  النظام.
                </template>
                <template v-else>
                  القائمة مقصورة على الطلبات والمراحل المصرّح لك بالاطلاع عليها حسب دورك. إن كنت
                  تتوقع رؤية طلبات ولا تظهر، فقد تكون في مراحل خارج نطاق صلاحياتك؛ راجع مسؤول النظام.
                </template>
              </EmptyDescription>
              <Button
                v-if="hasActiveFilters"
                variant="outline"
                size="sm"
                class="mt-3"
                @click="handleReset"
              >
                إعادة ضبط الفلاتر
              </Button>
              <Button
                v-else-if="!isSupervisor && view === 'queue'"
                variant="outline"
                size="sm"
                class="mt-3"
                @click="view = 'all'"
              >
                عرض جميع الطلبات
              </Button>
              <Button
                v-else-if="!isSupervisor && view === 'all' && canCreateRequest"
                size="sm"
                class="mt-3"
                @click="router.push('/workflows/new')"
              >
                إنشاء طلب جديد
              </Button>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table }">
          <DataTablePagination :table="table" />
        </template>
      </DataTable>
    </div>
  </div>
</template>
