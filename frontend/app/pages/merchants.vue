<script setup lang="ts">
import type {
  ColumnDef,
  ColumnFiltersState,
  PaginationState,
  VisibilityState,
} from '@tanstack/vue-table'
import {
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { h } from 'vue'
import {
  AlertTriangle,
  Building2,
  Edit,
  ExternalLink,
  MoreHorizontal,
  Plus,
  SearchX,
  Shield,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import MerchantDialog from '@/components/merchants/MerchantDialog.vue'
import type { MerchantFormData } from '@/components/merchants/MerchantDialog.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useMerchants } from '@/composables/useMerchants'
import { useBanks } from '@/composables/useBanks'
import { useTableExport } from '@/composables/useTableExport'
import { UserRole } from '@/types/enums'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import type { Merchant } from '@/types/models'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DataTable,
  DataTableBulkExport,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/merchants'],
})

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchMerchants, createMerchant, updateMerchant, suspendMerchant } = useMerchants()
const { fetchBanks } = useBanks()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()
const { notify } = useToast()

const merchants = ref<Merchant[]>([])
const banks = ref<import('@/types/models').Bank[]>([])
const loadingMerchants = ref(false)
const query = ref('')
const columnFilters = ref<ColumnFiltersState>([])
const createOpen = ref(false)
const editing = ref<Merchant | null>(null)
const viewing = ref<Merchant | null>(null)

const DEFAULT_MERCHANT_PAGE_SIZE = 20
const urlMerchantPage = computed(() => Number(route.query.page ?? 1))
const urlMerchantPageSize = computed(() =>
  Number(route.query.perPage ?? DEFAULT_MERCHANT_PAGE_SIZE),
)

const merchantPagination = computed<PaginationState>(() => ({
  pageIndex: urlMerchantPage.value - 1,
  pageSize: urlMerchantPageSize.value,
}))

function onMerchantPaginationChange(
  updater: PaginationState | ((old: PaginationState) => PaginationState),
) {
  const next = typeof updater === 'function' ? updater(merchantPagination.value) : updater
  router.push({
    query: {
      ...route.query,
      page: next.pageIndex === 0 ? undefined : String(next.pageIndex + 1),
      perPage: next.pageSize === DEFAULT_MERCHANT_PAGE_SIZE ? undefined : String(next.pageSize),
    },
  })
}

onMounted(async () => {
  loadingMerchants.value = true
  try {
    const [merchantsResult, banksResult] = await Promise.allSettled([
      fetchMerchants(),
      fetchBanks(),
    ])
    if (merchantsResult.status === 'fulfilled') merchants.value = merchantsResult.value ?? []
    if (banksResult.status === 'fulfilled') banks.value = banksResult.value ?? []
  } finally {
    loadingMerchants.value = false
  }
})

const isCbyAdmin = computed(() => user.value?.role === UserRole.CBY_ADMIN)
const isBankAdmin = computed(() => user.value?.role === UserRole.BANK_ADMIN)
const canManage = computed(() => isBankAdmin.value || isCbyAdmin.value)

function bankName(id?: number | null) {
  return banks.value.find((b) => b.id === id)?.name_ar ?? '—'
}

const scoped = computed(() => {
  if (isBankAdmin.value && user.value?.bank_id) {
    return merchants.value.filter((m) => m.bank_id === user.value?.bank_id)
  }
  return merchants.value
})

// CBY Admin: pre-filter by search; TanStack handles faceted column filters
const preFiltered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return scoped.value
  return scoped.value.filter((m) =>
    [m.name, m.commercial_register, m.tax_number, bankName(m.bank_id)].some((v) =>
      (v ?? '').toLowerCase().includes(q),
    ),
  )
})

// Bank Admin: pre-filter by search (no TanStack table for Bank Admin)
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return scoped.value.filter((m) => {
    if (!q) return true
    return [m.name, m.commercial_register, m.tax_number, bankName(m.bank_id)].some((v) =>
      (v ?? '').toLowerCase().includes(q),
    )
  })
})

const stats = computed(() => ({
  total: scoped.value.length,
  active: scoped.value.filter((m) => m.is_active).length,
  suspended: scoped.value.filter((m) => !m.is_active).length,
  incomplete: scoped.value.filter((m) => !m.commercial_register || !m.tax_number).length,
}))

