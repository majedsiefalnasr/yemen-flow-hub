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
import { ref, reactive, computed, watch, onMounted, h } from 'vue'
import {
  AlertTriangle,
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  Download, MoreHorizontal, Plus, Printer, Search, SearchX, X,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '../types/enums'
import type { User, Bank } from '../types/models'
import { useUsers } from '../composables/useUsers'
import { useBanks } from '../composables/useBanks'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS, BANK_ROLES, CBY_ROLES, BANK_ADMIN_MANAGED_ROLES } from '../constants/workflow'
import type { CreateUserPayload, UpdateUserPayload } from '../composables/useUsers'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'
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

const { fetchUsers, createUser, updateUser } = useUsers()
const { fetchBanks } = useBanks()
const auth = useAuthStore()

const users = ref<User[]>([])
const banks = ref<Bank[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const query = ref('')

const showModal = ref(false)
const editingUser = ref<User | null>(null)
const viewingUser = ref<User | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface UserForm {
  name: string
  email: string
  password: string
  role: UserRole | ''
  bank_id: number | null
  is_active: boolean
}

const form = reactive<UserForm>({
  name: '',
  email: '',
  password: '',
  role: '',
  bank_id: null,
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof UserForm, string>>>({})

const isBankAdmin = computed(() => auth.user?.role === UserRole.BANK_ADMIN)
const allowedRoles = computed(() => isBankAdmin.value ? BANK_ADMIN_MANAGED_ROLES : Object.values(UserRole))
const isBankRole = computed(() => form.role !== '' && BANK_ROLES.includes(form.role as UserRole))

watch(() => form.role, (newRole) => {
  if (newRole !== '' && CBY_ROLES.includes(newRole as UserRole)) {
    form.bank_id = null
  }
  if (isBankAdmin.value && newRole !== '') {
    form.bank_id = auth.user?.bank_id ?? null
  }
})

async function loadData() {
  loading.value = true
  error.value = null
  try {
    const [usersData, banksData] = await Promise.all([fetchUsers(), fetchBanks()])
    users.value = usersData
    banks.value = banksData
  }
  catch {
    error.value = 'تعذّر تحميل البيانات.'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingUser.value = null
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = isBankAdmin.value ? UserRole.DATA_ENTRY : ''
  form.bank_id = isBankAdmin.value ? auth.user?.bank_id ?? null : null
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function openEdit(user: User) {
  editingUser.value = user
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.role = user.role
  form.bank_id = isBankAdmin.value ? auth.user?.bank_id ?? null : user.bank_id
  form.is_active = user.is_active
  clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function clearErrors() {
  formErrors.name = undefined
  formErrors.email = undefined
  formErrors.password = undefined
  formErrors.role = undefined
  formErrors.bank_id = undefined
  formError.value = null
}

function validateForm(): boolean {
  clearErrors()
  let valid = true
  if (!form.name.trim()) { formErrors.name = 'الاسم مطلوب'; valid = false }
  if (!form.email.trim()) { formErrors.email = 'البريد الإلكتروني مطلوب'; valid = false }
  if (!editingUser.value && !form.password) { formErrors.password = 'كلمة المرور مطلوبة'; valid = false }
  if (form.password && form.password.length < 8) { formErrors.password = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'; valid = false }
  if (!form.role) { formErrors.role = 'الدور الوظيفي مطلوب'; valid = false }
  if (form.role && !allowedRoles.value.includes(form.role as UserRole)) {
    formErrors.role = 'هذا الدور غير متاح لهذا المستخدم'
    valid = false
  }
  if (form.role && BANK_ROLES.includes(form.role as UserRole) && !form.bank_id) {
    formErrors.bank_id = 'يجب تحديد البنك للأدوار المصرفية'
    valid = false
  }
  if (isBankAdmin.value && form.bank_id !== auth.user?.bank_id) {
    formErrors.bank_id = 'يمكن إدارة مستخدمي البنك الخاص بك فقط'
    valid = false
  }
  return valid
}

async function saveUser() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  try {
    if (editingUser.value) {
      const payload: UpdateUserPayload = {
        name: form.name.trim(),
        email: form.email.trim(),
        role: form.role as UserRole,
        bank_id: isBankAdmin.value ? auth.user?.bank_id ?? null : form.bank_id,
        is_active: form.is_active,
      }
      if (form.password) payload.password = form.password
      const updated = await updateUser(editingUser.value.id, payload)
      const idx = users.value.findIndex(u => u.id === updated.id)
      if (idx !== -1) users.value[idx] = updated
    }
    else {
      const payload: CreateUserPayload = {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        role: form.role as UserRole,
        bank_id: isBankAdmin.value ? auth.user?.bank_id ?? null : form.bank_id,
        is_active: form.is_active,
      }
      const created = await createUser(payload)
      users.value.unshift(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.data?.errors) {
      const errs = e.data.errors
      if (errs.name?.[0]) formErrors.name = errs.name[0]
      if (errs.email?.[0]) formErrors.email = errs.email[0]
      if (errs.password?.[0]) formErrors.password = errs.password[0]
      if (errs.role?.[0]) formErrors.role = errs.role[0]
      if (errs.bank_id?.[0]) formErrors.bank_id = errs.bank_id[0]
    }
    else {
      formError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
    }
  }
  finally {
    saving.value = false
  }
}

function bankLabel(user: User): string {
  return user.bank_name_ar ?? 'CBY'
}

const rowSelection = ref<Record<string, boolean>>({})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearSelection() {
  table.resetRowSelection()
}

const filteredUsers = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return users.value
  return users.value.filter(u =>
    u.name.toLowerCase().includes(q)
    || u.email.toLowerCase().includes(q)
    || (ROLE_LABELS[u.role] ?? u.role).toLowerCase().includes(q),
  )
})

function activeStatusCell(isActive: boolean, activeLabel = 'نشط', inactiveLabel = 'غير نشط') {
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
    accessorKey: 'name',
    header: 'الاسم',
    cell: ({ row }) => h('div', { class: 'flex flex-col gap-0.5' }, [
      h('button', {
        type: 'button',
        class: 'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
        title: 'معاينة سريعة',
        onClick: (e: Event) => { e.stopPropagation(); viewingUser.value = row.original },
      }, row.original.name),
      h('span', { class: 'font-mono text-xs text-muted-foreground', dir: 'ltr' }, row.original.email),
    ]),
  },
  {
    accessorKey: 'role',
    header: 'الدور',
    cell: ({ row }) => h(Badge, { variant: 'outline' }, () => ROLE_LABELS[row.original.role] ?? row.original.role),
  },
  {
    id: 'organization',
    header: 'الجهة',
    cell: ({ row }) => h('span', { class: 'text-sm text-muted-foreground' }, bankLabel(row.original)),
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
      const user = row.original
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
              h(DropdownMenuItem, { onClick: () => (viewingUser.value = user) }, () => 'عرض'),
              h(DropdownMenuItem, { onClick: () => openEdit(user) }, () => 'تعديل'),
            ],
          }),
        ],
      })
    },
  },
]

