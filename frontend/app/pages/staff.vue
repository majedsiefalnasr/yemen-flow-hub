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
import { ref, computed, onMounted, h } from 'vue'
import {
  AlertTriangle,
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  MoreHorizontal, Plus, Search, SearchX,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '../types/enums'
import type { ApiError, User } from '../types/models'
import { useUsers } from '../composables/useUsers'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'
import StaffModal from '../components/staff/StaffModal.vue'
import EmptyState from '../components/shared/EmptyState.vue'
import type { CreateUserPayload, UpdateUserPayload } from '../composables/useUsers'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
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
  requiredRoles: [UserRole.BANK_ADMIN],
})

const { fetchUsers, createUser, updateUser, getUser } = useUsers()
const auth = useAuthStore()

const staff = ref<User[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const showModal = ref(false)
const editingStaff = ref<User | null>(null)
const saving = ref(false)
const serverError = ref<string | null>(null)

const showDeactivateConfirm = ref(false)
const deactivatingStaff = ref<User | null>(null)
const deactivating = ref(false)

const searchQuery = ref('')
const roleFilter = ref<UserRole | ''>('')
const statusFilter = ref<'active' | 'inactive' | ''>('')

const filteredStaff = computed(() => {
  let list = staff.value
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(m =>
      m.name.toLowerCase().includes(q)
      || m.email.toLowerCase().includes(q),
    )
  }
  if (roleFilter.value) {
    list = list.filter(m => m.role === roleFilter.value)
  }
  if (statusFilter.value === 'active') {
    list = list.filter(m => m.is_active)
  }
  else if (statusFilter.value === 'inactive') {
    list = list.filter(m => !m.is_active)
  }
  return list
})

const totalCount = computed(() => staff.value.length)
const activeCount = computed(() => staff.value.filter(m => m.is_active).length)
const inactiveCount = computed(() => staff.value.filter(m => !m.is_active).length)

const isEmpty = computed(() => !loading.value && !error.value && staff.value.length === 0)

function getAvatarInitials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function getAvatarColor(id: number): string {
  const colors = ['var(--color-primary)', 'var(--color-voting)', 'var(--color-info)', 'var(--color-success)', 'var(--color-warning)', 'var(--color-destructive)']
  return colors[id % colors.length]!
}

function getFirstApiErrorMessage(err: unknown): string | null {
  const data = (err as { data?: ApiError })?.data
  if (!data) return null
  if (data.errors) {
    const firstKey = Object.keys(data.errors)[0]
    const firstMessage = firstKey ? data.errors[firstKey]?.[0] : null
    if (firstMessage) return firstMessage
  }
  return data.message ?? null
}

