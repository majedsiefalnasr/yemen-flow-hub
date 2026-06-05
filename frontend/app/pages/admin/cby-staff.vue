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
import {
  AlertCircle,
  AlertTriangle,
  Archive,
  ExternalLink,
  MoreHorizontal,
  Plus,
  PowerOff,
  RefreshCw,
  SearchX,
  ShieldCheck,
  UserCog,
  Zap,
} from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROLE_LABELS, CBY_ROLES, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { User, Bank } from '@/types/models'
import { useUsers, type CreateUserPayload, type UpdateUserPayload } from '@/composables/useUsers'
import { useBanks } from '@/composables/useBanks'
import { useTableExport } from '@/composables/useTableExport'
import { useAuthStore } from '@/stores/auth.store'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
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
import { Checkbox } from '@/components/ui/checkbox'
import {
  DataTable,
  DataTableBulkExport,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import AvatarPicker from '@/components/shared/AvatarPicker.vue'
import BoringAvatar from '@/components/shared/BoringAvatar.vue'
import {
  DEFAULT_AVATAR_VARIANT,
  persistUserAvatar,
  readUserAvatar,
  type AvatarVariant,
} from '@/composables/useUserAvatar'

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
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()
const { notify, error: toastError } = useToast()

const query = ref('')
const columnFilters = ref<ColumnFiltersState>([])
const createOpen = ref(false)
const editing = ref<User | null>(null)
const viewing = ref<User | null>(null)
const saving = ref(false)
const loading = ref(true)
const fetchError = ref(false)
const staffUsers = ref<User[]>([])
const deactivateTarget = ref<User | null>(null)
const deactivateBlocked = ref<string | null>(null)
const banksData = ref<Bank[]>([])

const form = reactive<StaffForm>({
  name: '',
  email: '',
  role: UserRole.SUPPORT_COMMITTEE,
  password: '',
})

const avatarVariant = ref<AvatarVariant>(DEFAULT_AVATAR_VARIANT)

function resolveBankName(user: User): string {
  if (user.bank_name_ar) return user.bank_name_ar
  if (user.bank_name_en) return user.bank_name_en
  if (user.bank_name) return user.bank_name
  if (user.bank_id) {
    const b = banksData.value.find((bk) => bk.id === user.bank_id)
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
    staffUsers.value = results.filter((u) => STAFF_ROLES.includes(u.role))
    banksData.value = bankResults
  } catch {
    fetchError.value = true
  } finally {
    loading.value = false
  }
}

onMounted(loadStaff)

// Pre-filter by search query — search column filtering handled here,
// faceted column filters handled by TanStack
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return staffUsers.value
  return staffUsers.value.filter(
    (u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q),
  )
})

const stats = computed(() => ({
  total: staffUsers.value.length,
  active: staffUsers.value.filter((u) => u.is_active).length,
  inactive: staffUsers.value.filter((u) => !u.is_active).length,
}))

const activeDirectors = computed(() =>
  staffUsers.value.filter((u) => u.role === UserRole.COMMITTEE_DIRECTOR && u.is_active),
)
const activeExecutiveMembers = computed(() =>
  staffUsers.value.filter((u) => u.role === UserRole.EXECUTIVE_MEMBER && u.is_active),
)

const rowSelection = ref<Record<string, boolean>>({})
const columnVisibility = ref<VisibilityState>({
  bank: false,
  last_seen: false,
})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)

function clearSelection() {
  table.resetRowSelection()
}

const formValid = computed(
  () =>
    form.name.trim().length > 0 &&
    /\S+@\S+\.\S+/.test(form.email) &&
    (Boolean(editing.value) || form.password.length >= 8),
)

const roleSelectHint = computed(() => {
  if (form.role === UserRole.COMMITTEE_DIRECTOR) {
    return 'هذا الدور مخصص لمدير اللجنة فقط. لا يمكن جمعه مع عضوية اللجنة التنفيذية على الحساب نفسه.'
  }
  if (form.role === UserRole.EXECUTIVE_MEMBER) {
    return 'هذا الدور مخصص لعضو اللجنة التنفيذية فقط. لا يمكن جمعه مع دور مدير اللجنة على الحساب نفسه.'
  }
  return null
})

