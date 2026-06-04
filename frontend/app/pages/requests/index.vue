<script setup lang="ts">
import type { ColumnFiltersState, PaginationState, VisibilityState } from '@tanstack/vue-table'
import { toast } from 'vue-sonner'
import {
  AlertCircle,
  CheckCircle2,
  ClipboardList,
  Download,
  Eye,
  FilePlus2,
  Lock,
  RefreshCw,
  SearchX,
  Vote,
  Upload,
} from 'lucide-vue-next'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import PageHeader from '@/components/layout/PageHeader.vue'
import { buildRequestsExportColumns } from '@/composables/useRequestsExport'
import { buildRequestsEmptyState } from '@/composables/useRequestsEmptyState'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { RequestStatus, UserRole } from '@/types/enums'
import {
  BANK_ROLES,
  CBY_BANK_FILTER_ROLES,
  ROLE_ATTENTION_STATUSES,
  ROUTE_ROLE_MAP,
} from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'
import { useBanks } from '@/composables/useBanks'
import type { RequestsFilter } from '@/composables/useRequests'
import { useRequests } from '@/composables/useRequests'
import {
  useRequestsColumns,
  buildStatusFilterOptions,
  REQUESTS_COLUMN_LABELS,
} from '@/composables/useRequestsColumns'
import type { Bank, ImportRequest } from '@/types/models'
import { Button } from '@/components/ui/button'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
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
  DataTableBulkExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'

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

const isBankScoped = computed(() => (user.value ? BANK_ROLES.includes(user.value.role) : false))
const showBankFilter = computed(() =>
  user.value ? CBY_BANK_FILTER_ROLES.includes(user.value.role) : false,
)
const canCreateRequest = computed(() => user.value?.role === UserRole.DATA_ENTRY)
const canLoadRequests = computed(
  () => authStore.isAuthenticated && !authStore.isLoggingOut && Boolean(user.value),
)

const currentUserId = computed(() => authStore.user?.id ?? null)

const { columns } = useRequestsColumns({
  role: computed(() => user.value?.role ?? UserRole.DATA_ENTRY),
  currentUserId,
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
function onPaginationChange(
  updater: PaginationState | ((old: PaginationState) => PaginationState),
) {
  const next = typeof updater === 'function' ? updater(pagination.value) : updater
  router.push({
    query: {
      ...route.query,
      page: next.pageIndex === 0 ? undefined : String(next.pageIndex + 1),
      perPage: next.pageSize === DEFAULT_PAGE_SIZE ? undefined : String(next.pageSize),
    },
  })
}

/**
 * Returns the attention-needed statuses for the current role, or undefined if
 * the role sees all requests by default (DATA_ENTRY, BANK_ADMIN, CBY_ADMIN).
 * Used as the initial status filter when no explicit filter is set.
 */
const roleAttentionStatuses = computed((): RequestStatus[] | undefined => {
  const role = user.value?.role
  if (!role) return undefined
  return ROLE_ATTENTION_STATUSES[role]
})

function buildFilter(overrides?: { page?: number; pageSize?: number }): RequestsFilter {
  const filter: RequestsFilter = {
    per_page: overrides?.pageSize ?? urlPageSize.value,
    page: overrides?.page ?? urlPage.value,
  }
  const q = query.value.trim()
  if (q) filter.search = q
  const statusCol = columnFilters.value.find((f) => f.id === 'status')
  if (statusCol && Array.isArray(statusCol.value) && statusCol.value.length > 0) {
    filter.status = statusCol.value as RequestStatus[]
  } else if (roleAttentionStatuses.value) {
    // No explicit status filter — fall back to attention-needed statuses for this role.
    filter.status = roleAttentionStatuses.value
  }
  const merchantCol = columnFilters.value.find((f) => f.id === 'merchant')
  if (merchantCol && Array.isArray(merchantCol.value) && merchantCol.value.length > 0)
    filter.bank_id = parseInt(merchantCol.value[0] as string)
  return filter
}

function refreshStats(): void {
  if (!canLoadRequests.value) return
  store.loadStats({ per_page: 1, page: 1, with_status_totals: true })
}

// Watch URL params → fetch from server whenever page or pageSize changes.
watch([urlPage, urlPageSize], ([newPage, newSize]) => {
  if (!canLoadRequests.value) return
  store.loadRequests(buildFilter({ page: newPage, pageSize: newSize }))
})

// Debounced server search — resets to page 1 via URL.
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(query, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    if (!canLoadRequests.value) return
    router.push({ query: { ...route.query, page: undefined } })
    store.loadRequests(buildFilter({ page: 1 }))
  }, 350)
})