// CBY-Admin risk intelligence computeds
const crossBankNames = computed(() => {
  if (!isCbyAdmin.value) return new Set<string>()
  const nameCount: Record<string, number> = {}
  for (const m of merchants.value) {
    const key = m.name.trim().toLowerCase()
    nameCount[key] = (nameCount[key] ?? 0) + 1
  }
  return new Set(
    Object.entries(nameCount)
      .filter(([, c]) => c > 1)
      .map(([n]) => n),
  )
})

const riskSummary = computed(() => {
  if (!isCbyAdmin.value) return null
  return {
    crossBank: merchants.value.filter((m) => crossBankNames.value.has(m.name.trim().toLowerCase()))
      .length,
    missingData: merchants.value.filter((m) => !m.commercial_register || !m.tax_number).length,
    inactive: merchants.value.filter((m) => !m.is_active).length,
  }
})

function merchantToForm(m: Merchant): MerchantFormData {
  return {
    name: m.name,
    commercial_register: m.commercial_register ?? '',
    tax_number: m.tax_number ?? '',
    address: m.address ?? '',
    phone: m.phone ?? '',
    business_type: m.business_type ?? '',
    is_active: m.is_active,
    bank_id: m.bank_id,
  }
}

// Duplicate confirmation state
const duplicateWarningOpen = ref(false)
const duplicateWarningReasons = ref<string[]>([])
const pendingNewMerchant = ref<MerchantFormData | null>(null)

function detectDuplicates(data: MerchantFormData): string[] {
  const bankId = data.bank_id ?? user.value?.bank_id ?? null
  const scopedMerchants = bankId
    ? merchants.value.filter((m) => m.bank_id === bankId)
    : merchants.value
  const reasons: string[] = []
  const nameLower = data.name.trim().toLowerCase()
  if (scopedMerchants.some((m) => m.name.trim().toLowerCase() === nameLower)) {
    reasons.push(`اسم التاجر "${data.name}" مسجّل مسبقاً لدى هذا البنك`)
  }
  if (
    data.commercial_register &&
    scopedMerchants.some((m) => m.commercial_register === data.commercial_register.trim())
  ) {
    reasons.push(`رقم السجل التجاري "${data.commercial_register}" مسجّل مسبقاً`)
  }
  if (data.tax_number && scopedMerchants.some((m) => m.tax_number === data.tax_number.trim())) {
    reasons.push(`الرقم الضريبي "${data.tax_number}" مسجّل مسبقاً`)
  }
  return reasons
}

async function saveNew(data: MerchantFormData) {
  const warnings = detectDuplicates(data)
  if (warnings.length > 0) {
    duplicateWarningReasons.value = warnings
    pendingNewMerchant.value = data
    duplicateWarningOpen.value = true
    return
  }
  await doCreateMerchant(data)
}

async function doCreateMerchant(data: MerchantFormData) {
  const created = await createMerchant({ ...data, bank_id: data.bank_id ?? undefined })
  merchants.value = [created, ...merchants.value]
  createOpen.value = false
  notify(`تم تسجيل التاجر "${created.name}"`)
}

async function confirmDuplicateAndSave() {
  duplicateWarningOpen.value = false
  if (pendingNewMerchant.value) {
    await doCreateMerchant(pendingNewMerchant.value)
    pendingNewMerchant.value = null
  }
}

function cancelDuplicateSave() {
  duplicateWarningOpen.value = false
  pendingNewMerchant.value = null
  duplicateWarningReasons.value = []
}

async function saveEdit(data: MerchantFormData) {
  if (!editing.value) return
  const updated = await updateMerchant(editing.value.id, data)
  merchants.value = merchants.value.map((m) => (m.id === updated.id ? updated : m))
  editing.value = null
  notify('تم تحديث بيانات التاجر')
}

const rowSelection = ref<Record<string, boolean>>({})
const columnVisibility = ref<VisibilityState>({
  business_type: false,
  transactions: false,
})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)

function clearSelection() {
  table.resetRowSelection()
}

async function toggleStatus(merchant: Merchant) {
  const updated = await suspendMerchant(merchant.id, !merchant.is_active)
  merchants.value = merchants.value.map((m) => (m.id === updated.id ? updated : m))
}

function openEditFromView() {
  if (viewing.value) {
    editing.value = viewing.value
    viewing.value = null
  }
}

