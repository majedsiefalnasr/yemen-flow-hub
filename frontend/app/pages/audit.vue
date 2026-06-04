<script setup lang="ts">
import type {
  ColumnDef,
  ColumnFiltersState,
  PaginationState,
  SortingState,
  VisibilityState,
} from '@tanstack/vue-table'
import {
  Activity,
  AlertTriangle,
  Download,
  FileWarning,
  MoreHorizontal,
  SearchX,
  ShieldCheck,
  X,
} from 'lucide-vue-next'
import { h } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useAudit } from '@/composables/useAudit'
import { useTableExport } from '@/composables/useTableExport'
import { useTableKeyboard } from '@/composables/useTableKeyboard'
import type { AuditLog } from '@/types/models'
import {
  DataTableBulkExport,
  DataTablePagination,
  DataTableViewOptions,
} from '@/components/ui/data-table'
import DataTable from '@/components/ui/data-table/DataTable.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import RankedListCard from '@/components/shared/dashboard/RankedListCard.vue'
import InsightsTabsCard from '@/components/shared/dashboard/InsightsTabsCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/audit'],
})

const { fetchAuditLogs, fetchAuditStats, fetchDuplicates, fetchRiskIndicators } = useAudit()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()
const route = useRoute()
const router = useRouter()

const DEFAULT_AUDIT_PAGE_SIZE = 30
const query = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const loadingAudit = ref(true)
const auditLoadError = ref<string | null>(null)
const auditLogs = ref<AuditLog[]>([])
const auditMeta = ref<{ last_page: number; total: number; per_page: number } | null>(null)
const todayCount = ref(0)
const duplicates = ref<
  {
    invoice_number: string
    banks: string[]
    requests: { id: number; reference_number: string }[]
  }[]
>([])
const risks = ref<{ title: string; body: string; level: 'عالية' | 'متوسطة' | 'منخفضة' }[]>([])

const urlAuditPage = computed(() => Number(route.query.page ?? 1))
const urlAuditPageSize = computed(() => Number(route.query.perPage ?? DEFAULT_AUDIT_PAGE_SIZE))

const auditPagination = computed<PaginationState>(() => ({
  pageIndex: urlAuditPage.value - 1,
  pageSize: urlAuditPageSize.value,
}))

function onAuditPaginationChange(
  updater: PaginationState | ((old: PaginationState) => PaginationState),
) {
  const next = typeof updater === 'function' ? updater(auditPagination.value) : updater
  router.push({
    query: {
      ...route.query,
      page: next.pageIndex === 0 ? undefined : String(next.pageIndex + 1),
      perPage: next.pageSize === DEFAULT_AUDIT_PAGE_SIZE ? undefined : String(next.pageSize),
    },
  })
}

async function loadAuditLogs() {
  loadingAudit.value = true
  auditLoadError.value = null
  try {
    const result = await fetchAuditLogs({
      page: urlAuditPage.value,
      per_page: urlAuditPageSize.value,
    })
    auditLogs.value = result.data
    auditMeta.value = {
      last_page: result.meta.last_page,
      total: result.meta.total,
      per_page: result.meta.per_page,
    }
  } catch {
    auditLoadError.value = 'تعذّر تحميل سجل التدقيق. تحقق من الاتصال وأعد المحاولة.'
  } finally {
    loadingAudit.value = false
  }
}

watch([urlAuditPage, urlAuditPageSize], () => loadAuditLogs())

onMounted(async () => {
  const [logsResult, statsResult, dupsResult, risksResult] = await Promise.allSettled([
    fetchAuditLogs({ page: urlAuditPage.value, per_page: urlAuditPageSize.value }),
    fetchAuditStats(),
    fetchDuplicates(),
    fetchRiskIndicators(),
  ])
  if (logsResult.status === 'fulfilled') {
    auditLogs.value = logsResult.value.data
    auditMeta.value = {
      last_page: logsResult.value.meta.last_page,
      total: logsResult.value.meta.total,
      per_page: logsResult.value.meta.per_page,
    }
  } else {
    auditLoadError.value = 'تعذّر تحميل سجل التدقيق. تحقق من الاتصال وأعد المحاولة.'
  }
  if (statsResult.status === 'fulfilled') todayCount.value = statsResult.value.today_count
  if (dupsResult.status === 'fulfilled') duplicates.value = dupsResult.value
  if (risksResult.status === 'fulfilled') risks.value = risksResult.value
  loadingAudit.value = false
})

