<script setup lang="ts">
import type { ColumnFiltersState, PaginationState, VisibilityState } from '@tanstack/vue-table'
import {
  AlertCircle, CheckCircle2, ClipboardList, Download, Loader2,
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
import type { RequestsFilter } from '@/composables/useRequests'
import { useRequests } from '@/composables/useRequests'
import { useRequestsColumns, buildStatusFilterOptions } from '@/composables/useRequestsColumns'
import type { Bank } from '@/types/models'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import type { ImportRequest } from '@/types/models'
import { useTableExport } from '@/composables/useTableExport'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  DataTable,
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
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()

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

// ── URL-driven pagination (same pattern as shadcn-admin tasks table) ──────────
const DEFAULT_PAGE_SIZE = 20
const route = useRoute()
const router = useRouter()

// Derive page and pageSize from URL — these are the single source of truth.
const urlPage = computed(() => Number(route.query.page ?? 1))
const urlPageSize = computed(() => Number(route.query.perPage ?? DEFAULT_PAGE_SIZE))

// TanStack pagination object derived from URL params.
const pagination = computed<PaginationState>(() => ({
  pageIndex: urlPage.value - 1,
  pageSize: urlPageSize.value,
}))

// Called by DataTable's onPaginationChange — writes new values to URL.
function onPaginationChange(updater: PaginationState | ((old: PaginationState) => PaginationState)) {
  const next = typeof updater === 'function' ? updater(pagination.value) : updater
  router.push({
    query: {
      ...route.query,
      page: next.pageIndex === 0 ? undefined : String(next.pageIndex + 1),
      perPage: next.pageSize === DEFAULT_PAGE_SIZE ? undefined : String(next.pageSize),
    },
  })
}

function buildFilter(overrides?: { page?: number; pageSize?: number }): RequestsFilter {
  const filter: RequestsFilter = {
    per_page: overrides?.pageSize ?? urlPageSize.value,
    page: overrides?.page ?? urlPage.value,
  }
  const q = query.value.trim()
  if (q) filter.search = q
  const statusCol = columnFilters.value.find(f => f.id === 'status')
  if (statusCol && Array.isArray(statusCol.value) && statusCol.value.length > 0)
    filter.status = statusCol.value as RequestStatus[]
  const merchantCol = columnFilters.value.find(f => f.id === 'merchant')
  if (merchantCol && Array.isArray(merchantCol.value) && merchantCol.value.length > 0)
    filter.bank_id = parseInt(merchantCol.value[0] as string)
  return filter
}

function refreshStats() {
  store.loadStats({ per_page: 1, page: 1, with_status_totals: true })
}

// Watch URL params → fetch from server whenever page or pageSize changes.
watch([urlPage, urlPageSize], ([newPage, newSize]) => {
  store.loadRequests(buildFilter({ page: newPage, pageSize: newSize }))
})

// Debounced server search — resets to page 1 via URL.
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(query, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    router.push({ query: { ...route.query, page: undefined } })
    store.loadRequests(buildFilter({ page: 1 }))
  }, 350)
})

// Column filter → reset to page 1, reload + persist.
watch(columnFilters, () => {
  if (process.client && user.value?.role)
    localStorage.setItem(`yfh-col-filters-${user.value.role}`, JSON.stringify(columnFilters.value))
  router.push({ query: { ...route.query, page: undefined } })
  store.loadRequests(buildFilter({ page: 1 }))
  refreshStats()
}, { deep: true })

onMounted(async () => {
  if (process.client && user.value?.role) {
    const saved = localStorage.getItem(`yfh-col-filters-${user.value.role}`)
    if (saved) {
      try { columnFilters.value = JSON.parse(saved) } catch {}
    }
  }
  await store.loadRequests(buildFilter())
  refreshStats()
  if (user.value && CBY_BANK_FILTER_ROLES.includes(user.value.role)) {
    banks.value = await fetchBanks()
  }
})


const hasActiveFilters = computed(() =>
  columnFilters.value.length > 0 || query.value.trim().length > 0,
)

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

