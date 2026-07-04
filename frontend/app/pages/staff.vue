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
import { ref, computed, onMounted, h } from 'vue'
import {
  AlertTriangle,
  CheckCircle2,
  MoreHorizontal,
  Plus,
  SearchX,
  ShieldCheck,
  Users,
} from 'lucide-vue-next'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '../types/enums'
import type { ApiError, User } from '../types/models'
import { useUsers } from '../composables/useUsers'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'
import StaffModal from '../components/staff/StaffModal.vue'
import EmptyState from '../components/shared/EmptyState.vue'
import type { CreateUserPayload, UpdateUserPayload } from '../composables/useUsers'
import BoringAvatar from '../components/shared/BoringAvatar.vue'
import AccountRecoveryDialog from '../components/security/AccountRecoveryDialog.vue'
import { persistUserAvatar, type AvatarVariant } from '../composables/useUserAvatar'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
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
import {
  DataTable,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'

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
const recoveryTarget = ref<User | null>(null)

const query = ref('')
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({
  last_login: false,
})

// Access Health — active-highlight visual state
const accessHealthFilter = ref<'active' | 'inactive' | 'bank_reviewer' | null>(null)

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)

const totalCount = computed(() => staff.value.length)
const activeCount = computed(() => staff.value.filter((m) => m.is_active).length)
const inactiveCount = computed(() => staff.value.filter((m) => !m.is_active).length)
const bankReviewerCount = computed(
  () => staff.value.filter((m) => m.role === UserRole.BANK_REVIEWER && m.is_active).length,
)

const isEmpty = computed(() => !loading.value && !error.value && staff.value.length === 0)

// Pre-filter by search query — TanStack handles column filters
const filteredStaff = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return staff.value
  return staff.value.filter(
    (m) => m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q),
  )
})

function getFirstApiErrorMessage(err: any): string | null {
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
  } catch {
    error.value = 'تعذّر تحميل بيانات موظفي البنك. تحقق من الاتصال وأعد المحاولة.'
  } finally {
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

function openAccountRecovery(member: User) {
  closeModal()
  recoveryTarget.value = member
}

function handleRecoveryUpdated(updated: User) {
  const idx = staff.value.findIndex((member) => member.id === updated.id)
  if (idx !== -1) staff.value[idx] = updated
  recoveryTarget.value = updated
}

async function handleSave(data: {
  name: string
  email: string
  role: UserRole
  department: string
  password?: string
  avatar_variant: AvatarVariant
}) {
  saving.value = true
  serverError.value = null
  if (!auth.user?.bank_id) {
    serverError.value = 'لا يمكن حفظ الموظف لأن البنك المرتبط بحسابك غير محدد.'
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
        avatar_variant: data.avatar_variant,
      }
      if (data.password) payload.password = data.password
      const updated = await updateUser(editingStaff.value.id, payload)
      const idx = staff.value.findIndex((s) => s.id === updated.id)
      if (idx !== -1) staff.value[idx] = updated
      persistUserAvatar(updated.email, { variant: data.avatar_variant })
      // Mirror onto the auth store so the topbar / sidebar avatar refresh
      // immediately when a bank admin edits their own row.
      if (auth.user && auth.user.id === updated.id) {
        auth.user = { ...auth.user, ...updated, avatar_variant: data.avatar_variant }
      }
    } else {
      const payload: CreateUserPayload = {
        name: data.name,
        email: data.email,
        password: data.password!,
        role: data.role,
        bank_id: auth.user.bank_id,
        is_active: true,
        avatar_variant: data.avatar_variant,
      }
      const created = await createUser(payload)
      staff.value.unshift(created)
      persistUserAvatar(created.email, { variant: data.avatar_variant })
    }
    closeModal()
  } catch (err: any) {
    serverError.value =
      getFirstApiErrorMessage(err) ?? 'تعذّر حفظ بيانات الموظف. راجع الحقول ثم أعد المحاولة.'
  } finally {
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
    const idx = staff.value.findIndex((s) => s.id === updated.id)
    if (idx !== -1) staff.value[idx] = updated
    closeDeactivate()
  } catch {
    closeDeactivate()
    await loadStaff()
  } finally {
    deactivating.value = false
  }
}

