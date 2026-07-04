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
import { AlertCircle, Plus, PowerOff, SearchX, Shield, Zap } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { z } from 'zod'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import type { GovernanceRole } from '@/types/models'
import { useGovernanceRoles } from '@/composables/useGovernanceRoles'
import { useOrganizations } from '@/composables/useOrganizations'
import { useTableExport } from '@/composables/useTableExport'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
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
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'roles' })

const { roles, loading, error, fetchRoles, createRole, updateRole, setRoleActive, deleteRole } =
  useGovernanceRoles()
const { organizations, fetchOrganizations } = useOrganizations()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()

const query = ref('')
const dialogOpen = ref(false)
const editing = ref<GovernanceRole | null>(null)
const saving = ref(false)
const columnVisibility = ref<VisibilityState>({})
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})
const deleteConfirmOpen = ref(false)
const deletingRole = ref<GovernanceRole | null>(null)

const roleSchema = toTypedSchema(
  z.object({
    organization_id: z.number().int().positive('المؤسسة مطلوبة'),
    code: z.string().min(2, 'الرمز مطلوب (حرفان على الأقل)'),
    name: z.string().min(2, 'الاسم مطلوب (حرفان على الأقل)'),
  }),
)

const form = useForm({ validationSchema: roleSchema })

onMounted(async () => {
  await Promise.all([fetchRoles(), fetchOrganizations()])
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return roles.value
  return roles.value.filter(
    (r) =>
      r.name.toLowerCase().includes(q) ||
      r.code.toLowerCase().includes(q) ||
      (r.organization?.name ?? '').toLowerCase().includes(q),
  )
})

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)

// Organization options for the faceted filter, matching the column's accessor
// (the organization name), so it behaves like the status filter button.
const orgFilterOptions = computed(() => {
  const names = new Set<string>()
  for (const r of roles.value) if (r.organization?.name) names.add(r.organization.name)
  return [...names].sort().map((name) => ({ label: name, value: name }))
})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

const stats = computed(() => ({
  total: roles.value.length,
  active: roles.value.filter((r) => r.is_active).length,
  inactive: roles.value.filter((r) => !r.is_active).length,
}))

const statusOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'موقوف', value: 'false' },
]