// Role-specific KPI cards — each role sees metrics relevant to their workflow stage
const roleKpiCards = computed(() => {
  // Use statsMeta.total for accurate all-pages total; fall back to meta.total or current page count
  const total = store.statsMeta?.total ?? store.meta?.total ?? store.requests.length
  const role = user.value?.role
  const noFilter = columnFilters.value.length === 0

  // Use statusCount() which reads from statsMeta.status_totals (all pages, accurate)
  const count = (...statuses: RequestStatus[]) => statusCount(statuses)

  const isActive = (...statuses: RequestStatus[]) =>
    isStatusFilterActive(statuses)

  const on = (...statuses: RequestStatus[]) =>
    () => setStatusFilter(statuses)

  const resetAll = () => { columnFilters.value = [] }

  switch (role) {
    case UserRole.DATA_ENTRY:
      return [
        { label: 'مسودات', value: count(RequestStatus.DRAFT, RequestStatus.DRAFT_REJECTED_INTERNAL), icon: FilePlus2, description: 'لم تُقدَّم بعد', active: isActive(RequestStatus.DRAFT, RequestStatus.DRAFT_REJECTED_INTERNAL), onClick: on(RequestStatus.DRAFT, RequestStatus.DRAFT_REJECTED_INTERNAL) },
        { label: 'بانتظار المراجعة', value: count(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW), icon: ClipboardList, description: 'مُقدَّمة في الانتظار', tone: 'warning' as const, active: isActive(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW), onClick: on(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW) },
        { label: 'مكتملة', value: count(RequestStatus.COMPLETED), icon: CheckCircle2, description: 'اكتملت المعالجة', tone: 'success' as const, active: isActive(RequestStatus.COMPLETED), onClick: on(RequestStatus.COMPLETED) },
        { label: 'تحتاج تصحيح', value: count(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED), icon: AlertCircle, description: 'أُعيدت للتعديل', tone: 'danger' as const, active: isActive(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED), onClick: on(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED) },
      ]

    case UserRole.BANK_REVIEWER:
      return [
        { label: 'بانتظار المراجعة', value: count(RequestStatus.SUBMITTED), icon: ClipboardList, description: 'جديدة لم تُراجَع', tone: 'warning' as const, active: isActive(RequestStatus.SUBMITTED), onClick: on(RequestStatus.SUBMITTED) },
        { label: 'قيد المراجعة', value: count(RequestStatus.BANK_REVIEW), icon: Eye, description: 'بدأت مراجعتها', tone: 'warning' as const, active: isActive(RequestStatus.BANK_REVIEW), onClick: on(RequestStatus.BANK_REVIEW) },
        { label: 'تم اعتمادها', value: count(RequestStatus.BANK_APPROVED), icon: CheckCircle2, description: 'اعتُمدت بنكياً', tone: 'success' as const, active: isActive(RequestStatus.BANK_APPROVED), onClick: on(RequestStatus.BANK_APPROVED) },
        { label: 'مرفوضة نهائياً', value: count(RequestStatus.BANK_REJECTED), icon: AlertCircle, description: 'رُفضت بشكل نهائي', tone: 'danger' as const, active: isActive(RequestStatus.BANK_REJECTED), onClick: on(RequestStatus.BANK_REJECTED) },
      ]

    case UserRole.SWIFT_OFFICER:
      return [
        { label: 'بانتظار SWIFT', value: count(RequestStatus.WAITING_FOR_SWIFT), icon: Upload, description: 'تحتاج رفع الوثيقة', tone: 'warning' as const, active: isActive(RequestStatus.WAITING_FOR_SWIFT), onClick: on(RequestStatus.WAITING_FOR_SWIFT) },
        { label: 'تم رفع SWIFT', value: count(RequestStatus.SWIFT_UPLOADED), icon: CheckCircle2, description: 'رُفعت الوثيقة بنجاح', tone: 'success' as const, active: isActive(RequestStatus.SWIFT_UPLOADED), onClick: on(RequestStatus.SWIFT_UPLOADED) },
        { label: 'مراحل التصويت', value: count(RequestStatus.WAITING_FOR_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_CLOSED), icon: Lock, description: 'ما بعد SWIFT', active: isActive(RequestStatus.WAITING_FOR_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_CLOSED), onClick: on(RequestStatus.WAITING_FOR_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_OPEN, RequestStatus.EXECUTIVE_VOTING_CLOSED) },
        { label: 'مكتملة', value: count(RequestStatus.COMPLETED), icon: CheckCircle2, description: 'اكتملت المعالجة', tone: 'success' as const, active: isActive(RequestStatus.COMPLETED), onClick: on(RequestStatus.COMPLETED) },
      ]

    case UserRole.SUPPORT_COMMITTEE:
      return [
        { label: 'بانتظار الحجز', value: count(RequestStatus.SUPPORT_REVIEW_PENDING), icon: ClipboardList, description: 'متاحة للمطالبة', tone: 'warning' as const, active: isActive(RequestStatus.SUPPORT_REVIEW_PENDING), onClick: on(RequestStatus.SUPPORT_REVIEW_PENDING) },
        { label: 'قيد المراجعة', value: count(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS), icon: Eye, description: 'محجوزة حالياً', tone: 'warning' as const, active: isActive(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS), onClick: on(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS) },
        { label: 'تم اعتمادها', value: count(RequestStatus.SUPPORT_APPROVED), icon: CheckCircle2, description: 'اعتمدتها اللجنة', tone: 'success' as const, active: isActive(RequestStatus.SUPPORT_APPROVED), onClick: on(RequestStatus.SUPPORT_APPROVED) },
        { label: 'مرفوضة أو مُعادة', value: count(RequestStatus.SUPPORT_REJECTED, RequestStatus.SUPPORT_RETURNED), icon: AlertCircle, description: 'رُفضت أو أُعيدت', tone: 'danger' as const, active: isActive(RequestStatus.SUPPORT_REJECTED, RequestStatus.SUPPORT_RETURNED), onClick: on(RequestStatus.SUPPORT_REJECTED, RequestStatus.SUPPORT_RETURNED) },
      ]

    case UserRole.EXECUTIVE_MEMBER:
      return [
        { label: 'جلسات مفتوحة', value: count(RequestStatus.EXECUTIVE_VOTING_OPEN), icon: Vote, description: 'تصويت مفتوح حالياً', tone: 'warning' as const, active: isActive(RequestStatus.EXECUTIVE_VOTING_OPEN), onClick: on(RequestStatus.EXECUTIVE_VOTING_OPEN) },
        { label: 'جلسات مغلقة', value: count(RequestStatus.EXECUTIVE_VOTING_CLOSED), icon: Lock, description: 'انتهى التصويت', active: isActive(RequestStatus.EXECUTIVE_VOTING_CLOSED), onClick: on(RequestStatus.EXECUTIVE_VOTING_CLOSED) },
        { label: 'قرارات معتمدة', value: count(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED), icon: CheckCircle2, description: 'اعتُمد تنفيذياً', tone: 'success' as const, active: isActive(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED), onClick: on(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED) },
        { label: 'قرارات مرفوضة', value: count(RequestStatus.EXECUTIVE_REJECTED), icon: AlertCircle, description: 'رُفض تنفيذياً', tone: 'danger' as const, active: isActive(RequestStatus.EXECUTIVE_REJECTED), onClick: on(RequestStatus.EXECUTIVE_REJECTED) },
      ]

    case UserRole.COMMITTEE_DIRECTOR:
      return [
        { label: 'جلسات نشطة', value: count(RequestStatus.EXECUTIVE_VOTING_OPEN), icon: Vote, description: 'تصويت مفتوح الآن', tone: 'warning' as const, active: isActive(RequestStatus.EXECUTIVE_VOTING_OPEN), onClick: on(RequestStatus.EXECUTIVE_VOTING_OPEN) },
        { label: 'بانتظار المصارفة', value: count(RequestStatus.EXECUTIVE_APPROVED), icon: Lock, description: 'انتظار تأكيد خارجي', tone: 'warning' as const, active: isActive(RequestStatus.EXECUTIVE_APPROVED), onClick: on(RequestStatus.EXECUTIVE_APPROVED) },
        { label: 'جلسات مغلقة', value: count(RequestStatus.EXECUTIVE_VOTING_CLOSED), icon: CheckCircle2, description: 'انتهى التصويت', active: isActive(RequestStatus.EXECUTIVE_VOTING_CLOSED), onClick: on(RequestStatus.EXECUTIVE_VOTING_CLOSED) },
        { label: 'مكتملة', value: count(RequestStatus.COMPLETED), icon: CheckCircle2, description: 'اكتملت المعالجة', tone: 'success' as const, active: isActive(RequestStatus.COMPLETED), onClick: on(RequestStatus.COMPLETED) },
      ]

    case UserRole.BANK_ADMIN: {
      const approved = count(RequestStatus.BANK_APPROVED, RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED)
      const rejected = count(RequestStatus.BANK_REJECTED, RequestStatus.EXECUTIVE_REJECTED, RequestStatus.SUPPORT_REJECTED)
      const approvalRate = total > 0 ? Math.round((approved / total) * 100) : 0
      return [
        { label: 'إجمالي الطلبات', value: total, icon: ClipboardList, description: 'جميع طلبات الجهة', active: noFilter, onClick: resetAll },
        { label: 'قيد المعالجة', value: count(...pendingStatuses), icon: Lock, description: 'بانتظار الإجراء', tone: 'warning' as const, active: isStatusFilterActive(pendingStatuses), onClick: filterByPending },
        { label: 'معدل الاعتماد', value: approvalRate, icon: CheckCircle2, description: `${approved} من ${total} معتمدة`, tone: 'success' as const, active: isActive(RequestStatus.BANK_APPROVED, RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED), onClick: filterByApproved },
        { label: 'مرفوضة', value: rejected, icon: AlertCircle, description: 'رُفضت في أي مرحلة', tone: 'danger' as const, active: isActive(RequestStatus.BANK_REJECTED, RequestStatus.EXECUTIVE_REJECTED, RequestStatus.SUPPORT_REJECTED), onClick: filterByRejected },
      ]
    }

    default: { // CBY_ADMIN and fallback
      const approved = count(RequestStatus.BANK_APPROVED, RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED)
      const rejected = count(RequestStatus.BANK_REJECTED, RequestStatus.EXECUTIVE_REJECTED, RequestStatus.SUPPORT_REJECTED)
      const pending = total - approved - rejected
      return [
        { label: 'إجمالي الطلبات', value: total, icon: ClipboardList, description: 'جميع الطلبات المعروضة', active: noFilter, onClick: resetAll },
        { label: 'قيد المعالجة', value: pending, icon: Lock, description: 'بانتظار الإجراء', tone: 'warning' as const, active: isStatusFilterActive(pendingStatuses), onClick: filterByPending },
        { label: 'معتمدة', value: approved, icon: CheckCircle2, description: 'مرّت بجميع المراحل', tone: 'success' as const, active: isActive(RequestStatus.BANK_APPROVED, RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED), onClick: filterByApproved },
        { label: 'تحتاج متابعة', value: count(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED, RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.SUPPORT_REJECTED), icon: AlertCircle, description: 'استثناءات تحتاج إجراء', tone: 'danger' as const, active: isActive(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED, RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.SUPPORT_REJECTED), onClick: on(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED, RequestStatus.DRAFT_REJECTED_INTERNAL, RequestStatus.SUPPORT_REJECTED) },
      ]
    }
  }
})