const filteredAudits = computed(() => {
  const q = query.value.trim()
  if (!q) return auditLogs.value
  const lower = q.toLowerCase()
  return auditLogs.value.filter(
    (entry) =>
      (entry.user?.name ?? '').toLowerCase().includes(lower) ||
      entry.action.toLowerCase().includes(lower),
  )
})

const kpis = computed(() => [
  {
    label: 'نشاطات اليوم',
    value: todayCount.value.toString(),
    icon: Activity,
    tone: 'text-info bg-info/10',
  },
  {
    label: 'تنبيهات مفتوحة',
    value: risks.value.length.toString(),
    icon: AlertTriangle,
    tone: 'text-[var(--color-text-warning)] bg-[var(--color-surface-warning)]',
  },
  {
    label: 'فواتير مكررة',
    value: duplicates.value.length.toString(),
    icon: FileWarning,
    tone: 'text-[var(--color-text-error)] bg-[var(--color-surface-error)]',
  },
  {
    label: 'حالات مخاطر',
    value: risks.value.filter((r) => r.level === 'عالية').length.toString(),
    icon: ShieldCheck,
    tone: 'text-[var(--color-text-error)] bg-[var(--color-surface-error)]',
  },
])

function kpiToneFromClass(tone: string): 'default' | 'info' | 'warning' | 'danger' {
  if (tone.includes('text-red')) return 'danger'
  if (tone.includes('text-amber')) return 'warning'
  if (tone.includes('text-info')) return 'info'
  return 'default'
}

// Smart summary bar computeds derived from loaded audit logs
const smartSummary = computed(() => {
  const logs = auditLogs.value
  const denied = logs.filter((l) => l.action === 'AUTHORIZATION_FAILURE')
  const failedLogins = logs.filter((l) => l.action === 'LOGIN_FAILED')
  const roleChanges = logs.filter(
    (l) => l.action === 'USER_UPDATED' && l.metadata && (l.metadata as any).role_changed,
  )
  const docDownloads = logs.filter((l) => l.action === 'DOCUMENT_DOWNLOADED')
  return {
    denied: denied.length,
    failedLogins: failedLogins.length,
    roleChanges: roleChanges.length,
    docDownloads: docDownloads.length,
  }
})

// Anomaly grouping: users with repeated denials or failed logins
const anomalyGroups = computed(() => {
  const logs = auditLogs.value
  const groups: { type: string; actor: string; count: number; level: 'عالية' | 'متوسطة' }[] = []

  // Repeated authorization failures by user
  const denialsByUser: Record<string, number> = {}
  for (const l of logs.filter((l) => l.action === 'AUTHORIZATION_FAILURE')) {
    const key = l.user?.name ?? `ID:${l.user_id}`
    denialsByUser[key] = (denialsByUser[key] ?? 0) + 1
  }
  for (const [actor, count] of Object.entries(denialsByUser)) {
    if (count >= 3)
      groups.push({
        type: 'رفض متكرر للصلاحيات',
        actor,
        count,
        level: count >= 5 ? 'عالية' : 'متوسطة',
      })
  }

  // Repeated failed logins by user
  const failsByUser: Record<string, number> = {}
  for (const l of logs.filter((l) => l.action === 'LOGIN_FAILED')) {
    const key = l.user?.name ?? l.user_role ?? 'مجهول'
    failsByUser[key] = (failsByUser[key] ?? 0) + 1
  }
  for (const [actor, count] of Object.entries(failsByUser)) {
    if (count >= 3)
      groups.push({
        type: 'محاولات دخول فاشلة متكررة',
        actor,
        count,
        level: count >= 5 ? 'عالية' : 'متوسطة',
      })
  }

  // Unusual document downloads (> 5 by same user)
  const downloadsByUser: Record<string, number> = {}
  for (const l of logs.filter((l) => l.action === 'DOCUMENT_DOWNLOADED')) {
    const key = l.user?.name ?? `ID:${l.user_id}`
    downloadsByUser[key] = (downloadsByUser[key] ?? 0) + 1
  }
  for (const [actor, count] of Object.entries(downloadsByUser)) {
    if (count >= 5) groups.push({ type: 'تحميل وثائق مكثف', actor, count, level: 'متوسطة' })
  }

  return groups.sort((a, b) => (a.level === 'عالية' ? -1 : 1) - (b.level === 'عالية' ? -1 : 1))
})

