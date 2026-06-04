<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, VisibilityState } from '@tanstack/vue-table'
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
import { Archive, Building2, Plus, PowerOff, SearchX, Zap } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { Bank } from '@/types/models'
import { useBanks, type CreateBankPayload, type UpdateBankPayload } from '@/composables/useBanks'
import { useTableExport } from '@/composables/useTableExport'
import { useAuthStore } from '@/stores/auth.store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import {
  DataTable,
  DataTableBulkExport,
  DataTableColumnHeader,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableRowActions,
  DataTableToolbar,
  DataTableViewOptions,
  type RowAction,
} from '@/components/ui/data-table'
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
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/banks'],
})

type BankForm = {
  name_ar: string
  name_en: string
  license_number: string
  code: string
  is_active: boolean
  adminName: string
  adminEmail: string
}

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)
const { fetchBanks, createBank, updateBank } = useBanks()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()
const { notify, error: toastError } = useToast()

const query = ref('')
const createOpen = ref(false)
const editing = ref<Bank | null>(null)
const viewing = ref<Bank | null>(null)
const saving = ref(false)
const banks = ref<Bank[]>([])
const loadingBanks = ref(false)
const columnVisibility = ref<VisibilityState>({})
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})

const form = reactive<BankForm>({
  name_ar: '',
  name_en: '',
  license_number: '',
  code: '',
  is_active: true,
  adminName: '',
  adminEmail: '',
})

onMounted(async () => {
  loadingBanks.value = true
  try {
    banks.value = await fetchBanks()
  } finally {
    loadingBanks.value = false
  }
})

// Pre-filter by search query across multiple fields
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return banks.value
  return banks.value.filter(
    (b) =>
      b.name_ar.toLowerCase().includes(q) ||
      b.name_en.toLowerCase().includes(q) ||
      (b.license_number ?? '').toLowerCase().includes(q) ||
      b.code.toLowerCase().includes(q),
  )
})

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

const stats = computed(() => ({
  total: banks.value.length,
  active: banks.value.filter((b) => b.is_active).length,
  inactive: banks.value.filter((b) => !b.is_active).length,
}))

const statusOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'موقوف', value: 'false' },
]

const BANK_COLUMN_LABELS: Record<string, string> = {
  license_number: 'رقم الترخيص',
  code: 'الرمز',
  is_active: 'الحالة',
}

function bankInitials(nameAr: string): string {
  return nameAr
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
}

const emailValid = computed(
  () => !form.adminEmail.trim() || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.adminEmail.trim()),
)
const formValid = computed(
  () =>
    form.name_ar.trim().length > 0 &&
    form.code.trim().length > 0 &&
    emailValid.value &&
    (Boolean(editing.value) ||
      (form.adminName.trim().length > 0 &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.adminEmail.trim()))),
)

function resetForm(initial?: Bank) {
  form.name_ar = initial?.name_ar ?? ''
  form.name_en = initial?.name_en ?? ''
  form.license_number = initial?.license_number ?? ''
  form.code = initial?.code ?? ''
  form.is_active = initial?.is_active ?? true
  form.adminName = ''
  form.adminEmail = ''
}

function openCreate() {
  editing.value = null
  resetForm()
  createOpen.value = true
}

function openEdit(bank: Bank) {
  editing.value = bank
  resetForm(bank)
}

function closeForm() {
  createOpen.value = false
  editing.value = null
  resetForm()
}

async function saveBank() {
  if (!formValid.value) return
  saving.value = true
  try {
    if (editing.value) {
      const payload: UpdateBankPayload = {
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim(),
        license_number: form.license_number.trim() || undefined,
        is_active: form.is_active,
      }
      const updated = await updateBank(editing.value.id, payload)
      banks.value = banks.value.map((b) => (b.id === editing.value!.id ? updated : b))
      notify('تم حفظ التعديلات')
    } else {
      const payload: CreateBankPayload = {
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim(),
        license_number: form.license_number.trim() || undefined,
        is_active: form.is_active,
      }
      const created = await createBank(payload)
      banks.value = [...banks.value, created]
      notify(`تم إضافة "${created.name_ar}"`)
    }
    closeForm()
  } catch {
    toastError('تعذر حفظ بيانات البنك. أعد المحاولة بعد قليل.')
  } finally {
    saving.value = false
  }
}

