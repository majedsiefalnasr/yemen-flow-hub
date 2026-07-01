<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { AlertCircle, Database, Plus, SearchX, Table2 } from 'lucide-vue-next'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import type { ReferenceTable, ReferenceValue } from '@/types/models'
import { useReferenceData } from '@/composables/useReferenceData'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import {
  DataTable,
  DataTableColumnHeader,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableRowActions,
  DataTableToolbar,
  DataTableViewOptions,
  type RowAction,
} from '@/components/ui/data-table'
import { TooltipProvider } from '@/components/ui/tooltip'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'

definePageMeta({
  middleware: ['auth', 'screen'],
  requiredScreen: 'reference_data',
})

const {
  referenceTables,
  referenceValues,
  tablesLoading,
  valuesLoading,
  error,
  fetchReferenceTables,
  fetchReferenceValues,
  createReferenceTable,
  updateReferenceTable,
  setReferenceTableActive,
  deleteReferenceTable,
  createReferenceValue,
  updateReferenceValue,
  setReferenceValueActive,
  deleteReferenceValue,
} = useReferenceData()

const selectedTable = ref<ReferenceTable | null>(null)
const tableQuery = ref('')
const valueQuery = ref('')

const tableDialogOpen = ref(false)
const editingTable = ref<ReferenceTable | null>(null)
const valueDialogOpen = ref(false)
const editingValue = ref<ReferenceValue | null>(null)
const deletingTable = ref<ReferenceTable | null>(null)
const deletingValue = ref<ReferenceValue | null>(null)

const keySchema = z
  .string()
  .min(1)
  .max(100)
  .regex(/^[\p{L}\p{M}\p{N}_-]+$/u, 'يسمح بالحروف والأرقام والشرطة والشرطة السفلية فقط')

const tableForm = useForm({
  validationSchema: toTypedSchema(
    z.object({ key: keySchema.min(2), label: z.string().min(2).max(255) }),
  ),
})

const valueForm = useForm({
  validationSchema: toTypedSchema(z.object({ key: keySchema, label: z.string().min(1).max(255) })),
})

const tableStats = computed(() => ({
  total: referenceTables.value.length,
  active: referenceTables.value.filter((t) => t.is_active).length,
  system: referenceTables.value.filter((t) => t.is_system).length,
}))

const selectedValueCount = computed(() => (selectedTable.value ? referenceValues.value.length : 0))

const statusOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'موقوف', value: 'false' },
]

const TABLE_COLUMN_LABELS: Record<string, string> = {
  key: 'الرمز',
  is_active: 'الحالة',
}

