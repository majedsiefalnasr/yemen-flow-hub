<script setup lang="ts">
import type { ColumnDef, VisibilityState } from '@tanstack/vue-table'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { computed, ref, reactive, onMounted, h } from 'vue'
import {
  AlertTriangle,
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  Download, MoreHorizontal, Plus, Printer, Search, SearchX, X,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '../types/enums'
import type { Bank } from '../types/models'
import { useBanks } from '../composables/useBanks'
import { useAuthStore } from '../stores/auth.store'
import type { CreateBankPayload, UpdateBankPayload } from '../composables/useBanks'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
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

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
})

const { fetchBanks, createBank, updateBank } = useBanks()
const auth = useAuthStore()
const isBankAdmin = computed(() => auth.user?.role === UserRole.BANK_ADMIN)

const banks = ref<Bank[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const query = ref('')

const showModal = ref(false)
const editingBank = ref<Bank | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface BankForm {
  name_ar: string
  name_en: string
  code: string
  is_active: boolean
}

const form = reactive<BankForm>({
  name_ar: '',
  name_en: '',
  code: '',
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof BankForm, string>>>({})

async function loadBanks() {
  loading.value = true
  error.value = null
  try {
    banks.value = await fetchBanks()
  }
  catch {
    error.value = 'تعذّر تحميل قائمة البنوك.'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingBank.value = null
  form.name_ar = ''
  form.name_en = ''
  form.code = ''
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function openEdit(bank: Bank) {
  editingBank.value = bank
  form.name_ar = bank.name_ar
  form.name_en = bank.name_en
  form.code = bank.code
  form.is_active = bank.is_active
  clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function clearErrors() {
  formErrors.name_ar = undefined
  formErrors.name_en = undefined
  formErrors.code = undefined
  formError.value = null
}

function validateForm(): boolean {
  clearErrors()
  let valid = true
  if (!form.name_ar.trim()) { formErrors.name_ar = 'الاسم بالعربية مطلوب'; valid = false }
  if (!isBankAdmin.value && !form.name_en.trim()) { formErrors.name_en = 'الاسم بالإنجليزية مطلوب'; valid = false }
  if (!isBankAdmin.value && !form.code.trim()) { formErrors.code = 'الرمز مطلوب'; valid = false }
  return valid
}

async function saveBank() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  try {
    if (editingBank.value) {
      const payload: UpdateBankPayload = isBankAdmin.value
        ? { name: form.name_ar.trim(), name_ar: form.name_ar.trim() }
        : {
            name: form.name_ar.trim(),
            name_ar: form.name_ar.trim(),
            name_en: form.name_en.trim(),
            code: form.code.trim().toUpperCase(),
            is_active: form.is_active,
          }
      const updated = await updateBank(editingBank.value.id, payload)
      const idx = banks.value.findIndex(b => b.id === updated.id)
      if (idx !== -1) banks.value[idx] = updated
    }
    else {
      const payload: CreateBankPayload = {
        name: form.name_ar.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim().toUpperCase(),
        is_active: form.is_active,
      }
      const created = await createBank(payload)
      banks.value.unshift(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.data?.errors) {
      const errs = e.data.errors
      if (errs.name_ar?.[0]) formErrors.name_ar = errs.name_ar[0]
      if (errs.name_en?.[0]) formErrors.name_en = errs.name_en[0]
      if (errs.code?.[0]) formErrors.code = errs.code[0]
    }
    else {
      formError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
    }
  }
  finally {
    saving.value = false
  }
}

const rowSelection = ref<Record<string, boolean>>({})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearSelection() {
  table.resetRowSelection()
}

const filteredBanks = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return banks.value
  return banks.value.filter(b =>
    b.name_ar.toLowerCase().includes(q)
    || b.name_en.toLowerCase().includes(q)
    || b.code.toLowerCase().includes(q),
  )
})

function activeStatusCell(isActive: boolean, activeLabel = 'نشط', inactiveLabel = 'موقوف') {
  const color = isActive ? 'var(--color-success)' : 'var(--color-locked)'
  const label = isActive ? activeLabel : inactiveLabel
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
    h('svg', {
      class: 'shrink-0', style: { color }, width: 15, height: 15,
      viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
      'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
    }, paths),
    h('span', { class: 'text-sm font-medium text-foreground' }, label),
  ])
}

const columns: ColumnDef<Bank>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        'modelValue': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (v: boolean | 'indeterminate') => table.toggleAllPageRowsSelected(!!v),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h('div', { onClick: (e: Event) => e.stopPropagation() }, [
        h(Checkbox, {
          'modelValue': row.getIsSelected(),
          'onUpdate:modelValue': (v: boolean | 'indeterminate') => row.toggleSelected(!!v),
          'aria-label': 'تحديد البنك',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'name',
    header: 'البنك',
    cell: ({ row }) => h('div', { class: 'flex flex-col gap-0.5' }, [
      h('span', { class: 'text-sm font-medium' }, row.original.name_ar),
      h('span', { class: 'text-xs text-muted-foreground', dir: 'ltr' }, row.original.name_en),
    ]),
  },
  {
    accessorKey: 'code',
    header: 'الرمز',
    cell: ({ row }) => h('code', { class: 'rounded bg-muted px-2 py-0.5 text-xs font-mono' }, row.original.code),
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const bank = row.original
      return h(DropdownMenu, {}, {
        default: () => [
          h(DropdownMenuTrigger, { asChild: true }, {
            default: () => h(Button, {
              variant: 'ghost', size: 'icon', class: 'h-8 w-8',
            }, {
              default: () => [
                h('span', { class: 'sr-only' }, 'فتح القائمة'),
                h(MoreHorizontal, { class: 'h-4 w-4' }),
              ],
            }),
          }),
          h(DropdownMenuContent, { align: 'end' }, {
            default: () => [
              h(DropdownMenuItem, { onClick: () => openEdit(bank) }, () => 'تعديل'),
            ],
          }),
        ],
      })
    },
  },
]

const table = useVueTable({
  get data() { return filteredBanks.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onRowSelectionChange: updater =>
    rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater,
  state: {
    get rowSelection() { return rowSelection.value },
  },
  initialState: { pagination: { pageSize: 20 } },
})

onMounted(loadBanks)
</script>

<template>
  <div class="flex flex-col gap-6">
    <PageHeader
      :title="isBankAdmin ? 'بيانات البنك' : 'إدارة البنوك'"
      :subtitle="isBankAdmin ? 'عرض معلومات بنكك' : 'إدارة قائمة البنوك التجارية المتاحة على المنصة'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: isBankAdmin ? 'بيانات البنك' : 'إدارة البنوك' }]"
    >
      <template v-if="!isBankAdmin" #actions>
        <Button size="sm" class="h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">إضافة بنك</span>
        </Button>
      </template>
    </PageHeader>

    <!-- Error State -->
    <Alert v-if="error" variant="destructive">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <!-- Toolbar: bulk (when selected) OR search (default) -->
    <div v-if="selectedCount > 0" class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
      <span class="text-sm font-medium text-primary">{{ selectedCount }} محدد</span>
      <div class="mx-2 h-4 w-px bg-border" />
      <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
        <Download class="h-3.5 w-3.5" />
        تصدير
      </Button>
      <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
        <Printer class="h-3.5 w-3.5" />
        طباعة
      </Button>
      <Button
        variant="ghost"
        size="sm"
        class="ms-auto h-7 gap-1 text-xs text-muted-foreground"
        @click="clearSelection"
      >
        <X class="h-3.5 w-3.5" />
        إلغاء التحديد
      </Button>
    </div>

    <div v-else class="flex items-center gap-2">
      <div class="relative min-w-[220px] flex-1">
        <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          placeholder="بحث بالاسم أو الرمز..."
          class="h-8 rounded-md pe-9 text-sm"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4 overflow-auto">
      <div v-if="loading || table.getRowModel().rows.length > 0" class="overflow-hidden rounded-lg border">
        <Table>
          <TableHeader class="bg-muted sticky top-0 z-10">
            <TableRow
              v-for="headerGroup in table.getHeaderGroups()"
              :key="headerGroup.id"
              class="hover:bg-transparent"
            >
              <TableHead
                v-for="header in headerGroup.headers"
                :key="header.id"
                class="h-10 px-4 text-sm font-medium text-foreground"
              >
                <FlexRender
                  v-if="!header.isPlaceholder"
                  :render="header.column.columnDef.header"
                  :props="header.getContext()"
                />
              </TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <template v-if="loading">
              <TableRow v-for="i in 6" :key="i">
                <TableCell class="px-4 py-3">
                  <div class="flex flex-col gap-1.5">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-3 w-36" />
                  </div>
                </TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-5 w-12 rounded" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-4 w-16" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
              </TableRow>
            </template>

            <template>
              <TableRow
                v-for="row in table.getRowModel().rows"
                :key="row.id"
                class="transition-colors hover:bg-muted/30"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="px-4 py-3 align-middle"
                >
                  <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                </TableCell>
              </TableRow>
            </template>
          </TableBody>
        </Table>
      </div>

      <!-- Empty state (outside table) -->
      <Empty
        v-if="!loading && !table.getRowModel().rows.length"
        class="min-h-[280px] rounded-xl border border-dashed bg-muted/20"
      >
        <EmptyHeader>
          <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <SearchX class="size-5" />
          </div>
          <EmptyTitle>لا توجد نتائج</EmptyTitle>
        </EmptyHeader>
        <EmptyContent>
          <EmptyDescription>جرّب تغيير البحث لعرض البنوك.</EmptyDescription>
        </EmptyContent>
      </Empty>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-2">
        <p class="text-sm text-muted-foreground">
          {{ table.getFilteredSelectedRowModel().rows.length }} من {{ table.getFilteredRowModel().rows.length }} بنك محدد
        </p>
        <div class="flex items-center gap-4">
          <p class="text-sm font-medium whitespace-nowrap">
            صفحة {{ table.getState().pagination.pageIndex + 1 }} من {{ table.getPageCount() }}
          </p>
          <div class="flex items-center gap-1">
            <Button
              variant="outline" size="icon" class="hidden h-8 w-8 lg:flex"
              :disabled="!table.getCanPreviousPage()" @click="table.setPageIndex(0)"
            >
              <span class="sr-only">الصفحة الأولى</span>
              <ChevronsRight class="h-4 w-4" />
            </Button>
            <Button
              variant="outline" size="icon" class="h-8 w-8"
              :disabled="!table.getCanPreviousPage()" @click="table.previousPage()"
            >
              <span class="sr-only">الصفحة السابقة</span>
              <ChevronRight class="h-4 w-4" />
            </Button>
            <Button
              variant="outline" size="icon" class="h-8 w-8"
              :disabled="!table.getCanNextPage()" @click="table.nextPage()"
            >
              <span class="sr-only">الصفحة التالية</span>
              <ChevronLeft class="h-4 w-4" />
            </Button>
            <Button
              variant="outline" size="icon" class="hidden h-8 w-8 lg:flex"
              :disabled="!table.getCanNextPage()" @click="table.setPageIndex(table.getPageCount() - 1)"
            >
              <span class="sr-only">الصفحة الأخيرة</span>
              <ChevronsLeft class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Dialog Modal -->
    <Dialog v-model:open="showModal" @update:open="(open) => !open && closeModal()">
      <DialogContent class="sm:max-w-[420px]" dir="rtl">
        <DialogHeader>
          <DialogTitle>{{ editingBank ? 'تعديل بيانات البنك' : 'إضافة بنك جديد' }}</DialogTitle>
        </DialogHeader>

        <Alert v-if="formError" variant="destructive">
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription>{{ formError }}</AlertDescription>
        </Alert>

        <div class="flex flex-col gap-4">
          <Field :data-invalid="!!formErrors.name_ar">
            <FieldLabel>الاسم بالعربية <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.name_ar" :aria-invalid="!!formErrors.name_ar" placeholder="مثال: البنك التجاري اليمني" type="text" />
            <FieldDescription v-if="formErrors.name_ar" class="text-destructive">{{ formErrors.name_ar }}</FieldDescription>
          </Field>

          <Field v-if="!isBankAdmin" :data-invalid="!!formErrors.name_en">
            <FieldLabel>الاسم بالإنجليزية <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.name_en" :aria-invalid="!!formErrors.name_en" placeholder="e.g. Yemen Commercial Bank" type="text" />
            <FieldDescription v-if="formErrors.name_en" class="text-destructive">{{ formErrors.name_en }}</FieldDescription>
          </Field>

          <Field v-if="!isBankAdmin" :data-invalid="!!formErrors.code">
            <FieldLabel>الرمز <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.code" :aria-invalid="!!formErrors.code" placeholder="مثال: YCB" type="text" maxlength="20" />
            <FieldDescription v-if="formErrors.code" class="text-destructive">{{ formErrors.code }}</FieldDescription>
          </Field>

          <Field v-if="!isBankAdmin" class="flex items-center gap-3">
            <FieldLabel class="flex-1">نشط</FieldLabel>
            <Switch v-model:checked="form.is_active" />
          </Field>
        </div>

        <DialogFooter class="gap-2">
          <Button variant="outline" :disabled="saving" @click="closeModal">إلغاء</Button>
          <Button :disabled="saving" @click="saveBank">{{ saving ? 'جارٍ الحفظ…' : 'حفظ' }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
