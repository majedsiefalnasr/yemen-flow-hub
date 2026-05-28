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
  MoreHorizontal, Plus, Search, SearchX,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { Bank } from '@/types/models'
import { useBanks, type CreateBankPayload, type UpdateBankPayload } from '@/composables/useBanks'
import { useAuthStore } from '@/stores/auth.store'
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

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/entities'],
})

type EntityForm = {
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
const { notify, error: toastError } = useToast()

const query = ref('')
const createOpen = ref(false)
const editing = ref<Bank | null>(null)
const viewing = ref<Bank | null>(null)
const saving = ref(false)
const banks = ref<Bank[]>([])
const loadingBanks = ref(false)

const form = reactive<EntityForm>({
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
  }
  finally {
    loadingBanks.value = false
  }
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return banks.value
  return banks.value.filter(b =>
    b.name_ar.toLowerCase().includes(q)
    || b.name_en.toLowerCase().includes(q)
    || (b.license_number ?? '').toLowerCase().includes(q)
    || b.code.toLowerCase().includes(q),
  )
})

const stats = computed(() => ({
  total: banks.value.length,
  active: banks.value.filter(b => b.is_active).length,
  inactive: banks.value.filter(b => !b.is_active).length,
}))

function bankInitials(nameAr: string): string {
  return nameAr.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('')
}

const emailValid = computed(() => !form.adminEmail.trim() || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.adminEmail.trim()))
const formValid = computed(() =>
  form.name_ar.trim().length > 0
  && form.code.trim().length > 0
  && emailValid.value
  && (Boolean(editing.value) || (form.adminName.trim().length > 0 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.adminEmail.trim()))),
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

