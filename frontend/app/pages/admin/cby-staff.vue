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
import { h } from 'vue'
import {
  AlertCircle, AlertTriangle, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  Download, ExternalLink, MoreHorizontal, Plus, Printer, RefreshCw, Search, SearchX, ShieldCheck, UserCog, X,
} from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROLE_LABELS, CBY_ROLES, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { User } from '@/types/models'
import { useUsers, type CreateUserPayload, type UpdateUserPayload } from '@/composables/useUsers'
import { useBanks } from '@/composables/useBanks'
import { useTableExport } from '@/composables/useTableExport'
import { useTableKeyboard } from '@/composables/useTableKeyboard'
import type { Bank } from '@/types/models'
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
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Label } from '@/components/ui/label'
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
import { DataTableViewOptions } from '@/components/ui/data-table'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/cby-staff'],
})

type StaffForm = {
  name: string
  email: string
  role: UserRole
  password: string
}

const STAFF_ROLES: UserRole[] = CBY_ROLES

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)
const { fetchUsers, createUser, updateUser } = useUsers()
const { fetchBanks } = useBanks()
const { exportToCSV } = useTableExport()
const { notify, error: toastError } = useToast()

const query = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const roleFilter = ref<'all' | UserRole>('all')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')
const filterBank = ref<'all' | string>('all')
const createOpen = ref(false)
const editing = ref<User | null>(null)
const viewing = ref<User | null>(null)
const saving = ref(false)
const loading = ref(true)
const fetchError = ref(false)
const staffUsers = ref<User[]>([])
const deactivateTarget = ref<User | null>(null)
const deactivateBlocked = ref<string | null>(null)

const form = reactive<StaffForm>({
  name: '',
  email: '',
  role: UserRole.SUPPORT_COMMITTEE,
  password: '',
})

function resolveBankName(user: User): string {
  if (user.bank_name_ar) return user.bank_name_ar
  if (user.bank_name_en) return user.bank_name_en
  if (user.bank_name) return user.bank_name
  if (user.bank_id) {
    const b = banksData.value.find(bk => bk.id === user.bank_id)
    if (b) return b.name_ar || b.name_en || ''
  }
  return ''
}

async function loadStaff() {
  loading.value = true
  fetchError.value = false
  try {
    const [results, bankResults] = await Promise.all([
      fetchUsers({ per_page: 200 }),
      fetchBanks().catch(() => [] as Bank[]),
    ])
    staffUsers.value = results.filter(u => STAFF_ROLES.includes(u.role))
    banksData.value = bankResults
  }
  catch {
    fetchError.value = true
  }
  finally {
    loading.value = false
  }
}

onMounted(loadStaff)

const banksData = ref<Bank[]>([])

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return staffUsers.value
    .filter(u => roleFilter.value === 'all' || u.role === roleFilter.value)
    .filter(u => filterStatus.value === 'all' || (filterStatus.value === 'active' ? u.is_active : !u.is_active))
    .filter(u => filterBank.value === 'all' || String(u.bank_id) === filterBank.value)
    .filter(u => !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
})

const stats = computed(() => ({
  total: staffUsers.value.length,
  active: staffUsers.value.filter(u => u.is_active).length,
  inactive: staffUsers.value.filter(u => !u.is_active).length,
}))

const activeDirectors = computed(() =>
  staffUsers.value.filter(u => u.role === UserRole.COMMITTEE_DIRECTOR && u.is_active),
)
const activeExecutiveMembers = computed(() =>
  staffUsers.value.filter(u => u.role === UserRole.EXECUTIVE_MEMBER && u.is_active),
)

const rowSelection = ref<Record<string, boolean>>({})
const columnVisibility = ref<VisibilityState>({
  bank: false,
  last_seen: false,
})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

useTableKeyboard(searchInputRef, {
  onEscape: () => {
    query.value = ''
  },
})

function clearSelection() {
  table.resetRowSelection()
}

const formValid = computed(() =>
  form.name.trim().length > 0
  && /\S+@\S+\.\S+/.test(form.email)
  && (Boolean(editing.value) || form.password.length >= 8),
)

const roleSelectHint = computed(() => {
  if (form.role === UserRole.COMMITTEE_DIRECTOR) {
    return 'مدير اللجنة حصري — لا يمكن الجمع بين هذا الدور وعضوية اللجنة التنفيذية على نفس الحساب.'
  }
  if (form.role === UserRole.EXECUTIVE_MEMBER) {
    return 'عضو تنفيذي حصري — لا يمكن الجمع بين هذا الدور ومدير اللجنة على نفس الحساب.'
  }
  return null
})

