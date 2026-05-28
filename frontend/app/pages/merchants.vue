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
  AlertTriangle, Building2,
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  Download, Edit, ExternalLink, MoreHorizontal, Plus, Printer, Search, SearchX, Shield, X,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import MerchantDialog from '@/components/merchants/MerchantDialog.vue'
import type { MerchantFormData } from '@/components/merchants/MerchantDialog.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useMerchants } from '@/composables/useMerchants'
import { useBanks } from '@/composables/useBanks'
import { UserRole } from '@/types/enums'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
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

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/merchants'],
})

const router = useRouter()
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
  try {
    const [merchantsResult, banksResult] = await Promise.allSettled([
      fetchMerchants(),
      fetchBanks(),
    ])
    if (merchantsResult.status === 'fulfilled') merchants.value = merchantsResult.value ?? []
    if (banksResult.status === 'fulfilled') banks.value = banksResult.value ?? []
  }
  finally {
    loadingMerchants.value = false
  }
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
    if (isCbyAdmin.value && merchantTab.value === 'cross_bank' && !crossBankNames.value.has(m.name.trim().toLowerCase())) return false
    if (isCbyAdmin.value && merchantTab.value === 'missing_data' && m.commercial_register && m.tax_number) return false
    if (isCbyAdmin.value && merchantTab.value === 'inactive' && m.is_active) return false
    if (!q) return true
    return [m.name, m.commercial_register, m.tax_number, bankName(m.bank_id)].some(v => (v ?? '').toLowerCase().includes(q))
  })
})

const merchantTab = ref<'all' | 'cross_bank' | 'missing_data' | 'inactive'>('all')

const stats = computed(() => ({
  total: scoped.value.length,
  active: scoped.value.filter(m => m.is_active).length,
  suspended: scoped.value.filter(m => !m.is_active).length,
  incomplete: scoped.value.filter(m => !m.commercial_register || !m.tax_number).length,
}))

// CBY-Admin risk intelligence computeds
const crossBankNames = computed(() => {
  if (!isCbyAdmin.value) return new Set<string>()
  const nameCount: Record<string, number> = {}
  for (const m of merchants.value) {
    const key = m.name.trim().toLowerCase()
    nameCount[key] = (nameCount[key] ?? 0) + 1
  }
  return new Set(Object.entries(nameCount).filter(([, c]) => c > 1).map(([n]) => n))
})

