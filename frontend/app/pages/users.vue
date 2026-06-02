<script setup lang="ts">
import type { ColumnDef, PaginationState } from '@tanstack/vue-table'
import { ref, reactive, computed, watch, onMounted, h } from 'vue'
import {
  AlertTriangle,
  MoreHorizontal, Plus, Search, SearchX, X,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '../types/enums'
import type { User, Bank } from '../types/models'
import { useUsers } from '../composables/useUsers'
import { useBanks } from '../composables/useBanks'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS, BANK_ROLES, CBY_ROLES, BANK_ADMIN_MANAGED_ROLES } from '../constants/workflow'
import type { CreateUserPayload, UpdateUserPayload } from '../composables/useUsers'
import AvatarPicker from '../components/shared/AvatarPicker.vue'
import BoringAvatar from '../components/shared/BoringAvatar.vue'
import { DEFAULT_AVATAR_VARIANT, persistUserAvatar, readUserAvatar, type AvatarVariant } from '../composables/useUserAvatar'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'
import { DataTablePagination } from '@/components/ui/data-table'
import DataTable from '@/components/ui/data-table/DataTable.vue'
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
import { Checkbox } from '@/components/ui/checkbox'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
})

const { fetchUsersPaginated, createUser, updateUser } = useUsers()
const { fetchBanks } = useBanks()
const auth = useAuthStore()

const users = ref<User[]>([])
const usersMeta = ref<{ last_page: number; total: number } | null>(null)
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

const avatarVariant = ref<AvatarVariant>(DEFAULT_AVATAR_VARIANT)

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

// Server-side paginated load (same pattern as the requests page).
async function loadUsers() {
  loading.value = true
  error.value = null
  try {
    const q = query.value.trim()
    const result = await fetchUsersPaginated({
      page: urlUserPage.value,
      per_page: urlUserPageSize.value,
      ...(q ? { search: q } : {}),
    })
    users.value = result.data
    usersMeta.value = { last_page: result.meta.last_page, total: result.meta.total }
  }
  catch {
    error.value = 'تعذر تحميل بيانات المستخدمين الآن. أعد المحاولة بعد قليل.'
  }
  finally {
    loading.value = false
  }
}

async function loadData() {
  // Banks are loaded once for the form's bank selector; users page-by-page.
  banks.value = await fetchBanks().catch(() => [])
  await loadUsers()
}

function openCreate() {
  editingUser.value = null
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = isBankAdmin.value ? UserRole.DATA_ENTRY : ''
  form.bank_id = isBankAdmin.value ? auth.user?.bank_id ?? null : null
  form.is_active = true
  avatarVariant.value = DEFAULT_AVATAR_VARIANT
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
  const stored = readUserAvatar(user.email)
  avatarVariant.value = (user.avatar_variant as AvatarVariant | undefined) ?? stored.variant
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
        avatar_variant: avatarVariant.value,
      }
      if (form.password) payload.password = form.password
      const updated = await updateUser(editingUser.value.id, payload)
      persistUserAvatar(form.email.trim(), { variant: avatarVariant.value })
      // Patch the auth store when the admin is editing their own row so the
      // topbar / sidebar avatar refresh without a full page reload.
      if (auth.user && auth.user.id === updated.id) {
        auth.user = { ...auth.user, ...updated, avatar_variant: avatarVariant.value }
      }
    }
    else {
      const payload: CreateUserPayload = {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        role: form.role as UserRole,
        bank_id: isBankAdmin.value ? auth.user?.bank_id ?? null : form.bank_id,
        is_active: form.is_active,
        avatar_variant: avatarVariant.value,
      }
      await createUser(payload)
      persistUserAvatar(form.email.trim(), { variant: avatarVariant.value })
    }
    closeModal()
    await loadUsers()
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
      formError.value = e.data?.message ?? 'تعذر حفظ بيانات المستخدم. راجع الحقول ثم أعد المحاولة.'
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
  rowSelection.value = {}
}