function activeStatusCell(isActive: boolean) {
  const color = isActive ? 'var(--color-success)' : 'var(--color-destructive)'
  const label = isActive ? 'نشط' : 'موقوف'
  const paths = isActive
    ? [
        h('path', { d: 'M22 11.08V12a10 10 0 1 1-5.93-9.14' }),
        h('polyline', { points: '22 4 12 14.01 9 11.01' }),
      ]
    : [
        h('circle', { cx: '12', cy: '12', r: '10' }),
        h('line', { x1: '15', y1: '9', x2: '9', y2: '15' }),
        h('line', { x1: '9', y1: '9', x2: '15', y2: '15' }),
      ]
  return h('span', { class: 'inline-flex items-center gap-1.5 whitespace-nowrap' }, [
    h(
      'svg',
      {
        class: 'shrink-0',
        style: { color },
        width: 15,
        height: 15,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        'stroke-width': '2.5',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
      },
      paths,
    ),
    h('span', { class: 'text-sm font-medium text-foreground' }, label),
  ])
}

const columns: ColumnDef<Merchant>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (v: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(!!v),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h('div', { onClick: (e: Event) => e.stopPropagation() }, [
        h(Checkbox, {
          modelValue: row.getIsSelected(),
          'onUpdate:modelValue': (v: boolean | 'indeterminate') => row.toggleSelected(!!v),
          'aria-label': 'تحديد التاجر',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'name',
    header: 'التاجر',
    cell: ({ row }) =>
      h(
        'button',
        {
          type: 'button',
          class:
            'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
          title: 'معاينة سريعة',
          onClick: (e: Event) => {
            e.stopPropagation()
            viewing.value = row.original
          },
        },
        row.original.name,
      ),
  },
  {
    accessorKey: 'commercial_register',
    header: 'السجل التجاري',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm text-muted-foreground' },
        row.original.commercial_register ?? '—',
      ),
  },
  {
    accessorKey: 'tax_number',
    header: 'الرقم الضريبي',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm tabular-nums text-muted-foreground' },
        row.original.tax_number ?? '—',
      ),
  },
  {
    accessorKey: 'business_type',
    header: 'القطاع',
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-muted-foreground' }, row.original.business_type ?? '—'),
  },
  {
    id: 'bank',
    header: 'البنك التابع له',
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.bank_id)),
    cell: ({ row }) =>
      h(Badge, { variant: 'outline', class: 'font-normal' }, () => [
        h(Building2, { class: 'ms-1 h-3 w-3' }),
        bankName(row.original.bank_id),
      ]),
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.is_active)),
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'transactions',
    header: 'المعاملات',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm font-semibold tabular-nums' },
        String(row.original.transaction_count ?? 0),
      ),
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    cell: ({ row }) => {
      const merchant = row.original
      const roleItems = isBankAdmin.value
        ? [
            h(
              DropdownMenuItem,
              {
                class: 'gap-1.5 text-primary',
                onClick: () => router.push('/requests/new'),
              },
              () => [h(ExternalLink, { class: 'h-3.5 w-3.5' }), 'إنشاء طلب تمويل'],
            ),
          ]
        : isCbyAdmin.value && crossBankNames.value.has(merchant.name.trim().toLowerCase())
          ? [
              h(
                DropdownMenuItem,
                {
                  class: 'gap-1.5 text-[var(--severity-amber)]',
                  onClick: () => {
                    table.getColumn('is_active')?.setFilterValue(undefined)
                  },
                },
                () => [h(AlertTriangle, { class: 'h-3.5 w-3.5' }), 'عرض مخاطر التكرار'],
              ),
            ]
          : []
      return h(
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
                    { onClick: () => (viewing.value = merchant) },
                    () => 'عرض التفاصيل',
                  ),
                  ...roleItems,
                  ...(isBankAdmin.value
                    ? [
                        h(
                          DropdownMenuItem,
                          { onClick: () => (editing.value = merchant) },
                          () => 'تعديل',
                        ),
                        h(DropdownMenuSeparator),
                        h(
                          DropdownMenuItem,
                          {
                            class: merchant.is_active
                              ? 'text-destructive'
                              : 'text-[var(--severity-green)]',
                            onClick: () => toggleStatus(merchant),
                          },
                          () => (merchant.is_active ? 'إيقاف النشاط' : 'تفعيل'),
                        ),
                      ]
                    : []),
                ],
              },
            ),
          ],
        },
      )
    },
  },
]