async function saveEntity() {
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
      banks.value = banks.value.map(b => b.id === editing.value!.id ? updated : b)
      notify('تم حفظ التعديلات')
    }
    else {
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
  }
  catch {
    toastError('حدث خطأ، يرجى المحاولة مجدداً')
  }
  finally {
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
    banks.value = banks.value.map(b => b.id === bank.id ? updated : b)
    notify(updated.is_active ? `تم تفعيل ${bank.name_ar}` : `تم إيقاف ${bank.name_ar}`)
  }
  catch {
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
    id: 'entity',
    header: 'الجهة',
    cell: ({ row }) => {
      const bank = row.original
      return h('div', { class: 'flex items-center gap-2' }, [
        h('div', { class: 'bank-avatar grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary text-xs font-bold' }, bankInitials(bank.name_ar)),
        h('div', {}, [
          h('div', { class: 'text-sm font-medium' }, bank.name_ar),
          h('div', { class: 'text-xs text-muted-foreground' }, bank.name_en),
        ]),
      ])
    },
  },
  {
    accessorKey: 'license_number',
    header: 'رقم الترخيص',
    cell: ({ row }) => h('span', { class: 'font-mono text-xs text-muted-foreground' }, row.original.license_number ?? '—'),
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
    header: 'إجراءات',
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
              h(DropdownMenuItem, { onClick: () => (viewing.value = bank) }, () => 'عرض'),
              h(DropdownMenuItem, { onClick: () => openEdit(bank) }, () => 'تعديل'),
              h(DropdownMenuSeparator),
              h(DropdownMenuItem, {
                class: bank.is_active ? 'text-destructive' : 'text-green-700',
                onClick: () => toggleStatus(bank),
              }, () => bank.is_active ? 'إيقاف' : 'تفعيل'),
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
  initialState: { pagination: { pageSize: 20 } },
})
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
        <Button size="sm" class="h-8 btn-primary" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">بنك جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards -->
    <div class="mb-6 grid grid-cols-3 gap-3">
      <div class="rounded-lg border bg-card p-4 shadow">
        <div class="text-2xl font-bold tabular-nums">{{ stats.total }}</div>
        <div class="text-xs text-muted-foreground">إجمالي البنوك</div>
      </div>
      <div class="rounded-lg border bg-card p-4 shadow">
        <div class="text-2xl font-bold tabular-nums">{{ stats.active }}</div>
        <div class="text-xs text-muted-foreground">نشط</div>
      </div>
      <div class="rounded-lg border bg-card p-4 shadow">
        <div class="text-2xl font-bold tabular-nums">{{ stats.inactive }}</div>
        <div class="text-xs text-muted-foreground">غير نشط</div>
      </div>
    </div>

    <!-- Toolbar: search -->
    <div class="mb-4 flex items-center gap-2">
      <div class="relative min-w-[220px] flex-1 max-w-md">
        <Search class="absolute inset-e-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          class="h-8 rounded-md pe-9 text-sm"
          placeholder="بحث بالاسم أو رقم الترخيص أو الكود..."
        />
      </div>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4 overflow-auto">
      <div class="overflow-hidden rounded-lg border">
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
            <template v-if="loadingBanks">
              <TableRow v-for="i in 6" :key="i">
                <TableCell class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <Skeleton class="h-8 w-8 rounded-lg" />
                    <div class="flex flex-col gap-1.5">
                      <Skeleton class="h-4 w-40" />
                      <Skeleton class="h-3 w-32" />
                    </div>
                  </div>
                </TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-4 w-24" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-5 w-12 rounded" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-4 w-16" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
              </TableRow>
            </template>

            <TableRow v-else-if="!table.getRowModel().rows.length">
              <TableCell :col-span="columns.length" class="p-8">
                <Empty data-empty-state-variant="entities" class="min-h-[200px] rounded-xl border border-dashed bg-muted/20">
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
              </TableCell>
            </TableRow>

            <template v-else>
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

      <!-- Pagination -->
      <div class="flex items-center justify-between px-2">
        <p class="text-sm text-muted-foreground">{{ table.getFilteredRowModel().rows.length }} بنك</p>
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

    <!-- Create / Edit Dialog -->
    <Dialog
      :open="createOpen || Boolean(editing)"
      @update:open="value => !value && closeForm()"
    >
      <DialogContent dir="rtl" class="sm:max-w-md">
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
            <Input v-model="form.name_en" dir="ltr" />
          </div>
          <div class="space-y-1.5">
            <Label>كود البنك *</Label>
            <Input v-model="form.code" dir="ltr" placeholder="YBRD" />
          </div>
          <div class="space-y-1.5">
            <Label>رقم الترخيص</Label>
            <Input v-model="form.license_number" dir="ltr" placeholder="BNK-004" />
          </div>
          <div class="space-y-1.5">
            <Label>الحالة</Label>
            <div class="flex gap-2">
              <Button type="button" :variant="form.is_active ? 'default' : 'outline'" size="sm" @click="form.is_active = true">نشط</Button>
              <Button type="button" :variant="!form.is_active ? 'default' : 'outline'" size="sm" @click="form.is_active = false">موقوف</Button>
            </div>
          </div>

          <div v-if="!editing" class="mt-2 border-t pt-3">
            <div class="mb-1 text-sm font-semibold">حساب مدير البنك <span class="text-destructive">*</span></div>
            <p class="mb-3 text-xs text-muted-foreground">يُنشأ حساب المدير الأول للبنك تلقائياً ويُستخدم لتسجيل الدخول وإضافة باقي المستخدمين.</p>
            <div class="space-y-3">
              <div class="space-y-1.5">
                <Label>اسم المدير *</Label>
                <Input v-model="form.adminName" placeholder="مثال: محمد علي" />
              </div>
              <div class="space-y-1.5">
                <Label>البريد الإلكتروني للمدير *</Label>
                <Input v-model="form.adminEmail" type="email" dir="ltr" placeholder="admin@bank.ye" />
                <p v-if="!emailValid" class="text-xs text-destructive">صيغة البريد غير صحيحة</p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button :disabled="!formValid || saving" @click="saveEntity">
            {{ editing ? 'حفظ التعديلات' : 'إضافة' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- View Dialog -->
    <Dialog :open="Boolean(viewing)" @update:open="value => !value && (viewing = null)">
      <DialogContent v-if="viewing" dir="rtl" class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5 text-primary" />
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
          <div v-if="viewing.user_count != null" class="flex items-center justify-between border-b pb-2">
            <span class="text-muted-foreground">عدد المستخدمين</span>
            <span class="font-medium">{{ viewing.user_count }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