function formatDate(ts: string) {
  return new Date(ts).toLocaleString('ar-EG')
}

const ACTION_LABELS: Record<string, string> = {
  LOGIN: 'تسجيل دخول',
  LOGIN_FAILED: 'فشل تسجيل الدخول',
  LOGOUT: 'تسجيل خروج',
  SETTINGS_UPDATED: 'تحديث الإعدادات',
  USER_CREATED: 'إنشاء مستخدم',
  USER_UPDATED: 'تحديث مستخدم',
  USER_DEACTIVATED: 'تعطيل مستخدم',
  PASSWORD_CHANGED: 'تغيير كلمة المرور',
  MFA_ENABLED: 'تفعيل المصادقة الثنائية',
  MFA_DISABLED: 'تعطيل المصادقة الثنائية',
  CLAIM_RELEASED: 'إفراج عن المطالبة',
  AUTHORIZATION_FAILURE: 'فشل التفويض',
  WORKFLOW_TRANSITION: 'انتقال سير عمل',
  DOCUMENT_UPLOADED: 'رفع وثيقة',
  DOCUMENT_DOWNLOADED: 'تحميل وثيقة',
  VOTE_SUBMITTED: 'تسجيل تصويت',
  VOTING_SESSION_OPENED: 'فتح جلسة تصويت',
  VOTING_SESSION_CLOSED: 'إغلاق جلسة تصويت',
  CUSTOMS_DECLARATION_ISSUED: 'إصدار تأكيد المصارفة الخارجية',
  FX_CONFIRMATION_PENDING: 'بانتظار تأكيد المصارفة الخارجية',
  FX_CONFIRMATION_COMPLETED: 'إتمام تأكيد المصارفة الخارجية',
}

function formatAction(action: string): string {
  return ACTION_LABELS[action] ?? action.replace(/_/g, ' ')
}

// ── Table state ────────────────────────────────────────────────────────────────
const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})
const columnVisibility = ref<VisibilityState>({
  from_status: false,
  to_status: false,
})
const auditDataTableRef = ref<any>(null)

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

useTableKeyboard(searchInputRef, {
  onEscape: () => {
    query.value = ''
  },
})

function clearSelection() {
  rowSelection.value = {}
}

