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
import { AlertCircle, KeyRound, Plus, SearchX, ShieldOff, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { z } from 'zod'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import type { GovernanceUser } from '@/types/models'
import { useIdentityUsers } from '@/composables/useIdentityUsers'
import { useOrganizations } from '@/composables/useOrganizations'
import { useTeams } from '@/composables/useTeams'
import { useGovernanceRoles } from '@/composables/useGovernanceRoles'
import { useGovernanceBanks } from '@/composables/useGovernanceBanks'
import { useAuthStore } from '@/stores/auth.store'
import { useTableExport } from '@/composables/useTableExport'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import {
  DataTable,
  DataTableBulkExport,
  DataTableColumnHeader,
  DataTableExport,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableRowActions,
  DataTableToolbar,
  DataTableViewOptions,
  type RowAction,
} from '@/components/ui/data-table'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'

const props = defineProps<{ audience: 'committee' | 'bank' }>()

const auth = useAuthStore()
const { organizations, fetchOrganizations } = useOrganizations()
const { teams, fetchTeams } = useTeams()
const { roles, fetchRoles } = useGovernanceRoles()
const { banks, fetchBanks } = useGovernanceBanks()
const { users, loading, error, fetchUsers, createUser, deactivateUser, resetPassword, resetMfa } =
  useIdentityUsers()
const { exportToCSV, exportToExcel, exportToJSON } = useTableExport()

const query = ref('')
const dialogOpen = ref(false)
const saving = ref(false)
const orgMissing = ref(false)
const columnVisibility = ref<VisibilityState>({ bank: props.audience === 'bank' })
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})

/* ------------------------------------------------------------------ */
/* Form setup                                                         */
/* ------------------------------------------------------------------ */

const userSchema = toTypedSchema(
  z
    .object({
      organization_id: z.number().int().positive('المؤسسة مطلوبة'),
      team_id: z.number().int().positive('الفريق مطلوب'),
      role_id: z.number().int().positive('الدور مطلوب'),
      bank_id: z.number().int().positive().nullable(),
      name: z.string().min(2, 'الاسم مطلوب (حرفان على الأقل)'),
      email: z.string().email('بريد إلكتروني غير صالح'),
      phone: z.string().optional(),
      password: z.string().min(8, 'كلمة المرور 8 أحرف على الأقل'),
    })
    .superRefine((values, ctx) => {
      const org = organizations.value.find((item) => item.id === values.organization_id)
      const isBankOrg = org?.code === 'commercial_banks'
      if (isBankOrg && !values.bank_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['bank_id'], message: 'البنك مطلوب' })
      }
      if (!isBankOrg && values.bank_id) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['bank_id'],
          message: 'لا يسمح بتحديد بنك لهذه المؤسسة',
        })
      }
    }),
)

const form = useForm({ validationSchema: userSchema })

const organizationId = ref<number>(0)
const selectedOrganization = computed(() =>
  organizations.value.find((item) => item.id === organizationId.value),
)
const bankRequired = computed(() => selectedOrganization.value?.code === 'commercial_banks')

watch(organizationId, async (value) => {
  form.setFieldValue('organization_id', value)
  form.setFieldValue('team_id', 0)
  form.setFieldValue('role_id', 0)
  form.setFieldValue('bank_id', null)
  if (value) await Promise.all([fetchTeams(Number(value)), fetchRoles(Number(value))])
})

/* Bridge computed for form org_id <-> Select string value */
const formOrgId = computed({
  get: () => {
    const val = form.values.organization_id
    return val ? String(val) : ''
  },
  set: (value: string) => {
    const num = value ? Number(value) : 0
    organizationId.value = num
  },
})

const formTeamId = computed({
  get: () => {
    const val = form.values.team_id
    return val ? String(val) : ''
  },
  set: (value: string) => {
    form.setFieldValue('team_id', value ? Number(value) : 0)
  },
})

const formRoleId = computed({
  get: () => {
    const val = form.values.role_id
    return val ? String(val) : ''
  },
  set: (value: string) => {
    form.setFieldValue('role_id', value ? Number(value) : 0)
  },
})