function resetForm(initial?: User) {
  form.name = initial?.name ?? ''
  form.email = initial?.email ?? ''
  form.role = initial?.role ?? UserRole.SUPPORT_COMMITTEE
  form.password = ''
  if (initial?.email) {
    const stored = readUserAvatar(initial.email)
    avatarVariant.value = (initial.avatar_variant as AvatarVariant | undefined) ?? stored.variant
  } else {
    avatarVariant.value = DEFAULT_AVATAR_VARIANT
  }
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
        avatar_variant: avatarVariant.value,
        ...(form.password ? { password: form.password } : {}),
      }
      const updated = await updateUser(editing.value.id, payload)
      staffUsers.value = staffUsers.value.map((u) => (u.id === editing.value!.id ? updated : u))
      persistUserAvatar(updated.email, { variant: avatarVariant.value })
      // When the admin is editing their own row, mirror the new variant onto
      // the auth store so surfaces that read `auth.user.avatar_variant`
      // (topbar, NavUser sidebar, profile screen) refresh immediately without
      // waiting for a page reload to re-fetch the user.
      if (authStore.user && authStore.user.id === updated.id) {
        authStore.user = { ...authStore.user, ...updated, avatar_variant: avatarVariant.value }
      }
      notify('تم حفظ تعديلات المستخدم')
    } else {
      const payload: CreateUserPayload = {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        role: form.role,
        bank_id: null,
        is_active: true,
        avatar_variant: avatarVariant.value,
      }
      const created = await createUser(payload)
      staffUsers.value = [...staffUsers.value, created]
      persistUserAvatar(created.email, { variant: avatarVariant.value })
      notify(`تمت إضافة ${created.name}`)
    }
    closeForm()
  } catch {
    toastError('تعذّر حفظ بيانات المستخدم. راجع الحقول وأعد المحاولة.')
  } finally {
    saving.value = false
  }
}