function userInitials(name: string) {
  return name.split(' ').map(p => p[0]).join('').slice(0, 2)
}

function resetForm(initial?: User) {
  form.name = initial?.name ?? ''
  form.email = initial?.email ?? ''
  form.role = initial?.role ?? UserRole.SUPPORT_COMMITTEE
  form.password = ''
}

function openCreate() {
  editing.value = null
  resetForm()
  createOpen.value = true
}

function openEdit(target: User) {
  editing.value = target
  resetForm(target)
}

function closeForm() {
  createOpen.value = false
  editing.value = null
  resetForm()
}

async function saveStaff() {
  if (!formValid.value) return
  saving.value = true
  try {
    if (editing.value) {
      const payload: UpdateUserPayload = {
        name: form.name.trim(),
        email: form.email.trim(),
        role: form.role,
        bank_id: null,
        is_active: editing.value.is_active,
        ...(form.password ? { password: form.password } : {}),
      }
      const updated = await updateUser(editing.value.id, payload)
      staffUsers.value = staffUsers.value.map(u => u.id === editing.value!.id ? updated : u)
      notify('تم حفظ التعديلات')
    }
    else {
      const payload: CreateUserPayload = {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        role: form.role,
        bank_id: null,
        is_active: true,
      }
      const created = await createUser(payload)
      staffUsers.value = [...staffUsers.value, created]
      notify(`تمت إضافة ${created.name}`)
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

function requestToggleActive(target: User) {
  if (!currentUser.value || target.id === currentUser.value.id) return
  if (target.is_active) {
    // Guard: block deactivating last active Director
    if (target.role === UserRole.COMMITTEE_DIRECTOR && activeDirectors.value.length <= 1) {
      deactivateBlocked.value = 'لا يمكن إلغاء تفعيل المدير الوحيد النشط. يجب أن يكون هناك مدير لجنة نشط واحد على الأقل في النظام في جميع الأوقات.'
      return
    }
    // Guard: block deactivating last active Executive Member (would prevent quorum)
    if (target.role === UserRole.EXECUTIVE_MEMBER && activeExecutiveMembers.value.length <= 1) {
      deactivateBlocked.value = 'لا يمكن إلغاء تفعيل العضو التنفيذي الوحيد النشط. يجب الإبقاء على أعضاء تصويت كافين لضمان النصاب القانوني.'
      return
    }
    deactivateBlocked.value = null
    deactivateTarget.value = target
  }
  else {
    void doToggleActive(target)
  }
}

async function doToggleActive(target: User) {
  deactivateTarget.value = null
  try {
    const payload: UpdateUserPayload = {
      name: target.name,
      email: target.email,
      role: target.role,
      bank_id: target.bank_id,
      is_active: !target.is_active,
    }
    const updated = await updateUser(target.id, payload)
    staffUsers.value = staffUsers.value.map(u => u.id === target.id ? updated : u)
    notify(updated.is_active ? `تم تفعيل ${target.name}` : `تم إلغاء تفعيل ${target.name}`)
  }
  catch {
    toastError('فشل تغيير الحالة')
  }
}

function activeStatusCell(isActive: boolean) {
  const color = isActive ? 'var(--color-success)' : 'var(--color-locked)'
  const label = isActive ? 'نشط' : 'غير نشط'
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

const columns: ColumnDef<User>[] = [
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
          'aria-label': 'تحديد المستخدم',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'user',
    header: 'المستخدم',
    cell: ({ row }) => {
      const staff = row.original
      return h('div', { class: 'flex items-center gap-2' }, [
        h(Avatar, { size: 'sm' }, {
          default: () => h(AvatarFallback, { class: 'bg-gradient-hero text-xs font-bold text-white' }, () => userInitials(staff.name)),
        }),
        h('div', { class: 'flex flex-col gap-0.5' }, [
          h('button', {
            type: 'button',
            class: 'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
            title: 'معاينة سريعة',
            onClick: (e: Event) => { e.stopPropagation(); viewing.value = staff },
          }, staff.name),
          h('span', { class: 'text-xs text-muted-foreground', dir: 'ltr' }, staff.email),
        ]),
      ])
    },
  },
  {
    accessorKey: 'role',
    header: 'الدور',
    cell: ({ row }) => h(Badge, { variant: 'secondary' }, () => ROLE_LABELS[row.original.role]),
  },
  {
    id: 'bank',
    header: 'الجهة',
    cell: ({ row }) => {
      const name = resolveBankName(row.original)
      return h('span', { class: 'text-sm text-muted-foreground' }, name || '—')
    },
  },
  {
    id: 'last_seen',
    header: 'آخر ظهور',
    cell: ({ row }) => {
      const ts = row.original.last_login_at
      if (!ts) return h('span', { class: 'text-sm text-muted-foreground', 'data-cell': 'last-seen' }, '—')
      return h('span', { class: 'text-sm text-muted-foreground', 'data-cell': 'last-seen' }, new Date(ts).toLocaleDateString('ar-EG'))
    },
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    cell: ({ row }) => {
      const staff = row.original
      const isSelf = staff.id === currentUser.value?.id
      const roleNavItems: ReturnType<typeof h>[] = []
      if (staff.role === UserRole.SUPPORT_COMMITTEE) {
        roleNavItems.push(h(DropdownMenuItem, {
          class: 'gap-1.5 text-primary',
          onClick: () => navigateTo('/requests'),
        }, () => [h(ExternalLink, { class: 'h-3.5 w-3.5' }), 'طابور المراجعة']))
      }
      else if (staff.role === UserRole.EXECUTIVE_MEMBER || staff.role === UserRole.COMMITTEE_DIRECTOR) {
        roleNavItems.push(h(DropdownMenuItem, {
          class: 'gap-1.5 text-[var(--voting)]',
          onClick: () => navigateTo('/voting'),
        }, () => [h(ExternalLink, { class: 'h-3.5 w-3.5' }), 'جلسات التصويت']))
      }
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
              h(DropdownMenuItem, { onClick: () => (viewing.value = staff) }, () => 'عرض التفاصيل'),
              h(DropdownMenuItem, { onClick: () => openEdit(staff) }, () => 'تعديل'),
              ...roleNavItems,
              ...(!isSelf
                ? [
                    h(DropdownMenuSeparator),
                    h(DropdownMenuItem, {
                      class: staff.is_active ? 'text-destructive' : 'text-[var(--severity-green)]',
                      onClick: () => requestToggleActive(staff),
                    }, () => staff.is_active ? 'إلغاء تفعيل' : 'تفعيل'),
                  ]
                : []),
            ],
          }),
        ],
      })
    },
  },
]