// Aggregate count — uses statsMeta.status_totals (accurate, all pages) when available
function statusCount(statuses: RequestStatus[]): number {
  const totals = store.statsMeta?.status_totals
  if (totals) return statuses.reduce((sum, s) => sum + (totals[s] ?? 0), 0)
  return store.requests.filter(r => statuses.includes(r.status)).length
}

// Status filter options built from STATUS_LABELS
const statusFilterOptions = computed(() => buildStatusFilterOptions())

// Bank filter options for roles that see bank filter
const bankFilterOptions = computed(() =>
  banks.value.map(b => ({ label: b.name_ar || b.name_en || '', value: String(b.id) })),
)

function handleReset() {
  query.value = ''
  columnFilters.value = []
}

function clearBulkSelection() {
  rowSelection.value = {}
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
  const selectedIndices = Object.entries(rowSelection.value)
    .filter(([, v]) => v)
    .map(([k]) => parseInt(k))
  const rows = store.requests.filter((_, i) => selectedIndices.includes(i))
  if (!rows.length || !exportColumns.value.length) return
  exportToCSV(rows, exportColumns.value, `${buildExportFilename()}-selected`)
}

// ── Smart export ─────────────────────────────────────────────────────────────
const exportLoading = ref(false)

const exportScopeOptions = computed(() => [
  {
    value: 'page' as const,
    label: 'الصفحة الحالية',
    detail: `${store.requests.length.toLocaleString('ar-EG')} صف مُحمَّل`,
  },
  {
    value: 'filtered' as const,
    label: hasActiveFilters.value ? 'جميع النتائج المطابقة' : 'جميع البيانات (الفلتر الحالي)',
    detail: `${(store.statsMeta?.total ?? store.meta?.total ?? '...').toLocaleString?.() ?? '...'} صف`,
  },
  {
    value: 'all' as const,
    label: 'جميع البيانات (بدون فلاتر)',
    detail: `${(store.totalCount).toLocaleString('ar-EG')} صف إجمالي`,
  },
])