async function toggleStatus(bank: Bank) {
  try {
    const payload: UpdateBankPayload = {
      name_ar: bank.name_ar,
      name_en: bank.name_en,
      code: bank.code,
      license_number: bank.license_number ?? undefined,
      is_active: !bank.is_active,
    }
    const updated = await updateBank(bank.id, payload)
    banks.value = banks.value.map((b) => (b.id === bank.id ? updated : b))
    notify(updated.is_active ? `تم تفعيل ${bank.name_ar}` : `تم إيقاف ${bank.name_ar}`)
  } catch {
    toastError('فشل تغيير الحالة')
  }
}

function activeStatusCell(isActive: boolean) {
  const color = isActive ? 'var(--color-success)' : 'var(--color-locked)'
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

const bankActions: RowAction<Bank>[] = [
  {
    label: 'عرض',
    onClick: (row) => (viewing.value = row.original),
  },
  {
    label: 'تعديل',
    onClick: (row) => openEdit(row.original),
  },
  {
    label: 'إيقاف',
    destructive: true,
    hidden: (row) => !row.original.is_active,
    onClick: (row) => toggleStatus(row.original),
  },
  {
    label: 'تفعيل',
    hidden: (row) => row.original.is_active,
    onClick: (row) => toggleStatus(row.original),
  },
]

// Export columns with formatting (cast needed for boolean/number fields)
const exportColumns = [
  { key: 'name_ar', columnId: 'entity', label: 'الاسم العربي' },
  { key: 'name_en', columnId: 'entity', label: 'الاسم الإنجليزي' },
  { key: 'code', columnId: 'code', label: 'الرمز' },
  { key: 'license_number', columnId: 'license_number', label: 'رقم الترخيص' },
  {
    key: 'is_active',
    columnId: 'is_active',
    label: 'الحالة',
    format: (_v: any, row: Bank) => (row.is_active ? 'نشط' : 'موقوف'),
  },
]

const columns: ColumnDef<Bank>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(!!value),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'aria-label': `تحديد ${row.original.name_ar}`,
      }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'entity',
    header: 'البنك',
    enableHiding: false,
    cell: ({ row }) => {
      const bank = row.original
      return h('div', { class: 'flex items-center gap-2' }, [
        h(
          'div',
          {
            class:
              'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary text-xs font-semibold leading-5',
          },
          bankInitials(bank.name_ar),
        ),
        h('div', {}, [
          h(
            'div',
            { class: 'font-section text-sm font-semibold leading-5 text-foreground' },
            bank.name_ar,
          ),
          h('div', { class: 'text-xs leading-5 text-muted-foreground' }, bank.name_en),
        ]),
      ])
    },
  },
  {
    accessorKey: 'license_number',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'رقم الترخيص' }),
    cell: ({ row }) =>
      h(
        'span',
        { class: 'font-mono text-xs text-muted-foreground' },
        row.original.license_number ?? '—',
      ),
  },
  {
    accessorKey: 'code',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'الرمز' }),
    cell: ({ row }) =>
      h('code', { class: 'rounded bg-muted px-2 py-0.5 text-xs font-mono' }, row.original.code),
  },
  {
    accessorKey: 'is_active',
    // Faceted filter uses string values ('true'/'false') so we compare via String()
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.is_active)),
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'الحالة' }),
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'actions',
    header: 'إجراءات',
    enableHiding: false,
    cell: ({ row }) => h(DataTableRowActions as any, { row, actions: bankActions }),
  },
]

