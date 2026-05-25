<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { h } from 'vue'
import {
  Building2,
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  Download, Edit, MoreHorizontal, Plus, Printer, Search, SearchX, X,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import MerchantDialog from '@/components/merchants/MerchantDialog.vue'
import type { MerchantFormData } from '@/components/merchants/MerchantDialog.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useMerchants } from '@/composables/useMerchants'
import { useBanks } from '@/composables/useBanks'
import { UserRole } from '@/types/enums'
import type { Merchant } from '@/types/models'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
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

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchMerchants, createMerchant, updateMerchant, suspendMerchant } = useMerchants()
const { fetchBanks } = useBanks()
const { notify } = useToast()

const merchants = ref<Merchant[]>([])
const banks = ref<import('@/types/models').Bank[]>([])
const loadingMerchants = ref(false)
const query = ref('')
const statusFilter = ref<'all' | 'active' | 'suspended'>('all')
const bankFilter = ref<number | 'all'>('all')
const createOpen = ref(false)
const editing = ref<Merchant | null>(null)
const viewing = ref<Merchant | null>(null)

onMounted(async () => {
  loadingMerchants.value = true
  const [merchantsResult, banksResult] = await Promise.allSettled([
    fetchMerchants(),
    fetchBanks(),
  ])
  if (merchantsResult.status === 'fulfilled') merchants.value = merchantsResult.value
  if (banksResult.status === 'fulfilled') banks.value = banksResult.value
  loadingMerchants.value = false
})

const isCbyAdmin = computed(() => user.value?.role === UserRole.CBY_ADMIN)
const isBankAdmin = computed(() => user.value?.role === UserRole.BANK_ADMIN)
const canManage = computed(() => isBankAdmin.value || isCbyAdmin.value)

function bankName(id?: number | null) {
  return banks.value.find(b => b.id === id)?.name_ar ?? '—'
}

const scoped = computed(() => {
  if (isBankAdmin.value && user.value?.bank_id) {
    return merchants.value.filter(m => m.bank_id === user.value?.bank_id)
  }
  return merchants.value
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return scoped.value.filter((m) => {
    if (statusFilter.value !== 'all' && (statusFilter.value === 'active') !== m.is_active) return false
    if (isCbyAdmin.value && bankFilter.value !== 'all' && m.bank_id !== bankFilter.value) return false
    if (!q) return true
    return [m.name, m.commercial_register, m.tax_number, bankName(m.bank_id)].some(v => (v ?? '').toLowerCase().includes(q))
  })
})

const stats = computed(() => ({
  total: scoped.value.length,
  active: scoped.value.filter(m => m.is_active).length,
  suspended: scoped.value.filter(m => !m.is_active).length,
}))

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

async function saveNew(data: MerchantFormData) {
  const created = await createMerchant({ ...data, bank_id: data.bank_id ?? undefined })
  merchants.value = [created, ...merchants.value]
  createOpen.value = false
  notify(`تم تسجيل التاجر "${created.name}"`)
}

async function saveEdit(data: MerchantFormData) {
  if (!editing.value) return
  const updated = await updateMerchant(editing.value.id, data)
  merchants.value = merchants.value.map(m => m.id === updated.id ? updated : m)
  editing.value = null
  notify('تم تحديث بيانات التاجر')
}

const rowSelection = ref<Record<string, boolean>>({})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearSelection() {
  table.resetRowSelection()
}

async function toggleStatus(merchant: Merchant) {
  const updated = await suspendMerchant(merchant.id, !merchant.is_active)
  merchants.value = merchants.value.map(m => m.id === updated.id ? updated : m)
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
    h('svg', {
      class: 'shrink-0', style: { color }, width: 15, height: 15,
      viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
      'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
    }, paths),
    h('span', { class: 'text-sm font-medium text-foreground' }, label),
  ])
}

const columns: ColumnDef<Merchant>[] = [
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
          'aria-label': 'تحديد التاجر',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'name',
    header: 'التاجر',
    cell: ({ row }) => h('span', { class: 'text-sm font-medium' }, row.original.name),
  },
  {
    accessorKey: 'commercial_register',
    header: 'السجل التجاري',
    cell: ({ row }) => h('span', { class: 'text-sm text-muted-foreground' }, row.original.commercial_register ?? '—'),
  },
  {
    accessorKey: 'tax_number',
    header: 'الرقم الضريبي',
    cell: ({ row }) => h('span', { class: 'text-sm tabular-nums text-muted-foreground' }, row.original.tax_number ?? '—'),
  },
  {
    accessorKey: 'business_type',
    header: 'القطاع',
    cell: ({ row }) => h('span', { class: 'text-sm text-muted-foreground' }, row.original.business_type ?? '—'),
  },
  {
    id: 'bank',
    header: 'البنك التابع له',
    cell: ({ row }) => h(Badge, { variant: 'outline', class: 'font-normal' }, () => [
      h(Building2, { class: 'ms-1 h-3 w-3' }),
      bankName(row.original.bank_id),
    ]),
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'transactions',
    header: 'المعاملات',
    cell: ({ row }) => h('span', { class: 'text-sm font-semibold tabular-nums' }, String(row.original.transaction_count ?? 0)),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const merchant = row.original
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
              h(DropdownMenuItem, { onClick: () => (viewing.value = merchant) }, () => 'عرض'),
              h(DropdownMenuItem, { onClick: () => (editing.value = merchant) }, () => 'تعديل'),
              h(DropdownMenuSeparator),
              h(DropdownMenuItem, {
                class: merchant.is_active ? 'text-destructive' : 'text-green-700',
                onClick: () => toggleStatus(merchant),
              }, () => merchant.is_active ? 'إيقاف' : 'تفعيل'),
            ],
          }),
        ],
      })
    },
  },
]