const MERCHANT_COLUMN_LABELS: Record<string, string> = {
  name: 'التاجر',
  commercial_register: 'السجل التجاري',
  tax_number: 'الرقم الضريبي',
  business_type: 'القطاع',
  bank: 'البنك',
  is_active: 'الحالة',
  transactions: 'المعاملات',
}

const statusFilterOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'موقوف', value: 'false' },
]
const bankFilterOptions = computed(() =>
  banks.value.map((b) => ({ label: b.name_ar || b.name_en, value: String(b.id) })),
)

const exportCols = [
  { key: 'name', label: 'التاجر' },
  { key: 'commercial_register', label: 'السجل التجاري' },
  { key: 'tax_number', label: 'الرقم الضريبي' },
  { key: 'business_type', label: 'القطاع' },
  {
    key: 'bank_id',
    label: 'البنك',
    format: (_value: unknown, row: Merchant) => bankName(row.bank_id),
  },
  {
    key: 'is_active',
    label: 'الحالة',
    format: (_value: unknown, row: Merchant) => (row.is_active ? 'نشط' : 'موقوف'),
  },
  {
    key: 'transaction_count',
    label: 'المعاملات',
    format: (_value: unknown, row: Merchant) => String(row.transaction_count ?? 0),
  },
] as const

const table = useVueTable({
  get data() {
    return preFiltered.value
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onColumnFiltersChange: (updater) =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onRowSelectionChange: (updater) =>
    (rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater),
  onColumnVisibilityChange: (updater) =>
    (columnVisibility.value =
      typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  onPaginationChange: (updater) => {
    onMerchantPaginationChange(
      updater as PaginationState | ((old: PaginationState) => PaginationState),
    )
  },
  state: {
    get columnFilters() {
      return columnFilters.value
    },
    get rowSelection() {
      return rowSelection.value
    },
    get columnVisibility() {
      return columnVisibility.value
    },
    get pagination() {
      return merchantPagination.value
    },
  },
})

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `merchants-${new Date().toISOString().slice(0, 10)}`
}

function exportSelectedRows(format: 'csv' | 'excel' | 'json' = 'csv') {
  const rows = table.getFilteredSelectedRowModel().rows.map((row) => row.original)
  if (!rows.length) return
  const filename = `${buildExportFilename()}-selected`
  if (format === 'csv')
    exportToCSV(rows as unknown as Record<string, unknown>[], exportCols as any, filename)
  else if (format === 'excel')
    exportToExcel(rows as unknown as Record<string, unknown>[], exportCols as any, filename)
  else exportToJSON(rows as unknown as Record<string, unknown>[], exportCols as any, filename)
}
</script>

<template>
  <div v-if="user && canManage">
    <PageHeader
      title="التجار"
      :subtitle="
        isCbyAdmin
          ? 'عرض جميع التجار المسجّلين على المنصّة مع البنوك التابعة لها'
          : 'تسجيل ومتابعة التجار والمستوردين المرتبطين بالبنك'
      "
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التجار' }]"
    >
      <template v-if="isBankAdmin" #actions>
        <Button size="sm" class="h-8" @click="createOpen = true">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">تاجر جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards -->
    <div class="mb-6">
      <MetricGrid :columns="4">
        <MetricCard
          label="إجمالي"
          :value="stats.total"
          :icon="Building2"
          :active="columnFilters.length === 0"
          @click="table.resetColumnFilters()"
        />
        <MetricCard
          label="نشط"
          :value="stats.active"
          :icon="Building2"
          tone="success"
          :active="
            columnFilters.some(
              (f) =>
                f.id === 'is_active' &&
                Array.isArray(f.value) &&
                f.value.includes('true') &&
                f.value.length === 1,
            )
          "
          @click="table.getColumn('is_active')?.setFilterValue(['true'])"
        />
        <MetricCard
          label="موقوف"
          :value="stats.suspended"
          :icon="Building2"
          tone="danger"
          :active="
            columnFilters.some(
              (f) =>
                f.id === 'is_active' &&
                Array.isArray(f.value) &&
                f.value.includes('false') &&
                f.value.length === 1,
            )
          "
          @click="table.getColumn('is_active')?.setFilterValue(['false'])"
        />
        <MetricCard
          label="سجلات ناقصة"
          :value="stats.incomplete"
          :icon="Building2"
          tone="warning"
          :clickable="false"
        />
      </MetricGrid>
    </div>

    <!-- CBY Admin: Smart summary bar -->
    <div v-if="isCbyAdmin && riskSummary" class="mb-4 space-y-2">
      <Card
        v-if="riskSummary.crossBank > 0"
        class="border-0 border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ riskSummary.crossBank }} تاجر يظهر في أكثر من بنك — مراجعة مخاطر التكرار مطلوبة
          </span>
        </div>
      </Card>
      <Card
        v-if="riskSummary.missingData > 0"
        class="border-0 border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ riskSummary.missingData }} تاجر ببيانات ناقصة (سجل تجاري أو رقم ضريبي)
          </span>
        </div>
      </Card>
    </div>

    <!-- CBY Admin: tanstack table view -->
    <template v-if="isCbyAdmin">
      <div class="relative flex flex-col gap-4">
        <DataTable
          :data="preFiltered"
          :columns="columns"
          :loading="loadingMerchants"
          :pagination="merchantPagination"
          :column-filters="columnFilters"
          :column-visibility="columnVisibility"
          :row-selection="rowSelection"
          row-class="group/row"
          @update:pagination="onMerchantPaginationChange"
          @update:column-filters="(v) => (columnFilters = v)"
          @update:column-visibility="(v) => (columnVisibility = v)"
          @update:row-selection="(v) => (rowSelection = v)"
        >
          <template #toolbar="{ table }">
            <DataTableToolbar
              :table="table"
              search-placeholder="بحث برقم السجل، الرقم الضريبي، أو الاسم..."
              :has-filters="hasActiveFilters"
              :selected-count="selectedCount"
              @update:search="(v) => (query = v)"
              @reset="handleReset"
              @clear-selection="clearSelection"
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
                  v-if="table.getColumn('is_active')"
                  :column="table.getColumn('is_active')!"
                  title="الحالة"
                  :options="statusFilterOptions"
                />
                <DataTableFacetedFilter
                  v-if="table.getColumn('bank') && bankFilterOptions.length > 0"
                  :column="table.getColumn('bank')!"
                  title="البنك"
                  :options="bankFilterOptions"
                />
              </template>
              <template #actions>
                <DataTableViewOptions :table="table" :column-labels="MERCHANT_COLUMN_LABELS" />
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
                  <SearchX class="size-5" />
                </div>
                <EmptyTitle>
                  {{ merchants.length === 0 ? 'لا يوجد تجار مسجّلون بعد' : 'لا توجد تجار مطابقون' }}
                </EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{
                    merchants.length === 0
                      ? isCbyAdmin
                        ? 'لم يتم تسجيل أي تجار عبر البنوك حتى الآن.'
                        : 'ابدأ بتسجيل أول تاجر أو مستورد باستخدام زر "تاجر جديد" أعلاه.'
                      : 'جرّب إزالة فلتر الحالة أو البنك، أو تغيير نص البحث.'
                  }}
                </EmptyDescription>
              </EmptyContent>
            </Empty>
          </template>
          <template #pagination="{ table }">
            <DataTablePagination :table="table" />
          </template>
        </DataTable>
      </div>
    </template>

    <!-- Bank Admin: card grid view -->
    <template v-else>
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <!-- Skeleton loading cards -->
        <template v-if="loadingMerchants">
          <Card v-for="i in 6" :key="`skel-card-${i}`" class="flex flex-col border-0 p-5 shadow">
            <div class="mb-3 flex items-start justify-between">
              <Skeleton class="h-12 w-12 rounded-xl" />
              <Skeleton class="h-5 w-16 rounded-full" />
            </div>
            <Skeleton class="mb-1 h-4 w-3/4" />
            <Skeleton class="h-3 w-1/2" />
            <div class="mt-4 space-y-2">
              <Skeleton class="h-3 w-full" />
              <Skeleton class="h-3 w-full" />
              <Skeleton class="h-3 w-3/4" />
            </div>
            <div class="mt-auto border-t pt-4">
              <Skeleton class="h-8 w-full rounded-md" />
            </div>
          </Card>
        </template>

        <!-- Empty state -->
        <template v-else-if="filtered.length === 0">
          <div class="col-span-full">
            <Empty class="bg-muted/20 min-h-[240px] rounded-xl border border-dashed">
              <EmptyHeader>
                <div
                  class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
                >
                  <SearchX class="size-5" />
                </div>
                <EmptyTitle>
                  {{ merchants.length === 0 ? 'لا يوجد تجار مسجّلون بعد' : 'لا توجد تجار مطابقون' }}
                </EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{
                    merchants.length === 0
                      ? 'ابدأ بتسجيل أول تاجر أو مستورد باستخدام زر "تاجر جديد" أعلاه.'
                      : 'جرّب تغيير البحث أو فلتر الحالة لعرض المزيد من التجار.'
                  }}
                </EmptyDescription>
              </EmptyContent>
            </Empty>
          </div>
        </template>

        <!-- Merchant cards -->
        <template v-else>
          <Card
            v-for="merchant in filtered"
            :key="merchant.id"
            class="hover:shadow-soft flex flex-col border-0 p-5 shadow transition-shadow"
          >
            <div class="mb-3 flex items-start justify-between">
              <div
                class="bg-primary text-primary-foreground grid h-12 w-12 place-items-center rounded-xl"
              >
                <Building2 class="h-6 w-6" />
              </div>
              <Badge
                :class="
                  merchant.is_active
                    ? 'border-0 bg-[var(--color-surface-success)] text-[var(--color-text-success)]'
                    : 'border-0 bg-[var(--color-surface-error)] text-[var(--color-text-error)]'
                "
              >
                {{ merchant.is_active ? 'نشط' : 'موقوف' }}
              </Badge>
            </div>
            <div class="font-heading text-foreground text-base leading-6 font-semibold">
              {{ merchant.name }}
            </div>
            <div class="text-muted-foreground text-xs leading-5">
              {{ merchant.business_type ?? '—' }}
            </div>
            <div class="mt-4 space-y-1.5 text-xs">
              <div class="flex justify-between gap-2">
                <span class="font-section text-muted-foreground leading-5 font-medium"
                  >السجل التجاري</span
                >
                <span class="text-foreground leading-5 font-medium">{{
                  merchant.commercial_register ?? '—'
                }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="font-section text-muted-foreground leading-5 font-medium"
                  >الرقم الضريبي</span
                >
                <span class="text-foreground leading-5 font-medium">{{
                  merchant.tax_number ?? '—'
                }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="font-section text-muted-foreground leading-5 font-medium">البنك</span>
                <span class="text-foreground leading-5 font-medium">{{
                  bankName(merchant.bank_id)
                }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="font-section text-muted-foreground leading-5 font-medium"
                  >العنوان</span
                >
                <span class="text-foreground text-end leading-5 font-medium">{{
                  merchant.address ?? '—'
                }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="font-section text-muted-foreground leading-5 font-medium">هاتف</span>
                <span class="text-foreground leading-5 font-medium">{{
                  merchant.phone ?? '—'
                }}</span>
              </div>
            </div>
            <div class="mt-auto flex items-center justify-between border-t pt-4">
              <div class="text-xs">
                <span class="font-section text-muted-foreground leading-5 font-medium"
                  >المعاملات:
                </span>
                <span class="text-foreground leading-5 font-semibold tabular-nums">{{
                  merchant.transaction_count ?? 0
                }}</span>
              </div>
              <div class="flex gap-1">
                <Button size="sm" variant="ghost" class="h-8" @click="toggleStatus(merchant)">
                  {{ merchant.is_active ? 'إيقاف' : 'تفعيل' }}
                </Button>
                <Button size="icon" variant="ghost" class="h-8 w-8" @click="editing = merchant">
                  <Edit class="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
          </Card>
        </template>
      </div>
    </template>

    <Dialog v-model:open="createOpen">
      <MerchantDialog
        title="تسجيل تاجر جديد"
        :banks="banks"
        :default-bank-id="user?.bank_id"
        :lock-bank="Boolean(user?.bank_id && !isCbyAdmin)"
        @save="saveNew"
      />
    </Dialog>

    <Dialog :open="Boolean(editing)" @update:open="(v) => !v && (editing = null)">
      <MerchantDialog
        v-if="editing"
        title="تعديل بيانات التاجر"
        :banks="banks"
        :initial="merchantToForm(editing)"
        :default-bank-id="user?.bank_id"
        :lock-bank="false"
        @save="saveEdit"
      />
    </Dialog>

    <!-- Unified quick-view Dialog (both roles) -->
    <Dialog :open="Boolean(viewing)" @update:open="(v) => !v && (viewing = null)">
      <DialogContent v-if="viewing" :class="isCbyAdmin ? 'sm:max-w-2xl' : 'sm:max-w-lg'">
        <DialogHeader class="pb-3">
          <DialogTitle class="flex items-center gap-2 text-base">
            <div
              class="bg-primary/10 text-primary grid h-9 w-9 shrink-0 place-items-center rounded-lg"
            >
              <Building2 class="h-4 w-4" />
            </div>
            {{ viewing.name }}
          </DialogTitle>
          <DialogDescription>
            {{ isCbyAdmin ? 'ملف التاجر — عرض رقابي' : 'تفاصيل التاجر — عرض فقط' }}
          </DialogDescription>
        </DialogHeader>

        <!-- CBY Admin: rich regulatory profile -->
        <template v-if="isCbyAdmin">
          <div class="max-h-[55vh] space-y-4 overflow-y-auto pb-1">
            <!-- Status + risk signals -->
            <div class="flex flex-wrap gap-2">
              <Badge
                :class="
                  viewing.is_active
                    ? 'border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]'
                    : 'border border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]'
                "
              >
                {{ viewing.is_active ? 'نشط' : 'غير نشط' }}
              </Badge>
              <Badge
                v-if="crossBankNames.has(viewing.name.trim().toLowerCase())"
                class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"
              >
                <AlertTriangle class="me-1 h-3 w-3" />
                ظهور في أكثر من بنك
              </Badge>
              <Badge
                v-if="!viewing.commercial_register || !viewing.tax_number"
                class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"
              >
                <AlertTriangle class="me-1 h-3 w-3" />
                بيانات ناقصة
              </Badge>
            </div>

            <!-- Registration info -->
            <Card class="border p-4">
              <h3
                class="font-section text-muted-foreground mb-3 text-xs font-semibold tracking-wide uppercase"
              >
                معلومات التسجيل
              </h3>
              <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="space-y-0.5">
                  <div class="text-muted-foreground text-xs">السجل التجاري</div>
                  <div class="font-medium">{{ viewing.commercial_register ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-muted-foreground text-xs">الرقم الضريبي</div>
                  <div class="font-medium">{{ viewing.tax_number ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-muted-foreground text-xs">القطاع</div>
                  <div class="font-medium">{{ viewing.business_type ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-muted-foreground text-xs">عدد المعاملات</div>
                  <div class="text-foreground leading-5 font-semibold tabular-nums">
                    {{ viewing.transaction_count ?? 0 }}
                  </div>
                </div>
                <div class="col-span-2 space-y-0.5">
                  <div class="text-muted-foreground text-xs">العنوان</div>
                  <div class="font-medium">{{ viewing.address ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-muted-foreground text-xs">هاتف</div>
                  <div class="font-medium">{{ viewing.phone ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-muted-foreground text-xs">البريد</div>
                  <div class="font-medium">{{ viewing.email ?? '—' }}</div>
                </div>
              </div>
            </Card>

            <!-- Associated banks -->
            <Card class="border p-4">
              <h3
                class="font-section text-muted-foreground mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase"
              >
                <Shield class="h-3.5 w-3.5" />
                البنوك المرتبطة
              </h3>
              <div class="space-y-1.5 text-sm">
                <div class="flex items-center justify-between">
                  <span class="font-medium">{{ bankName(viewing.bank_id) }}</span>
                  <Badge variant="secondary" class="text-xs">مسجّل</Badge>
                </div>
                <template v-if="crossBankNames.has(viewing.name.trim().toLowerCase())">
                  <div
                    v-for="other in merchants.filter(
                      (m) =>
                        m.id !== viewing?.id &&
                        m.name.trim().toLowerCase() === viewing?.name.trim().toLowerCase(),
                    )"
                    :key="other.id"
                    class="flex items-center justify-between text-[var(--severity-amber)]"
                  >
                    <span class="font-medium">{{ bankName(other.bank_id) }}</span>
                    <Badge
                      class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-xs text-[var(--severity-amber)]"
                      >مكرر</Badge
                    >
                  </div>
                </template>
              </div>
            </Card>
          </div>

          <!-- CBY Admin: view-only footer -->
          <div class="border-t pt-3">
            <p class="text-muted-foreground text-xs">
              عرض رقابي — لا تتاح إجراءات التعديل لمسؤول البنك المركزي
            </p>
          </div>
        </template>

        <!-- Bank Admin: simple profile + quick actions -->
        <template v-else>
          <div class="grid gap-3 py-1 text-sm sm:grid-cols-2">
            <div class="space-y-0.5">
              <div class="text-muted-foreground text-xs">السجل التجاري</div>
              <div class="font-medium">{{ viewing.commercial_register ?? '—' }}</div>
            </div>
            <div class="space-y-0.5">
              <div class="text-muted-foreground text-xs">الرقم الضريبي</div>
              <div class="font-medium">{{ viewing.tax_number ?? '—' }}</div>
            </div>
            <div class="space-y-0.5">
              <div class="text-muted-foreground text-xs">القطاع</div>
              <div class="font-medium">{{ viewing.business_type ?? '—' }}</div>
            </div>
            <div class="space-y-0.5">
              <div class="text-muted-foreground text-xs">الحالة</div>
              <Badge
                :class="
                  viewing.is_active
                    ? 'border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]'
                    : 'border border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]'
                "
              >
                {{ viewing.is_active ? 'نشط' : 'موقوف' }}
              </Badge>
            </div>
            <div class="space-y-0.5">
              <div class="text-muted-foreground text-xs">البنك التابع له</div>
              <div class="font-medium">{{ bankName(viewing.bank_id) }}</div>
            </div>
            <div class="space-y-0.5">
              <div class="text-muted-foreground text-xs">عدد المعاملات</div>
              <div class="font-semibold tabular-nums">{{ viewing.transaction_count ?? 0 }}</div>
            </div>
            <div class="space-y-0.5 sm:col-span-2">
              <div class="text-muted-foreground text-xs">العنوان</div>
              <div class="font-medium">{{ viewing.address ?? '—' }}</div>
            </div>
            <div class="space-y-0.5 sm:col-span-2">
              <div class="text-muted-foreground text-xs">هاتف التواصل</div>
              <div class="font-medium">{{ viewing.phone ?? '—' }}</div>
            </div>
          </div>

          <!-- Bank Admin quick actions -->
          <DialogFooter class="gap-2 border-t pt-4">
            <Button variant="outline" size="sm" @click="openEditFromView">
              <Edit class="me-1.5 h-3.5 w-3.5" />
              تعديل
            </Button>
            <Button
              size="sm"
              @click="
                () => {
                  router.push('/requests/new')
                  viewing = null
                }
              "
            >
              <Plus class="me-1.5 h-3.5 w-3.5" />
              إنشاء طلب تمويل
            </Button>
          </DialogFooter>
        </template>
      </DialogContent>
    </Dialog>

    <!-- Duplicate merchant confirmation dialog -->
    <AlertDialog v-model:open="duplicateWarningOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <div class="mb-2 flex items-center gap-2 text-[var(--severity-amber)]">
            <AlertTriangle class="h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">تحذير: احتمال تكرار بيانات</span>
          </div>
          <AlertDialogTitle>تاجر مشابه موجود مسبقاً</AlertDialogTitle>
          <AlertDialogDescription class="space-y-2">
            <p>تم اكتشاف تشابه مع سجلات تجار موجودة:</p>
            <ul class="text-foreground list-disc space-y-1 ps-4 text-xs">
              <li v-for="reason in duplicateWarningReasons" :key="reason">{{ reason }}</li>
            </ul>
            <p class="text-muted-foreground text-xs">
              يمكنك إلغاء العملية ومراجعة البيانات، أو تأكيد الإضافة إذا كان التاجر مختلفاً فعلاً.
            </p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="cancelDuplicateSave">
            إلغاء — مراجعة البيانات
          </AlertDialogCancel>
          <AlertDialogAction data-testid="duplicate-confirm-btn" @click="confirmDuplicateAndSave">
            تأكيد الإضافة رغم التشابه
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>

  <div v-else>
    <PageHeader title="التجار" subtitle="هذه الصفحة متاحة لمسؤول النظام أو مسؤول البنك فقط." />
    <Card class="border-0 p-6 shadow">
      <div class="text-muted-foreground text-sm">لا تملك صلاحية الوصول إلى التجار.</div>
    </Card>
  </div>
</template>