// Column filter → reset to page 1, reload + persist.
watch(
  columnFilters,
  () => {
    if (import.meta.client && user.value?.role)
      localStorage.setItem(
        `yfh-col-filters-${user.value.role}`,
        JSON.stringify(columnFilters.value),
      )
    if (!canLoadRequests.value) return
    router.push({ query: { ...route.query, page: undefined } })
    store.loadRequests(buildFilter({ page: 1 }))
    refreshStats()
  },
  { deep: true },
)

onMounted(async () => {
  if (!canLoadRequests.value) return
  if (import.meta.client && user.value?.role) {
    const saved = localStorage.getItem(`yfh-col-filters-${user.value.role}`)
    if (saved) {
      try {
        columnFilters.value = JSON.parse(saved)
      } catch {}
    }
  }
  await store.loadRequests(buildFilter())
  refreshStats()
  if (user.value && CBY_BANK_FILTER_ROLES.includes(user.value.role)) {
    banks.value = await fetchBanks()
  }
})

// Consider filters "active" only when the user has explicitly set something beyond the role default.
const hasActiveFilters = computed(() => {
  if (query.value.trim().length > 0) return true
  if (columnFilters.value.length === 0) return false
  // Check if the status filter differs from the role default
  const statusCol = columnFilters.value.find((f) => f.id === 'status')
  const defaults = roleAttentionStatuses.value
  if (!statusCol || !Array.isArray(statusCol.value)) {
    return columnFilters.value.some((f) => f.id !== 'status')
  }
  const active = statusCol.value as RequestStatus[]
  if (!defaults || active.length !== defaults.length) return true
  return (
    active.some((s, i) => s !== defaults[i]) || columnFilters.value.some((f) => f.id !== 'status')
  )
})

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