const table = useVueTable({
  get data() {
    return filtered.value
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onColumnVisibilityChange: (updater) =>
    (columnVisibility.value =
      typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  onColumnFiltersChange: (updater) =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onRowSelectionChange: (updater) =>
    (rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater),
  state: {
    get columnVisibility() {
      return columnVisibility.value
    },
    get columnFilters() {
      return columnFilters.value
    },
    get rowSelection() {
      return rowSelection.value
    },
  },
  initialState: { pagination: { pageSize: 20 } },
})
const noBanks = computed(() => !loadingBanks.value && banks.value.length === 0)

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `banks-${new Date().toISOString().slice(0, 10)}`
}

function clearBulkSelection() {
  table.resetRowSelection()
}

function getSelectedBanks(): Bank[] {
  return table.getFilteredSelectedRowModel().rows.map((r) => r.original)
}

function bulkExportCSV() {
  const rows = getSelectedBanks()
  if (!rows.length) return
  exportToCSV(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportExcel() {
  const rows = getSelectedBanks()
  if (!rows.length) return
  exportToExcel(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportJSON() {
  const rows = getSelectedBanks()
  if (!rows.length) return
  exportToJSON(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

const bulkToggling = ref(false)
async function bulkToggleStatus(activate: boolean) {
  const rows = getSelectedBanks().filter((b) => b.is_active !== activate)
  if (!rows.length) return
  bulkToggling.value = true
  try {
    await Promise.all(rows.map((b) => toggleStatus(b)))
    clearBulkSelection()
    notify(activate ? `تم تفعيل ${rows.length} بنك` : `تم إيقاف ${rows.length} بنك`)
  } catch {
    toastError('فشل تغيير الحالة لبعض البنوك')
  } finally {
    bulkToggling.value = false
  }
}

const archiveConfirmOpen = ref(false)
const archiving = ref(false)
async function bulkArchive() {
  const rows = getSelectedBanks()
  if (!rows.length) return
  archiving.value = true
  try {
    // Archive = deactivate (no hard-delete in this system)
    await Promise.all(rows.filter((b) => b.is_active).map((b) => toggleStatus(b)))
    clearBulkSelection()
    notify(`تم أرشفة ${rows.length} بنك`)
  } catch {
    toastError('فشل أرشفة بعض البنوك')
  } finally {
    archiving.value = false
  }
}
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <h1 class="page-title sr-only">إدارة البنوك التجارية</h1>
    <PageHeader
      title="إدارة البنوك التجارية"
      subtitle="إنشاء بنوك جديدة، عرض البيانات، تعديلها وتغيير حالة التفعيل"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'إدارة البنوك' }]"
    >
      <template #actions>
        <Button size="sm" class="btn-primary h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">بنك جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards — clicking sets the is_active column filter -->
    <div class="mb-6">
      <MetricGrid :columns="3">
        <MetricCard
          label="إجمالي البنوك"
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
          label="غير نشط"
          :value="stats.inactive"
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
      </MetricGrid>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="filtered"
        :columns="columns"
        :loading="loadingBanks"
        :column-visibility="columnVisibility"
        :column-filters="columnFilters"
        :row-selection="rowSelection"
        @update:column-visibility="(v) => (columnVisibility = v)"
        @update:column-filters="(v) => (columnFilters = v)"
        @update:row-selection="(v) => (rowSelection = v)"
      >
        <template #toolbar="{ table: dataTable }">
          <DataTableToolbar
            :table="dataTable"
            search-placeholder="بحث بالاسم أو الكود أو رقم الترخيص..."
            :has-filters="hasActiveFilters"
            :selected-count="selectedCount"
            @update:search="(v) => (query = v)"
            @reset="handleReset"
            @clear-selection="clearBulkSelection"
          >
            <template #bulk-actions>
              <DataTableBulkExport
                @csv="bulkExportCSV"
                @excel="bulkExportExcel"
                @json="bulkExportJSON"
              />
              <Button
                variant="outline"
                size="sm"
                class="h-7 gap-1.5 text-xs"
                :disabled="bulkToggling"
                @click="bulkToggleStatus(true)"
              >
                <Zap class="size-3.5" />
                تفعيل
              </Button>
              <Button
                variant="outline"
                size="sm"
                class="h-7 gap-1.5 text-xs"
                :disabled="bulkToggling"
                @click="bulkToggleStatus(false)"
              >
                <PowerOff class="size-3.5" />
                إيقاف
              </Button>
              <Button
                variant="outline"
                size="sm"
                class="text-destructive hover:text-destructive h-7 gap-1.5 text-xs"
                :disabled="archiving"
                @click="archiveConfirmOpen = true"
              >
                <Archive class="size-3.5" />
                أرشفة
              </Button>
            </template>
            <template #filters>
              <DataTableFacetedFilter
                v-if="dataTable.getColumn('is_active')"
                :column="dataTable.getColumn('is_active')!"
                title="الحالة"
                :options="statusOptions"
              />
            </template>
            <template #actions>
              <DataTableViewOptions :table="dataTable" :column-labels="BANK_COLUMN_LABELS" />
              <DataTableExport
                :table="dataTable as any"
                :export-columns="exportColumns as any"
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
              <EmptyTitle>{{
                noBanks ? 'لا توجد بنوك مسجّلة بعد' : 'لا توجد بنوك مطابقة'
              }}</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                {{
                  noBanks
                    ? 'ابدأ بإضافة أول بنك تجاري باستخدام زر "بنك جديد" أعلاه.'
                    : 'جرّب تغيير البحث أو إزالة فلتر الحالة لعرض المزيد من البنوك.'
                }}
              </EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table: dataTable }">
          <DataTablePagination :table="dataTable" />
        </template>
      </DataTable>
    </div>

    <!-- Create / Edit Dialog -->
    <Dialog :open="createOpen || Boolean(editing)" @update:open="(value) => !value && closeForm()">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل بيانات البنك' : 'إضافة بنك جديد' }}</DialogTitle>
        </DialogHeader>

        <div class="space-y-3 py-2">
          <div class="space-y-1.5">
            <Label>اسم البنك (عربي) *</Label>
            <Input v-model="form.name_ar" />
          </div>
          <div class="space-y-1.5">
            <Label>اسم البنك (إنجليزي)</Label>
            <Input v-model="form.name_en" />
          </div>
          <div class="space-y-1.5">
            <Label>كود البنك *</Label>
            <Input v-model="form.code" placeholder="YBRD" />
          </div>
          <div class="space-y-1.5">
            <Label>رقم الترخيص</Label>
            <Input v-model="form.license_number" placeholder="BNK-004" />
          </div>
          <div class="space-y-1.5">
            <Label>الحالة</Label>
            <div class="flex gap-2">
              <Button
                type="button"
                :variant="form.is_active ? 'default' : 'outline'"
                size="sm"
                @click="form.is_active = true"
                >نشط</Button
              >
              <Button
                type="button"
                :variant="!form.is_active ? 'default' : 'outline'"
                size="sm"
                @click="form.is_active = false"
                >موقوف</Button
              >
            </div>
          </div>

          <div v-if="!editing" class="mt-2 border-t pt-3">
            <div class="mb-1 text-sm font-semibold">
              حساب مدير البنك <span class="text-destructive">*</span>
            </div>
            <p class="text-muted-foreground mb-3 text-xs">
              يُنشأ حساب المدير الأول للبنك تلقائياً ويُستخدم لتسجيل الدخول وإضافة باقي المستخدمين.
            </p>
            <div class="space-y-3">
              <div class="space-y-1.5">
                <Label>اسم المدير *</Label>
                <Input v-model="form.adminName" placeholder="مثال: محمد علي" />
              </div>
              <div class="space-y-1.5">
                <Label>البريد الإلكتروني للمدير *</Label>
                <Input v-model="form.adminEmail" type="email" placeholder="admin@bank.ye" />
                <p v-if="!emailValid" class="text-destructive text-xs">صيغة البريد غير صحيحة</p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button :disabled="!formValid || saving" @click="saveBank">
            {{ editing ? 'حفظ التعديلات' : 'إضافة' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- View Dialog -->
    <Dialog :open="Boolean(viewing)" @update:open="(value) => !value && (viewing = null)">
      <DialogContent v-if="viewing" class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Building2 class="text-primary h-5 w-5" />
            {{ viewing.name_ar }}
          </DialogTitle>
          <DialogDescription>تفاصيل البنك</DialogDescription>
        </DialogHeader>
        <div class="space-y-3 py-2 text-sm">
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-muted-foreground">الاسم الإنجليزي</span>
            <span class="font-medium">{{ viewing.name_en }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-muted-foreground">الكود</span>
            <span class="font-mono font-medium">{{ viewing.code }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-muted-foreground">رقم الترخيص</span>
            <span class="font-mono font-medium">{{ viewing.license_number ?? '—' }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-muted-foreground">الحالة</span>
            <span class="font-medium">{{ viewing.is_active ? 'نشط' : 'موقوف' }}</span>
          </div>
          <div
            v-if="viewing.user_count != null"
            class="flex items-center justify-between border-b pb-2"
          >
            <span class="text-muted-foreground">عدد المستخدمين</span>
            <span class="font-medium">{{ viewing.user_count }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="archiveConfirmOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد أرشفة البنوك المحددة</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم إيقاف تفعيل {{ selectedCount }} بنك وأرشفته. يمكن إعادة تفعيله لاحقاً.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="bulkArchive">تأكيد الأرشفة</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
