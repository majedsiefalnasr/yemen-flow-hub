<script setup lang="ts">
import { Edit, Eye, Plus, Power, Search, ShieldCheck, UserCog } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROLE_LABELS, CBY_ROLES } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { User } from '@/types/models'
import { useUsers, type CreateUserPayload, type UpdateUserPayload } from '@/composables/useUsers'
import { useAuthStore } from '@/stores/auth.store'

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
const { notify, error: toastError } = useToast()

const query = ref('')
const roleFilter = ref<'all' | UserRole>('all')
const createOpen = ref(false)
const editing = ref<User | null>(null)
const viewing = ref<User | null>(null)
const saving = ref(false)
const staffUsers = ref<User[]>([])

const form = reactive<StaffForm>({
  name: '',
  email: '',
  role: UserRole.SUPPORT_COMMITTEE,
  password: '',
})

onMounted(async () => {
  const results = await fetchUsers({ per_page: 200 })
  staffUsers.value = results.filter(u => STAFF_ROLES.includes(u.role))
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return staffUsers.value
    .filter(u => roleFilter.value === 'all' || u.role === roleFilter.value)
    .filter(u => !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
})

const stats = computed(() => ({
  total: staffUsers.value.length,
  active: staffUsers.value.filter(u => u.is_active).length,
  inactive: staffUsers.value.filter(u => !u.is_active).length,
}))

const formValid = computed(() =>
  form.name.trim().length > 0
  && /\S+@\S+\.\S+/.test(form.email)
  && (Boolean(editing.value) || form.password.length >= 8),
)

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

async function toggleActive(target: User) {
  if (!currentUser.value || target.id === currentUser.value.id) return
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
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <PageHeader
      title="مستخدمي النظام"
      subtitle="إدارة موظفي البنك المركزي — اللجان المساندة والتنفيذية ومسؤولي النظام"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'مستخدمي النظام' }]"
    >
      <template #actions>
        <Button @click="openCreate">
          <Plus class="ms-1 h-4 w-4" />
          مستخدم جديد
        </Button>
      </template>
    </PageHeader>

    <div class="mb-4 grid grid-cols-3 gap-3">
      <Card class="border-0 p-4 shadow-card">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
          <UserCog class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">
          {{ stats.total }}
        </div>
        <div class="text-xs text-muted-foreground">
          إجمالي المستخدمين
        </div>
      </Card>
      <Card class="border-0 p-4 shadow-card">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-success/10 text-success">
          <ShieldCheck class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">
          {{ stats.active }}
        </div>
        <div class="text-xs text-muted-foreground">
          نشط
        </div>
      </Card>
      <Card class="border-0 p-4 shadow-card">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-destructive/10 text-destructive">
          <Power class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">
          {{ stats.inactive }}
        </div>
        <div class="text-xs text-muted-foreground">
          غير نشط
        </div>
      </Card>
    </div>

    <Card class="mb-4 flex flex-col gap-3 border-0 p-4 shadow-card sm:flex-row">
      <div class="relative flex-1">
        <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          class="pe-10"
          placeholder="بحث بالاسم أو البريد..."
        />
      </div>
      <Select v-model="roleFilter">
        <SelectTrigger class="w-full sm:w-64">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">
            كل الأدوار
          </SelectItem>
          <SelectItem
            v-for="role in STAFF_ROLES"
            :key="role"
            :value="role"
          >
            {{ ROLE_LABELS[role] }}
          </SelectItem>
        </SelectContent>
      </Select>
    </Card>

    <Card class="overflow-hidden border-0 shadow-card">
      <div class="overflow-x-auto">
        <Table class="w-full min-w-[720px] text-sm">
          <TableHeader class="bg-muted/40 text-xs text-muted-foreground">
            <TableRow class="text-end">
              <TableHead class="px-4 py-3">
                المستخدم
              </TableHead>
              <TableHead class="px-4 py-3">
                البريد
              </TableHead>
              <TableHead class="px-4 py-3">
                الدور
              </TableHead>
              <TableHead class="px-4 py-3">
                الحالة
              </TableHead>
              <TableHead class="sticky start-0 z-10 bg-muted/40 px-4 py-3 text-start shadow-[6px_0_8px_-6px_rgba(0,0,0,0.12)]">
                إجراءات
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="staff in filtered"
              :key="staff.id"
              class="border-t hover:bg-muted/30"
            >
              <TableCell class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <Avatar size="sm">
                    <AvatarFallback class="bg-gradient-hero text-xs font-bold text-white">
                      {{ userInitials(staff.name) }}
                    </AvatarFallback>
                  </Avatar>
                  <div class="font-medium">
                    {{ staff.name }}
                  </div>
                </div>
              </TableCell>
              <TableCell class="px-4 py-3 text-xs">
                {{ staff.email }}
              </TableCell>
              <TableCell class="px-4 py-3">
                <Badge variant="secondary">
                  {{ ROLE_LABELS[staff.role] }}
                </Badge>
              </TableCell>
              <TableCell class="px-4 py-3">
                <Badge :class="staff.is_active ? 'border-0 bg-success/15 text-success' : 'border-0 bg-destructive/15 text-destructive'">
                  {{ staff.is_active ? 'نشط' : 'غير نشط' }}
                </Badge>
              </TableCell>
              <TableCell class="sticky start-0 z-10 bg-card px-4 py-3 shadow-[6px_0_8px_-6px_rgba(0,0,0,0.12)]">
                <div class="flex justify-end gap-1">
                  <Button
                    size="sm"
                    variant="ghost"
                    @click="viewing = staff"
                  >
                    <Eye class="ms-1 h-3.5 w-3.5" />
                    عرض
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    @click="openEdit(staff)"
                  >
                    <Edit class="ms-1 h-3.5 w-3.5" />
                    تعديل
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    :class="staff.is_active ? 'text-destructive' : 'text-success'"
                    :disabled="staff.id === currentUser?.id"
                    :title="staff.id === currentUser?.id ? 'لا يمكنك تعطيل حسابك' : ''"
                    @click="toggleActive(staff)"
                  >
                    <Power class="ms-1 h-3.5 w-3.5" />
                    {{ staff.is_active ? 'إلغاء تفعيل' : 'تفعيل' }}
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="filtered.length === 0">
              <TableCell
                colspan="5"
                class="px-4 py-8 text-center text-sm text-muted-foreground"
              >
                لا توجد نتائج.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <Dialog
      :open="createOpen || Boolean(editing)"
      @update:open="value => !value && closeForm()"
    >
      <DialogContent
        dir="rtl"
        class="sm:max-w-md"
      >
        <DialogHeader>
          <DialogTitle>
            {{ editing ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم نظام' }}
          </DialogTitle>
          <DialogDescription>
            مستخدمو البنك المركزي فقط (لجان وإدارة النظام).
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2">
          <div class="space-y-1.5">
            <Label>الاسم *</Label>
            <Input v-model="form.name" />
          </div>
          <div class="space-y-1.5">
            <Label>البريد الإلكتروني *</Label>
            <Input
              v-model="form.email"
              type="email"
              dir="ltr"
            />
          </div>
          <div class="space-y-1.5">
            <Label>{{ editing ? 'كلمة المرور (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور *' }}</Label>
            <Input
              v-model="form.password"
              type="password"
              dir="ltr"
              :placeholder="editing ? '••••••••' : 'كلمة مرور قوية'"
            />
          </div>
          <div class="space-y-1.5">
            <Label>الدور *</Label>
            <Select v-model="form.role">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="role in STAFF_ROLES"
                  :key="role"
                  :value="role"
                >
                  {{ ROLE_LABELS[role] }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <DialogFooter>
          <Button
            :disabled="!formValid || saving"
            @click="saveStaff"
          >
            {{ editing ? 'حفظ التعديلات' : 'إضافة' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog
      :open="Boolean(viewing)"
      @update:open="value => !value && (viewing = null)"
    >
      <DialogContent
        v-if="viewing"
        dir="rtl"
        class="sm:max-w-md"
      >
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <UserCog class="h-5 w-5 text-primary" />
            {{ viewing.name }}
          </DialogTitle>
          <DialogDescription>
            تفاصيل المستخدم
          </DialogDescription>
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
            <span class="text-start font-medium">{{ new Date(viewing.last_login_at).toLocaleString('ar-EG') }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