async function loadStaff() {
  loading.value = true
  error.value = null
  try {
    staff.value = await fetchUsers()
  }
  catch {
    error.value = 'تعذّر تحميل بيانات الموظفين.'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingStaff.value = null
  serverError.value = null
  showModal.value = true
}

function openEdit(member: User) {
  editingStaff.value = member
  serverError.value = null
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function openDeactivate(member: User) {
  deactivatingStaff.value = member
  showDeactivateConfirm.value = true
}

function closeDeactivate() {
  showDeactivateConfirm.value = false
  deactivatingStaff.value = null
}

async function handleSave(data: {
  name: string
  email: string
  role: UserRole
  department: string
  password?: string
}) {
  saving.value = true
  serverError.value = null
  if (!auth.user?.bank_id) {
    serverError.value = 'خطأ: معرّف البنك غير متاح.'
    saving.value = false
    return
  }
  try {
    if (editingStaff.value) {
      const currentUser = await getUser(editingStaff.value.id)
      const payload: UpdateUserPayload = {
        name: data.name,
        email: data.email,
        role: data.role,
        bank_id: auth.user.bank_id,
        is_active: currentUser.is_active,
      }
      if (data.password) payload.password = data.password
      const updated = await updateUser(editingStaff.value.id, payload)
      const idx = staff.value.findIndex(s => s.id === updated.id)
      if (idx !== -1) staff.value[idx] = updated
    }
    else {
      const payload: CreateUserPayload = {
        name: data.name,
        email: data.email,
        password: data.password!,
        role: data.role,
        bank_id: auth.user.bank_id,
        is_active: true,
      }
      const created = await createUser(payload)
      staff.value.unshift(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    serverError.value = getFirstApiErrorMessage(err) ?? 'حدث خطأ أثناء الحفظ.'
  }
  finally {
    saving.value = false
  }
}

async function confirmDeactivate() {
  if (!deactivatingStaff.value) return
  deactivating.value = true
  try {
    const target = deactivatingStaff.value
    const currentUser = await getUser(target.id)
    const payload: UpdateUserPayload = {
      name: currentUser.name,
      email: currentUser.email,
      role: currentUser.role,
      bank_id: currentUser.bank_id,
      is_active: false,
    }
    const updated = await updateUser(target.id, payload)
    const idx = staff.value.findIndex(s => s.id === updated.id)
    if (idx !== -1) staff.value[idx] = updated
    closeDeactivate()
  }
  catch {
    closeDeactivate()
    await loadStaff()
  }
  finally {
    deactivating.value = false
  }
}

function formatJoinDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ar-YE', { year: 'numeric', month: 'short', day: 'numeric' })
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
    id: 'member',
    header: 'الموظف',
    cell: ({ row }) => {
      const member = row.original
      return h('div', { class: 'flex items-center gap-3' }, [
        h(Avatar, { class: 'size-8' }, {
          default: () => h(AvatarFallback, {
            style: { background: getAvatarColor(member.id), color: 'white' },
          }, () => getAvatarInitials(member.name)),
        }),
        h('div', {}, [
          h('p', { class: 'text-sm font-medium' }, member.name),
          h('p', { class: 'text-xs text-muted-foreground', dir: 'ltr' }, member.email),
        ]),
      ])
    },
  },
  {
    accessorKey: 'role',
    header: 'الدور',
    cell: ({ row }) => h(Badge, { variant: 'outline' }, () => ROLE_LABELS[row.original.role] ?? row.original.role),
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'joined',
    header: 'تاريخ الانضمام',
    cell: ({ row }) => h('span', { class: 'text-sm text-muted-foreground' }, formatJoinDate(row.original.created_at)),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const member = row.original
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
              h(DropdownMenuItem, { onClick: () => openEdit(member) }, () => 'تعديل'),
              ...(member.is_active
                ? [
                    h(DropdownMenuSeparator),
                    h(DropdownMenuItem, {
                      class: 'text-destructive',
                      onClick: () => openDeactivate(member),
                    }, () => 'إلغاء التفعيل'),
                  ]
                : []),
            ],
          }),
        ],
      })
    },
  },
]

