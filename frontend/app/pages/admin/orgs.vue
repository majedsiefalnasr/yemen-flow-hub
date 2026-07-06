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
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { AlertCircle, Building2, Plus, PowerOff, SearchX, Zap } from 'lucide-vue-next'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useOrganizations } from '@/composables/useOrganizations'
import { useTableExport } from '@/composables/useTableExport'
import type { Organization } from '@/types/models'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/components/ui/form'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
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
  middleware: ['auth', 'screen'],
  requiredScreen: 'organizations',
})

const {
  organizations,
  loading,
  error,
  fetchOrganizations,
  createOrganization,
  updateOrganization,
  setOrganizationActive,
  deleteOrganization,
} = useOrganizations()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()

const query = ref('')
const dialogOpen = ref(false)
const editing = ref<Organization | null>(null)
const saving = ref(false)
const deleteConfirmOpen = ref(false)
const deletingOrg = ref<Organization | null>(null)

const columnVisibility = ref<VisibilityState>({})
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})

const formSchema = toTypedSchema(
  z.object({
    code: z.string().min(2, 'الرمز مطلوب (حرفان على الأقل)').max(100),
    name: z.string().min(2, 'الاسم مطلوب (حرفان على الأقل)').max(255),
    classification: z.enum(['BANKING_SECTOR', 'NATIONAL_COMMITTEE', 'OTHER']),
  }),
)

const CLASSIFICATION_OPTIONS: Array<{
  value: Organization['classification']
  label: string
}> = [
  { value: 'BANKING_SECTOR', label: 'القطاع المصرفي' },
  { value: 'NATIONAL_COMMITTEE', label: 'اللجنة الوطنية' },
  { value: 'OTHER', label: 'أخرى' },
]

function classificationLabel(value: Organization['classification']): string {
  return CLASSIFICATION_OPTIONS.find((option) => option.value === value)?.label ?? value
}

const form = useForm({ validationSchema: formSchema })

const onSubmit = form.handleSubmit(async (values) => {
  saving.value = true
  try {
    if (editing.value) {
      await updateOrganization(editing.value, {
        name: values.name,
        classification: values.classification,
      })
      toast.success('تم تحديث المؤسسة')
    } else {
      await createOrganization(values)
      toast.success('تم إنشاء المؤسسة')
    }
    closeForm()
  } catch (err) {
    toast.error(extractApiErrorMessage(err, 'تعذّر حفظ المؤسسة'))
  } finally {
    saving.value = false
  }
})

function openCreate() {
  editing.value = null
  form.resetForm({
    values: { code: '', name: '', classification: 'OTHER' },
  })
  dialogOpen.value = true
}

function openEdit(org: Organization) {
  editing.value = org
  form.resetForm({
    values: { code: org.code, name: org.name, classification: org.classification },
  })
  dialogOpen.value = true
}

function closeForm() {
  dialogOpen.value = false
  editing.value = null
  saving.value = false
}

function confirmDelete(org: Organization) {
  deletingOrg.value = org
  deleteConfirmOpen.value = true
}

async function executeDelete() {
  if (!deletingOrg.value) return
  try {
    await deleteOrganization(deletingOrg.value)
    toast.success(`تم حذف "${deletingOrg.value.name}"`)
  } catch {
    toast.error('تعذّر حذف المؤسسة')
  } finally {
    deletingOrg.value = null
    deleteConfirmOpen.value = false
  }
}

onMounted(() => fetchOrganizations())

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return organizations.value
  return organizations.value.filter(
    (o) => o.name.toLowerCase().includes(q) || o.code.toLowerCase().includes(q),
  )
})

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

const stats = computed(() => ({
  total: organizations.value.length,
  active: organizations.value.filter((o) => o.is_active).length,
  inactive: organizations.value.filter((o) => !o.is_active).length,
}))

const statusOptions = [
  { label: 'نشطة', value: 'true' },
  { label: 'موقوفة', value: 'false' },
]

const COLUMN_LABELS: Record<string, string> = {
  code: 'الرمز',
  classification: 'التصنيف',
  is_active: 'الحالة',
}