const COLUMN_LABELS: Record<string, string> = {
  code: 'الرمز',
  organization: 'المؤسسة',
  is_active: 'الحالة',
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

function openCreate() {
  editing.value = null
  form.resetForm({ values: { organization_id: 0, code: '', name: '' } })
  dialogOpen.value = true
}

function openEdit(role: GovernanceRole) {
  editing.value = role
  form.resetForm({
    values: {
      organization_id: role.organization_id,
      code: role.code,
      name: role.name,
    },
  })
  dialogOpen.value = true
}

function closeForm() {
  dialogOpen.value = false
  editing.value = null
  form.resetForm()
}

const onSubmit = form.handleSubmit(async (values) => {
  saving.value = true
  try {
    if (editing.value) {
      const payload: { name: string } = { name: values.name }
      await updateRole(editing.value, payload)
      toast.success('تم حفظ التعديلات')
    } else {
      await createRole(values)
      toast.success('تم إنشاء الدور')
    }
    closeForm()
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ بيانات الدور'))
  } finally {
    saving.value = false
  }
})

async function toggleStatus(role: GovernanceRole) {
  try {
    await setRoleActive(role, !role.is_active)
    toast.success(role.is_active ? `تم إيقاف ${role.name}` : `تم تفعيل ${role.name}`)
  } catch {
    toast.error('فشل تغيير الحالة')
  }
}

function confirmDelete(role: GovernanceRole) {
  deletingRole.value = role
  deleteConfirmOpen.value = true
}

async function executeDelete() {
  if (!deletingRole.value) return
  try {
    await deleteRole(deletingRole.value)
    toast.success('تم حذف الدور')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الدور'))
  } finally {
    deletingRole.value = null
    deleteConfirmOpen.value = false
  }
}

const roleActions: RowAction<GovernanceRole>[] = [
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
  {
    label: 'حذف',
    destructive: true,
    hidden: (row) => row.original.is_system,
    onClick: (row) => confirmDelete(row.original),
  },
]

const exportColumns = [
  { key: 'name', columnId: 'role', label: 'اسم الدور' },
  { key: 'code', columnId: 'code', label: 'الرمز' },
  {
    key: 'organization',
    columnId: 'organization',
    label: 'المؤسسة',
    format: (_v: any, row: GovernanceRole) => row.organization?.name ?? '—',
  },
  {
    key: 'is_active',
    columnId: 'is_active',
    label: 'الحالة',
    format: (_v: any, row: GovernanceRole) => (row.is_active ? 'نشط' : 'موقوف'),
  },
]

const columns: ColumnDef<GovernanceRole>[] = [
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
    id: 'role',
    header: 'الدور',
    enableHiding: false,
    cell: ({ row }) => {
      const role = row.original
      return h('div', { class: 'flex items-center gap-2' }, [
        h(
          'div',
          {
            class:
              'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary text-xs font-semibold leading-5',
          },
          role.name.trim().charAt(0),
        ),
        h('div', {}, [
          h(
            'div',
            { class: 'font-section text-sm font-semibold leading-5 text-foreground' },
            role.name,
          ),
          h('div', { class: 'text-xs leading-5 text-muted-foreground font-mono' }, role.code),
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
    id: 'organization',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'المؤسسة' }),
    accessorFn: (row) => row.organization?.name ?? '—',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.organization?.name ?? '—'),
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.organization?.name ?? '—'),
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
    cell: ({ row }) => h(DataTableRowActions as any, { row, actions: roleActions }),
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

const noRoles = computed(() => !loading.value && roles.value.length === 0 && !error.value)

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `roles-${new Date().toISOString().slice(0, 10)}`
}

function clearBulkSelection() {
  table.resetRowSelection()
}

function getSelectedRoles(): GovernanceRole[] {
  return table.getFilteredSelectedRowModel().rows.map((r) => r.original)
}

function bulkExportCSV() {
  const rows = getSelectedRoles()
  if (!rows.length) return
  exportToCSV(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportExcel() {
  const rows = getSelectedRoles()
  if (!rows.length) return
  exportToExcel(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportJSON() {
  const rows = getSelectedRoles()
  if (!rows.length) return
  exportToJSON(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

const bulkToggling = ref(false)
async function bulkToggleStatus(activate: boolean) {
  const rows = getSelectedRoles().filter((r) => r.is_active !== activate)
  if (!rows.length) return
  bulkToggling.value = true
  try {
    await Promise.all(rows.map((r) => setRoleActive(r, activate)))
    clearBulkSelection()
    toast.success(activate ? `تم تفعيل ${rows.length} دور` : `تم إيقاف ${rows.length} دور`)
  } catch {
    toast.error('فشل تغيير الحالة لبعض الأدوار')
  } finally {
    bulkToggling.value = false
  }
}

const formOrgId = computed({
  get: () => {
    const val = form.values.organization_id
    return val ? String(val) : ''
  },
  set: (value: string) => {
    form.setFieldValue('organization_id', value ? Number(value) : 0)
  },
})
</script>

<template>
  <ScreenGuard screen="roles">
    <div>
      <h1 class="page-title sr-only">إدارة الأدوار</h1>
      <PageHeader
        title="إدارة الأدوار"
        subtitle="إنشاء أدوار جديدة، عرض البيانات، تعديلها وتغيير حالة التفعيل"
        :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الأدوار' }]"
      >
        <template #actions>
          <Button size="sm" class="btn-primary h-8" @click="openCreate">
            <Plus class="h-4 w-4" />
            <span class="hidden lg:inline">دور جديد</span>
          </Button>
        </template>
      </PageHeader>

      <Alert v-if="error" variant="destructive" role="alert" class="mb-6">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل الأدوار</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
        <AlertAction>
          <Button variant="outline" size="sm" @click="fetchRoles()">إعادة المحاولة</Button>
        </AlertAction>
      </Alert>

      <div class="mb-6">
        <MetricGrid :columns="3">
          <MetricCard
            label="إجمالي الأدوار"
            :value="stats.total"
            :icon="Shield"
            :active="columnFilters.length === 0"
            @click="table.resetColumnFilters()"
          />
          <MetricCard
            label="نشط"
            :value="stats.active"
            :icon="Shield"
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
            :icon="Shield"
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
              search-placeholder="بحث بالاسم أو الرمز أو المؤسسة..."
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
                <DataTableFacetedFilter
                  v-if="dataTable.getColumn('organization') && orgFilterOptions.length > 1"
                  :column="dataTable.getColumn('organization')!"
                  title="المؤسسة"
                  :options="orgFilterOptions"
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
                  noRoles ? 'لا توجد أدوار مسجّلة بعد' : 'لا توجد أدوار مطابقة'
                }}</EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{
                    noRoles
                      ? 'ابدأ بإضافة أول دور باستخدام زر "دور جديد" أعلاه.'
                      : 'جرّب تغيير البحث أو إزالة الفلاتر لعرض المزيد من الأدوار.'
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
            <DialogTitle>{{ editing ? 'تعديل بيانات الدور' : 'إضافة دور جديد' }}</DialogTitle>
            <DialogDescription>
              {{
                editing
                  ? 'عدّل اسم الدور. الرمز والمؤسسة ثابتان بعد الإنشاء.'
                  : 'أدخل بيانات الدور الجديد واختر المؤسسة التابع لها.'
              }}
            </DialogDescription>
          </DialogHeader>

          <form class="flex flex-col gap-4" @submit="onSubmit">
            <FormField v-slot="{ componentField }" name="name">
              <FormItem>
                <FormLabel>اسم الدور *</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="مثال: دور إدخال البيانات" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="code">
              <FormItem>
                <FormLabel>الرمز *</FormLabel>
                <FormControl>
                  <Input
                    v-bind="componentField"
                    placeholder="مثال: role_data_entry"
                    dir="ltr"
                    :disabled="Boolean(editing)"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField name="organization_id">
              <FormItem>
                <FormLabel>المؤسسة *</FormLabel>
                <Select v-model="formOrgId" :disabled="Boolean(editing)">
                  <FormControl>
                    <SelectTrigger class="w-full">
                      <SelectValue placeholder="اختر المؤسسة" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="org in organizations" :key="org.id" :value="String(org.id)">
                      {{ org.name }}
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
            <AlertDialogTitle>تأكيد حذف الدور</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف الدور {{ deletingRole?.name }} نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel @click="deletingRole = null">إلغاء</AlertDialogCancel>
            <AlertDialogAction @click="executeDelete">تأكيد الحذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  </ScreenGuard>
</template>
