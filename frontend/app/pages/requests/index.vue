<script setup lang="ts">
import type { ColumnFiltersState, VisibilityState } from '@tanstack/vue-table'
import {
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import {
  AlertCircle, CheckCircle2, ClipboardList,
  Edit, Eye, FilePlus2, Lock, RefreshCw, SearchX, Vote, Upload,
} from 'lucide-vue-next'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import PageHeader from '@/components/layout/PageHeader.vue'
import { buildRequestsExportColumns } from '@/composables/useRequestsExport'
import { buildRequestsEmptyState } from '@/composables/useRequestsEmptyState'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { RequestStatus, UserRole } from '@/types/enums'
import {
  BANK_ROLES,
  CBY_BANK_FILTER_ROLES,
  ROUTE_ROLE_MAP,
  STATUS_LABELS,
} from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'
import { useBanks } from '@/composables/useBanks'
import { useRequestsColumns, buildStatusFilterOptions } from '@/composables/useRequestsColumns'
import type { Bank } from '@/types/models'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import type { ImportRequest } from '@/types/models'
import { useTableExport } from '@/composables/useTableExport'
import {
  DataTable,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'
import { REQUESTS_COLUMN_LABELS } from '@/composables/useRequestsColumns'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests'],
})

const authStore = useAuthStore()
const store = useRequestsStore()
const { fetchBanks } = useBanks()
const { exportToCSV } = useTableExport()

const user = computed(() => authStore.user)
const query = ref('')
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({
  last_activity: false,
  cby_age: false,
  cby_sla: false,
  cby_voting: false,
  cby_fx: false,
  cby_risk: false,
  director_ready_to_close: false,
  director_fx_state: false,
  swift_documents: false,
})
const rowSelection = ref<Record<string, boolean>>({})
const banks = ref<Bank[]>([])

// Quick preview dialog — available for all roles via reference-number click
const previewRequest = ref<ImportRequest | null>(null)
const previewOpen = ref(false)

function openPreview(request: ImportRequest) {
  previewRequest.value = request
  previewOpen.value = true
}

const isCbyAdmin = computed(() => user.value?.role === UserRole.CBY_ADMIN)
const isDirector = computed(() => user.value?.role === UserRole.COMMITTEE_DIRECTOR)
const isBankAdmin = computed(() => user.value?.role === UserRole.BANK_ADMIN)
const isSupportCommittee = computed(() => user.value?.role === UserRole.SUPPORT_COMMITTEE)
const isDataEntry = computed(() => user.value?.role === UserRole.DATA_ENTRY)
const isSwiftOfficer = computed(() => user.value?.role === UserRole.SWIFT_OFFICER)
const isExecutiveMember = computed(() => user.value?.role === UserRole.EXECUTIVE_MEMBER)
const isBankScoped = computed(() => user.value ? BANK_ROLES.includes(user.value.role) : false)
const showBankFilter = computed(() => user.value ? CBY_BANK_FILTER_ROLES.includes(user.value.role) : false)
const canCreateRequest = computed(() => user.value?.role === UserRole.DATA_ENTRY)

const currentUserId = computed(() => authStore.user?.id ?? null)

const { columns } = useRequestsColumns({
  role: computed(() => user.value?.role ?? UserRole.DATA_ENTRY),
  currentUserId,
  onPreviewClick: openPreview,
})

onMounted(async () => {
  await store.loadRequests({ per_page: 200 })
  if (user.value && CBY_BANK_FILTER_ROLES.includes(user.value.role)) {
    banks.value = await fetchBanks()
  }
})

// Pre-filter by search query — TanStack handles column filters
const filteredRequests = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return store.requests
  return store.requests.filter(req =>
    req.reference_number.toLowerCase().includes(q)
    || (req.merchant?.name ?? '').toLowerCase().includes(q)
    || (req.invoice_number ?? '').toLowerCase().includes(q),
  )
})

const hasActiveFilters = computed(() =>
  columnFilters.value.length > 0 || query.value.trim().length > 0,
)

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