const riskSummary = computed(() => {
  if (!isCbyAdmin.value) return null
  return {
    crossBank: merchants.value.filter(m => crossBankNames.value.has(m.name.trim().toLowerCase())).length,
    missingData: merchants.value.filter(m => !m.commercial_register || !m.tax_number).length,
    inactive: merchants.value.filter(m => !m.is_active).length,
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

// Duplicate confirmation state — holds pending form data until user confirms
const duplicateWarningOpen = ref(false)
const duplicateWarningReasons = ref<string[]>([])
const pendingNewMerchant = ref<MerchantFormData | null>(null)

function detectDuplicates(data: MerchantFormData): string[] {
  const bankId = data.bank_id ?? user.value?.bank_id ?? null
  const scopedMerchants = bankId
    ? merchants.value.filter(m => m.bank_id === bankId)
    : merchants.value
  const reasons: string[] = []
  const nameLower = data.name.trim().toLowerCase()
  if (scopedMerchants.some(m => m.name.trim().toLowerCase() === nameLower)) {
    reasons.push(`اسم التاجر "${data.name}" مسجّل مسبقاً لدى هذا البنك`)
  }
  if (data.commercial_register && scopedMerchants.some(m => m.commercial_register === data.commercial_register.trim())) {
    reasons.push(`رقم السجل التجاري "${data.commercial_register}" مسجّل مسبقاً`)
  }
  if (data.tax_number && scopedMerchants.some(m => m.tax_number === data.tax_number.trim())) {
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

function openEditFromView() {
  if (viewing.value) {
    editing.value = viewing.value
    viewing.value = null
  }
}

function toggleFromView() {
  if (viewing.value) {
    toggleStatus(viewing.value)
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
    cell: ({ row }) => h('button', {
      type: 'button',
      class: 'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
      title: 'معاينة سريعة',
      onClick: (e: Event) => { e.stopPropagation(); viewing.value = row.original },
    }, row.original.name),
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
      const roleItems = isBankAdmin.value
        ? [h(DropdownMenuItem, {
            class: 'gap-1.5 text-primary',
            onClick: () => router.push('/requests/new'),
          }, () => [h(ExternalLink, { class: 'h-3.5 w-3.5' }), 'إنشاء طلب تمويل'])]
        : (isCbyAdmin.value && crossBankNames.value.has(merchant.name.trim().toLowerCase()))
            ? [h(DropdownMenuItem, {
                class: 'gap-1.5 text-[var(--severity-amber)]',
                onClick: () => { merchantTab.value = 'cross_bank' },
              }, () => [h(AlertTriangle, { class: 'h-3.5 w-3.5' }), 'عرض مخاطر التكرار'])]
            : []
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
              h(DropdownMenuItem, { onClick: () => (viewing.value = merchant) }, () => 'عرض التفاصيل'),
              h(DropdownMenuItem, { onClick: () => (editing.value = merchant) }, () => 'تعديل'),
              ...roleItems,
              h(DropdownMenuSeparator),
              h(DropdownMenuItem, {
                class: merchant.is_active ? 'text-destructive' : 'text-[var(--severity-green)]',
                onClick: () => toggleStatus(merchant),
              }, () => merchant.is_active ? 'إيقاف النشاط' : 'تفعيل'),
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
    <div class="mb-6 grid grid-cols-4 max-lg:grid-cols-2 gap-3">
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.total }}</div>
        <div class="text-xs text-muted-foreground">إجمالي</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--severity-green)]/10 text-[var(--severity-green)]">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.active }}</div>
        <div class="text-xs text-muted-foreground">نشط</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--severity-red)]/10 text-[var(--severity-red)]">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.suspended }}</div>
        <div class="text-xs text-muted-foreground">موقوف</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.incomplete }}</div>
        <div class="text-xs text-muted-foreground">سجلات ناقصة</div>
      </Card>
    </div>

    <!-- CBY Admin: Smart summary bar -->
    <div v-if="isCbyAdmin && riskSummary" class="mb-4 space-y-2">
      <Card
        v-if="riskSummary.crossBank > 0"
        class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4 py-3">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ riskSummary.crossBank }} تاجر يظهر في أكثر من بنك — مراجعة مخاطر التكرار مطلوبة
          </span>
          <Button size="sm" variant="ghost" class="h-7 text-xs" @click="merchantTab = 'cross_bank'">
            عرض
          </Button>
        </div>
      </Card>
      <Card
        v-if="riskSummary.missingData > 0"
        class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <div class="flex items-center gap-3 px-4 py-3">
          <AlertTriangle class="h-4 w-4 shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <span class="flex-1 text-sm font-medium">
            {{ riskSummary.missingData }} تاجر ببيانات ناقصة (سجل تجاري أو رقم ضريبي)
          </span>
          <Button size="sm" variant="ghost" class="h-7 text-xs" @click="merchantTab = 'missing_data'">
            عرض
          </Button>
        </div>
      </Card>
    </div>

    <!-- CBY Admin: Risk tabs -->
    <div v-if="isCbyAdmin" class="mb-4">
      <Tabs :model-value="merchantTab" dir="rtl" @update:model-value="v => merchantTab = v as typeof merchantTab.value">
        <TabsList class="h-auto gap-1 rounded-full bg-muted p-1">
          <TabsTrigger value="all" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            الكل
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ scoped.length }}</Badge>
          </TabsTrigger>
          <TabsTrigger value="cross_bank" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            متعدد البنوك
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ riskSummary?.crossBank ?? 0 }}</Badge>
          </TabsTrigger>
          <TabsTrigger value="missing_data" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            بيانات ناقصة
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ stats.incomplete }}</Badge>
          </TabsTrigger>
          <TabsTrigger value="inactive" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            غير نشط
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ stats.suspended }}</Badge>
          </TabsTrigger>
        </TabsList>
      </Tabs>
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
        <Search class="absolute inset-e-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
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

      <Tabs v-model="statusFilter" dir="rtl" class="shrink-0">
        <TabsList class="h-auto gap-1 rounded-full bg-muted p-1">
          <TabsTrigger value="all" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            الكل
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ stats.total }}</Badge>
          </TabsTrigger>
          <TabsTrigger value="active" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            نشط
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ stats.active }}</Badge>
          </TabsTrigger>
          <TabsTrigger value="suspended" class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            موقوف
            <Badge variant="secondary" class="h-5 min-w-5 rounded-full px-1 text-xs">{{ stats.suspended }}</Badge>
          </TabsTrigger>
        </TabsList>
      </Tabs>
    </div>

    <!-- CBY Admin: tanstack table view -->
    <template v-if="isCbyAdmin">
      <div class="relative flex flex-col gap-4">
        <div v-if="loadingMerchants || table.getRowModel().rows.length > 0" class="rounded-lg border overflow-x-auto">
          <Table class="min-w-max w-full">
            <TableHeader class="bg-muted sticky top-0 z-30">
              <TableRow
                v-for="headerGroup in table.getHeaderGroups()"
                :key="headerGroup.id"
                class="hover:bg-transparent"
              >
                <TableHead
                  v-for="header in headerGroup.headers"
                  :key="header.id"
                  class="h-10 text-sm font-medium text-foreground"
                  :class="header.column.id === 'actions'
                    ? 'sticky end-0 z-20 bg-muted w-12 px-2'
                    : 'px-4'"
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

              <template v-else>
                <TableRow
                  v-for="row in table.getRowModel().rows"
                  :key="row.id"
                  class="group/row transition-colors hover:bg-muted/30"
                >
                  <TableCell
                    v-for="cell in row.getVisibleCells()"
                    :key="cell.id"
                    class="py-3 align-middle"
                    :class="cell.column.id === 'actions'
                      ? 'sticky end-0 z-10 bg-background w-12 px-2 group-hover/row:bg-muted/30'
                      : 'px-4'"
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

    <!-- Unified quick-view Dialog (both roles) -->
    <Dialog :open="Boolean(viewing)" @update:open="v => !v && (viewing = null)">
      <DialogContent v-if="viewing" dir="rtl" :class="isCbyAdmin ? 'sm:max-w-2xl' : 'sm:max-w-lg'">
        <DialogHeader class="pb-3">
          <DialogTitle class="flex items-center gap-2 text-base">
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
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
              <Badge :class="viewing.is_active ? 'border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]' : 'border border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]'">
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
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">معلومات التسجيل</h3>
              <div class="grid grid-cols-2 gap-3 text-sm">
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
                  <div class="text-xs text-muted-foreground">عدد المعاملات</div>
                  <div class="font-bold tabular-nums">{{ viewing.transaction_count ?? 0 }}</div>
                </div>
                <div class="col-span-2 space-y-0.5">
                  <div class="text-xs text-muted-foreground">العنوان</div>
                  <div class="font-medium">{{ viewing.address ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-xs text-muted-foreground">هاتف</div>
                  <div class="font-medium">{{ viewing.phone ?? '—' }}</div>
                </div>
                <div class="space-y-0.5">
                  <div class="text-xs text-muted-foreground">البريد</div>
                  <div class="font-medium">{{ viewing.email ?? '—' }}</div>
                </div>
              </div>
            </Card>

            <!-- Associated banks -->
            <Card class="border p-4">
              <h3 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
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
                    v-for="other in merchants.filter(m => m.id !== viewing?.id && m.name.trim().toLowerCase() === viewing?.name.trim().toLowerCase())"
                    :key="other.id"
                    class="flex items-center justify-between text-[var(--severity-amber)]"
                  >
                    <span class="font-medium">{{ bankName(other.bank_id) }}</span>
                    <Badge class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)] text-xs">مكرر</Badge>
                  </div>
                </template>
              </div>
            </Card>
          </div>

          <!-- CBY Admin quick actions -->
          <DialogFooter class="gap-2 border-t pt-4">
            <Button
              variant="outline"
              size="sm"
              :class="viewing.is_active
                ? 'text-destructive border-destructive/30 hover:bg-destructive/5'
                : 'text-[var(--severity-green)] border-[var(--severity-green)]/30 hover:bg-[var(--severity-green)]/5'"
              @click="toggleFromView"
            >
              {{ viewing.is_active ? 'إيقاف النشاط' : 'تفعيل' }}
            </Button>
            <Button size="sm" @click="openEditFromView">
              <Edit class="h-3.5 w-3.5 me-1.5" />
              تعديل البيانات
            </Button>
          </DialogFooter>
        </template>

        <!-- Bank Admin: simple profile + quick actions -->
        <template v-else>
          <div class="grid gap-3 py-1 text-sm sm:grid-cols-2">
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
              <Badge :class="viewing.is_active ? 'bg-[var(--severity-green)]/10 text-[var(--severity-green)] border-[var(--severity-green)]/30 border' : 'bg-[var(--severity-red)]/10 text-[var(--severity-red)] border-[var(--severity-red)]/30 border'">
                {{ viewing.is_active ? 'نشط' : 'موقوف' }}
              </Badge>
            </div>
            <div class="space-y-0.5">
              <div class="text-xs text-muted-foreground">البنك التابع له</div>
              <div class="font-medium">{{ bankName(viewing.bank_id) }}</div>
            </div>
            <div class="space-y-0.5">
              <div class="text-xs text-muted-foreground">عدد المعاملات</div>
              <div class="font-semibold tabular-nums">{{ viewing.transaction_count ?? 0 }}</div>
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

          <!-- Bank Admin quick actions -->
          <DialogFooter class="gap-2 border-t pt-4">
            <Button variant="outline" size="sm" @click="openEditFromView">
              <Edit class="h-3.5 w-3.5 me-1.5" />
              تعديل
            </Button>
            <Button size="sm" @click="() => { router.push('/requests/new'); viewing = null }">
              <Plus class="h-3.5 w-3.5 me-1.5" />
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
            <ul class="list-disc ps-4 text-xs text-foreground space-y-1">
              <li v-for="reason in duplicateWarningReasons" :key="reason">{{ reason }}</li>
            </ul>
            <p class="text-xs text-muted-foreground">
              يمكنك إلغاء العملية ومراجعة البيانات، أو تأكيد الإضافة إذا كان التاجر مختلفاً فعلاً.
            </p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="cancelDuplicateSave">
            إلغاء — مراجعة البيانات
          </AlertDialogCancel>
          <AlertDialogAction
            class="bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
            data-testid="duplicate-confirm-btn"
            @click="confirmDuplicateAndSave"
          >
            تأكيد الإضافة رغم التشابه
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
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