const formBankId = computed({
  get: () => {
    const val = form.values.bank_id
    return val ? String(val) : ''
  },
  set: (value: string) => {
    form.setFieldValue('bank_id', value ? Number(value) : null)
  },
})

/* ------------------------------------------------------------------ */
/* Data & computed                                                    */
/* ------------------------------------------------------------------ */

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return users.value
  return users.value.filter(
    (u) =>
      u.name.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q) ||
      (u.organization?.name ?? '').toLowerCase().includes(q) ||
      (u.team?.name ?? '').toLowerCase().includes(q) ||
      (u.role?.name ?? '').toLowerCase().includes(q),
  )
})

const stats = computed(() => ({
  total: users.value.length,
  active: users.value.filter((u) => u.is_active).length,
  inactive: users.value.filter((u) => !u.is_active).length,
}))

const hasActiveFilters = computed(
  () => columnFilters.value.length > 0 || query.value.trim().length > 0,
)
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)
const noUsers = computed(() => !loading.value && users.value.length === 0 && !error.value)

/* ------------------------------------------------------------------ */
/* Table configuration                                                */
/* ------------------------------------------------------------------ */

const statusOptions = [
  { label: 'نشط', value: 'true' },
  { label: 'غير نشط', value: 'false' },
]

// Faceted filter options derived from the loaded users, so admins can narrow a
// large committee/bank roster by role or team without leaving the toolbar row.
const roleFilterOptions = computed(() => {
  const names = new Set<string>()
  for (const u of users.value) if (u.role?.name) names.add(u.role.name)
  return [...names].sort().map((name) => ({ label: name, value: name }))
})
const teamFilterOptions = computed(() => {
  const names = new Set<string>()
  for (const u of users.value) if (u.team?.name) names.add(u.team.name)
  return [...names].sort().map((name) => ({ label: name, value: name }))
})