const table = useVueTable({
  get data() { return filteredStaff.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  initialState: { pagination: { pageSize: 20 } },
})

onMounted(loadStaff)
</script>

<template>
  <div class="flex flex-col gap-6">
    <PageHeader
      title="موظفو الجهة"
      :subtitle="auth.user?.bank_name_ar ?? auth.user?.bank_name_en ?? 'إدارة موظفي البنك'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'موظفو الجهة' }]"
    >
      <template #actions>
        <Button size="sm" class="h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">موظف جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 gap-4">
      <Card class="border-0 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM6 20h12a6 6 0 006-6V4a6 6 0 00-6-6H6a6 6 0 00-6 6v10a6 6 0 006 6z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">إجمالي الموظفين</p>
            <p class="text-2xl font-bold tabular-nums">{{ totalCount }}</p>
          </div>
        </div>
      </Card>
      <Card class="border-0 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-green-50 flex items-center justify-center text-green-700">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">نشط</p>
            <p class="text-2xl font-bold tabular-nums">{{ activeCount }}</p>
          </div>
        </div>
      </Card>
      <Card class="border-0 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-red-50 flex items-center justify-center text-red-700">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l-2-2m0 0l-2-2m2 2l2-2m-2 2l2 2m2-2l2 2" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">غير نشط</p>
            <p class="text-2xl font-bold tabular-nums">{{ inactiveCount }}</p>
          </div>
        </div>
      </Card>
    </div>

    <!-- Error -->
    <Alert v-if="error" variant="destructive">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ error }}</span>
        <Button variant="outline" size="sm" @click="loadStaff">إعادة المحاولة</Button>
      </AlertDescription>
    </Alert>

    <!-- Empty State (no staff at all) -->
    <EmptyState v-else-if="isEmpty" variant="staff">
      <Button @click="openCreate">
        <Plus class="ms-1 h-4 w-4" data-icon="inline-start" />
        إضافة أول موظف
      </Button>
    </EmptyState>

    <template v-else>
      <!-- Toolbar: search + filters -->
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
          <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            placeholder="بحث بالاسم أو البريد الإلكتروني..."
            class="h-8 rounded-md pe-9 text-sm"
          />
        </div>

        <Select v-model="roleFilter">
          <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-44">
            <SelectValue placeholder="جميع الأدوار" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">جميع الأدوار</SelectItem>
            <SelectItem value="DATA_ENTRY">{{ ROLE_LABELS[UserRole.DATA_ENTRY] }}</SelectItem>
            <SelectItem value="BANK_REVIEWER">{{ ROLE_LABELS[UserRole.BANK_REVIEWER] }}</SelectItem>
          </SelectContent>
        </Select>

        <Select v-model="statusFilter">
          <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-40">
            <SelectValue placeholder="جميع الحالات" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">جميع الحالات</SelectItem>
            <SelectItem value="active">نشط</SelectItem>
            <SelectItem value="inactive">غير نشط</SelectItem>
          </SelectContent>
        </Select>
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
              <template v-if="loading">
                <TableRow v-for="i in 6" :key="i">
                  <TableCell class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <Skeleton class="size-8 rounded-full" />
                      <div class="flex flex-col gap-1.5">
                        <Skeleton class="h-4 w-32" />
                        <Skeleton class="h-3 w-44" />
                      </div>
                    </div>
                  </TableCell>
                  <TableCell class="px-4 py-3"><Skeleton class="h-5 w-24 rounded-full" /></TableCell>
                  <TableCell class="px-4 py-3"><Skeleton class="h-4 w-16" /></TableCell>
                  <TableCell class="px-4 py-3"><Skeleton class="h-4 w-24" /></TableCell>
                  <TableCell class="px-4 py-3"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
                </TableRow>
              </template>

              <TableRow v-else-if="!table.getRowModel().rows.length">
                <TableCell :col-span="columns.length" class="p-8">
                  <Empty class="min-h-[200px] rounded-xl border border-dashed bg-muted/20">
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
          <p class="text-sm text-muted-foreground">
            {{ table.getFilteredRowModel().rows.length }} موظف
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
    </template>

    <!-- Staff Modal -->
    <StaffModal
      v-if="showModal"
      :staff="editingStaff"
      :saving="saving"
      :server-error="serverError"
      @save="handleSave"
      @close="closeModal"
    />

    <!-- Deactivate Dialog -->
    <AlertDialog :open="showDeactivateConfirm" @update:open="(v) => !v && closeDeactivate()">
      <AlertDialogContent dir="rtl">
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد إلغاء التفعيل</AlertDialogTitle>
          <AlertDialogDescription>
            هل أنت متأكد من إلغاء تفعيل حساب <strong>{{ deactivatingStaff?.name }}</strong>؟
            لن يتمكن من تسجيل الدخول بعد ذلك.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel :disabled="deactivating">إلغاء</AlertDialogCancel>
          <Button variant="destructive" :disabled="deactivating" @click="confirmDeactivate">
            {{ deactivating ? 'جارٍ التعطيل…' : 'تعطيل' }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