const exportScope = ref<'page' | 'filtered' | 'all'>('filtered')
const exportFormat = ref<'csv' | 'excel' | 'json'>('csv')
const exportDialogOpen = ref(false)

async function doExport() {
  if (!exportColumns.value.length) return
  exportLoading.value = true
  try {
    let rows: ImportRequest[] = []

    if (exportScope.value === 'page') {
      rows = store.requests
    }
    else {
      const { fetchRequests } = useRequests()
      const filter: RequestsFilter = exportScope.value === 'filtered'
        ? { ...buildFilter({ page: 1, pageSize: 10000 }) }
        : { per_page: 10000, page: 1 }
      const result = await fetchRequests(filter)
      rows = result.data
    }

    const filename = buildExportFilename()
    if (exportFormat.value === 'csv') exportToCSV(rows, exportColumns.value, filename)
    else if (exportFormat.value === 'excel') exportToExcel(rows, exportColumns.value, filename)
    else exportToJSON(rows, exportColumns.value, filename)

    exportDialogOpen.value = false
  }
  catch {
    // Error handled by user-visible loading state
  }
  finally {
    exportLoading.value = false
  }
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
  columnFilters.value = [{ id: 'status', value: [RequestStatus.BANK_APPROVED, RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED] }]
}

function filterByPending() {
  columnFilters.value = [{ id: 'status', value: pendingStatuses }]
}