const table = useVueTable({
  get data() { return filteredUsers.value },
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

onMounted(loadData)
</script>

<template>
  <div class="flex flex-col gap-6">
    <PageHeader
      :title="isBankAdmin ? 'مستخدمو البنك' : 'مستخدمو النظام'"
      :subtitle="isBankAdmin ? 'إدارة مستخدمي البنك' : 'إدارة مستخدمي النظام'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: isBankAdmin ? 'مستخدمو البنك' : 'مستخدمو النظام' }]"
    >
      <template #actions>
        <Button size="sm" class="h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">إضافة مستخدم</span>
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
          placeholder="بحث بالاسم أو البريد أو الدور..."
          class="h-8 rounded-md pe-9 text-sm"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
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
              <TableRow v-for="i in 8" :key="i">
                <TableCell class="px-4 py-3">
                  <div class="flex flex-col gap-1.5">
                    <Skeleton class="h-4 w-36" />
                    <Skeleton class="h-3 w-48" />
                  </div>
                </TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-5 w-20 rounded-full" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-4 w-28" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-4 w-16" /></TableCell>
                <TableCell class="px-4 py-3 w-12"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
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
        class="min-h-[280px] rounded-xl border border-dashed bg-muted/20"
      >
        <EmptyHeader>
          <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <SearchX class="size-5" />
          </div>
          <EmptyTitle>لا توجد نتائج</EmptyTitle>
        </EmptyHeader>
        <EmptyContent>
          <EmptyDescription>جرّب تغيير البحث لعرض المستخدمين.</EmptyDescription>
        </EmptyContent>
      </Empty>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-2">
        <p class="text-sm text-muted-foreground">
          {{ table.getFilteredSelectedRowModel().rows.length }} من {{ table.getFilteredRowModel().rows.length }} مستخدم محدد
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
    <Dialog :open="showModal" @update:open="(open) => !open && closeModal()">
      <DialogContent class="sm:max-w-[420px]" dir="rtl">
        <DialogHeader>
          <DialogTitle>{{ editingUser ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد' }}</DialogTitle>
        </DialogHeader>

        <Alert v-if="formError" variant="destructive">
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription>{{ formError }}</AlertDescription>
        </Alert>

        <div class="flex flex-col gap-4">
          <Field :data-invalid="!!formErrors.name">
            <FieldLabel>الاسم <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.name" :aria-invalid="!!formErrors.name" placeholder="الاسم الكامل" type="text" />
            <FieldDescription v-if="formErrors.name" class="text-destructive">{{ formErrors.name }}</FieldDescription>
          </Field>

          <Field :data-invalid="!!formErrors.email">
            <FieldLabel>البريد الإلكتروني <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.email" :aria-invalid="!!formErrors.email" placeholder="example@bank.ye" type="email" />
            <FieldDescription v-if="formErrors.email" class="text-destructive">{{ formErrors.email }}</FieldDescription>
          </Field>

          <Field :data-invalid="!!formErrors.password">
            <FieldLabel>كلمة المرور {{ editingUser ? '(اتركها فارغة للإبقاء على الحالية)' : '*' }}</FieldLabel>
            <Input v-model="form.password" :aria-invalid="!!formErrors.password" placeholder="8 أحرف على الأقل" type="password" />
            <FieldDescription v-if="formErrors.password" class="text-destructive">{{ formErrors.password }}</FieldDescription>
          </Field>

          <Field :data-invalid="!!formErrors.role">
            <FieldLabel>الدور الوظيفي <span class="text-destructive">*</span></FieldLabel>
            <Select v-model="form.role">
              <SelectTrigger :aria-invalid="!!formErrors.role"><SelectValue placeholder="اختر الدور" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="role in allowedRoles" :key="role" :value="role">{{ ROLE_LABELS[role] }}</SelectItem>
              </SelectContent>
            </Select>
            <FieldDescription v-if="formErrors.role" class="text-destructive">{{ formErrors.role }}</FieldDescription>
          </Field>

          <Field v-if="isBankRole" :data-invalid="!!formErrors.bank_id">
            <FieldLabel>البنك <span class="text-destructive">*</span></FieldLabel>
            <Select v-model="form.bank_id">
              <SelectTrigger :aria-invalid="!!formErrors.bank_id"><SelectValue placeholder="اختر البنك" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name_ar }}</SelectItem>
              </SelectContent>
            </Select>
            <FieldDescription v-if="formErrors.bank_id" class="text-destructive">{{ formErrors.bank_id }}</FieldDescription>
          </Field>

          <Field class="flex items-center gap-3">
            <FieldLabel class="flex-1">نشط</FieldLabel>
            <Switch v-model:checked="form.is_active" />
          </Field>
        </div>

        <DialogFooter class="gap-2">
          <Button variant="outline" :disabled="saving" @click="closeModal">إلغاء</Button>
          <Button :disabled="saving" @click="saveUser">{{ saving ? 'جارٍ الحفظ…' : 'حفظ' }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Quick-view Dialog -->
    <Dialog :open="Boolean(viewingUser)" @update:open="v => !v && (viewingUser = null)">
      <DialogContent v-if="viewingUser" dir="rtl" class="sm:max-w-md">
        <DialogHeader class="pb-2">
          <DialogTitle class="text-base">{{ viewingUser.name }}</DialogTitle>
          <DialogDescription dir="ltr" class="text-xs">{{ viewingUser.email }}</DialogDescription>
        </DialogHeader>

        <div class="grid grid-cols-2 gap-x-4 gap-y-3 py-1 text-sm">
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">الدور</p>
            <Badge variant="secondary" class="text-xs">{{ ROLE_LABELS[viewingUser.role] ?? viewingUser.role }}</Badge>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">الحالة</p>
            <Badge :class="viewingUser.is_active ? 'bg-[var(--severity-green)]/10 text-[var(--severity-green)] border-[var(--severity-green)]/30 border' : 'bg-[var(--severity-red)]/10 text-[var(--severity-red)] border-[var(--severity-red)]/30 border'">
              {{ viewingUser.is_active ? 'نشط' : 'غير نشط' }}
            </Badge>
          </div>
          <div v-if="viewingUser.bank_id" class="col-span-2 space-y-0.5">
            <p class="text-xs text-muted-foreground">البنك التابع له</p>
            <p class="font-medium">{{ bankLabel(viewingUser) }}</p>
          </div>
        </div>

        <Separator />

        <DialogFooter class="gap-2">
          <Button variant="outline" size="sm" @click="() => { viewingUser = null }">إغلاق</Button>
          <Button size="sm" @click="() => { openEdit(viewingUser!); viewingUser = null }">تعديل البيانات</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