const COLUMN_LABELS: Record<string, string> = {
  organization: 'المؤسسة',
  team: 'الفريق',
  role: 'الدور',
  bank: 'البنك',
  is_active: 'الحالة',
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

function isSelf(user: GovernanceUser): boolean {
  return user.id === auth.user?.id
}

async function onResetPassword(user: GovernanceUser): Promise<void> {
  try {
    const password = await resetPassword(user)
    toast.success(`كلمة المرور المؤقتة: ${password}`, { duration: 15000 })
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر إعادة تعيين كلمة المرور'))
  }
}

async function onResetMfa(user: GovernanceUser): Promise<void> {
  try {
    await resetMfa(user)
    toast.success('تم إعادة تعيين المصادقة الثنائية')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر إعادة تعيين المصادقة الثنائية'))
  }
}

async function onDeactivate(user: GovernanceUser): Promise<void> {
  try {
    await deactivateUser(user)
    toast.success('تم إيقاف المستخدم')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر إيقاف المستخدم'))
  }
}

const userActions: RowAction<GovernanceUser>[] = [
  { label: 'إعادة كلمة المرور', icon: KeyRound, onClick: (row) => onResetPassword(row.original) },
  { label: 'إعادة MFA', icon: ShieldOff, onClick: (row) => onResetMfa(row.original) },
  {
    label: 'إيقاف',
    destructive: true,
    hidden: (row) => isSelf(row.original) || !row.original.is_active,
    confirm: {
      title: 'تأكيد إيقاف المستخدم',
      description: 'سيتم إيقاف حساب المستخدم وإنهاء جلساته الحالية.',
      confirmLabel: 'تأكيد الإيقاف',
    },
    onClick: (row) => onDeactivate(row.original),
  },
]

const exportColumns = [
  { key: 'name', columnId: 'user', label: 'الاسم' },
  { key: 'email', label: 'البريد الإلكتروني' },
  {
    key: 'organization',
    label: 'المؤسسة',
    format: (_v: any, row: GovernanceUser) => row.organization?.name ?? '—',
  },
  {
    key: 'team',
    label: 'الفريق',
    format: (_v: any, row: GovernanceUser) => row.team?.name ?? '—',
  },
  {
    key: 'role',
    label: 'الدور',
    format: (_v: any, row: GovernanceUser) => row.role?.name ?? '—',
  },
  {
    key: 'bank',
    label: 'البنك',
    format: (_v: any, row: GovernanceUser) => row.bank?.name_ar ?? '—',
  },
  {
    key: 'is_active',
    label: 'الحالة',
    format: (_v: any, row: GovernanceUser) => (row.is_active ? 'نشط' : 'غير نشط'),
  },
]

const columns: ColumnDef<GovernanceUser>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(!!value),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'aria-label': `تحديد ${row.original.name}`,
      }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'user',
    header: 'المستخدم',
    enableHiding: false,
    cell: ({ row }) => {
      const user = row.original
      const initial = user.name.trim().charAt(0)
      return h('div', { class: 'flex items-center gap-2' }, [
        h(
          'div',
          {
            class:
              'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary text-xs font-semibold leading-5',
          },
          initial,
        ),
        h('div', {}, [
          h(
            'div',
            { class: 'font-section text-sm font-semibold leading-5 text-foreground' },
            user.name,
          ),
          h('div', { class: 'text-xs leading-5 text-muted-foreground' }, user.email),
        ]),
      ])
    },
  },
  {
    id: 'organization',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'المؤسسة' }),
    accessorFn: (row) => row.organization?.name ?? '—',
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.organization?.name ?? '—'),
  },
  {
    id: 'team',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'الفريق' }),
    accessorFn: (row) => row.team?.name ?? '—',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.team?.name ?? '—'),
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.team?.name ?? '—'),
  },
  {
    id: 'role',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'الدور' }),
    accessorFn: (row) => row.role?.name ?? '—',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.role?.name ?? '—'),
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.role?.name ?? '—'),
  },
  {
    id: 'bank',
    header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'البنك' }),
    accessorFn: (row) => row.bank?.name_ar ?? '—',
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-foreground' }, row.original.bank?.name_ar ?? '—'),
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
    cell: ({ row }) => h(DataTableRowActions as any, { row, actions: userActions }),
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
  onColumnVisibilityChange: (updater) =>
    (columnVisibility.value =
      typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  onColumnFiltersChange: (updater) =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onRowSelectionChange: (updater) =>
    (rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater),
  state: {
    get columnVisibility() {
      return columnVisibility.value
    },
    get columnFilters() {
      return columnFilters.value
    },
    get rowSelection() {
      return rowSelection.value
    },
  },
  initialState: { pagination: { pageSize: 20 } },
})

/* ------------------------------------------------------------------ */
/* Toolbar helpers                                                    */
/* ------------------------------------------------------------------ */

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

function buildExportFilename(): string {
  return `users-${props.audience}-${new Date().toISOString().slice(0, 10)}`
}

function clearBulkSelection() {
  table.resetRowSelection()
}

function getSelectedUsers(): GovernanceUser[] {
  return table.getFilteredSelectedRowModel().rows.map((r) => r.original)
}