const VALUE_COLUMN_LABELS: Record<string, string> = {
  key: 'الرمز',
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

// ── Client-side filtered data ───────────────────────────────────────────

const filteredTables = computed(() => {
  const q = tableQuery.value.trim().toLowerCase()
  if (!q) return referenceTables.value
  return referenceTables.value.filter(
    (t) => t.key.toLowerCase().includes(q) || t.label.toLowerCase().includes(q),
  )
})

const filteredValues = computed(() => {
  const q = valueQuery.value.trim().toLowerCase()
  if (!q) return referenceValues.value
  return referenceValues.value.filter(
    (v) => v.key.toLowerCase().includes(q) || v.label.toLowerCase().includes(q),
  )
})

// ── Tables panel columns + actions ──────────────────────────────────────

const tableActions: RowAction<ReferenceTable>[] = [
  {
    label: 'عرض القيم',
    onClick: (row) => selectTable(row.original),
  },
  {
    label: 'تعديل',
    onClick: (row) => openEditTable(row.original),
  },
  {
    label: 'إيقاف',
    destructive: true,
    hidden: (row) => !row.original.is_active,
    onClick: (row) => toggleTable(row.original),
  },
  {
    label: 'تفعيل',
    hidden: (row) => row.original.is_active,
    onClick: (row) => toggleTable(row.original),
  },
  {
    label: 'حذف',
    destructive: true,
    hidden: (row) => row.original.is_system || row.original.is_in_use,
    onClick: (row) => (deletingTable.value = row.original),
  },
]

const tableColumns: ColumnDef<ReferenceTable>[] = [
  {
    id: 'select',
    header: ({ table: t }) =>
      h(Checkbox, {
        modelValue:
          t.getIsAllPageRowsSelected() || (t.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          t.toggleAllPageRowsSelected(!!value),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'aria-label': `تحديد ${row.original.label}`,
      }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'table_name',
    header: 'الجدول المرجعي',
    enableHiding: false,
    cell: ({ row }) => {
      const t = row.original
      return h('div', { class: 'flex items-center gap-2' }, [
        h(
          'div',
          {
            class:
              'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary text-xs font-semibold leading-5',
          },
          t.label.trim().charAt(0),
        ),
        h('div', {}, [
          h('div', { class: 'font-section text-sm font-semibold leading-5 text-foreground' }, [
            t.label,
            ...(t.is_system
              ? [
                  h(
                    Badge,
                    { variant: 'outline', class: 'ms-2 text-[10px] leading-tight' },
                    () => 'نظامي',
                  ),
                ]
              : []),
          ]),
          h('div', { class: 'text-xs leading-5 text-muted-foreground font-mono' }, t.key),
        ]),
      ])
    },
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
    cell: ({ row }) => h(DataTableRowActions as any, { row, actions: tableActions }),
  },
]

// ── Values panel columns + actions ──────────────────────────────────────

const valueActions: RowAction<ReferenceValue>[] = [
  {
    label: 'تعديل',
    onClick: (row) => openEditValue(row.original),
  },
  {
    label: 'إيقاف',
    destructive: true,
    hidden: (row) => !row.original.is_active,
    onClick: (row) => toggleValue(row.original),
  },
  {
    label: 'تفعيل',
    hidden: (row) => row.original.is_active,
    onClick: (row) => toggleValue(row.original),
  },
  {
    label: 'حذف',
    destructive: true,
    hidden: (row) => row.original.is_system || row.original.is_in_use,
    onClick: (row) => (deletingValue.value = row.original),
  },
]

const valueColumns: ColumnDef<ReferenceValue>[] = [
  {
    id: 'select',
    header: ({ table: t }) =>
      h(Checkbox, {
        modelValue:
          t.getIsAllPageRowsSelected() || (t.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          t.toggleAllPageRowsSelected(!!value),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'aria-label': `تحديد ${row.original.label}`,
      }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'value_name',
    header: 'القيمة',
    enableHiding: false,
    cell: ({ row }) => {
      const v = row.original
      return h('div', {}, [
        h('div', { class: 'font-section text-sm font-semibold leading-5 text-foreground' }, [
          v.label,
          ...(v.is_system
            ? [
                h(
                  Badge,
                  { variant: 'outline', class: 'ms-2 text-[10px] leading-tight' },
                  () => 'نظامي',
                ),
              ]
            : []),
        ]),
        h('div', { class: 'text-xs leading-5 text-muted-foreground font-mono' }, v.key),
      ])
    },
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
    cell: ({ row }) => h(DataTableRowActions as any, { row, actions: valueActions }),
  },
]

// ── Actions ──────────────────────────────────────────────────────────────

async function selectTable(table: ReferenceTable) {
  selectedTable.value = table
  valueQuery.value = ''
  await fetchReferenceValues(table.id, { page: 1 })
}

function openCreateTable() {
  editingTable.value = null
  tableForm.resetForm({ values: { key: '', label: '' } })
  tableDialogOpen.value = true
}

function openEditTable(table: ReferenceTable) {
  editingTable.value = table
  tableForm.resetForm({ values: { key: table.key, label: table.label } })
  tableDialogOpen.value = true
}

const submitTable = tableForm.handleSubmit(async (values) => {
  try {
    if (editingTable.value) {
      await updateReferenceTable(editingTable.value, { label: values.label })
      toast.success('تم تحديث الجدول المرجعي')
    } else {
      await createReferenceTable(values)
      toast.success('تم إنشاء الجدول المرجعي')
    }
    tableDialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ الجدول المرجعي'))
  }
})

async function toggleTable(table: ReferenceTable) {
  try {
    await setReferenceTableActive(table, !table.is_active)
    if (selectedTable.value?.id === table.id) {
      selectedTable.value = referenceTables.value.find((item) => item.id === table.id) ?? null
    }
    toast.success(table.is_active ? `تم إيقاف ${table.label}` : `تم تفعيل ${table.label}`)
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر تحديث حالة الجدول المرجعي'))
  }
}

async function removeTable() {
  if (!deletingTable.value) return
  const table = deletingTable.value
  try {
    await deleteReferenceTable(table)
    toast.success('تم حذف الجدول المرجعي')
    if (selectedTable.value?.id === table.id) {
      selectedTable.value = null
    }
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الجدول المرجعي'))
  } finally {
    deletingTable.value = null
  }
}

function openCreateValue() {
  editingValue.value = null
  valueForm.resetForm({ values: { key: '', label: '' } })
  valueDialogOpen.value = true
}

function openEditValue(value: ReferenceValue) {
  editingValue.value = value
  valueForm.resetForm({ values: { key: value.key, label: value.label } })
  valueDialogOpen.value = true
}

const submitValue = valueForm.handleSubmit(async (values) => {
  if (!selectedTable.value) return
  try {
    if (editingValue.value) {
      await updateReferenceValue(editingValue.value, { label: values.label })
      toast.success('تم تحديث القيمة المرجعية')
    } else {
      await createReferenceValue({ ...values, reference_table_id: selectedTable.value.id })
      toast.success('تم إنشاء القيمة المرجعية')
    }
    valueDialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ القيمة المرجعية'))
  }
})

async function toggleValue(value: ReferenceValue) {
  try {
    await setReferenceValueActive(value, !value.is_active)
    toast.success(value.is_active ? `تم إيقاف ${value.label}` : `تم تفعيل ${value.label}`)
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر تحديث حالة القيمة المرجعية'))
  }
}

async function removeValue() {
  if (!deletingValue.value) return
  const value = deletingValue.value
  try {
    await deleteReferenceValue(value)
    toast.success('تم حذف القيمة المرجعية')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف القيمة المرجعية'))
  } finally {
    deletingValue.value = null
  }
}

async function retryLoad() {
  if (selectedTable.value) {
    await fetchReferenceValues(selectedTable.value.id, { page: 1 })
    return
  }
  await fetchReferenceTables({ page: 1 })
}

const tableHasFilters = computed(() => tableQuery.value.trim().length > 0)
const valueHasFilters = computed(() => valueQuery.value.trim().length > 0)

onMounted(() => fetchReferenceTables({ page: 1 }))
</script>

<template>
  <ScreenGuard screen="reference_data">
    <TooltipProvider>
      <div>
        <h1 class="page-title sr-only">إدارة البيانات المرجعية</h1>
        <PageHeader
          title="البيانات المرجعية"
          subtitle="إنشاء وإدارة الجداول والقيم المرجعية للنظام"
          :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'البيانات المرجعية' }]"
        >
          <template #actions>
            <ScreenGuard screen="reference_data" capability="CREATE">
              <Button size="sm" class="btn-primary h-8" @click="openCreateTable">
                <Plus class="h-4 w-4" />
                <span class="hidden lg:inline">جدول مرجعي جديد</span>
              </Button>
            </ScreenGuard>
          </template>
        </PageHeader>

        <Alert v-if="error" variant="destructive" role="alert" class="mb-6">
          <AlertCircle class="h-4 w-4" />
          <AlertTitle>تعذّر تحميل البيانات المرجعية</AlertTitle>
          <AlertDescription>{{ error }}</AlertDescription>
          <AlertAction>
            <Button variant="outline" size="sm" @click="retryLoad">إعادة المحاولة</Button>
          </AlertAction>
        </Alert>

        <div class="mb-6">
          <MetricGrid :columns="4">
            <MetricCard label="إجمالي الجداول" :value="tableStats.total" :icon="Database" />
            <MetricCard label="نشط" :value="tableStats.active" :icon="Database" tone="success" />
            <MetricCard label="نظامي" :value="tableStats.system" :icon="Database" tone="info" />
            <MetricCard
              label="قيم الجدول المحدد"
              :value="selectedValueCount"
              :icon="Table2"
              tone="warning"
            />
          </MetricGrid>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <!-- Tables panel -->
          <Card class="border-0 shadow">
            <CardHeader class="pb-2">
              <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                <Table2 class="text-primary h-4 w-4" />
                الجداول المرجعية
              </CardTitle>
            </CardHeader>
            <CardContent class="p-4 pt-0">
              <DataTable
                :data="filteredTables"
                :columns="tableColumns"
                :loading="tablesLoading"
                :row-class="() => 'cursor-pointer'"
                @row-click="selectTable"
              >
                <template #toolbar="{ table: dt }">
                  <DataTableToolbar
                    :table="dt"
                    search-placeholder="بحث بالرمز أو الاسم..."
                    :has-filters="tableHasFilters"
                    :selected-count="
                      Object.values(dt.getState().rowSelection).filter(Boolean).length
                    "
                    @update:search="(v) => (tableQuery = v)"
                    @reset="tableQuery = ''"
                    @clear-selection="dt.resetRowSelection()"
                  >
                    <template #filters>
                      <DataTableFacetedFilter
                        v-if="dt.getColumn('is_active')"
                        :column="dt.getColumn('is_active')!"
                        title="الحالة"
                        :options="statusOptions"
                      />
                    </template>
                    <template #actions>
                      <DataTableViewOptions :table="dt" :column-labels="TABLE_COLUMN_LABELS" />
                    </template>
                  </DataTableToolbar>
                </template>
                <template #empty>
                  <Empty class="bg-muted/20 min-h-[200px] rounded-xl border border-dashed">
                    <EmptyHeader>
                      <div
                        class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-xl"
                      >
                        <SearchX class="size-4" />
                      </div>
                      <EmptyTitle class="text-sm">لا توجد جداول مرجعية</EmptyTitle>
                    </EmptyHeader>
                    <EmptyContent>
                      <EmptyDescription class="text-xs">
                        ابدأ بإضافة جدول مرجعي جديد.
                      </EmptyDescription>
                    </EmptyContent>
                  </Empty>
                </template>
                <template #pagination="{ table: dt }">
                  <DataTablePagination :table="dt" />
                </template>
              </DataTable>
            </CardContent>
          </Card>

          <!-- Values panel -->
          <Card class="border-0 shadow">
            <CardHeader class="pb-2">
              <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                  <Database class="text-primary h-4 w-4" />
                  {{ selectedTable ? `قيم: ${selectedTable.label}` : 'القيم المرجعية' }}
                </CardTitle>
                <ScreenGuard v-if="selectedTable" screen="reference_data" capability="CREATE">
                  <Button size="sm" variant="outline" class="h-7" @click="openCreateValue">
                    <Plus class="h-3.5 w-3.5" />
                    إضافة قيمة
                  </Button>
                </ScreenGuard>
              </div>
            </CardHeader>
            <CardContent class="p-4 pt-0">
              <template v-if="selectedTable">
                <div
                  class="border-border bg-muted/30 mb-4 rounded-lg border p-3"
                  aria-label="الجدول المحدد"
                >
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <div class="text-muted-foreground text-xs font-medium">الجدول المحدد</div>
                      <div class="font-section mt-1 text-sm font-semibold">
                        {{ selectedTable.label }}
                      </div>
                      <div class="text-muted-foreground font-mono text-xs">
                        {{ selectedTable.key }}
                      </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                      <Badge :variant="selectedTable.is_active ? 'secondary' : 'outline'">
                        {{ selectedTable.is_active ? 'نشط' : 'موقوف' }}
                      </Badge>
                      <Badge v-if="selectedTable.is_system" variant="outline">نظامي</Badge>
                      <Badge v-if="selectedTable.is_in_use" variant="outline">مستخدم</Badge>
                    </div>
                  </div>
                </div>

                <DataTable :data="filteredValues" :columns="valueColumns" :loading="valuesLoading">
                  <template #toolbar="{ table: dt }">
                    <DataTableToolbar
                      :table="dt"
                      search-placeholder="بحث بالرمز أو الاسم..."
                      :has-filters="valueHasFilters"
                      :selected-count="
                        Object.values(dt.getState().rowSelection).filter(Boolean).length
                      "
                      @update:search="(v) => (valueQuery = v)"
                      @reset="valueQuery = ''"
                      @clear-selection="dt.resetRowSelection()"
                    >
                      <template #filters>
                        <DataTableFacetedFilter
                          v-if="dt.getColumn('is_active')"
                          :column="dt.getColumn('is_active')!"
                          title="الحالة"
                          :options="statusOptions"
                        />
                      </template>
                      <template #actions>
                        <DataTableViewOptions :table="dt" :column-labels="VALUE_COLUMN_LABELS" />
                      </template>
                    </DataTableToolbar>
                  </template>
                  <template #empty>
                    <Empty class="bg-muted/20 min-h-[200px] rounded-xl border border-dashed">
                      <EmptyHeader>
                        <div
                          class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-xl"
                        >
                          <SearchX class="size-4" />
                        </div>
                        <EmptyTitle class="text-sm">لا توجد قيم لهذا الجدول</EmptyTitle>
                      </EmptyHeader>
                      <EmptyContent>
                        <EmptyDescription class="text-xs">
                          أضف قيمة جديدة باستخدام زر "إضافة قيمة" أعلاه.
                        </EmptyDescription>
                      </EmptyContent>
                    </Empty>
                  </template>
                  <template #pagination="{ table: dt }">
                    <DataTablePagination :table="dt" />
                  </template>
                </DataTable>
              </template>

              <Empty v-else class="bg-muted/20 min-h-[300px] rounded-xl border border-dashed">
                <EmptyHeader>
                  <div
                    class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
                  >
                    <Database class="size-5" />
                  </div>
                  <EmptyTitle>اختر جدولاً مرجعياً</EmptyTitle>
                </EmptyHeader>
                <EmptyContent>
                  <EmptyDescription> اختر جدولاً من القائمة لعرض وإدارة قيمه. </EmptyDescription>
                </EmptyContent>
              </Empty>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Table create/edit dialog -->
      <Dialog v-model:open="tableDialogOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{{
              editingTable ? 'تعديل الجدول المرجعي' : 'إضافة جدول مرجعي'
            }}</DialogTitle>
            <DialogDescription>الرمز ثابت بعد الإنشاء، ويمكن تعديل الاسم فقط.</DialogDescription>
          </DialogHeader>
          <form class="flex flex-col gap-4 py-2" @submit="submitTable">
            <FormField v-slot="{ componentField }" name="key">
              <FormItem>
                <FormLabel>الرمز *</FormLabel>
                <FormControl>
                  <Input
                    v-bind="componentField"
                    :disabled="Boolean(editingTable)"
                    dir="ltr"
                    placeholder="مثال: currencies"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField }" name="label">
              <FormItem>
                <FormLabel>الاسم *</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="مثال: العملات" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <DialogFooter>
              <Button type="button" variant="outline" @click="tableDialogOpen = false">
                إلغاء
              </Button>
              <Button type="submit" :disabled="tableForm.isSubmitting.value">حفظ</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <!-- Value create/edit dialog -->
      <Dialog v-model:open="valueDialogOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{{
              editingValue ? 'تعديل القيمة المرجعية' : 'إضافة قيمة مرجعية'
            }}</DialogTitle>
            <DialogDescription>الرمز ثابت بعد الإنشاء، ويمكن تعديل الاسم فقط.</DialogDescription>
          </DialogHeader>
          <form class="flex flex-col gap-4 py-2" @submit="submitValue">
            <FormField v-slot="{ componentField }" name="key">
              <FormItem>
                <FormLabel>الرمز *</FormLabel>
                <FormControl>
                  <Input
                    v-bind="componentField"
                    :disabled="Boolean(editingValue)"
                    dir="ltr"
                    placeholder="مثال: USD"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField }" name="label">
              <FormItem>
                <FormLabel>الاسم *</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="مثال: دولار أمريكي" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <DialogFooter>
              <Button type="button" variant="outline" @click="valueDialogOpen = false">
                إلغاء
              </Button>
              <Button type="submit" :disabled="valueForm.isSubmitting.value">حفظ</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <!-- Delete table confirmation -->
      <AlertDialog :open="Boolean(deletingTable)" @update:open="deletingTable = null">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>تأكيد حذف الجدول المرجعي</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف الجدول {{ deletingTable?.label }} نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel @click="deletingTable = null">إلغاء</AlertDialogCancel>
            <AlertDialogAction @click="removeTable">تأكيد الحذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <!-- Delete value confirmation -->
      <AlertDialog :open="Boolean(deletingValue)" @update:open="deletingValue = null">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>تأكيد حذف القيمة المرجعية</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف القيمة {{ deletingValue?.label }} نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel @click="deletingValue = null">إلغاء</AlertDialogCancel>
            <AlertDialogAction @click="removeValue">تأكيد الحذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </TooltipProvider>
  </ScreenGuard>
</template>