function requestToggleActive(target: User) {
  if (!currentUser.value || target.id === currentUser.value.id) return
  if (target.is_active) {
    if (target.role === UserRole.COMMITTEE_DIRECTOR && activeDirectors.value.length <= 1) {
      deactivateBlocked.value =
        'لا يمكن إلغاء تفعيل مدير اللجنة الوحيد النشط. يجب بقاء مدير لجنة نشط واحد على الأقل لإدارة جلسات التصويت.'
      return
    }
    if (target.role === UserRole.EXECUTIVE_MEMBER && activeExecutiveMembers.value.length <= 1) {
      deactivateBlocked.value =
        'لا يمكن إلغاء تفعيل العضو التنفيذي الوحيد النشط. يجب بقاء أعضاء تصويت كافين لضمان النصاب القانوني.'
      return
    }
    deactivateBlocked.value = null
    deactivateTarget.value = target
  } else {
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
    staffUsers.value = staffUsers.value.map((u) => (u.id === target.id ? updated : u))
    notify(updated.is_active ? `تم تفعيل ${target.name}` : `تم إلغاء تفعيل ${target.name}`)
  } catch {
    toastError('تعذّر تغيير حالة المستخدم. أعد المحاولة.')
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
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (v: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(!!v),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h('div', { onClick: (e: Event) => e.stopPropagation() }, [
        h(Checkbox, {
          modelValue: row.getIsSelected(),
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
        h(BoringAvatar, {
          name: staff.name || staff.email,
          identity: staff.email,
          variant: (staff.avatar_variant as AvatarVariant | undefined) ?? undefined,
          size: 32,
          square: true,
          class: 'size-8 shrink-0 overflow-hidden rounded-md',
        }),
        h('div', { class: 'flex flex-col gap-0.5' }, [
          h(
            'button',
            {
              type: 'button',
              class:
                'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
              title: 'معاينة سريعة',
              onClick: (e: Event) => {
                e.stopPropagation()
                viewing.value = staff
              },
            },
            staff.name,
          ),
          h('span', { class: 'text-xs text-muted-foreground', dir: 'ltr' }, staff.email),
        ]),
      ])
    },
  },
  {
    accessorKey: 'role',
    header: 'الدور',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.role),
    cell: ({ row }) => h(Badge, { variant: 'secondary' }, () => ROLE_LABELS[row.original.role]),
  },
  {
    id: 'bank',
    header: 'الجهة',
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.bank_id)),
    cell: ({ row }) => {
      const name = resolveBankName(row.original)
      return h('span', { class: 'text-sm text-muted-foreground' }, name || 'غير متاح')
    },
  },
  {
    id: 'last_seen',
    header: 'آخر ظهور',
    cell: ({ row }) => {
      const ts = row.original.last_login_at
      if (!ts)
        return h(
          'span',
          { class: 'text-sm text-muted-foreground', 'data-cell': 'last-seen' },
          'لم يسجل الدخول بعد',
        )
      return h(
        'span',
        { class: 'text-sm text-muted-foreground', 'data-cell': 'last-seen' },
        new Date(ts).toLocaleDateString('ar-EG'),
      )
    },
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    filterFn: (row, _id, value: string[]) => value.includes(String(row.original.is_active)),
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
        roleNavItems.push(
          h(
            DropdownMenuItem,
            {
              class: 'gap-1.5 text-primary',
              onClick: () => navigateTo('/requests'),
            },
            () => [h(ExternalLink, { class: 'h-3.5 w-3.5' }), 'طابور المراجعة'],
          ),
        )
      } else if (
        staff.role === UserRole.EXECUTIVE_MEMBER ||
        staff.role === UserRole.COMMITTEE_DIRECTOR
      ) {
        roleNavItems.push(
          h(
            DropdownMenuItem,
            {
              class: 'gap-1.5 text-[var(--voting)]',
              onClick: () => navigateTo('/voting'),
            },
            () => [h(ExternalLink, { class: 'h-3.5 w-3.5' }), 'جلسات التصويت'],
          ),
        )
      }
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
                  h(
                    DropdownMenuItem,
                    { onClick: () => (viewing.value = staff) },
                    () => 'عرض التفاصيل',
                  ),
                  h(DropdownMenuItem, { onClick: () => openEdit(staff) }, () => 'تعديل'),
                  ...roleNavItems,
                  ...(!isSelf
                    ? [
                        h(DropdownMenuSeparator),
                        h(
                          DropdownMenuItem,
                          {
                            class: staff.is_active
                              ? 'text-destructive'
                              : 'text-[var(--severity-green)]',
                            onClick: () => requestToggleActive(staff),
                          },
                          () => (staff.is_active ? 'إلغاء تفعيل' : 'تفعيل'),
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

const CBY_STAFF_COLUMN_LABELS: Record<string, string> = {
  user: 'المستخدم',
  role: 'الدور',
  bank: 'الجهة',
  last_seen: 'آخر ظهور',
  is_active: 'الحالة',
}

const roleFilterOptions = STAFF_ROLES.map((r) => ({ label: ROLE_LABELS[r], value: r }))
const statusFilterOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'غير نشط', value: 'false' },
]
const bankFilterOptions = computed(() =>
  banksData.value.map((b) => ({ label: b.name_ar || b.name_en, value: String(b.id) })),
)

const exportCols = [
  { key: 'name', label: 'الاسم' },
  { key: 'email', label: 'البريد الإلكتروني' },
  {
    key: 'role',
    label: 'الدور',
    format: (_value: any, row: User) => ROLE_LABELS[row.role] ?? row.role,
  },
  {
    key: 'bank_id',
    label: 'الجهة',
    format: (_value: any, row: User) => resolveBankName(row),
  },
  {
    key: 'is_active',
    label: 'الحالة',
    format: (_value: any, row: User) => (row.is_active ? 'نشط' : 'غير نشط'),
  },
  {
    key: 'last_login_at',
    label: 'آخر ظهور',
    format: (_value: any, row: User) =>
      row.last_login_at
        ? new Date(row.last_login_at).toLocaleDateString('ar-EG')
        : 'لم يسجل الدخول بعد',
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
  onColumnFiltersChange: (updater) =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onRowSelectionChange: (updater) =>
    (rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater),
  onColumnVisibilityChange: (updater) =>
    (columnVisibility.value =
      typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  state: {
    get columnFilters() {
      return columnFilters.value
    },
    get rowSelection() {
      return rowSelection.value
    },
    get columnVisibility() {
      return columnVisibility.value
    },
  },
  initialState: { pagination: { pageSize: 20 } },
})

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `cby-staff-${new Date().toISOString().slice(0, 10)}`
}

function getSelectedUsers(): User[] {
  return table.getFilteredSelectedRowModel().rows.map((r) => r.original)
}

function bulkExportCSV() {
  const rows = getSelectedUsers()
  if (!rows.length) return
  exportToCSV(
    rows as any as Record<string, any>[],
    exportCols as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportExcel() {
  const rows = getSelectedUsers()
  if (!rows.length) return
  exportToExcel(
    rows as any as Record<string, any>[],
    exportCols as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportJSON() {
  const rows = getSelectedUsers()
  if (!rows.length) return
  exportToJSON(
    rows as any as Record<string, any>[],
    exportCols as any,
    `${buildExportFilename()}-selected`,
  )
}

const bulkToggling = ref(false)
async function bulkToggleStatus(activate: boolean) {
  const rows = getSelectedUsers().filter(
    (u) => u.is_active !== activate && u.id !== currentUser.value?.id,
  )
  if (!rows.length) return
  bulkToggling.value = true
  try {
    await Promise.all(rows.map((u) => doToggleActive(u)))
    clearSelection()
    notify(activate ? `تم تفعيل ${rows.length} مستخدم` : `تم إلغاء تفعيل ${rows.length} مستخدم`)
  } catch {
    toastError('فشل تغيير الحالة لبعض المستخدمين')
  } finally {
    bulkToggling.value = false
  }
}

const archiveConfirmOpen = ref(false)
const archiving = ref(false)
async function bulkArchive() {
  const rows = getSelectedUsers().filter((u) => u.id !== currentUser.value?.id)
  if (!rows.length) return
  archiving.value = true
  try {
    await Promise.all(rows.filter((u) => u.is_active).map((u) => doToggleActive(u)))
    clearSelection()
    notify(`تم أرشفة ${rows.length} مستخدم`)
  } catch {
    toastError('فشل أرشفة بعض المستخدمين')
  } finally {
    archiving.value = false
  }
}
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <PageHeader
      title="مستخدمي النظام"
      subtitle="إدارة موظفي البنك المركزي واللجان المساندة والتنفيذية ومسؤولي النظام"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'مستخدمي النظام' }]"
    >
      <template #actions>
        <Button size="sm" class="btn-primary h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">مستخدم جديد</span>
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards — clicking sets column filters -->
    <div class="mb-6">
      <MetricGrid :columns="3">
        <MetricCard
          label="إجمالي المستخدمين"
          :value="stats.total"
          :icon="UserCog"
          :active="columnFilters.length === 0"
          @click="table.resetColumnFilters()"
        />
        <MetricCard
          label="نشط"
          :value="stats.active"
          :icon="ShieldCheck"
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
          :icon="AlertCircle"
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
      <DataTable
        :data="filtered"
        :columns="columns"
        :loading="loading"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        :row-selection="rowSelection"
        row-class="group/row"
        @update:column-filters="(v) => (columnFilters = v)"
        @update:column-visibility="(v) => (columnVisibility = v)"
        @update:row-selection="(v) => (rowSelection = v)"
      >
        <template #toolbar="{ table: dataTable }">
          <DataTableToolbar
            :table="dataTable"
            search-placeholder="بحث بالاسم أو البريد الإلكتروني"
            :has-filters="hasActiveFilters"
            :selected-count="selectedCount"
            @update:search="(v) => (query = v)"
            @reset="handleReset"
            @clear-selection="clearSelection"
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
                إلغاء تفعيل
              </Button>
              <Button
                variant="outline"
                size="sm"
                class="text-destructive hover:text-destructive h-7 gap-1.5 text-xs"
                :disabled="archiving"
                @click="archiveConfirmOpen = true"
              >
                <Archive class="size-3.5" />
                أرشفة
              </Button>
            </template>
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
              <DataTableFacetedFilter
                v-if="dataTable.getColumn('bank') && bankFilterOptions.length > 0"
                :column="dataTable.getColumn('bank')!"
                title="الجهة"
                :options="bankFilterOptions"
              />
            </template>
            <template #actions>
              <DataTableViewOptions :table="dataTable" :column-labels="CBY_STAFF_COLUMN_LABELS" />
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
          <Empty
            data-empty-state-variant="cby-staff"
            class="bg-muted/20 min-h-[280px] rounded-xl border border-dashed"
          >
            <EmptyHeader>
              <div
                class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
              >
                <SearchX class="size-5" />
              </div>
              <EmptyTitle>
                {{
                  staffUsers.length === 0
                    ? 'لا يوجد مستخدمو نظام للبنك المركزي بعد'
                    : 'لا توجد نتائج مطابقة'
                }}
              </EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                {{
                  staffUsers.length === 0
                    ? 'ابدأ بإضافة أول مستخدم للبنك المركزي باستخدام زر "مستخدم جديد" أعلاه.'
                    : 'جرّب تغيير البحث أو إزالة فلتر الدور أو الجهة أو الحالة.'
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

    <!-- Create / Edit Dialog -->
    <Dialog :open="createOpen || Boolean(editing)" @update:open="(value) => !value && closeForm()">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم نظام' }}</DialogTitle>
          <DialogDescription>
            أضف أو عدّل مستخدمي البنك المركزي للجان وإدارة النظام.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2">
          <div class="border-border bg-muted/20 rounded-lg border p-3">
            <AvatarPicker
              v-model="avatarVariant"
              :seed="form.email || form.name || 'new-user'"
              :size="44"
              label="مظهر الصورة الرمزية"
            />
          </div>
          <div class="space-y-1.5">
            <Label>الاسم <span class="text-destructive">*</span></Label>
            <Input v-model="form.name" placeholder="مثال: أحمد محمد" />
          </div>
          <div class="space-y-1.5">
            <Label>البريد الإلكتروني <span class="text-destructive">*</span></Label>
            <Input v-model="form.email" type="email" placeholder="name@cby.gov.ye" />
          </div>
          <div class="space-y-1.5">
            <Label>{{
              editing ? 'كلمة المرور (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور المؤقتة *'
            }}</Label>
            <Input
              v-model="form.password"
              type="password"
              :placeholder="editing ? 'اتركها فارغة دون تغيير' : '8 أحرف على الأقل'"
            />
          </div>
          <div class="space-y-1.5">
            <Label>الدور <span class="text-destructive">*</span></Label>
            <Select v-model="form.role">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="role in STAFF_ROLES" :key="role" :value="role">{{
                  ROLE_LABELS[role]
                }}</SelectItem>
              </SelectContent>
            </Select>
            <p
              v-if="roleSelectHint"
              class="flex items-start gap-1.5 text-xs text-[var(--severity-amber)]"
            >
              <AlertTriangle class="mt-px h-3.5 w-3.5 shrink-0" />
              {{ roleSelectHint }}
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button :disabled="!formValid || saving" @click="saveStaff">
            {{ saving ? 'جارٍ حفظ المستخدم...' : editing ? 'حفظ التعديلات' : 'إضافة المستخدم' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Deactivation blocked: critical-role protection -->
    <AlertDialog
      :open="Boolean(deactivateBlocked)"
      @update:open="(value) => !value && (deactivateBlocked = null)"
    >
      <AlertDialogContent>
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
    <AlertDialog
      :open="Boolean(deactivateTarget)"
      @update:open="(value) => !value && (deactivateTarget = null)"
    >
      <AlertDialogContent v-if="deactivateTarget">
        <AlertDialogHeader>
          <AlertDialogTitle>إلغاء تفعيل مستخدم البنك المركزي</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم منع <strong>{{ deactivateTarget.name }}</strong> ({{
              ROLE_LABELS[deactivateTarget.role]
            }}) من تسجيل الدخول.
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
          <AlertDialogAction
            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            @click="doToggleActive(deactivateTarget)"
          >
            إلغاء تفعيل المستخدم
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- View Dialog -->
    <Dialog :open="Boolean(viewing)" @update:open="(value) => !value && (viewing = null)">
      <DialogContent v-if="viewing" class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <UserCog class="text-primary h-5 w-5" />
            {{ viewing.name }}
          </DialogTitle>
          <DialogDescription>تفاصيل حساب المستخدم وصلاحياته</DialogDescription>
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
          <div
            v-if="viewing.last_login_at"
            class="flex items-center justify-between gap-3 border-b pb-2"
          >
            <span class="text-muted-foreground">آخر تسجيل دخول</span>
            <span class="text-start font-medium">{{
              new Date(viewing.last_login_at).toLocaleString('ar-EG')
            }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="archiveConfirmOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>أرشفة المستخدمين المحددين</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم إلغاء تفعيل {{ selectedCount }} مستخدم وأرشفتهم. يمكن إعادة تفعيلهم لاحقا عند
            الحاجة.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="bulkArchive">أرشفة المستخدمين</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