function activeStatusCell(isActive: boolean) {
  const color = isActive ? 'var(--color-success)' : 'var(--color-locked)'
  const label = isActive ? 'نشطة' : 'موقوفة'
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
        'stroke-width': 2,
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
      },
      paths,
    ),
    h('span', { class: 'text-sm', style: { color } }, label),
  ])
}

const orgActions: RowAction<Organization>[] = [
  {
    label: 'تعديل',
    onClick: (row) => openEdit(row.original),
  },
  {
    label: 'تفعيل',
    onClick: async (row) => {
      try {
        await setOrganizationActive(row.original, true)
        toast.success(`تم تفعيل "${row.original.name}"`)
      } catch {
        toast.error('فشل التفعيل')
      }
    },
    hidden: (row) => row.original.is_active || row.original.is_system,
  },
  {
    label: 'إيقاف',
    onClick: async (row) => {
      try {
        await setOrganizationActive(row.original, false)
        toast.success(`تم إيقاف "${row.original.name}"`)
      } catch {
        toast.error('فشل الإيقاف')
      }
    },
    hidden: (row) => !row.original.is_active || row.original.is_system,
  },
  {
    label: 'حذف',
    destructive: true,
    hidden: (row) => row.original.is_system,
    onClick: (row) => confirmDelete(row.original),
  },
]

const exportColumns = [
  { key: 'id', label: '#' },
  { key: 'code', label: 'الرمز' },
  { key: 'name', label: 'الاسم' },
  { key: 'is_active', label: 'الحالة' },
]

const columns: ColumnDef<Organization>[] = [
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
        'aria-label': `تحديد ${row.original.name}`,
      }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'org',
    header: 'المؤسسة',
    enableHiding: false,
    cell: ({ row }) => {
      const org = row.original
      return h('div', { class: 'flex items-center gap-2' }, [
        h(
          'div',
          {
            class:
              'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary text-xs font-semibold leading-5',
          },
          org.name.trim().charAt(0),
        ),
        h('div', {}, [
          h(
            'div',
            { class: 'font-section text-sm font-semibold leading-5 text-foreground' },
            org.name,
          ),
          h('div', { class: 'text-xs leading-5 text-muted-foreground font-mono' }, org.code),
        ]),
      ])
    },
  },
  {
    id: 'code',
    accessorKey: 'code',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'الرمز' }),
    cell: ({ row }) =>
      h('code', { class: 'rounded bg-muted px-2 py-0.5 text-xs font-mono' }, row.original.code),
  },
  {
    id: 'classification',
    accessorKey: 'classification',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'التصنيف' }),
    cell: ({ row }) => h('span', { class: 'text-sm' }, classificationLabel(row.original.classification)),
  },
  {
    accessorKey: 'is_active',
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.is_active)),
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'الحالة' }),
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'actions',
    header: 'إجراءات',
    enableHiding: false,
    cell: ({ row }) => h(DataTableRowActions as any, { row, actions: orgActions }),
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

const noOrgs = computed(() => !loading.value && organizations.value.length === 0 && !error.value)

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `organizations-${new Date().toISOString().slice(0, 10)}`
}

function clearBulkSelection() {
  table.resetRowSelection()
}

function getSelectedOrgs(): Organization[] {
  return table.getFilteredSelectedRowModel().rows.map((r) => r.original)
}