const columns: ColumnDef<AuditLog>[] = [
  {
    id: 'select',
    header: ({ table: t }) =>
      h(Checkbox, {
        modelValue:
          t.getIsAllPageRowsSelected() || (t.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (v: boolean | 'indeterminate') => t.toggleAllPageRowsSelected(!!v),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h('div', { onClick: (e: Event) => e.stopPropagation() }, [
        h(Checkbox, {
          modelValue: row.getIsSelected(),
          'onUpdate:modelValue': (v: boolean | 'indeterminate') => row.toggleSelected(!!v),
          'aria-label': 'تحديد السجل',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'user',
    header: 'المستخدم',
    cell: ({ row }) => h('span', { class: 'font-medium' }, row.original.user?.name ?? 'غير معروف'),
  },
  {
    accessorKey: 'action',
    header: 'الإجراء',
    cell: ({ row }) =>
      h(
        'span',
        {
          class:
            'inline-flex items-center rounded-md border border-border bg-muted px-2 py-0.5 text-xs font-medium text-foreground',
        },
        formatAction(row.original.action),
      ),
  },
  {
    accessorKey: 'from_status',
    header: 'من',
    cell: ({ row }) =>
      h('span', { class: 'text-xs text-muted-foreground' }, row.original.from_status ?? '—'),
  },
  {
    accessorKey: 'to_status',
    header: 'إلى',
    cell: ({ row }) =>
      h('span', { class: 'text-xs text-muted-foreground' }, row.original.to_status ?? '—'),
  },
  {
    accessorKey: 'created_at',
    header: 'التوقيت',
    cell: ({ row }) =>
      h('span', { class: 'text-xs text-muted-foreground' }, formatDate(row.original.created_at)),
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    cell: ({ row }) =>
      h(
        DropdownMenu,
        {},
        {
          default: () => [
            h(
              DropdownMenuTrigger,
              { asChild: true },
              {
                default: () =>
                  h(
                    Button,
                    {
                      variant: 'ghost',
                      size: 'icon',
                      class: 'h-8 w-8',
                      onClick: (e: Event) => e.stopPropagation(),
                    },
                    {
                      default: () => [
                        h('span', { class: 'sr-only' }, 'فتح القائمة'),
                        h(MoreHorizontal, { class: 'h-4 w-4' }),
                      ],
                    },
                  ),
              },
            ),
            h(
              DropdownMenuContent,
              { align: 'end' },
              {
                default: () => [
                  h(
                    DropdownMenuItem,
                    { onClick: () => exportAuditRows([row.original], 'single') },
                    () => 'تصدير السجل',
                  ),
                ],
              },
            ),
          ],
        },
      ),
  },
]

const AUDIT_COLUMN_LABELS: Record<string, string> = {
  user: 'المستخدم',
  action: 'الإجراء',
  from_status: 'من',
  to_status: 'إلى',
  created_at: 'التوقيت',
}

function buildAuditExportColumns() {
  return [
    {
      key: 'user',
      label: 'المستخدم',
      format: (_value: any, row: AuditLog) => row.user?.name ?? 'غير معروف',
    },
    {
      key: 'action',
      label: 'الإجراء',
      format: (_value: any, row: AuditLog) => formatAction(row.action),
    },
    { key: 'from_status', label: 'من' },
    { key: 'to_status', label: 'إلى' },
    {
      key: 'created_at',
      label: 'التوقيت',
      format: (_value: any, row: AuditLog) => formatDate(row.created_at),
    },
    { key: 'ip_address', label: 'IP' },
  ] as const
}

function exportAuditRows(
  rows: AuditLog[],
  suffix: 'filtered' | 'selected' | 'single',
  format: 'csv' | 'excel' | 'json' = 'csv',
) {
  if (!rows.length) return
  const stamp = new Date().toISOString().slice(0, 10)
  const filename = `audit-logs-${suffix}-${stamp}`
  const cols = buildAuditExportColumns() as any
  const data = rows as any as Record<string, any>[]
  if (format === 'csv') exportToCSV(data, cols, filename)
  else if (format === 'excel') exportToExcel(data, cols, filename)
  else exportToJSON(data, cols, filename)
}

function exportSelectedAuditRows(format: 'csv' | 'excel' | 'json' = 'csv') {
  const rows =
    auditDataTableRef.value?.table?.getSelectedRowModel?.().rows?.map((row: any) => row.original) ??
    []
  if (rows.length > 0) {
    exportAuditRows(rows, 'selected', format)
    return
  }
  exportAuditRows(filteredAudits.value, 'filtered', format)
}

// ── Row expansion ─────────────────────────────────────────────────────────────
const expandedLogs = ref(new Set<number>())

function toggleLog(id: number) {
  if (expandedLogs.value.has(id)) {
    expandedLogs.value.delete(id)
  } else {
    expandedLogs.value.add(id)
  }
  expandedLogs.value = new Set(expandedLogs.value)
}

function truncateUa(ua: string | null | undefined, max = 80): string {
  if (!ua) return '—'
  return ua.length > max ? ua.slice(0, max) + '…' : ua
}

type AuditLogMeta = { before?: Record<string, any>; after?: Record<string, any> } | null

const MISSING_DIFF_VALUE = '—'
const EMPTY_DIFF_VALUE = 'فارغ'

function hasDiffValue(record: Record<string, any>, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(record, key)
}

function formatDiffValue(record: Record<string, any>, key: string): any {
  if (!hasDiffValue(record, key)) return MISSING_DIFF_VALUE
  const value = record[key]
  return value === null ? EMPTY_DIFF_VALUE : value
}

function diffRows(meta: AuditLogMeta): Array<{ key: string; before: any; after: any }> {
  if (!meta) return []
  const before = (meta.before ?? {}) as Record<string, any>
  const after = (meta.after ?? {}) as Record<string, any>
  const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]))
  return keys
    .filter((key) => {
      const bHas = hasDiffValue(before, key)
      const aHas = hasDiffValue(after, key)
      if (!bHas && !aHas) return false
      return !bHas || !aHas || before[key] !== after[key]
    })
    .map((key) => ({
      key,
      before: formatDiffValue(before, key),
      after: formatDiffValue(after, key),
    }))
}
</script>

<template>
  <div>
    <PageHeader
      title="التدقيق والامتثال"
      subtitle="سجل النشاط، كشف الفواتير المكررة، وتنبيهات المخاطر الأمنية"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التدقيق والامتثال' }]"
    />

    <LoadErrorAlert
      v-if="auditLoadError"
      class="mb-4"
      :message="auditLoadError"
      title="تعذّر تحميل سجل التدقيق"
      @retry="loadAuditLogs()"
    />

    <!-- Smart summary bar -->
    <div v-if="!loadingAudit && !auditLoadError" class="mb-4 space-y-2">
      <Card
        v-if="smartSummary.denied >= 3"
        class="border-0 border-[var(--severity-red)] bg-[var(--severity-red)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ smartSummary.denied }} محاولة وصول مرفوضة — مراجعة التفويض مطلوبة
          </span>
        </div>
      </Card>
      <Card
        v-if="smartSummary.failedLogins >= 5"
        class="border-0 border-[var(--severity-red)] bg-[var(--severity-red)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ smartSummary.failedLogins }} محاولة دخول فاشلة — تحقق من الأنشطة المشبوهة
          </span>
        </div>
      </Card>
      <Card
        v-if="smartSummary.roleChanges > 0"
        class="border-0 border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4 py-3">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ smartSummary.roleChanges }} تغيير دور حساس — مراجعة الصلاحيات مطلوبة
          </span>
        </div>
      </Card>
    </div>

    <div v-if="!auditLoadError" class="mb-6">
      <MetricGrid :columns="4">
        <MetricCard
          v-for="kpi in kpis"
          :key="kpi.label"
          :label="kpi.label"
          :value="kpi.value"
          :icon="kpi.icon"
          :tone="kpiToneFromClass(kpi.tone)"
          :clickable="false"
        />
      </MetricGrid>
    </div>

    <Tabs v-if="!auditLoadError" default-value="logs">
      <TabsList>
        <TabsTrigger value="logs"> سجل النشاط </TabsTrigger>
        <TabsTrigger value="duplicates"> الفواتير المكررة </TabsTrigger>
        <TabsTrigger value="risk"> مؤشرات المخاطر </TabsTrigger>
        <TabsTrigger value="anomalies">
          الأنماط الشاذة
          <Badge
            v-if="anomalyGroups.length > 0"
            class="ms-1.5 border border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[10px] text-[var(--severity-red)]"
          >
            {{ anomalyGroups.length }}
          </Badge>
        </TabsTrigger>
      </TabsList>

      <TabsContent value="logs" class="mt-4">
        <!-- Bulk toolbar (when rows selected) OR search row (default) -->
        <div
          v-if="selectedCount > 0"
          class="border-primary/20 bg-primary/5 mb-3 flex items-center gap-2 rounded-lg border px-3 py-2"
        >
          <span class="text-primary text-sm font-medium">{{ selectedCount }} محدد</span>
          <div class="bg-border mx-2 h-4 w-px" />
          <DataTableBulkExport
            @csv="exportSelectedAuditRows('csv')"
            @excel="exportSelectedAuditRows('excel')"
            @json="exportSelectedAuditRows('json')"
          />
          <Button
            variant="ghost"
            size="sm"
            class="text-muted-foreground ms-auto h-7 gap-1 text-xs"
            @click="clearSelection"
          >
            <X class="h-3.5 w-3.5" />
            إلغاء التحديد
          </Button>
        </div>

        <div v-else class="mb-3 flex items-center gap-2">
          <div class="relative max-w-xs flex-1">
            <Input
              ref="searchInputRef"
              v-model="query"
              class="h-8 rounded-md pe-9 text-sm"
              placeholder="بحث: مستخدم، إجراء..."
            />
          </div>
          <Button
            variant="outline"
            size="sm"
            class="h-8 gap-1.5"
            :disabled="filteredAudits.length === 0"
            @click="exportAuditRows(filteredAudits, 'filtered')"
          >
            <Download class="h-4 w-4" />
            تصدير
          </Button>
        </div>

        <Card class="border-0 p-4 shadow">
          <DataTable
            ref="auditDataTableRef"
            :data="auditLogs"
            :columns="columns"
            :loading="loadingAudit"
            :page-count="auditMeta?.last_page ?? 1"
            :pagination="auditPagination"
            :sorting="sorting"
            :column-filters="columnFilters"
            :row-selection="rowSelection"
            :column-visibility="columnVisibility"
            :is-row-expanded="(row) => expandedLogs.has(row.id)"
            row-class="border-t hover:bg-muted/30"
            @update:pagination="onAuditPaginationChange"
            @update:sorting="(v) => (sorting = v)"
            @update:column-filters="(v) => (columnFilters = v)"
            @update:row-selection="(v) => (rowSelection = v)"
            @update:column-visibility="(v) => (columnVisibility = v)"
            @row-click="(row) => toggleLog(row.id)"
          >
            <template #toolbar="{ table }">
              <div class="flex justify-end">
                <DataTableViewOptions :table="table" :column-labels="AUDIT_COLUMN_LABELS" />
              </div>
            </template>
            <template #empty>
              <Empty class="bg-muted/20 min-h-[200px] rounded-xl border border-dashed">
                <EmptyHeader>
                  <div
                    class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
                  >
                    <SearchX class="size-5" />
                  </div>
                  <EmptyTitle>لا توجد سجلات مطابقة</EmptyTitle>
                </EmptyHeader>
                <EmptyContent>
                  <EmptyDescription>جرّب تغيير نص البحث.</EmptyDescription>
                </EmptyContent>
              </Empty>
            </template>
            <template #row-expanded="{ row }">
              <div class="text-muted-foreground mb-2 text-xs">
                <span class="font-medium">IP: </span>{{ row.ip_address ?? '—' }}
                <span class="mx-2">·</span>
                <span data-testid="log-ua-full">{{ truncateUa(row.user_agent) }}</span>
              </div>
              <template v-if="diffRows(row.metadata as AuditLogMeta).length > 0">
                <table data-testid="log-diff-table" class="w-full rounded border text-xs">
                  <thead>
                    <tr class="bg-muted/40 border-b">
                      <th class="px-3 py-1.5 text-start font-medium">الحقل</th>
                      <th class="px-3 py-1.5 text-start font-medium">قبل</th>
                      <th class="px-3 py-1.5 text-start font-medium">بعد</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="dr in diffRows(row.metadata as AuditLogMeta)"
                      :key="dr.key"
                      class="border-b last:border-0"
                    >
                      <td class="px-3 py-1.5 font-mono">{{ dr.key }}</td>
                      <td class="px-3 py-1.5 text-[var(--severity-red)]">{{ dr.before }}</td>
                      <td class="px-3 py-1.5 text-[var(--severity-green)]">{{ dr.after }}</td>
                    </tr>
                  </tbody>
                </table>
              </template>
              <div v-else class="detail-empty text-muted-foreground text-xs">
                لا توجد تفاصيل إضافية
              </div>
            </template>
            <template #pagination="{ table }">
              <DataTablePagination :table="table" :total-rows="auditMeta?.total" />
            </template>
          </DataTable>
        </Card>
      </TabsContent>

      <TabsContent value="duplicates" class="mt-4">
        <Card class="border-0 p-5 shadow">
          <div
            v-if="duplicates.length > 0"
            class="border-destructive/30 mb-4 flex items-center gap-2 rounded-lg border bg-[var(--color-surface-error)] p-3"
          >
            <AlertTriangle class="h-5 w-5 text-[var(--color-text-error)]" />
            <div class="text-sm">
              <span class="font-semibold">تم اكتشاف {{ duplicates.length }} حالات</span>
              لفواتير مكررة بحاجة لمراجعة عاجلة.
            </div>
          </div>

          <Empty
            v-if="duplicates.length === 0"
            class="bg-muted/20 min-h-[160px] rounded-xl border border-dashed"
          >
            <EmptyHeader>
              <EmptyTitle>لا توجد فواتير مكررة</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>لم يُكتشف أي تكرار في أرقام الفواتير.</EmptyDescription>
            </EmptyContent>
          </Empty>

          <div class="space-y-3">
            <div
              v-for="dup in duplicates"
              :key="dup.invoice_number"
              class="hover:border-destructive/40 rounded-lg border p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="flex items-center gap-2">
                    <Badge variant="destructive"> مكرر </Badge>
                    <span class="font-mono font-semibold">{{ dup.invoice_number }}</span>
                  </div>
                  <div class="text-muted-foreground mt-1 text-xs">
                    البنوك: {{ dup.banks.join('، ') }}
                  </div>
                </div>
                <div class="text-muted-foreground text-start text-xs">
                  {{ dup.requests.length }} طلبات مرتبطة
                </div>
              </div>
            </div>
          </div>
        </Card>
      </TabsContent>

      <TabsContent value="risk" class="mt-4">
        <RankedListCard title="مؤشرات المخاطر النشطة" content-class="p-5">
          <Empty
            v-if="risks.length === 0"
            class="bg-muted/20 min-h-[160px] rounded-xl border border-dashed"
          >
            <EmptyHeader>
              <EmptyTitle>لا توجد مؤشرات مخاطر</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>النظام في وضع سليم، لا تنبيهات نشطة.</EmptyDescription>
            </EmptyContent>
          </Empty>

          <div class="space-y-3">
            <div
              v-for="risk in risks"
              :key="risk.title"
              class="flex items-start gap-3 rounded-lg border p-3"
            >
              <ShieldCheck
                :class="[
                  'mt-0.5 h-5 w-5',
                  risk.level === 'عالية'
                    ? 'text-[var(--color-text-error)]'
                    : risk.level === 'متوسطة'
                      ? 'text-[var(--color-text-warning)]'
                      : 'text-info',
                ]"
              />
              <div class="flex-1">
                <div class="text-sm font-medium">
                  {{ risk.title }}
                </div>
                <div class="text-muted-foreground text-xs">
                  {{ risk.body }}
                </div>
              </div>
              <Badge :variant="risk.level === 'عالية' ? 'destructive' : 'secondary'">
                {{ risk.level }}
              </Badge>
            </div>
          </div>
        </RankedListCard>
      </TabsContent>

      <TabsContent value="anomalies" class="mt-4">
        <InsightsTabsCard
          title="تجميع الأنماط الشاذة"
          description="محاولات رفض متكررة، دخول فاشل، تحميل وثائق مكثف"
          content-class="p-5"
        >
          <Empty
            v-if="anomalyGroups.length === 0"
            class="bg-muted/20 min-h-[160px] rounded-xl border border-dashed"
          >
            <EmptyHeader>
              <EmptyTitle>لا توجد أنماط شاذة</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>لم يُرصد أي نمط غير طبيعي في السجلات المحملة.</EmptyDescription>
            </EmptyContent>
          </Empty>

          <div class="space-y-3">
            <div
              v-for="(group, idx) in anomalyGroups"
              :key="idx"
              class="flex items-center gap-3 rounded-lg border p-3"
              :class="
                group.level === 'عالية'
                  ? 'border-[var(--severity-red)]/30 bg-[var(--severity-red)]/5'
                  : 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5'
              "
            >
              <AlertTriangle
                class="h-4 w-4 shrink-0"
                :class="
                  group.level === 'عالية'
                    ? 'text-[var(--severity-red)]'
                    : 'text-[var(--severity-amber)]'
                "
              />
              <div class="flex-1">
                <div class="font-section text-foreground text-sm leading-5 font-semibold">
                  {{ group.type }}
                </div>
                <div class="text-muted-foreground text-xs leading-5">{{ group.actor }}</div>
              </div>
              <div class="text-foreground text-sm leading-5 font-semibold tabular-nums">
                {{ group.count }}×
              </div>
              <Badge
                class="border text-xs"
                :class="
                  group.level === 'عالية'
                    ? 'border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]'
                    : 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
                "
              >
                {{ group.level }}
              </Badge>
            </div>
          </div>
          <template #aside>
            <MetricCard
              label="أنماط عالية"
              :value="anomalyGroups.filter((g) => g.level === 'عالية').length"
              tone="danger"
              :clickable="false"
            />
            <MetricCard
              label="إجمالي التجميعات"
              :value="anomalyGroups.length"
              tone="warning"
              :clickable="false"
            />
          </template>
        </InsightsTabsCard>
      </TabsContent>
    </Tabs>
  </div>
</template>