// Role-specific KPI cards — each role sees metrics relevant to their workflow stage
const roleKpiCards = computed(() => {
  // Use statsMeta.total for accurate all-pages total; fall back to meta.total or current page count
  const total = store.statsMeta?.total ?? store.meta?.total ?? store.requests.length
  const role = user.value?.role
  const noFilter = columnFilters.value.length === 0

  // Use statusCount() which reads from statsMeta.status_totals (all pages, accurate)
  const count = (...statuses: RequestStatus[]) => statusCount(statuses)

  const isActive = (...statuses: RequestStatus[]) => isStatusFilterActive(statuses)

  const on =
    (...statuses: RequestStatus[]) =>
    () =>
      setStatusFilter(statuses)

  const resetAll = () => {
    columnFilters.value = []
  }

  switch (role) {
    case UserRole.DATA_ENTRY:
      return [
        {
          label: 'مسودات',
          value: count(RequestStatus.DRAFT, RequestStatus.DRAFT_REJECTED_INTERNAL),
          icon: FilePlus2,
          description: 'لم تُقدَّم بعد',
          active: isActive(RequestStatus.DRAFT, RequestStatus.DRAFT_REJECTED_INTERNAL),
          onClick: on(RequestStatus.DRAFT, RequestStatus.DRAFT_REJECTED_INTERNAL),
        },
        {
          label: 'بانتظار المراجعة',
          value: count(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW),
          icon: ClipboardList,
          description: 'مقدمة وتنتظر المراجعة',
          tone: 'warning' as const,
          active: isActive(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW),
          onClick: on(RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW),
        },
        {
          label: 'مكتملة',
          value: count(RequestStatus.COMPLETED),
          icon: CheckCircle2,
          description: 'اكتملت المعالجة',
          tone: 'success' as const,
          active: isActive(RequestStatus.COMPLETED),
          onClick: on(RequestStatus.COMPLETED),
        },
        {
          label: 'تحتاج تصحيح',
          value: count(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED),
          icon: AlertCircle,
          description: 'أُعيدت للتعديل',
          tone: 'danger' as const,
          active: isActive(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED),
          onClick: on(RequestStatus.BANK_RETURNED, RequestStatus.SUPPORT_RETURNED),
        },
      ]

    case UserRole.BANK_REVIEWER:
      return [
        {
          label: 'بانتظار المراجعة',
          value: count(RequestStatus.SUBMITTED),
          icon: ClipboardList,
          description: 'جديدة لم تُراجَع',
          tone: 'warning' as const,
          active: isActive(RequestStatus.SUBMITTED),
          onClick: on(RequestStatus.SUBMITTED),
        },
        {
          label: 'قيد المراجعة',
          value: count(RequestStatus.BANK_REVIEW),
          icon: Eye,
          description: 'بدأت مراجعتها',
          tone: 'warning' as const,
          active: isActive(RequestStatus.BANK_REVIEW),
          onClick: on(RequestStatus.BANK_REVIEW),
        },
        {
          label: 'تم اعتمادها',
          value: count(RequestStatus.BANK_APPROVED),
          icon: CheckCircle2,
          description: 'اعتمدها البنك',
          tone: 'success' as const,
          active: isActive(RequestStatus.BANK_APPROVED),
          onClick: on(RequestStatus.BANK_APPROVED),
        },
        {
          label: 'مرفوضة نهائياً',
          value: count(RequestStatus.BANK_REJECTED),
          icon: AlertCircle,
          description: 'رفضها البنك نهائياً',
          tone: 'danger' as const,
          active: isActive(RequestStatus.BANK_REJECTED),
          onClick: on(RequestStatus.BANK_REJECTED),
        },
      ]

    case UserRole.SWIFT_OFFICER:
      return [
        {
          label: 'بانتظار SWIFT',
          value: count(RequestStatus.WAITING_FOR_SWIFT),
          icon: Upload,
          description: 'تحتاج رفع الوثيقة',
          tone: 'warning' as const,
          active: isActive(RequestStatus.WAITING_FOR_SWIFT),
          onClick: on(RequestStatus.WAITING_FOR_SWIFT),
        },
        {
          label: 'تم رفع SWIFT',
          value: count(RequestStatus.SWIFT_UPLOADED),
          icon: CheckCircle2,
          description: 'تم رفع الوثيقة بنجاح',
          tone: 'success' as const,
          active: isActive(RequestStatus.SWIFT_UPLOADED),
          onClick: on(RequestStatus.SWIFT_UPLOADED),
        },
        {
          label: 'مراحل التصويت',
          value: count(
            RequestStatus.WAITING_FOR_VOTING_OPEN,
            RequestStatus.EXECUTIVE_VOTING_OPEN,
            RequestStatus.EXECUTIVE_VOTING_CLOSED,
          ),
          icon: Lock,
          description: 'ما بعد SWIFT',
          active: isActive(
            RequestStatus.WAITING_FOR_VOTING_OPEN,
            RequestStatus.EXECUTIVE_VOTING_OPEN,
            RequestStatus.EXECUTIVE_VOTING_CLOSED,
          ),
          onClick: on(
            RequestStatus.WAITING_FOR_VOTING_OPEN,
            RequestStatus.EXECUTIVE_VOTING_OPEN,
            RequestStatus.EXECUTIVE_VOTING_CLOSED,
          ),
        },
        {
          label: 'مكتملة',
          value: count(RequestStatus.COMPLETED),
          icon: CheckCircle2,
          description: 'اكتملت المعالجة',
          tone: 'success' as const,
          active: isActive(RequestStatus.COMPLETED),
          onClick: on(RequestStatus.COMPLETED),
        },
      ]

    case UserRole.SUPPORT_COMMITTEE:
      return [
        {
          label: 'انتظار لجنة الدعم',
          value: count(RequestStatus.SUPPORT_REVIEW_PENDING),
          icon: ClipboardList,
          description: 'متاحة للمراجعة',
          tone: 'warning' as const,
          active: isActive(RequestStatus.SUPPORT_REVIEW_PENDING),
          onClick: on(RequestStatus.SUPPORT_REVIEW_PENDING),
        },
        {
          label: 'قيد المراجعة',
          value: count(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS),
          icon: Eye,
          description: 'محجوزة حالياً',
          tone: 'warning' as const,
          active: isActive(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS),
          onClick: on(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS),
        },
        {
          label: 'تم اعتمادها',
          value: count(RequestStatus.SUPPORT_APPROVED),
          icon: CheckCircle2,
          description: 'اعتمدتها اللجنة',
          tone: 'success' as const,
          active: isActive(RequestStatus.SUPPORT_APPROVED),
          onClick: on(RequestStatus.SUPPORT_APPROVED),
        },
        {
          label: 'مرفوضة أو معادة',
          value: count(RequestStatus.SUPPORT_REJECTED, RequestStatus.SUPPORT_RETURNED),
          icon: AlertCircle,
          description: 'رفضتها اللجنة أو أعادتها',
          tone: 'danger' as const,
          active: isActive(RequestStatus.SUPPORT_REJECTED, RequestStatus.SUPPORT_RETURNED),
          onClick: on(RequestStatus.SUPPORT_REJECTED, RequestStatus.SUPPORT_RETURNED),
        },
      ]

    case UserRole.EXECUTIVE_MEMBER:
      return [
        {
          label: 'جلسات مفتوحة',
          value: count(RequestStatus.EXECUTIVE_VOTING_OPEN),
          icon: Vote,
          description: 'تصويت مفتوح حالياً',
          tone: 'warning' as const,
          active: isActive(RequestStatus.EXECUTIVE_VOTING_OPEN),
          onClick: on(RequestStatus.EXECUTIVE_VOTING_OPEN),
        },
        {
          label: 'جلسات مغلقة',
          value: count(RequestStatus.EXECUTIVE_VOTING_CLOSED),
          icon: Lock,
          description: 'انتهى التصويت',
          active: isActive(RequestStatus.EXECUTIVE_VOTING_CLOSED),
          onClick: on(RequestStatus.EXECUTIVE_VOTING_CLOSED),
        },
        {
          label: 'قرارات معتمدة',
          value: count(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED),
          icon: CheckCircle2,
          description: 'اعتمدتها اللجنة التنفيذية',
          tone: 'success' as const,
          active: isActive(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED),
          onClick: on(RequestStatus.EXECUTIVE_APPROVED, RequestStatus.COMPLETED),
        },
        {
          label: 'قرارات مرفوضة',
          value: count(RequestStatus.EXECUTIVE_REJECTED),
          icon: AlertCircle,
          description: 'رفضتها اللجنة التنفيذية',
          tone: 'danger' as const,
          active: isActive(RequestStatus.EXECUTIVE_REJECTED),
          onClick: on(RequestStatus.EXECUTIVE_REJECTED),
        },
      ]

    case UserRole.COMMITTEE_DIRECTOR:
      return [
        {
          label: 'جلسات نشطة',
          value: count(RequestStatus.EXECUTIVE_VOTING_OPEN),
          icon: Vote,
          description: 'تصويت مفتوح الآن',
          tone: 'warning' as const,
          active: isActive(RequestStatus.EXECUTIVE_VOTING_OPEN),
          onClick: on(RequestStatus.EXECUTIVE_VOTING_OPEN),
        },
        {
          label: 'بانتظار المصارفة',
          value: count(RequestStatus.EXECUTIVE_APPROVED),
          icon: Lock,
          description: 'انتظار تأكيد خارجي',
          tone: 'warning' as const,
          active: isActive(RequestStatus.EXECUTIVE_APPROVED),
          onClick: on(RequestStatus.EXECUTIVE_APPROVED),
        },
        {
          label: 'جلسات مغلقة',
          value: count(RequestStatus.EXECUTIVE_VOTING_CLOSED),
          icon: CheckCircle2,
          description: 'انتهى التصويت',
          active: isActive(RequestStatus.EXECUTIVE_VOTING_CLOSED),
          onClick: on(RequestStatus.EXECUTIVE_VOTING_CLOSED),
        },
        {
          label: 'مكتملة',
          value: count(RequestStatus.COMPLETED),
          icon: CheckCircle2,
          description: 'اكتملت المعالجة',
          tone: 'success' as const,
          active: isActive(RequestStatus.COMPLETED),
          onClick: on(RequestStatus.COMPLETED),
        },
      ]

    case UserRole.BANK_ADMIN: {
      const approved = count(
        RequestStatus.BANK_APPROVED,
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.COMPLETED,
      )
      const rejected = count(
        RequestStatus.BANK_REJECTED,
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.SUPPORT_REJECTED,
      )
      const approvalRate = total > 0 ? Math.round((approved / total) * 100) : 0
      return [
        {
          label: 'إجمالي الطلبات',
          value: total,
          icon: ClipboardList,
          description: 'جميع طلبات الجهة',
          active: noFilter,
          onClick: resetAll,
        },
        {
          label: 'قيد المعالجة',
          value: count(...pendingStatuses),
          icon: Lock,
          description: 'بانتظار الإجراء',
          tone: 'warning' as const,
          active: isStatusFilterActive(pendingStatuses),
          onClick: filterByPending,
        },
        {
          label: 'معدل الاعتماد',
          value: approvalRate,
          icon: CheckCircle2,
          description: `${approved} من ${total} معتمدة`,
          tone: 'success' as const,
          active: isActive(
            RequestStatus.BANK_APPROVED,
            RequestStatus.EXECUTIVE_APPROVED,
            RequestStatus.COMPLETED,
          ),
          onClick: filterByApproved,
        },
        {
          label: 'مرفوضة',
          value: rejected,
          icon: AlertCircle,
          description: 'تم رفضها في إحدى المراحل',
          tone: 'danger' as const,
          active: isActive(
            RequestStatus.BANK_REJECTED,
            RequestStatus.EXECUTIVE_REJECTED,
            RequestStatus.SUPPORT_REJECTED,
          ),
          onClick: filterByRejected,
        },
      ]
    }

    default: {
      // CBY_ADMIN and fallback
      const approved = count(
        RequestStatus.BANK_APPROVED,
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.COMPLETED,
      )
      const rejected = count(
        RequestStatus.BANK_REJECTED,
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.SUPPORT_REJECTED,
      )
      const pending = total - approved - rejected
      return [
        {
          label: 'إجمالي الطلبات',
          value: total,
          icon: ClipboardList,
          description: 'جميع الطلبات المعروضة',
          active: noFilter,
          onClick: resetAll,
        },
        {
          label: 'قيد المعالجة',
          value: pending,
          icon: Lock,
          description: 'بانتظار الإجراء',
          tone: 'warning' as const,
          active: isStatusFilterActive(pendingStatuses),
          onClick: filterByPending,
        },
        {
          label: 'معتمدة',
          value: approved,
          icon: CheckCircle2,
          description: 'مرّت بجميع المراحل',
          tone: 'success' as const,
          active: isActive(
            RequestStatus.BANK_APPROVED,
            RequestStatus.EXECUTIVE_APPROVED,
            RequestStatus.COMPLETED,
          ),
          onClick: filterByApproved,
        },
        {
          label: 'تحتاج متابعة',
          value: count(
            RequestStatus.BANK_RETURNED,
            RequestStatus.SUPPORT_RETURNED,
            RequestStatus.DRAFT_REJECTED_INTERNAL,
            RequestStatus.SUPPORT_REJECTED,
          ),
          icon: AlertCircle,
          description: 'استثناءات تحتاج إجراء',
          tone: 'danger' as const,
          active: isActive(
            RequestStatus.BANK_RETURNED,
            RequestStatus.SUPPORT_RETURNED,
            RequestStatus.DRAFT_REJECTED_INTERNAL,
            RequestStatus.SUPPORT_REJECTED,
          ),
          onClick: on(
            RequestStatus.BANK_RETURNED,
            RequestStatus.SUPPORT_RETURNED,
            RequestStatus.DRAFT_REJECTED_INTERNAL,
            RequestStatus.SUPPORT_REJECTED,
          ),
        },
      ]
    }
  }
})