function formatJoinDate(dateStr: string | null | undefined): string {
  if (!dateStr) return 'غير متاح'
  return new Date(dateStr).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
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

const columns: ColumnDef<User>[] = [
  {
    id: 'member',
    header: 'الموظف',
    cell: ({ row }) => {
      const member = row.original
      return h('div', { class: 'flex items-center gap-3' }, [
        h(BoringAvatar, {
          name: member.name || member.email,
          identity: member.email,
          variant: (member.avatar_variant as AvatarVariant | undefined) ?? undefined,
          size: 32,
          square: true,
          class: 'avatar size-8 overflow-hidden rounded-md',
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
    filterFn: (row, _id, value: string[]) => value.includes(row.original.role),
    cell: ({ row }) =>
      h(Badge, { variant: 'outline' }, () => ROLE_LABELS[row.original.role] ?? row.original.role),
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.is_active)),
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'joined',
    header: 'تاريخ الانضمام',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm text-muted-foreground' },
        formatJoinDate(row.original.created_at),
      ),
  },
  {
    id: 'last_login',
    header: 'آخر دخول',
    cell: ({ row }) => {
      const ts = row.original.last_login_at
      return h(
        'span',
        { class: 'text-xs text-muted-foreground' },
        ts ? formatJoinDate(ts) : 'غير متاح',
      )
    },
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    cell: ({ row }) => {
      const member = row.original
      return h(
        DropdownMenu,
        {},
        {
          default: () => [
            h(
              DropdownMenuTrigger,
              { asChild: true },
              {
                default: () =>
                  h(
                    Button,
                    {
                      variant: 'ghost',
                      size: 'icon',
                      class: 'h-8 w-8',
                    },
                    {
                      default: () => [
                        h('span', { class: 'sr-only' }, 'فتح القائمة'),
                        h(MoreHorizontal, { class: 'h-4 w-4' }),
                      ],
                    },
                  ),
              },
            ),
            h(
              DropdownMenuContent,
              { align: 'end' },
              {
                default: () => [
                  h(DropdownMenuItem, { onClick: () => openEdit(member) }, () => 'تعديل'),
                  h(
                    DropdownMenuItem,
                    { onClick: () => openAccountRecovery(member) },
                    () => 'استعادة الوصول للحساب',
                  ),
                  ...(member.is_active
                    ? [
                        h(DropdownMenuSeparator),
                        h(
                          DropdownMenuItem,
                          {
                            class: 'btn-deactivate text-destructive',
                            onClick: () => openDeactivate(member),
                          },
                          () => 'إلغاء التفعيل',
                        ),
                      ]
                    : []),
                ],
              },
            ),
          ],
        },
      )
    },
  },
]

const STAFF_COLUMN_LABELS: Record<string, string> = {
  member: 'الموظف',
  role: 'الدور',
  is_active: 'الحالة',
  joined: 'تاريخ الانضمام',
  last_login: 'آخر دخول',
}

const roleFilterOptions = [
  { label: ROLE_LABELS[UserRole.DATA_ENTRY], value: UserRole.DATA_ENTRY },
  { label: ROLE_LABELS[UserRole.BANK_REVIEWER], value: UserRole.BANK_REVIEWER },
]
const statusFilterOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'غير نشط', value: 'false' },
]

const exportCols = [
  { key: 'name', label: 'الاسم' },
  { key: 'email', label: 'البريد الإلكتروني' },
  {
    key: 'role',
    label: 'الدور',
    format: (_value: any, row: User) => ROLE_LABELS[row.role] ?? row.role,
  },
  {
    key: 'is_active',
    label: 'الحالة',
    format: (_value: any, row: User) => (row.is_active ? 'نشط' : 'غير نشط'),
  },
  {
    key: 'created_at',
    label: 'تاريخ الانضمام',
    format: (_value: any, row: User) => formatJoinDate(row.created_at),
  },
  {
    key: 'last_login_at',
    label: 'آخر دخول',
    format: (_value: any, row: User) => formatJoinDate(row.last_login_at),
  },
]

const table = useVueTable({
  get data() {
    return filteredStaff.value
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onColumnFiltersChange: (updater) =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onColumnVisibilityChange: (updater) =>
    (columnVisibility.value =
      typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  state: {
    get columnFilters() {
      return columnFilters.value
    },
    get columnVisibility() {
      return columnVisibility.value
    },
  },
  initialState: { pagination: { pageSize: 20 } },
})

function handleReset() {
  query.value = ''
  accessHealthFilter.value = null
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `staff-${new Date().toISOString().slice(0, 10)}`
}

function applyAccessHealthFilter(key: typeof accessHealthFilter.value) {
  if (accessHealthFilter.value === key) {
    // Toggle off — clear filters
    accessHealthFilter.value = null
    table.resetColumnFilters()
  } else {
    accessHealthFilter.value = key
    table.resetColumnFilters()
    if (key === 'active') {
      table.getColumn('is_active')?.setFilterValue(['true'])
    } else if (key === 'inactive') {
      table.getColumn('is_active')?.setFilterValue(['false'])
    } else if (key === 'bank_reviewer') {
      table.getColumn('role')?.setFilterValue([UserRole.BANK_REVIEWER])
    }
  }
}

onMounted(loadStaff)
</script>

<template>
  <div class="flex flex-col gap-6 py-2">
    <h1 class="page-title sr-only">موظفو الجهة</h1>
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

    <!-- Access Health Cards — clicking sets column filters -->
    <div class="mb-6">
      <MetricGrid :columns="4">
        <MetricCard
          label="إجمالي الموظفين"
          :value="totalCount"
          :icon="Users"
          :active="columnFilters.length === 0"
          @click="applyAccessHealthFilter(null)"
        />
        <MetricCard
          label="موظف نشط"
          :value="activeCount"
          :icon="CheckCircle2"
          tone="success"
          :active="accessHealthFilter === 'active'"
          @click="applyAccessHealthFilter('active')"
        />
        <MetricCard
          label="موقوف"
          :value="inactiveCount"
          :icon="AlertTriangle"
          tone="danger"
          :active="accessHealthFilter === 'inactive'"
          @click="applyAccessHealthFilter('inactive')"
        />
        <MetricCard
          label="تغطية مراجع البنك"
          :value="bankReviewerCount"
          :icon="ShieldCheck"
          tone="info"
          :active="accessHealthFilter === 'bank_reviewer'"
          @click="applyAccessHealthFilter('bank_reviewer')"
        />
      </MetricGrid>
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
    <EmptyState v-if="isEmpty" variant="staff">
      <Button @click="openCreate">
        <Plus class="ms-1 h-4 w-4" data-icon="inline-start" />
        إضافة أول موظف
      </Button>
    </EmptyState>

    <template v-else-if="!error && !isEmpty">
      <!-- Table -->
      <div class="relative flex flex-col gap-4">
        <DataTable
          :data="filteredStaff"
          :columns="columns"
          :loading="loading"
          :column-filters="columnFilters"
          :column-visibility="columnVisibility"
          @update:column-filters="(v) => (columnFilters = v)"
          @update:column-visibility="(v) => (columnVisibility = v)"
        >
          <template #toolbar="{ table: dataTable }">
            <DataTableToolbar
              :table="dataTable"
              search-placeholder="بحث بالاسم أو البريد الإلكتروني..."
              :has-filters="hasActiveFilters"
              @update:search="(v) => (query = v)"
              @reset="handleReset"
            >
              <template #filters>
                <DataTableFacetedFilter
                  v-if="dataTable.getColumn('role')"
                  :column="dataTable.getColumn('role')!"
                  title="الدور"
                  :options="roleFilterOptions"
                />
                <DataTableFacetedFilter
                  v-if="dataTable.getColumn('is_active')"
                  :column="dataTable.getColumn('is_active')!"
                  title="الحالة"
                  :options="statusFilterOptions"
                />
              </template>
              <template #actions>
                <DataTableViewOptions :table="dataTable" :column-labels="STAFF_COLUMN_LABELS" />
                <DataTableExport
                  :table="dataTable as any"
                  :export-columns="exportCols as any"
                  :filename="buildExportFilename()"
                  :formats="['csv', 'tsv', 'json', 'excel', 'pdf']"
                  :respect-column-visibility="true"
                />
              </template>
            </DataTableToolbar>
          </template>
          <template #empty>
            <Empty class="bg-muted/20 min-h-[200px] rounded-xl border border-dashed">
              <EmptyHeader>
                <div
                  class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
                >
                  <SearchX class="size-5" />
                </div>
                <EmptyTitle>
                  {{
                    staff.length === 0
                      ? 'لا يوجد موظفون مسجلون لهذا البنك بعد'
                      : 'لا توجد نتائج مطابقة'
                  }}
                </EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{
                    staff.length === 0
                      ? 'ابدأ بإضافة أول موظف في بنكك باستخدام زر "موظف جديد" أعلاه.'
                      : 'جرّب تغيير البحث أو إزالة فلتر الدور أو الحالة.'
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
    </template>

    <!-- Staff Modal -->
    <StaffModal
      v-if="showModal"
      :staff="editingStaff"
      :saving="saving"
      :server-error="serverError"
      @save="handleSave"
      @recover="openAccountRecovery"
      @close="closeModal"
    />

    <AccountRecoveryDialog
      :target="recoveryTarget"
      @close="recoveryTarget = null"
      @updated="handleRecoveryUpdated"
    />

    <!-- Deactivate Dialog -->
    <AlertDialog :open="showDeactivateConfirm" @update:open="(v) => !v && closeDeactivate()">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>إلغاء تفعيل حساب الموظف</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم منع <strong>{{ deactivatingStaff?.name }}</strong> من تسجيل الدخول إلى المنصة بعد
            إلغاء التفعيل.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <!-- Pre-check: workload impact by role -->
        <div
          v-if="deactivatingStaff?.role === UserRole.DATA_ENTRY"
          class="rounded-md border border-[var(--severity-amber)]/40 bg-[var(--severity-amber)]/5 p-3 text-sm"
          role="alert"
          data-testid="deactivate-precheck-data-entry"
        >
          <p class="font-semibold text-[var(--severity-amber)]">تحقق من المسودات النشطة</p>
          <p class="text-foreground mt-1 text-xs">
            قد تكون لدى هذا الموظف طلبات مسودة أو مُعادة للتصحيح. بعد إلغاء التفعيل لن يتمكن من
            تعديلها أو إعادة تقديمها. انقل مسؤولية هذه الطلبات قبل المتابعة.
          </p>
        </div>
        <div
          v-else-if="deactivatingStaff?.role === UserRole.BANK_REVIEWER"
          class="rounded-md border border-[var(--severity-amber)]/40 bg-[var(--severity-amber)]/5 p-3 text-sm"
          role="alert"
          data-testid="deactivate-precheck-bank-reviewer"
        >
          <p class="font-semibold text-[var(--severity-amber)]">تحقق من الطلبات قيد المراجعة</p>
          <p class="text-foreground mt-1 text-xs">
            قد تكون لدى هذا المراجع طلبات قيد المراجعة حاليا. بعد إلغاء التفعيل لن يتمكن من إكمالها.
            تأكد من توفر مراجع آخر نشط قبل المتابعة.
          </p>
        </div>

        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel :disabled="deactivating">إلغاء</AlertDialogCancel>
          <Button
            variant="destructive"
            class="btn-danger"
            :disabled="deactivating"
            @click="confirmDeactivate"
          >
            {{ deactivating ? 'جارٍ إلغاء التفعيل...' : 'إلغاء تفعيل الحساب' }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