const table = useVueTable({
  get data() { return filtered.value },
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
</script>

<template>
  <div v-if="user && canManage">
    <PageHeader
      title="إدارة التجار"
      :subtitle="isCbyAdmin ? 'عرض جميع التجار المسجّلين على المنصّة مع البنوك التابعة لها' : 'تسجيل ومتابعة التجار والمستوردين المرتبطين بالبنك'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التجار' }]"
    >
      <template #actions>
        <Button size="sm" class="h-8" @click="createOpen = true">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">تاجر جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards -->
    <div class="mb-6 grid grid-cols-3 gap-3">
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.total }}</div>
        <div class="text-xs text-muted-foreground">إجمالي</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-green-50/10 text-green-700">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.active }}</div>
        <div class="text-xs text-muted-foreground">نشط</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-red-700/10 text-red-700">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.suspended }}</div>
        <div class="text-xs text-muted-foreground">موقوف</div>
      </Card>
    </div>

    <!-- Toolbar: bulk (when selected in table view) OR search + filters (default) -->
    <div v-if="isCbyAdmin && selectedCount > 0" class="mb-4 flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
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

    <div v-else class="mb-4 flex flex-wrap items-center gap-2">
      <div class="relative min-w-[220px] flex-1">
        <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          placeholder="بحث برقم السجل، الرقم الضريبي، أو الاسم..."
          class="h-8 rounded-md pe-9 text-sm"
        />
      </div>

      <Select
        v-if="isCbyAdmin"
        :model-value="bankFilter === 'all' ? 'all' : bankFilter.toString()"
        @update:model-value="v => bankFilter = v === 'all' ? 'all' : Number(v)"
      >
        <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-48">
          <SelectValue placeholder="البنك" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">كل البنوك</SelectItem>
          <SelectItem v-for="bank in banks" :key="bank.id" :value="bank.id.toString()">{{ bank.name_ar }}</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="statusFilter">
        <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-40">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">كل الحالات</SelectItem>
          <SelectItem value="active">نشط فقط</SelectItem>
          <SelectItem value="suspended">موقوف فقط</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- CBY Admin: tanstack table view -->
    <template v-if="isCbyAdmin">
      <div class="relative flex flex-col gap-4 overflow-auto">
        <div v-if="loadingMerchants || table.getRowModel().rows.length > 0" class="overflow-hidden rounded-lg border">
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
              <template v-if="loadingMerchants">
                <TableRow v-for="i in 8" :key="`skel-${i}`">
                  <TableCell v-for="col in columns" :key="col.id ?? (col as any).accessorKey" class="px-4 py-3">
                    <Skeleton class="h-4 w-full" />
                  </TableCell>
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
          v-if="!loadingMerchants && !table.getRowModel().rows.length"
          class="min-h-[280px] rounded-xl border border-dashed bg-muted/20"
        >
          <EmptyHeader>
            <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
              <SearchX class="size-5" />
            </div>
            <EmptyTitle>لا توجد نتائج مطابقة</EmptyTitle>
          </EmptyHeader>
          <EmptyContent>
            <EmptyDescription>جرّب تغيير البحث أو الفلاتر.</EmptyDescription>
          </EmptyContent>
        </Empty>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-2">
          <p class="text-sm text-muted-foreground">{{ table.getFilteredSelectedRowModel().rows.length }} من {{ table.getFilteredRowModel().rows.length }} تاجر محدد</p>
          <div class="flex items-center gap-4">
            <p class="text-sm font-medium whitespace-nowrap">
              صفحة {{ table.getState().pagination.pageIndex + 1 }} من {{ table.getPageCount() }}
            </p>
            <div class="flex items-center gap-1">
              <Button variant="outline" size="icon" class="hidden h-8 w-8 lg:flex" :disabled="!table.getCanPreviousPage()" @click="table.setPageIndex(0)">
                <span class="sr-only">الصفحة الأولى</span><ChevronsRight class="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" class="h-8 w-8" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">
                <span class="sr-only">الصفحة السابقة</span><ChevronRight class="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" class="h-8 w-8" :disabled="!table.getCanNextPage()" @click="table.nextPage()">
                <span class="sr-only">الصفحة التالية</span><ChevronLeft class="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" class="hidden h-8 w-8 lg:flex" :disabled="!table.getCanNextPage()" @click="table.setPageIndex(table.getPageCount() - 1)">
                <span class="sr-only">الصفحة الأخيرة</span><ChevronsLeft class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
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
            <Empty class="min-h-[240px] rounded-xl border border-dashed bg-muted/20">
              <EmptyHeader>
                <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                  <SearchX class="size-5" />
                </div>
                <EmptyTitle>
                  {{ merchants.length === 0 ? 'لا يوجد تجار مسجّلون بعد' : 'لا توجد نتائج مطابقة' }}
                </EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{ merchants.length === 0 ? 'ابدأ بتسجيل أول تاجر باستخدام الزر أعلاه.' : 'جرّب تغيير البحث أو الفلاتر.' }}
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
            class="flex flex-col border-0 p-5 shadow transition-shadow hover:shadow-soft"
          >
            <div class="mb-3 flex items-start justify-between">
              <div class="grid h-12 w-12 place-items-center rounded-xl bg-primary text-primary-foreground">
                <Building2 class="h-6 w-6" />
              </div>
              <Badge :class="merchant.is_active ? 'border-0 bg-green-50/15 text-green-700' : 'border-0 bg-red-700/15 text-red-700'">
                {{ merchant.is_active ? 'نشط' : 'موقوف' }}
              </Badge>
            </div>
            <div class="text-base font-semibold">{{ merchant.name }}</div>
            <div class="text-xs text-muted-foreground">{{ merchant.business_type ?? '—' }}</div>
            <div class="mt-4 space-y-1.5 text-xs">
              <div class="flex justify-between gap-2">
                <span class="text-muted-foreground">السجل التجاري</span>
                <span class="font-medium">{{ merchant.commercial_register ?? '—' }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-muted-foreground">الرقم الضريبي</span>
                <span class="font-medium">{{ merchant.tax_number ?? '—' }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-muted-foreground">البنك</span>
                <span class="font-medium">{{ bankName(merchant.bank_id) }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-muted-foreground">العنوان</span>
                <span class="text-end font-medium">{{ merchant.address ?? '—' }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-muted-foreground">هاتف</span>
                <span class="font-medium">{{ merchant.phone ?? '—' }}</span>
              </div>
            </div>
            <div class="mt-auto flex items-center justify-between border-t pt-4">
              <div class="text-xs">
                <span class="text-muted-foreground">المعاملات: </span>
                <span class="font-bold tabular-nums">{{ merchant.transaction_count ?? 0 }}</span>
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

    <Dialog :open="Boolean(editing)" @update:open="v => !v && (editing = null)">
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

    <Dialog :open="Boolean(viewing)" @update:open="v => !v && (viewing = null)">
      <DialogContent v-if="viewing" class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            {{ viewing.name }}
          </DialogTitle>
          <DialogDescription>تفاصيل التاجر — عرض فقط</DialogDescription>
        </DialogHeader>
        <div class="grid gap-3 py-2 text-sm sm:grid-cols-2">
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">السجل التجاري</div>
            <div class="font-medium">{{ viewing.commercial_register ?? '—' }}</div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">الرقم الضريبي</div>
            <div class="font-medium">{{ viewing.tax_number ?? '—' }}</div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">القطاع</div>
            <div class="font-medium">{{ viewing.business_type ?? '—' }}</div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">الحالة</div>
            <div class="font-medium">{{ viewing.is_active ? 'نشط' : 'موقوف' }}</div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">البنك التابع له</div>
            <div class="font-medium">{{ bankName(viewing.bank_id) }}</div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">عدد المعاملات</div>
            <div class="font-medium">{{ viewing.transaction_count ?? 0 }}</div>
          </div>
          <div class="space-y-0.5 sm:col-span-2">
            <div class="text-xs text-muted-foreground">العنوان</div>
            <div class="font-medium">{{ viewing.address ?? '—' }}</div>
          </div>
          <div class="space-y-0.5 sm:col-span-2">
            <div class="text-xs text-muted-foreground">هاتف التواصل</div>
            <div class="font-medium">{{ viewing.phone ?? '—' }}</div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>

  <div v-else>
    <PageHeader
      title="إدارة التجار"
      subtitle="هذه الصفحة متاحة لمسؤول النظام أو مسؤول البنك فقط."
    />
    <Card class="border-0 p-6 shadow">
      <div class="text-sm text-muted-foreground">لا تملك صلاحية إدارة التجار.</div>
    </Card>
  </div>
</template>