// Aggregate count — uses statsMeta.status_totals (accurate, all pages) when available
function statusCount(statuses: RequestStatus[]): number {
  const totals = store.statsMeta?.status_totals
  if (totals) return statuses.reduce((sum, s) => sum + (totals[s] ?? 0), 0)
  return store.requests.filter((r) => statuses.includes(r.status)).length
}

// Status filter options built from STATUS_LABELS
const statusFilterOptions = computed(() => buildStatusFilterOptions())

// Bank filter options for roles that see bank filter
const bankFilterOptions = computed(() =>
  banks.value.map((b) => ({ label: b.name_ar || b.name_en || '', value: String(b.id) })),
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

function exportSelectedRows(format: 'csv' | 'excel' | 'json' = 'csv') {
  const selectedIndices = Object.entries(rowSelection.value)
    .filter(([, v]) => v)
    .map(([k]) => parseInt(k))
  const rows = store.requests.filter((_, i) => selectedIndices.includes(i))
  if (!rows.length || !exportColumns.value.length) return
  const filename = `${buildExportFilename()}-selected`
  if (format === 'csv') exportToCSV(rows, exportColumns.value, filename)
  else if (format === 'excel')
    exportToExcel(
      rows as unknown as Record<string, unknown>[],
      exportColumns.value as any,
      filename,
    )
  else
    exportToJSON(rows as unknown as Record<string, unknown>[], exportColumns.value as any, filename)
}

// ── Smart export ─────────────────────────────────────────────────────────────
const exportLoading = ref(false)

async function doExport(scope: 'page' | 'filtered' | 'all', format: 'csv' | 'excel' | 'json') {
  if (!exportColumns.value.length) return
  exportLoading.value = true
  try {
    let rows: ImportRequest[] = []
    if (scope === 'page') {
      rows = store.requests
    } else {
      const { fetchRequests } = useRequests()
      const filter: RequestsFilter =
        scope === 'filtered'
          ? { ...buildFilter({ page: 1, pageSize: 10000 }) }
          : { per_page: 10000, page: 1 }
      const result = await fetchRequests(filter)
      rows = result.data
    }
    const filename = buildExportFilename()
    const exportRows = rows as unknown as Record<string, unknown>[]
    const columns = exportColumns.value as unknown as Parameters<
      typeof exportToCSV<Record<string, unknown>>
    >[1]
    if (format === 'csv') exportToCSV(exportRows, columns, filename)
    else if (format === 'excel') exportToExcel(exportRows, columns, filename)
    else exportToJSON(exportRows, columns, filename)
  } catch {
    toast.error('تعذّر التصدير. تحقق من اتصالك وأعد المحاولة.')
  } finally {
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
  columnFilters.value = [
    {
      id: 'status',
      value: [
        RequestStatus.BANK_APPROVED,
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.COMPLETED,
      ],
    },
  ]
}

function filterByPending() {
  columnFilters.value = [{ id: 'status', value: pendingStatuses }]
}

function filterByRejected() {
  columnFilters.value = [
    {
      id: 'status',
      value: [
        RequestStatus.BANK_REJECTED,
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.SUPPORT_REJECTED,
      ],
    },
  ]
}

function filterBySmartSummary(statuses: RequestStatus[]) {
  columnFilters.value = [{ id: 'status', value: statuses }]
}

function setStatusFilter(statuses: RequestStatus[]) {
  columnFilters.value = [{ id: 'status', value: statuses }]
}

function isStatusFilterActive(statuses: RequestStatus[]): boolean {
  const f = columnFilters.value.find((cf) => cf.id === 'status')
  if (!f || !Array.isArray(f.value)) return false
  return statuses.some((s) => (f.value as RequestStatus[]).includes(s))
}

const requestsEmptyState = computed(() =>
  buildRequestsEmptyState({
    role: user.value?.role,
    hasAnyRequests: store.requests.length > 0,
    hasActiveFilters: hasActiveFilters.value,
  }),
)
</script>

<template>
  <div v-if="user">
    <PageHeader
      title="طلبات تمويل الواردات"
      :subtitle="
        isBankScoped
          ? 'طلبات جهتك، للعرض والإدارة فقط'
          : roleAttentionStatuses
            ? 'الطلبات التي تتطلب إجراءً منك'
            : 'جميع الطلبات المقدمة عبر المنصة'
      "
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
          تحديث
        </Button>
        <Button v-if="canCreateRequest" as="a" href="/requests/new" size="sm">
          <FilePlus2 class="h-4 w-4" />
          إنشاء طلب
        </Button>
      </template>
    </PageHeader>

    <!-- Inline error state -->
    <Alert v-if="store.error" variant="destructive" role="alert" class="mb-4">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في تحميل الطلبات</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="store.loadRequests(buildFilter())"
          >إعادة المحاولة</Button
        >
      </AlertAction>
    </Alert>

    <!-- Role-specific KPI cards — clicking sets a status filter -->
    <div class="mb-8">
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
        @update:column-filters="(v) => (columnFilters = v)"
        @update:column-visibility="(v) => (columnVisibility = v)"
        @update:row-selection="(v) => (rowSelection = v)"
      >
        <template #toolbar="{ table }">
          <DataTableToolbar
            :table="table"
            search-placeholder="بحث برقم الطلب، التاجر، أو رقم الفاتورة..."
            :has-filters="hasActiveFilters"
            :selected-count="selectedCount"
            @update:search="(v) => (query = v)"
            @reset="handleReset"
            @clear-selection="clearBulkSelection"
          >
            <template #bulk-actions>
              <DataTableBulkExport
                @csv="exportSelectedRows('csv')"
                @excel="exportSelectedRows('excel')"
                @json="exportSelectedRows('json')"
              />
            </template>
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
                  <Button
                    variant="outline"
                    size="sm"
                    :disabled="!exportColumns.length || exportLoading"
                  >
                    <Download class="me-2 h-4 w-4" />
                    تصدير
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                  <DropdownMenuLabel
                    >الصفحة الحالية:
                    {{ store.requests.length.toLocaleString('ar-EG') }} صف</DropdownMenuLabel
                  >
                  <DropdownMenuSeparator />
                  <DropdownMenuItem @click="doExport('page', 'csv')">CSV</DropdownMenuItem>
                  <DropdownMenuItem @click="doExport('page', 'excel')">Excel</DropdownMenuItem>
                  <DropdownMenuItem @click="doExport('page', 'json')">JSON</DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuLabel
                    >{{ hasActiveFilters ? 'نتائج الفلتر' : 'جميع البيانات' }}:
                    {{
                      (store.statsMeta?.total ?? store.meta?.total ?? '...').toLocaleString?.() ??
                      '...'
                    }}
                    صف</DropdownMenuLabel
                  >
                  <DropdownMenuSeparator />
                  <DropdownMenuItem @click="doExport('filtered', 'csv')">CSV</DropdownMenuItem>
                  <DropdownMenuItem @click="doExport('filtered', 'excel')">Excel</DropdownMenuItem>
                  <DropdownMenuItem @click="doExport('filtered', 'json')">JSON</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </template>
          </DataTableToolbar>
        </template>
        <template #empty>
          <Empty class="bg-muted/20 min-h-[280px] rounded-xl border border-dashed">
            <EmptyHeader>
              <div
                class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
              >
                <SearchX class="size-5" />
              </div>
              <EmptyTitle>{{ requestsEmptyState?.title ?? 'لا توجد طلبات مطابقة' }}</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                {{
                  requestsEmptyState?.description ??
                  'جرّب تغيير البحث أو الفلاتر لعرض الطلبات المتاحة.'
                }}
              </EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table }">
          <DataTablePagination
            :table="table"
            :total-rows="store.statsMeta?.total ?? store.meta?.total"
          />
        </template>
      </DataTable>
    </div>
  </div>
</template>