const CBY_STAFF_COLUMN_LABELS: Record<string, string> = {
  user: 'المستخدم',
  role: 'الدور',
  bank: 'الجهة',
  last_seen: 'آخر ظهور',
  is_active: 'الحالة',
}

const table = useVueTable({
  get data() { return filtered.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onRowSelectionChange: updater =>
    rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater,
  onColumnVisibilityChange: updater =>
    columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater,
  state: {
    get rowSelection() { return rowSelection.value },
    get columnVisibility() { return columnVisibility.value },
  },
  initialState: { pagination: { pageSize: 20 } },
})

const selectedUsers = computed(() => table.getSelectedRowModel().rows.map(row => row.original))

function buildExportFileName(suffix: string): string {
  const stamp = new Date().toISOString().slice(0, 10)
  return `cby-staff-${suffix}-${stamp}`
}

function exportColumns() {
  return [
    { key: 'name', label: 'الاسم' },
    { key: 'email', label: 'البريد الإلكتروني' },
    {
      key: 'role',
      label: 'الدور',
      format: (_value: unknown, row: User) => ROLE_LABELS[row.role] ?? row.role,
    },
    {
      key: 'bank_id',
      label: 'الجهة',
      format: (_value: unknown, row: User) => resolveBankName(row),
    },
    {
      key: 'is_active',
      label: 'الحالة',
      format: (_value: unknown, row: User) => row.is_active ? 'نشط' : 'غير نشط',
    },
    {
      key: 'last_login_at',
      label: 'آخر ظهور',
      format: (_value: unknown, row: User) => row.last_login_at ? new Date(row.last_login_at).toLocaleDateString('ar-EG') : '—',
    },
  ] as const
}

function exportCurrentUsers() {
  if (!filtered.value.length) return
  exportToCSV(filtered.value as unknown as Record<string, unknown>[], exportColumns() as any, buildExportFileName('filtered'))
}

function exportSelectedUsers() {
  const rows = selectedUsers.value.length > 0 ? selectedUsers.value : filtered.value
  if (!rows.length) return
  exportToCSV(rows as unknown as Record<string, unknown>[], exportColumns() as any, buildExportFileName('selected'))
}
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <PageHeader
      title="مستخدمي النظام"
      subtitle="إدارة موظفي البنك المركزي — اللجان المساندة والتنفيذية ومسؤولي النظام"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'مستخدمي النظام' }]"
    >
      <template #actions>
        <Button size="sm" class="btn-primary h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">مستخدم جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards -->
    <div class="mb-6 grid grid-cols-3 gap-3">
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
          <UserCog class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.total }}</div>
        <div class="text-xs text-muted-foreground">إجمالي المستخدمين</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-green-50/10 text-green-700">
          <ShieldCheck class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.active }}</div>
        <div class="text-xs text-muted-foreground">نشط</div>
      </Card>
      <Card class="border-0 p-4 shadow">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-red-700/10 text-red-700">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
          </svg>
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">{{ stats.inactive }}</div>
        <div class="text-xs text-muted-foreground">غير نشط</div>
      </Card>
    </div>

    <!-- Toolbar: bulk (when selected) OR search + role filter (default) -->
    <div v-if="selectedCount > 0" class="mb-4 flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
      <span class="text-sm font-medium text-primary">{{ selectedCount }} محدد</span>
      <div class="mx-2 h-4 w-px bg-border" />
      <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs" @click="exportSelectedUsers">
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
          ref="searchInputRef"
          v-model="query"
          class="h-8 rounded-md pe-9 text-sm"
          placeholder="بحث بالاسم أو البريد..."
        />
      </div>
      <div data-testid="filter-role">
        <Select v-model="roleFilter">
          <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-56">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">كل الأدوار</SelectItem>
            <SelectItem v-for="r in STAFF_ROLES" :key="r" :value="r">
              {{ ROLE_LABELS[r] }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div data-testid="filter-bank">
        <Select v-model="filterBank">
          <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-48">
            <SelectValue placeholder="كل الجهات" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">كل الجهات</SelectItem>
            <SelectItem v-for="b in banksData" :key="b.id" :value="String(b.id)">
              {{ b.name_ar }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div data-testid="filter-status">
        <Select v-model="filterStatus">
          <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-40">
            <SelectValue placeholder="كل الحالات" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">كل الحالات</SelectItem>
            <SelectItem value="active">نشط</SelectItem>
            <SelectItem value="inactive">غير نشط</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <Button
        variant="outline"
        size="sm"
        class="ms-auto h-8 gap-1.5"
        :disabled="filtered.length === 0"
        @click="exportCurrentUsers"
      >
        <Download class="h-4 w-4" />
        تصدير
      </Button>

      <DataTableViewOptions
        :table="table"
        :column-labels="CBY_STAFF_COLUMN_LABELS"
      />
    </div>

    <!-- Fetch error state -->
    <Alert v-if="fetchError" variant="destructive" role="alert" class="mb-4">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>تعذّر تحميل المستخدمين</AlertTitle>
      <AlertDescription class="flex items-center gap-3">
        <span>حدث خطأ أثناء جلب البيانات. تحقق من الاتصال ثم أعد المحاولة.</span>
        <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs" @click="loadStaff">
          <RefreshCw class="h-3 w-3" />
          إعادة المحاولة
        </Button>
      </AlertDescription>
    </Alert>

    <!-- Table -->
    <div v-if="!fetchError" class="relative flex flex-col gap-4">
      <div v-if="loading || table.getRowModel().rows.length > 0" class="rounded-lg border overflow-x-auto">
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
            <template v-if="loading">
              <TableRow v-for="i in 6" :key="`skel-${i}`">
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
        v-if="!loading && !table.getRowModel().rows.length"
        data-empty-state-variant="cby-staff"
        class="min-h-[280px] rounded-xl border border-dashed bg-muted/20"
      >
        <EmptyHeader>
          <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <SearchX class="size-5" />
          </div>
          <EmptyTitle>
            {{ staffUsers.length === 0 ? 'لا يوجد مستخدمون بعد' : 'لا توجد نتائج' }}
          </EmptyTitle>
        </EmptyHeader>
        <EmptyContent>
          <EmptyDescription>
            {{ staffUsers.length === 0 ? 'ابدأ بإضافة مستخدم جديد باستخدام الزر أعلاه.' : 'جرّب تغيير البحث أو فلتر الدور.' }}
          </EmptyDescription>
        </EmptyContent>
      </Empty>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-2">
        <p class="text-sm text-muted-foreground">{{ table.getFilteredSelectedRowModel().rows.length }} من {{ table.getFilteredRowModel().rows.length }} مستخدم محدد</p>
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
      <DialogContent  class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم نظام' }}</DialogTitle>
          <DialogDescription>مستخدمو البنك المركزي فقط (لجان وإدارة النظام).</DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2">
          <div class="space-y-1.5">
            <Label>الاسم *</Label>
            <Input v-model="form.name" />
          </div>
          <div class="space-y-1.5">
            <Label>البريد الإلكتروني *</Label>
            <Input v-model="form.email" type="email"  />
          </div>
          <div class="space-y-1.5">
            <Label>{{ editing ? 'كلمة المرور (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور *' }}</Label>
            <Input v-model="form.password" type="password"  :placeholder="editing ? '••••••••' : 'كلمة مرور قوية'" />
          </div>
          <div class="space-y-1.5">
            <Label>الدور *</Label>
            <Select v-model="form.role">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="role in STAFF_ROLES" :key="role" :value="role">{{ ROLE_LABELS[role] }}</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="roleSelectHint" class="flex items-start gap-1.5 text-xs text-[var(--severity-amber)]">
              <AlertTriangle class="mt-px h-3.5 w-3.5 shrink-0" />
              {{ roleSelectHint }}
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button :disabled="!formValid || saving" @click="saveStaff">
            {{ editing ? 'حفظ التعديلات' : 'إضافة' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Deactivation blocked: critical-role protection -->
    <AlertDialog :open="Boolean(deactivateBlocked)" @update:open="value => !value && (deactivateBlocked = null)">
      <AlertDialogContent >
        <AlertDialogHeader>
          <AlertDialogTitle class="flex items-center gap-2">
            <AlertTriangle class="h-5 w-5 text-[var(--severity-red)]" />
            لا يمكن إلغاء التفعيل
          </AlertDialogTitle>
          <AlertDialogDescription>{{ deactivateBlocked }}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deactivateBlocked = null">حسناً</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Deactivation confirmation with workload context -->
    <AlertDialog :open="Boolean(deactivateTarget)" @update:open="value => !value && (deactivateTarget = null)">
      <AlertDialogContent v-if="deactivateTarget" >
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد إلغاء التفعيل</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم إلغاء تفعيل <strong>{{ deactivateTarget.name }}</strong>
            ({{ ROLE_LABELS[deactivateTarget.role] }}).
            <template v-if="deactivateTarget.role === UserRole.COMMITTEE_DIRECTOR">
              <br class="mb-1" />
              سيبقى {{ activeDirectors.length - 1 }} مدير نشط بعد هذا الإجراء.
            </template>
            <template v-else-if="deactivateTarget.role === UserRole.EXECUTIVE_MEMBER">
              <br class="mb-1" />
              سيبقى {{ activeExecutiveMembers.length - 1 }} عضو تنفيذي نشط بعد هذا الإجراء.
            </template>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deactivateTarget = null">إلغاء</AlertDialogCancel>
          <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="doToggleActive(deactivateTarget)">
            تأكيد الإلغاء
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- View Dialog -->
    <Dialog :open="Boolean(viewing)" @update:open="value => !value && (viewing = null)">
      <DialogContent v-if="viewing"  class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <UserCog class="h-5 w-5 text-primary" />
            {{ viewing.name }}
          </DialogTitle>
          <DialogDescription>تفاصيل المستخدم</DialogDescription>
        </DialogHeader>
        <div class="space-y-3 py-2 text-sm">
          <div class="flex items-center justify-between gap-3 border-b pb-2">
            <span class="text-muted-foreground">البريد</span>
            <span class="text-start font-medium">{{ viewing.email }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 border-b pb-2">
            <span class="text-muted-foreground">الدور</span>
            <span class="text-start font-medium">{{ ROLE_LABELS[viewing.role] }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 border-b pb-2">
            <span class="text-muted-foreground">الحالة</span>
            <span class="text-start font-medium">{{ viewing.is_active ? 'نشط' : 'غير نشط' }}</span>
          </div>
          <div v-if="viewing.last_login_at" class="flex items-center justify-between gap-3 border-b pb-2">
            <span class="text-muted-foreground">آخر تسجيل دخول</span>
            <span class="text-start font-medium">{{ new Date(viewing.last_login_at).toLocaleString('ar-EG') }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