// URL-driven client-side pagination (same UX as the requests page).
const DEFAULT_USER_PAGE_SIZE = 20
const route = useRoute()
const router = useRouter()
const urlUserPage = computed(() => Number(route.query.page ?? 1))
const urlUserPageSize = computed(() => Number(route.query.perPage ?? DEFAULT_USER_PAGE_SIZE))

const userPagination = computed<PaginationState>(() => ({
  pageIndex: urlUserPage.value - 1,
  pageSize: urlUserPageSize.value,
}))

function onUserPaginationChange(updater: PaginationState | ((old: PaginationState) => PaginationState)) {
  const next = typeof updater === 'function' ? updater(userPagination.value) : updater
  router.push({
    query: {
      ...route.query,
      page: next.pageIndex === 0 ? undefined : String(next.pageIndex + 1),
      perPage: next.pageSize === DEFAULT_USER_PAGE_SIZE ? undefined : String(next.pageSize),
    },
  })
}

// Re-fetch from the server whenever the page or page size changes.
watch([urlUserPage, urlUserPageSize], () => loadUsers())

// Debounced server-side search — resets to page 1 via the URL.
let userSearchTimeout: ReturnType<typeof setTimeout> | null = null
watch(query, () => {
  if (userSearchTimeout) clearTimeout(userSearchTimeout)
  userSearchTimeout = setTimeout(() => {
    if (urlUserPage.value !== 1) router.push({ query: { ...route.query, page: undefined } })
    else loadUsers()
  }, 350)
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
    cell: ({ row }) => h('div', { class: 'flex items-center gap-3' }, [
      h(BoringAvatar, {
        name: row.original.name || row.original.email,
        identity: row.original.email,
        variant: (row.original.avatar_variant as AvatarVariant | undefined) ?? undefined,
        size: 32,
        square: true,
        class: 'size-8 shrink-0 overflow-hidden rounded-md',
      }),
      h('div', { class: 'flex min-w-0 flex-col gap-0.5' }, [
        h('button', {
          type: 'button',
          class: 'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
          title: 'معاينة سريعة',
          onClick: (e: Event) => { e.stopPropagation(); viewingUser.value = row.original },
        }, row.original.name),
        h('span', { class: 'font-mono text-xs text-muted-foreground truncate', dir: 'ltr' }, row.original.email),
      ]),
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
        <Search class="absolute inset-e-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          placeholder="بحث بالاسم أو البريد أو الدور..."
          class="h-8 rounded-md pe-9 text-sm"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="users"
        :columns="columns"
        :loading="loading"
        :page-count="usersMeta?.last_page ?? 1"
        :pagination="userPagination"
        :row-selection="rowSelection"
        @update:pagination="onUserPaginationChange"
        @update:row-selection="(v) => rowSelection = v"
        :row-class="'group/row'"
      >
        <template #empty>
          <Empty class="min-h-[280px] rounded-xl border border-dashed bg-muted/20">
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
        </template>
        <template #pagination="{ table }">
          <DataTablePagination :table="table" :total-rows="usersMeta?.total" />
        </template>
      </DataTable>
    </div>

    <!-- Dialog Modal -->
    <Dialog :open="showModal" @update:open="(open) => !open && closeModal()">
      <DialogContent class="sm:max-w-[420px]" >
        <DialogHeader>
          <DialogTitle>{{ editingUser ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد' }}</DialogTitle>
        </DialogHeader>

        <Alert v-if="formError" variant="destructive">
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription>{{ formError }}</AlertDescription>
        </Alert>

        <div class="rounded-lg border border-border bg-muted/20 p-3">
          <AvatarPicker
            v-model="avatarVariant"
            :seed="form.email || form.name || 'new-user'"
            :size="44"
            label="مظهر الصورة الرمزية"
          />
        </div>

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
      <DialogContent v-if="viewingUser"  class="sm:max-w-md">
        <DialogHeader class="pb-2">
          <DialogTitle class="text-base">{{ viewingUser.name }}</DialogTitle>
          <DialogDescription  class="text-xs">{{ viewingUser.email }}</DialogDescription>
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