// KPIs computed from ALL store requests (not filtered — for accurate totals)
const requestKpis = computed(() => {
  const rows = store.requests
  const total = rows.length
  const approved = rows.filter(req => [RequestStatus.BANK_APPROVED, RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED].includes(req.status)).length
  const rejected = rows.filter(req => [RequestStatus.BANK_REJECTED, RequestStatus.EXECUTIVE_REJECTED, RequestStatus.SUPPORT_REJECTED].includes(req.status)).length
  const pending = Math.max(total - approved - rejected, 0)
  const approvalRate = total > 0 ? Math.round((approved / total) * 100) : 0
  return { total, approved, pending, rejected, approvalRate }
})

const cbySmartSummary = computed(() => {
  if (!isCbyAdmin.value) return []
  const reqs = store.requests
  const needsAttention = reqs.filter(r =>
    [RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED, RequestStatus.SUPPORT_REJECTED].includes(r.status),
  ).length
  const voting = reqs.filter(r =>
    [RequestStatus.WAITING_FOR_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_CLOSED].includes(r.status),
  ).length
  const fxPending = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_APPROVED).length
  const now = Date.now()
  const stalledCount = reqs.filter((r) => {
    const updated = new Date(r.updated_at).getTime()
    const ageDays = (now - updated) / (1000 * 60 * 60 * 24)
    return ageDays > 2
      && r.status !== RequestStatus.COMPLETED
      && r.status !== RequestStatus.BANK_REJECTED
      && r.status !== RequestStatus.SUPPORT_REJECTED
      && r.status !== RequestStatus.EXECUTIVE_REJECTED
  }).length
  const items: Array<{ label: string; count: number; statuses: RequestStatus[]; color: string }> = []
  if (needsAttention > 0) items.push({ label: 'يحتاج متابعة', count: needsAttention, statuses: [RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED, RequestStatus.SUPPORT_REJECTED], color: 'var(--severity-amber)' })
  if (voting > 0) items.push({ label: 'تصويت نشط', count: voting, statuses: [RequestStatus.WAITING_FOR_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_CLOSED], color: '#5856d6' })
  if (fxPending > 0) items.push({ label: 'انتظار تأكيد المصارفة', count: fxPending, statuses: [RequestStatus.EXECUTIVE_APPROVED], color: 'var(--severity-red)' })
  if (stalledCount > 0) items.push({ label: 'طلبات متوقفة > 48 ساعة', count: stalledCount, statuses: [RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW, RequestStatus.BANK_APPROVED, RequestStatus.SUPPORT_REVIEW_PENDING], color: 'var(--severity-amber)' })
  return items
})

const directorSmartSummary = computed(() => {
  if (!isDirector.value) return []
  const reqs = store.requests
  const activeVoting = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN).length
  const pendingTieBreak = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN && r.is_tie).length
  const pendingFx = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_APPROVED).length
  const now = Date.now()
  const weekAgo = now - (7 * 24 * 60 * 60 * 1000)
  const finalizedThisWeek = reqs.filter((r) => {
    if (!(r.status === RequestStatus.EXECUTIVE_APPROVED || r.status === RequestStatus.EXECUTIVE_REJECTED)) return false
    const updated = new Date(r.updated_at).getTime()
    return !Number.isNaN(updated) && updated >= weekAgo
  }).length
  return [
    { key: 'active_voting', label: 'جلسات نشطة', count: activeVoting, statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN], color: '#5856d6' },
    { key: 'tie_break', label: 'تعادل يحتاج حسماً', count: pendingTieBreak, statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN], color: 'var(--severity-amber)' },
    { key: 'fx_pending', label: 'بانتظار تأكيد المصارفة', count: pendingFx, statuses: [RequestStatus.EXECUTIVE_APPROVED], color: 'var(--severity-amber)' },
    { key: 'finalized', label: 'مُنهاة هذا الأسبوع', count: finalizedThisWeek, statuses: [RequestStatus.EXECUTIVE_APPROVED, RequestStatus.EXECUTIVE_REJECTED], color: 'var(--severity-green)' },
  ]
})