function filterByRejected() {
  columnFilters.value = [{ id: 'status', value: [RequestStatus.BANK_REJECTED, RequestStatus.EXECUTIVE_REJECTED, RequestStatus.SUPPORT_REJECTED] }]
}

function filterBySmartSummary(statuses: RequestStatus[]) {
  columnFilters.value = [{ id: 'status', value: statuses }]
}

function setStatusFilter(statuses: RequestStatus[]) {
  columnFilters.value = [{ id: 'status', value: statuses }]
}

function isStatusFilterActive(statuses: RequestStatus[]): boolean {
  const f = columnFilters.value.find(cf => cf.id === 'status')
  if (!f || !Array.isArray(f.value)) return false
  return statuses.some(s => (f.value as RequestStatus[]).includes(s))
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
          :disabled="store.loadingList"
          @click="store.loadRequests(buildFilter())"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': store.loadingList }" />
          <span class="hidden lg:inline">تحديث</span>
        </Button>
        <Button v-if="canCreateRequest" as="a" href="/requests/new" size="sm">
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

    <!-- Inline error state -->
    <Alert v-if="store.error" variant="destructive" role="alert" class="mb-4">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في تحميل الطلبات</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="store.loadRequests(buildFilter())">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <!-- Role-specific KPI cards — clicking sets a status filter -->
    <div class="mb-4">
      <MetricGrid :columns="4">
        <MetricCard
          v-for="card in roleKpiCards"
          :key="card.label"
          :label="card.label"
          :value="card.value"
          :icon="card.icon"
          :description="card.description"
          :tone="card.tone"
          :active="card.active"
          @click="card.onClick()"
        />
      </MetricGrid>
    </div>


    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="store.requests"
        :columns="columns"
        :loading="store.loadingList"
        :page-count="store.meta?.last_page ?? 1"
        :pagination="pagination"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        :row-selection="rowSelection"
        @update:pagination="onPaginationChange"
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
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline" size="sm" class="flex" :disabled="!exportColumns.length">
                    <Download class="me-2 h-4 w-4" />
                    تصدير
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-64">
                  <DropdownMenuLabel>نطاق التصدير</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem @click="exportScope = 'page'; exportDialogOpen = true">
                    <div class="space-y-0.5">
                      <p class="text-sm font-medium">الصفحة الحالية</p>
                      <p class="text-xs text-muted-foreground">{{ store.requests.length.toLocaleString('ar-EG') }} صف</p>
                    </div>
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="exportScope = 'filtered'; exportDialogOpen = true">
                    <div class="space-y-0.5">
                      <p class="text-sm font-medium">{{ hasActiveFilters ? 'جميع النتائج المطابقة' : 'جميع البيانات' }}</p>
                      <p class="text-xs text-muted-foreground">{{ (store.statsMeta?.total ?? store.meta?.total ?? '...').toLocaleString?.() ?? '...' }} صف</p>
                    </div>
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="hasActiveFilters" />
                  <DropdownMenuItem v-if="hasActiveFilters" @click="exportScope = 'all'; exportDialogOpen = true">
                    <div class="space-y-0.5">
                      <p class="text-sm font-medium">جميع البيانات (بدون فلاتر)</p>
                      <p class="text-xs text-muted-foreground">{{ store.totalCount.toLocaleString('ar-EG') }} صف إجمالي</p>
                    </div>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
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
          <DataTablePagination :table="table" :total-rows="store.statsMeta?.total ?? store.meta?.total" />
        </template>
      </DataTable>
    </div>

    <!-- Export format dialog — shown after user picks scope from dropdown -->
    <Dialog v-model:open="exportDialogOpen">
      <DialogContent class="max-w-xs">
        <DialogHeader>
          <DialogTitle>اختر صيغة التصدير</DialogTitle>
          <DialogDescription>
            {{ exportScopeOptions.find(s => s.value === exportScope)?.label }}
            — {{ exportScopeOptions.find(s => s.value === exportScope)?.detail }}
          </DialogDescription>
        </DialogHeader>
        <div class="flex gap-2 pt-1">
          <Button
            v-for="fmt in [{ value: 'csv', label: 'CSV' }, { value: 'excel', label: 'Excel' }, { value: 'json', label: 'JSON' }]"
            :key="fmt.value"
            variant="outline"
            size="sm"
            class="flex-1"
            :class="exportFormat === fmt.value ? 'border-primary bg-primary/5 font-semibold' : ''"
            @click="exportFormat = fmt.value as 'csv' | 'excel' | 'json'"
          >
            {{ fmt.label }}
          </Button>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="exportDialogOpen = false">إلغاء</Button>
          <Button :disabled="exportLoading" @click="doExport">
            <Loader2 v-if="exportLoading" class="h-4 w-4 me-2 animate-spin" />
            {{ exportLoading ? 'جارٍ التصدير...' : 'تصدير' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

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
          <Tooltip v-if="isCbyAdmin || isDirector">
            <TooltipTrigger as-child>
              <Badge
                class="border text-xs"
                :style="{
                  backgroundColor: `${slaState(previewRequest).color}18`,
                  color: slaState(previewRequest).color,
                  borderColor: `${slaState(previewRequest).color}38`,
                }"
              >
                {{ slaState(previewRequest).label }}
              </Badge>
            </TooltipTrigger>
            <TooltipContent><p>وقت المعالجة: أقل من 72 ساعة ضمن SLA — 72 إلى 120 في خطر — أكثر من 120 انتهاك</p></TooltipContent>
          </Tooltip>

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
          <Tooltip v-if="isDataEntry && [RequestStatus.DRAFT, RequestStatus.BANK_RETURNED].includes(previewRequest.status as RequestStatus)">
            <TooltipTrigger as-child>
              <Button size="sm" @click="navigateTo(`/requests/${previewRequest.id}/edit`)">
                <Edit class="me-1.5 h-3.5 w-3.5" />
                تعديل الطلب
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>تعديل بيانات الطلب قبل إعادة التقديم</p></TooltipContent>
          </Tooltip>

          <!-- BANK_REVIEWER: review action -->
          <Tooltip v-if="user?.role === UserRole.BANK_REVIEWER">
            <TooltipTrigger as-child>
              <Button
                size="sm"
                class="bg-[var(--severity-green)] text-white hover:bg-[var(--severity-green)]/90"
                @click="navigateTo(`/requests/${previewRequest.id}`)"
              >
                <CheckCircle2 class="me-1.5 h-3.5 w-3.5" />
                مراجعة واتخاذ قرار
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>مراجعة محتوى الطلب والموافقة أو الرفض أو إعادته للمدخل</p></TooltipContent>
          </Tooltip>

          <!-- SUPPORT_COMMITTEE: claim if unclaimed -->
          <Tooltip v-if="isSupportCommittee && !previewRequest.is_claimed">
            <TooltipTrigger as-child>
              <Button
                size="sm"
                class="bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
                @click="navigateTo(`/requests/${previewRequest.id}`)"
              >
                <Lock class="me-1.5 h-3.5 w-3.5" />
                حجز ومراجعة
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>حجز الطلب لمراجعته — مهلة 15 دقيقة قبل انتهاء الحجز تلقائياً</p></TooltipContent>
          </Tooltip>
          <Button
            v-else-if="isSupportCommittee && previewRequest.is_claimed_by_me"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            متابعة المراجعة
          </Button>

          <!-- SWIFT_OFFICER: upload if waiting -->
          <Tooltip v-if="isSwiftOfficer && previewRequest.status === RequestStatus.WAITING_FOR_SWIFT">
            <TooltipTrigger as-child>
              <Button
                size="sm"
                class="bg-[var(--info)] text-white hover:bg-[var(--info)]/90"
                @click="navigateTo(`/requests/${previewRequest.id}`)"
              >
                <Upload class="me-1.5 h-3.5 w-3.5" />
                رفع وثيقة SWIFT
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>رفع وثيقة SWIFT بصيغة PDF — مطلوب لاستكمال مسار الطلب</p></TooltipContent>
          </Tooltip>

          <!-- EXECUTIVE_MEMBER: vote if session open -->
          <Tooltip v-if="isExecutiveMember && previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN">
            <TooltipTrigger as-child>
              <Button
                size="sm"
                class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90"
                @click="navigateTo(`/requests/${previewRequest.id}`)"
              >
                <Vote class="me-1.5 h-3.5 w-3.5" />
                التصويت الآن
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>تسجيل تصويتك على هذا الطلب — الجلسة مفتوحة حالياً</p></TooltipContent>
          </Tooltip>

          <!-- COMMITTEE_DIRECTOR: manage session -->
          <Tooltip v-if="isDirector">
            <TooltipTrigger as-child>
              <Button
                size="sm"
                class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90"
                @click="navigateTo(`/requests/${previewRequest.id}`)"
              >
                <Vote class="me-1.5 h-3.5 w-3.5" />
                {{ previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN ? 'إغلاق الجلسة' : 'فتح جلسة التصويت' }}
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>{{ previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN ? 'إغلاق جلسة التصويت وإصدار النتيجة' : 'فتح جلسة تصويت جديدة للجنة التنفيذية' }}</p></TooltipContent>
          </Tooltip>

          <!-- CBY_ADMIN: audit trail shortcut -->
          <Tooltip v-if="isCbyAdmin">
            <TooltipTrigger as-child>
              <Button
                variant="outline"
                size="sm"
                @click="navigateTo(`/requests/${previewRequest.id}`)"
              >
                <ClipboardList class="me-1.5 h-3.5 w-3.5" />
                سجل التدقيق
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>عرض سجل التدقيق والتاريخ الكامل لهذا الطلب</p></TooltipContent>
          </Tooltip>

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