function bulkExportCSV() {
  const rows = getSelectedUsers()
  if (!rows.length) return
  exportToCSV(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportExcel() {
  const rows = getSelectedUsers()
  if (!rows.length) return
  exportToExcel(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

function bulkExportJSON() {
  const rows = getSelectedUsers()
  if (!rows.length) return
  exportToJSON(
    rows as any as Record<string, any>[],
    exportColumns as any,
    `${buildExportFilename()}-selected`,
  )
}

/* ------------------------------------------------------------------ */
/* Create dialog                                                      */
/* ------------------------------------------------------------------ */

function openCreate() {
  form.resetForm({
    values: {
      organization_id: organizationId.value,
      team_id: 0,
      role_id: 0,
      bank_id: null,
      name: '',
      email: '',
      phone: '',
      password: '',
    },
  })
  dialogOpen.value = true
}

function closeForm() {
  dialogOpen.value = false
  form.resetForm()
}

const onSubmit = form.handleSubmit(async (values) => {
  saving.value = true
  try {
    await createUser(values)
    toast.success('تم إنشاء المستخدم')
    closeForm()
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر إنشاء المستخدم'))
  } finally {
    saving.value = false
  }
})

/* ------------------------------------------------------------------ */
/* Lifecycle                                                          */
/* ------------------------------------------------------------------ */

onMounted(async () => {
  await Promise.all([fetchOrganizations(), fetchBanks()])
  const targetCode = props.audience === 'bank' ? 'commercial_banks' : 'national_committee'
  const targetOrg = organizations.value.find((item) => item.code === targetCode)
  if (!targetOrg) {
    orgMissing.value = true
    return
  }
  organizationId.value = targetOrg.id
  await fetchUsers(props.audience === 'bank' ? { organization_id: targetOrg.id } : {})
})
</script>

<template>
  <ScreenGuard screen="users">
    <div>
      <h1 class="page-title sr-only">
        {{ audience === 'bank' ? 'مستخدمو البنك' : 'مستخدمو اللجنة والإدارة' }}
      </h1>
      <PageHeader
        :title="audience === 'bank' ? 'مستخدمو البنك' : 'مستخدمو اللجنة والإدارة'"
        subtitle="إدارة الهوية المؤسسية للمستخدمين، إنشاء حسابات جديدة وتعديل الصلاحيات"
        :breadcrumbs="[
          { label: 'الرئيسية', to: '/' },
          { label: audience === 'bank' ? 'مستخدمو البنك' : 'المستخدمون' },
        ]"
      >
        <template #actions>
          <Button size="sm" class="btn-primary h-8" :disabled="orgMissing" @click="openCreate">
            <Plus class="h-4 w-4" />
            <span class="hidden lg:inline">مستخدم جديد</span>
          </Button>
        </template>
      </PageHeader>

      <Alert v-if="error" variant="destructive" role="alert" class="mb-6">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل المستخدمين</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
        <AlertAction>
          <Button variant="outline" size="sm" @click="fetchUsers()">إعادة المحاولة</Button>
        </AlertAction>
      </Alert>

      <Alert v-if="orgMissing" variant="destructive" role="alert" class="mb-6">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل المؤسسة</AlertTitle>
        <AlertDescription>
          لم يتم العثور على المؤسسة المطلوبة. يرجى المحاولة لاحقاً أو التواصل مع المسؤول.
        </AlertDescription>
      </Alert>

      <div class="mb-6">
        <MetricGrid :columns="3">
          <MetricCard
            label="إجمالي المستخدمين"
            :value="stats.total"
            :icon="Users"
            :active="columnFilters.length === 0"
            @click="table.resetColumnFilters()"
          />
          <MetricCard
            label="نشط"
            :value="stats.active"
            :icon="Users"
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
            :icon="Users"
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

      <div class="relative flex flex-col gap-4">
        <DataTable
          :data="filtered"
          :columns="columns"
          :loading="loading"
          :column-visibility="columnVisibility"
          :column-filters="columnFilters"
          :row-selection="rowSelection"
          @update:column-visibility="(v) => (columnVisibility = v)"
          @update:column-filters="(v) => (columnFilters = v)"
          @update:row-selection="(v) => (rowSelection = v)"
        >
          <template #toolbar="{ table: dataTable }">
            <DataTableToolbar
              :table="dataTable"
              search-placeholder="بحث بالاسم أو البريد أو المؤسسة..."
              :has-filters="hasActiveFilters"
              :selected-count="selectedCount"
              @update:search="(v) => (query = v)"
              @reset="handleReset"
              @clear-selection="clearBulkSelection"
            >
              <template #bulk-actions>
                <DataTableBulkExport
                  @csv="bulkExportCSV"
                  @excel="bulkExportExcel"
                  @json="bulkExportJSON"
                />
              </template>
              <template #filters>
                <DataTableFacetedFilter
                  v-if="dataTable.getColumn('is_active')"
                  :column="dataTable.getColumn('is_active')!"
                  title="الحالة"
                  :options="statusOptions"
                />
                <DataTableFacetedFilter
                  v-if="dataTable.getColumn('role') && roleFilterOptions.length > 1"
                  :column="dataTable.getColumn('role')!"
                  title="الدور"
                  :options="roleFilterOptions"
                />
                <DataTableFacetedFilter
                  v-if="dataTable.getColumn('team') && teamFilterOptions.length > 1"
                  :column="dataTable.getColumn('team')!"
                  title="الفريق"
                  :options="teamFilterOptions"
                />
              </template>
              <template #actions>
                <DataTableViewOptions :table="dataTable" :column-labels="COLUMN_LABELS" />
                <DataTableExport
                  :table="dataTable as any"
                  :export-columns="exportColumns as any"
                  :filename="buildExportFilename()"
                  :formats="['csv', 'tsv', 'json', 'excel', 'pdf']"
                  :respect-column-visibility="true"
                />
              </template>
            </DataTableToolbar>
          </template>
          <template #empty>
            <Empty class="bg-muted/20 min-h-[280px] rounded-xl border border-dashed">
              <EmptyHeader>
                <div
                  class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
                >
                  <SearchX class="size-5" />
                </div>
                <EmptyTitle>{{
                  noUsers ? 'لا يوجد مستخدمون مسجّلون بعد' : 'لا يوجد مستخدمون مطابقون'
                }}</EmptyTitle>
              </EmptyHeader>
              <EmptyContent>
                <EmptyDescription>
                  {{
                    noUsers
                      ? 'ابدأ بإضافة أول مستخدم باستخدام زر "مستخدم جديد" أعلاه.'
                      : 'جرّب تغيير البحث أو إزالة الفلاتر لعرض المزيد من المستخدمين.'
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

      <!-- Create User Dialog -->
      <Dialog :open="dialogOpen" @update:open="(value) => !value && closeForm()">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>إضافة مستخدم جديد</DialogTitle>
            <DialogDescription>
              أدخل بيانات المستخدم الجديد واختر الفريق والدور المناسبين.
            </DialogDescription>
          </DialogHeader>

          <form class="flex flex-col gap-4 py-2" @submit="onSubmit">
            <FormField v-slot="{ componentField }" name="name">
              <FormItem>
                <FormLabel>الاسم *</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="الاسم الكامل" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="email">
              <FormItem>
                <FormLabel>البريد الإلكتروني *</FormLabel>
                <FormControl>
                  <Input
                    v-bind="componentField"
                    type="email"
                    placeholder="user@example.com"
                    dir="ltr"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="phone">
              <FormItem>
                <FormLabel>الهاتف</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="رقم الهاتف" dir="ltr" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="password">
              <FormItem>
                <FormLabel>كلمة المرور المؤقتة *</FormLabel>
                <FormControl>
                  <Input
                    v-bind="componentField"
                    type="password"
                    placeholder="8 أحرف على الأقل"
                    dir="ltr"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField name="organization_id">
              <FormItem>
                <FormLabel>المؤسسة *</FormLabel>
                <Select v-model="formOrgId">
                  <FormControl>
                    <SelectTrigger class="w-full">
                      <SelectValue placeholder="اختر المؤسسة" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="org in organizations" :key="org.id" :value="String(org.id)">
                      {{ org.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-if="bankRequired" name="bank_id">
              <FormItem>
                <FormLabel>البنك *</FormLabel>
                <Select v-model="formBankId">
                  <FormControl>
                    <SelectTrigger class="w-full">
                      <SelectValue placeholder="اختر البنك" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="bank in banks" :key="bank.id" :value="String(bank.id)">
                      {{ bank.name_ar }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField name="role_id">
              <FormItem>
                <FormLabel>الدور *</FormLabel>
                <Select v-model="formRoleId">
                  <FormControl>
                    <SelectTrigger class="w-full">
                      <SelectValue placeholder="اختر الدور" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="role in roles" :key="role.id" :value="String(role.id)">
                      {{ role.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField name="team_id">
              <FormItem>
                <FormLabel>الفريق *</FormLabel>
                <Select v-model="formTeamId">
                  <FormControl>
                    <SelectTrigger class="w-full">
                      <SelectValue placeholder="اختر الفريق" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="team in teams" :key="team.id" :value="String(team.id)">
                      {{ team.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <DialogFooter>
              <Button type="button" variant="outline" @click="closeForm">إلغاء</Button>
              <Button type="submit" :disabled="saving || form.isSubmitting.value">
                {{ saving ? 'جارٍ الحفظ...' : 'إضافة' }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </ScreenGuard>
</template>