// Status filter options built from STATUS_LABELS
const statusFilterOptions = computed(() => buildStatusFilterOptions())

// Bank filter options for roles that see bank filter
const bankFilterOptions = computed(() =>
  banks.value.map(b => ({ label: b.name_ar || b.name_en || '', value: String(b.id) })),
)

const table = useVueTable({
  get data() { return filteredRequests.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onColumnFiltersChange: updater =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onColumnVisibilityChange: updater =>
    (columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  onRowSelectionChange: updater =>
    (rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater),
  state: {
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
  initialState: { pagination: { pageSize: 20 } },
})

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function clearBulkSelection() {
  table.resetRowSelection()
}

function openRequest(id: number) {
  navigateTo(`/requests/${id}`)
}

const exportColumns = computed(() => {
  if (!user.value) return []
  return buildRequestsExportColumns(user.value.role)
})

function buildExportFilename(): string {
  return `requests-${new Date().toISOString().slice(0, 10)}`
}

function exportSelectedRows() {
  const rows = table.getFilteredSelectedRowModel().rows.map(row => row.original)
  if (!rows.length || !exportColumns.value.length) return
  exportToCSV(rows, exportColumns.value, `${buildExportFilename()}-selected`)
}

// MetricCard quick-filter handlers
const pendingStatuses: RequestStatus[] = [
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
  RequestStatus.BANK_RETURNED,
  RequestStatus.SUPPORT_RETURNED,
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.FX_CONFIRMATION_PENDING,
]

function filterByApproved() {
  table.getColumn('status')?.setFilterValue([
    RequestStatus.BANK_APPROVED,
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.COMPLETED,
  ])
}

function filterByPending() {
  table.getColumn('status')?.setFilterValue(pendingStatuses)
}

function filterByRejected() {
  table.getColumn('status')?.setFilterValue([
    RequestStatus.BANK_REJECTED,
    RequestStatus.EXECUTIVE_REJECTED,
    RequestStatus.SUPPORT_REJECTED,
  ])
}

function filterBySmartSummary(statuses: RequestStatus[]) {
  table.getColumn('status')?.setFilterValue(statuses)
}

// Preview dialog helpers
function relativeAge(isoDate: string | null | undefined): string {
  if (!isoDate) return '—'
  const ms = Date.now() - new Date(isoDate).getTime()
  const hrs = Math.floor(ms / 3600000)
  if (hrs < 24) return `${hrs} ساعة`
  return `${Math.floor(hrs / 24)} يوم`
}

function slaState(request: ImportRequest): { label: string; color: string } {
  const hrs = (Date.now() - new Date(request.created_at).getTime()) / 3600000
  if (hrs > 120) return { label: 'انتهاك SLA', color: 'var(--severity-red)' }
  if (hrs > 72) return { label: 'خطر SLA', color: 'var(--severity-amber)' }
  return { label: 'ضمن SLA', color: 'var(--severity-green)' }
}

function formatDate(isoDate: string | null | undefined): string {
  if (!isoDate) return '—'
  return new Date(isoDate).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' })
}

const requestsEmptyState = computed(() => buildRequestsEmptyState({
  role: user.value?.role,
  hasAnyRequests: store.requests.length > 0,
  hasActiveFilters: hasActiveFilters.value,
}))
</script>

<template>
  <div v-if="user">
    <PageHeader
      title="طلبات تمويل الواردات"
      :subtitle="isBankScoped ? 'طلبات جهتك فقط' : 'جميع الطلبات المقدمة عبر المنصة مع حالاتها ومراحل المعالجة'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الطلبات' }]"
    >
      <template #actions>
        <Button
          variant="outline"
          size="sm"
          class="h-8"
          :disabled="store.loadingList"
          @click="store.loadRequests({ per_page: 200 })"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': store.loadingList }" />
          <span class="hidden lg:inline">تحديث</span>
        </Button>
        <Button v-if="canCreateRequest" as="a" href="/requests/new" size="sm" class="h-8">
          <FilePlus2 class="h-4 w-4" />
          <span class="hidden lg:inline">طلب جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- BANK_ADMIN: read-only oversight chip -->
    <div v-if="isBankAdmin" class="mb-4">
      <Badge variant="outline" class="gap-1 rounded-full px-3 py-1 text-xs font-medium text-muted-foreground border-border">
        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        إدارة وعرض فقط
      </Badge>
    </div>

    <!-- CBY_ADMIN: Smart Summary Bar — operational exceptions -->
    <div
      v-if="isCbyAdmin && cbySmartSummary.length > 0"
      class="mb-4 flex flex-wrap gap-2"
      role="region"
      aria-label="ملخص استثنائي"
      data-testid="cby-smart-summary"
    >
      <button
        v-for="item in cbySmartSummary"
        :key="item.label"
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium transition-colors hover:bg-muted/60 cursor-pointer"
        :style="{ borderColor: item.color, color: item.color }"
        @click="filterBySmartSummary(item.statuses)"
      >
        <span class="font-bold">{{ item.count }}</span>
        {{ item.label }}
      </button>
    </div>

    <!-- COMMITTEE_DIRECTOR: Smart Summary Bar -->
    <div
      v-if="isDirector"
      class="mb-4 flex flex-wrap gap-2"
      role="region"
      aria-label="ملخص الحوكمة"
      data-testid="director-smart-summary"
    >
      <button
        v-for="item in directorSmartSummary"
        :key="item.key"
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium transition-colors hover:bg-muted/60 cursor-pointer"
        :style="{ borderColor: item.color, color: item.color }"
        @click="filterBySmartSummary(item.statuses)"
      >
        <span class="font-bold">{{ item.count }}</span>
        {{ item.label }}
      </button>
    </div>

    <!-- Inline error state -->
    <Alert v-if="store.error" variant="destructive" role="alert" class="mb-4">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في تحميل الطلبات</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="store.loadRequests({ per_page: 200 })">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <!-- KPI Cards — clicking sets a column filter -->
    <div class="mb-6">
      <MetricGrid :columns="4">
        <MetricCard
          label="إجمالي الطلبات"
          :value="requestKpis.total"
          :icon="ClipboardList"
          description="المعروض حسب الفلاتر"
          :active="columnFilters.length === 0"
          @click="table.resetColumnFilters()"
        />
        <MetricCard
          label="معتمدة"
          :value="requestKpis.approved"
          :icon="CheckCircle2"
          tone="success"
          :description="`معدل اعتماد ${requestKpis.approvalRate}%`"
          :active="columnFilters.some(f => f.id === 'status' && Array.isArray(f.value) && f.value.includes('COMPLETED'))"
          @click="filterByApproved"
        />
        <MetricCard
          label="قيد المعالجة"
          :value="requestKpis.pending"
          :icon="Lock"
          tone="warning"
          description="بانتظار الإجراء"
          :active="columnFilters.some(f => f.id === 'status' && Array.isArray(f.value) && f.value.includes('SUBMITTED'))"
          @click="filterByPending"
        />
        <MetricCard
          label="مرفوضة"
          :value="requestKpis.rejected"
          :icon="AlertCircle"
          tone="danger"
          description="تحتاج متابعة"
          :active="columnFilters.some(f => f.id === 'status' && Array.isArray(f.value) && f.value.includes('BANK_REJECTED'))"
          @click="filterByRejected"
        />
      </MetricGrid>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="filteredRequests"
        :columns="columns"
        :loading="store.loadingList"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        :row-selection="rowSelection"
        @update:column-filters="(v) => columnFilters = v"
        @update:column-visibility="(v) => columnVisibility = v"
        @update:row-selection="(v) => rowSelection = v"
        @row-click="(row) => openRequest((row as ImportRequest).id)"
      >
        <template #toolbar="{ table }">
          <DataTableToolbar
            :table="table"
            search-placeholder="بحث برقم الطلب، التاجر، أو رقم الفاتورة..."
            :has-filters="hasActiveFilters"
            :selected-count="selectedCount"
            @update:search="v => query = v"
            @reset="handleReset"
            @export-selected="exportSelectedRows"
            @clear-selection="clearBulkSelection"
          >
            <template #filters>
              <DataTableFacetedFilter
                v-if="table.getColumn('status')"
                :column="table.getColumn('status')!"
                title="الحالة"
                :options="statusFilterOptions"
              />
              <DataTableFacetedFilter
                v-if="showBankFilter && table.getColumn('merchant') && bankFilterOptions.length > 0"
                :column="table.getColumn('merchant')!"
                title="البنك"
                :options="bankFilterOptions"
              />
            </template>
            <template #actions>
              <DataTableViewOptions :table="table" :column-labels="REQUESTS_COLUMN_LABELS" />
              <DataTableExport
                :table="(table as any)"
                :export-columns="(exportColumns as any)"
                :filename="buildExportFilename()"
                :formats="['csv', 'tsv', 'json', 'excel', 'pdf']"
                :respect-column-visibility="true"
              />
            </template>
          </DataTableToolbar>
        </template>
        <template #empty>
          <Empty class="min-h-[280px] rounded-xl border border-dashed bg-muted/20">
            <EmptyHeader>
              <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <SearchX class="size-5" />
              </div>
              <EmptyTitle>{{ requestsEmptyState?.title ?? 'لا توجد طلبات مطابقة' }}</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                {{ requestsEmptyState?.description ?? 'جرّب تغيير البحث أو الفلاتر لعرض الطلبات المتاحة.' }}
              </EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table }">
          <DataTablePagination :table="table" />
        </template>
      </DataTable>
    </div>

    <!-- Quick Preview Dialog — all roles, triggered by reference-number click -->
    <Dialog v-model:open="previewOpen">
      <DialogContent v-if="previewRequest" class="sm:max-w-lg">
        <DialogHeader class="pb-2">
          <DialogTitle class="flex items-center gap-2 text-base">
            <span class="font-mono text-lg font-bold text-primary">{{ previewRequest.reference_number }}</span>
            <Badge variant="outline" class="text-xs font-normal">معاينة سريعة</Badge>
          </DialogTitle>
          <DialogDescription class="text-xs">
            انقر على الطلب في أي وقت للوصول إلى الصفحة الكاملة وجميع الإجراءات
          </DialogDescription>
        </DialogHeader>

        <!-- Status row -->
        <div class="flex flex-wrap items-center gap-2 py-1">
          <StatusBadge :status="previewRequest.status" :role="user!.role" />

          <!-- SLA badge (CBY Admin / Director) -->
          <Badge
            v-if="isCbyAdmin || isDirector"
            class="border text-xs"
            :style="{
              backgroundColor: `${slaState(previewRequest).color}18`,
              color: slaState(previewRequest).color,
              borderColor: `${slaState(previewRequest).color}38`,
            }"
          >
            {{ slaState(previewRequest).label }}
          </Badge>

          <!-- Voting badge -->
          <Badge
            v-if="previewRequest.voting_session_status && (isExecutiveMember || isDirector || isCbyAdmin)"
            class="border border-[var(--voting)]/30 bg-[var(--voting)]/10 text-[var(--voting)] text-xs"
          >
            <Vote class="me-1 h-3 w-3" />
            {{ previewRequest.voting_session_status }}
          </Badge>

          <!-- Claim badge (Support Committee) -->
          <Badge
            v-if="isSupportCommittee && previewRequest.is_claimed"
            class="border text-xs"
            :class="previewRequest.is_claimed_by_me
              ? 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
              : 'border-border bg-muted text-muted-foreground'"
          >
            <Lock class="me-1 h-3 w-3" />
            {{ previewRequest.is_claimed_by_me ? 'محجوز لك' : 'محجوز' }}
          </Badge>

          <!-- Duplicate warning -->
          <Badge
            v-if="(previewRequest.duplicate_warnings?.length ?? 0) > 0"
            variant="destructive"
            class="rounded-full text-xs"
          >
            فاتورة مكررة
          </Badge>
        </div>

        <Separator />

        <!-- Key info grid -->
        <div class="grid grid-cols-2 gap-x-4 gap-y-3 py-1 text-sm">
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">البنك</p>
            <p class="font-medium">{{ previewRequest.bank_name ?? '—' }}</p>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">التاجر</p>
            <p class="font-medium">{{ previewRequest.merchant?.name ?? '—' }}</p>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">المبلغ</p>
            <p class="font-mono font-semibold">
              {{ previewRequest.amount.toLocaleString('en-US') }}
              <span class="text-muted-foreground">{{ previewRequest.currency }}</span>
            </p>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">تاريخ التقديم</p>
            <p class="font-medium">{{ formatDate(previewRequest.created_at) }}</p>
          </div>

          <!-- CBY Admin / Director extras -->
          <template v-if="isCbyAdmin || isDirector">
            <div class="space-y-0.5">
              <p class="text-xs text-muted-foreground">عمر الطلب</p>
              <p class="font-medium">{{ relativeAge(previewRequest.created_at) }}</p>
            </div>
            <div class="space-y-0.5">
              <p class="text-xs text-muted-foreground">المصارفة الخارجية</p>
              <p class="font-medium" :class="previewRequest.has_fx_request_document ? 'text-[var(--severity-green)]' : 'text-muted-foreground'">
                {{ previewRequest.has_fx_request_document ? 'مرفوعة' : 'لم ترفع بعد' }}
              </p>
            </div>
          </template>
        </div>

        <Separator />

        <!-- Role-specific quick actions -->
        <DialogFooter class="flex-wrap gap-2 sm:flex-nowrap">
          <!-- DATA_ENTRY: edit if in editable state -->
          <Button
            v-if="isDataEntry && [RequestStatus.DRAFT, RequestStatus.BANK_RETURNED].includes(previewRequest.status as RequestStatus)"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}/edit`)"
          >
            <Edit class="me-1.5 h-3.5 w-3.5" />
            تعديل الطلب
          </Button>

          <!-- BANK_REVIEWER: review action -->
          <Button
            v-if="user?.role === UserRole.BANK_REVIEWER"
            size="sm"
            class="bg-[var(--severity-green)] text-white hover:bg-[var(--severity-green)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <CheckCircle2 class="me-1.5 h-3.5 w-3.5" />
            مراجعة واتخاذ قرار
          </Button>

          <!-- SUPPORT_COMMITTEE: claim if unclaimed -->
          <Button
            v-if="isSupportCommittee && !previewRequest.is_claimed"
            size="sm"
            class="bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Lock class="me-1.5 h-3.5 w-3.5" />
            حجز ومراجعة
          </Button>
          <Button
            v-else-if="isSupportCommittee && previewRequest.is_claimed_by_me"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            متابعة المراجعة
          </Button>

          <!-- SWIFT_OFFICER: upload if waiting -->
          <Button
            v-if="isSwiftOfficer && previewRequest.status === RequestStatus.WAITING_FOR_SWIFT"
            size="sm"
            class="bg-[var(--info)] text-white hover:bg-[var(--info)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Upload class="me-1.5 h-3.5 w-3.5" />
            رفع وثيقة SWIFT
          </Button>

          <!-- EXECUTIVE_MEMBER: vote if session open -->
          <Button
            v-if="isExecutiveMember && previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN"
            size="sm"
            class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Vote class="me-1.5 h-3.5 w-3.5" />
            التصويت الآن
          </Button>

          <!-- COMMITTEE_DIRECTOR: manage session -->
          <Button
            v-if="isDirector"
            size="sm"
            class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Vote class="me-1.5 h-3.5 w-3.5" />
            {{ previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN ? 'إغلاق الجلسة' : 'فتح جلسة التصويت' }}
          </Button>

          <!-- CBY_ADMIN: audit trail shortcut -->
          <Button
            v-if="isCbyAdmin"
            variant="outline"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <ClipboardList class="me-1.5 h-3.5 w-3.5" />
            سجل التدقيق
          </Button>

          <!-- Always: open full request -->
          <Button
            variant="outline"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Eye class="me-1.5 h-3.5 w-3.5" />
            فتح الطلب الكامل
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