function bulkExportCSV() {
  const rows = getSelectedOrgs()
  if (!rows.length) return
  exportToCSV(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportExcel() {
  const rows = getSelectedOrgs()
  if (!rows.length) return
  exportToExcel(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportJSON() {
  const rows = getSelectedOrgs()
  if (!rows.length) return
  exportToJSON(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

const bulkToggling = ref(false)
async function bulkToggleStatus(activate: boolean) {
  const rows = getSelectedOrgs().filter((o) => o.is_active !== activate && !o.is_system)
  if (!rows.length) return
  bulkToggling.value = true
  try {
    await Promise.all(rows.map((o) => setOrganizationActive(o, activate)))
    clearBulkSelection()
    toast.success(activate ? `تم تفعيل ${rows.length} مؤسسة` : `تم إيقاف ${rows.length} مؤسسة`)
  } catch {
    toast.error('فشل تغيير الحالة لبعض المؤسسات')
  } finally {
    bulkToggling.value = false
  }
}
</script>

<template>
  <ScreenGuard screen="organizations">
    <div>
      <h1 class="page-title sr-only">إدارة المؤسسات</h1>
      <PageHeader
        title="إدارة المؤسسات"
        subtitle="إنشاء مؤسسات جديدة، عرض البيانات، تعديلها وتغيير حالة التفعيل"
        :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'إدارة المؤسسات' }]"
      >
        <template #actions>
          <Button size="sm" class="btn-primary h-8" @click="openCreate">
            <Plus class="h-4 w-4" />
            <span class="hidden lg:inline">مؤسسة جديدة</span>
          </Button>
        </template>
      </PageHeader>

      <Alert v-if="error" variant="destructive" role="alert" class="mb-6">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل المؤسسات</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
        <AlertAction>
          <Button variant="outline" size="sm" @click="fetchOrganizations()">إعادة المحاولة</Button>
        </AlertAction>
      </Alert>

      <div class="mb-6">
        <MetricGrid :columns="3">
          <MetricCard
            label="إجمالي المؤسسات"
            :value="stats.total"
            :icon="Building2"
            :active="columnFilters.length === 0"
            @click="table.resetColumnFilters()"
          />
          <MetricCard
            label="نشطة"
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
            label="موقوفة"
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

      <div class="relative flex flex-col gap-4">
        <DataTable
          :data="filtered"
          :columns="columns"
          :loading="loading"
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
              search-placeholder="بحث بالاسم أو الرمز..."
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
                <DataTableViewOptions :table="dataTable" :column-labels="COLUMN_LABELS" />
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
                  noOrgs ? 'لا توجد مؤسسات مسجّلة بعد' : 'لا توجد مؤسسات مطابقة'
                }}</EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{
                    noOrgs
                      ? 'ابدأ بإضافة أول مؤسسة باستخدام زر "مؤسسة جديدة" أعلاه.'
                      : 'جرّب تغيير البحث أو إزالة الفلاتر لعرض المزيد من المؤسسات.'
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

      <Dialog :open="dialogOpen" @update:open="(value) => !value && closeForm()">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{{ editing ? 'تعديل بيانات المؤسسة' : 'إضافة مؤسسة جديدة' }}</DialogTitle>
            <DialogDescription>
              {{
                editing
                  ? 'عدّل اسم المؤسسة. الرمز ثابت بعد الإنشاء.'
                  : 'أدخل بيانات المؤسسة الجديدة.'
              }}
            </DialogDescription>
          </DialogHeader>

          <form class="flex flex-col gap-4" @submit="onSubmit">
            <FormField v-slot="{ componentField }" name="code">
              <FormItem>
                <FormLabel>الرمز *</FormLabel>
                <FormControl>
                  <Input
                    v-bind="componentField"
                    placeholder="مثال: CBY"
                    dir="ltr"
                    :disabled="Boolean(editing)"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="name">
              <FormItem>
                <FormLabel>اسم المؤسسة *</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="مثال: البنك المركزي اليمني" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="classification">
              <FormItem>
                <FormLabel>التصنيف *</FormLabel>
                <Select v-bind="componentField">
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="اختر التصنيف" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem
                      v-for="option in CLASSIFICATION_OPTIONS"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <DialogFooter>
              <Button type="button" variant="outline" @click="closeForm">إلغاء</Button>
              <Button type="submit" :disabled="saving || form.isSubmitting.value">
                {{ editing ? 'حفظ التعديلات' : 'إضافة' }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog v-model:open="deleteConfirmOpen">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>تأكيد حذف المؤسسة</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف المؤسسة {{ deletingOrg?.name }} نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel @click="deletingOrg = null">إلغاء</AlertDialogCancel>
            <AlertDialogAction @click="executeDelete">تأكيد الحذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  </ScreenGuard>
</template>
