<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { UserRole } from '../../types/enums'
import type { User, Bank } from '../../types/models'
import { useUsers } from '../../composables/useUsers'
import { useBanks } from '../../composables/useBanks'
import { ROLE_LABELS, BANK_ROLES, CBY_ROLES } from '../../constants/workflow'
import type { CreateUserPayload, UpdateUserPayload } from '../../composables/useUsers'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchUsers, createUser, updateUser } = useUsers()
const { fetchBanks } = useBanks()

const users = ref<User[]>([])
const banks = ref<Bank[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const searchQuery = ref('')
const filterRole = ref<UserRole | ''>('')
const filterBank = ref<number | ''>('')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')

const filteredUsers = computed(() => {
  let list = users.value
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(u =>
      u.name.toLowerCase().includes(q)
      || u.email.toLowerCase().includes(q),
    )
  }
  if (filterRole.value) list = list.filter(u => u.role === filterRole.value)
  if (filterBank.value !== '') list = list.filter(u => u.bank_id === filterBank.value)
  if (filterStatus.value === 'active') list = list.filter(u => u.is_active)
  if (filterStatus.value === 'inactive') list = list.filter(u => !u.is_active)
  return list
})

const totalCount = computed(() => users.value.length)
const activeCount = computed(() => users.value.filter(u => u.is_active).length)
const inactiveCount = computed(() => users.value.filter(u => !u.is_active).length)

// View modal
const showViewModal = ref(false)
const viewingUser = ref<User | null>(null)

// Edit/create modal
const showModal = ref(false)
const editingUser = ref<User | null>(null)
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

const allRoles = Object.values(UserRole)
const isBankRole = computed(() => form.role !== '' && BANK_ROLES.includes(form.role as UserRole))

watch(() => form.role, (newRole) => {
  if (newRole !== '' && CBY_ROLES.includes(newRole as UserRole)) {
    form.bank_id = null
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

function openView(user: User) {
  viewingUser.value = user
  showViewModal.value = true
}

function closeView() {
  showViewModal.value = false
  viewingUser.value = null
}

function openCreate() {
  editingUser.value = null
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = ''
  form.bank_id = null
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
  form.bank_id = user.bank_id
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
  if (form.role && BANK_ROLES.includes(form.role as UserRole) && !form.bank_id) {
    formErrors.bank_id = 'يجب تحديد البنك للأدوار المصرفية'
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
        bank_id: form.bank_id,
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
        bank_id: form.bank_id,
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

async function toggleActivation(user: User) {
  saving.value = true
  try {
    const payload: UpdateUserPayload = {
      name: user.name,
      email: user.email,
      role: user.role,
      bank_id: user.bank_id,
      is_active: !user.is_active,
    }
    const updated = await updateUser(user.id, payload)
    const idx = users.value.findIndex(u => u.id === updated.id)
    if (idx !== -1) users.value[idx] = updated
  }
  catch {
    await loadData()
  }
  finally {
    saving.value = false
  }
}

function getAvatarInitials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function getAvatarColor(id: number): string {
  const colors = ['#0066cc', '#5856d6', '#32ade6', '#1b5e20', '#f57f17', '#c62828']
  return colors[id % colors.length]!
}

function entityLabel(user: User): string {
  const directLabel = user.bank_name_ar ?? user.bank_name_en ?? user.bank_name ?? null
  if (directLabel && directLabel.trim().length > 0) return directLabel
  if (user.bank_id !== null) {
    const resolvedBank = banks.value.find(bank => bank.id === user.bank_id)
    return resolvedBank?.name_ar ?? '—'
  }
  return 'البنك المركزي اليمني'
}

function lastSeenLabel(user: User): string {
  const raw = user.last_seen_at ?? user.last_login_at ?? user.created_at ?? null
  if (!raw) return '—'
  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return raw
  return new Intl.DateTimeFormat('ar-YE', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed)
}

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ar-YE', { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(loadData)
</script>

<template>
  <div class="page" dir="rtl">
    <!-- Page header -->
    <div class="page-header">
      <div>
        <nav class="breadcrumbs">
          <span class="breadcrumb-item">لوحة التحكم</span>
          <span class="breadcrumb-sep">/</span>
          <span class="breadcrumb-item breadcrumb-current">مستخدمي النظام</span>
        </nav>
        <div class="page-title-row">
          <div>
            <h1 class="page-title">مستخدمي النظام</h1>
            <p class="page-subtitle">مستخدمو البنك المركزي اليمني (اللجان وإدارة النظام)</p>
          </div>
          <button class="btn-primary" @click="openCreate">مستخدم جديد</button>
        </div>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-icon stat-icon--blue">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
        </div>
        <div>
          <div class="stat-value">{{ totalCount }}</div>
          <div class="stat-label">إجمالي المستخدمين</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--green">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
        </div>
        <div>
          <div class="stat-value">{{ activeCount }}</div>
          <div class="stat-label">نشط</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--red">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </svg>
        </div>
        <div>
          <div class="stat-value">{{ inactiveCount }}</div>
          <div class="stat-label">غير نشط</div>
        </div>
      </div>
    </div>

    <!-- Search + filter card -->
    <div class="filter-card">
      <div class="search-input-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          v-model="searchQuery"
          class="search-input"
          type="text"
          placeholder="بحث بالاسم أو البريد الإلكتروني..."
        >
      </div>
      <select v-model="filterRole" class="filter-select" data-testid="filter-role">
        <option value="">جميع الأدوار</option>
        <option v-for="r in allRoles" :key="r" :value="r">{{ ROLE_LABELS[r] }}</option>
      </select>
      <select v-model="filterBank" class="filter-select" data-testid="filter-bank">
        <option value="">جميع الجهات</option>
        <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name_ar }}</option>
      </select>
      <select v-model="filterStatus" class="filter-select" data-testid="filter-status">
        <option value="all">جميع الحالات</option>
        <option value="active">نشط</option>
        <option value="inactive">موقوف</option>
      </select>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card">
      <div class="skeleton-table">
        <div v-for="i in 5" :key="i" class="skeleton-row">
          <div class="skeleton-cell skeleton-avatar" />
          <div class="skeleton-cell skeleton-name" />
          <div class="skeleton-cell skeleton-role" />
          <div class="skeleton-cell skeleton-badge" />
          <div class="skeleton-cell skeleton-date" />
          <div class="skeleton-cell skeleton-actions" />
        </div>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="state-message state-error">
      {{ error }}
      <button class="btn-secondary retry-btn" @click="loadData">إعادة المحاولة</button>
    </div>

    <!-- Table -->
    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>المستخدم</th>
            <th>الدور</th>
            <th>الجهة</th>
            <th>الحالة</th>
            <th>آخر ظهور</th>
            <th class="col-actions">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredUsers.length === 0">
            <td colspan="6" class="empty-row" data-empty-state-variant="cby-staff">لا توجد نتائج.</td>
          </tr>
          <tr v-for="user in filteredUsers" :key="user.id">
            <td>
              <div class="user-cell">
                <div class="avatar" :style="{ background: getAvatarColor(user.id) }">
                  {{ getAvatarInitials(user.name) }}
                </div>
                <div>
                  <div class="user-name">{{ user.name }}</div>
                  <div class="user-email" dir="ltr">{{ user.email }}</div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge badge-role">{{ ROLE_LABELS[user.role] ?? user.role }}</span>
            </td>
            <td class="text-muted">{{ entityLabel(user) }}</td>
            <td>
              <span :class="['badge', user.is_active ? 'badge-active' : 'badge-inactive']">
                {{ user.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td class="text-muted">{{ lastSeenLabel(user) }}</td>
            <td class="col-actions">
              <div class="actions-cell">
                <button class="btn-action btn-view" @click="openView(user)">عرض</button>
                <button class="btn-action btn-edit" @click="openEdit(user)">تعديل</button>
                <button
                  class="btn-action"
                  :class="user.is_active ? 'btn-deactivate' : 'btn-activate'"
                  @click="toggleActivation(user)"
                >
                  {{ user.is_active ? 'إلغاء التفعيل' : 'تفعيل' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- View modal -->
    <div v-if="showViewModal" class="modal-backdrop" @click.self="closeView">
      <div class="modal" dir="rtl">
        <div class="modal-header">
          <h2 class="modal-title">تفاصيل المستخدم</h2>
          <button class="close-btn" aria-label="إغلاق" @click="closeView">✕</button>
        </div>
        <p class="modal-description">مستخدمو البنك المركزي اليمني فقط (للجان وإدارة النظام)</p>
        <div v-if="viewingUser" class="view-fields">
          <div class="view-field">
            <span class="view-label">الاسم</span>
            <span class="view-value">{{ viewingUser.name }}</span>
          </div>
          <div class="view-field">
            <span class="view-label">البريد الإلكتروني</span>
            <span class="view-value" dir="ltr">{{ viewingUser.email }}</span>
          </div>
          <div class="view-field">
            <span class="view-label">الدور الوظيفي</span>
            <span class="view-value">{{ ROLE_LABELS[viewingUser.role] ?? viewingUser.role }}</span>
          </div>
          <div class="view-field">
            <span class="view-label">الجهة</span>
            <span class="view-value">{{ entityLabel(viewingUser) }}</span>
          </div>
          <div class="view-field">
            <span class="view-label">الحالة</span>
            <span :class="['badge', viewingUser.is_active ? 'badge-active' : 'badge-inactive']">
              {{ viewingUser.is_active ? 'نشط' : 'موقوف' }}
            </span>
          </div>
          <div class="view-field">
            <span class="view-label">آخر تسجيل دخول</span>
            <span class="view-value">{{ lastSeenLabel(viewingUser) }}</span>
          </div>
          <div class="view-field">
            <span class="view-label">تاريخ الإنشاء</span>
            <span class="view-value">{{ formatDate(viewingUser.created_at) }}</span>
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="closeView">إغلاق</button>
          <button class="btn-primary" @click="() => { closeView(); openEdit(viewingUser!) }">تعديل</button>
        </div>
      </div>
    </div>

    <!-- Edit/Create modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" dir="rtl">
        <div class="modal-header">
          <h2 class="modal-title">{{ editingUser ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد' }}</h2>
          <button class="close-btn" aria-label="إغلاق" :disabled="saving" @click="closeModal">✕</button>
        </div>
        <p class="modal-description">مستخدمو البنك المركزي اليمني فقط (للجان وإدارة النظام)</p>

        <div v-if="formError" class="form-error-banner" role="alert">{{ formError }}</div>

        <div class="form-field">
          <label class="form-label">الاسم الكامل <span class="required">*</span></label>
          <input v-model="form.name" class="form-input" :class="{ error: formErrors.name }" type="text" placeholder="الاسم الكامل">
          <span v-if="formErrors.name" class="field-error" role="alert">{{ formErrors.name }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">البريد الإلكتروني <span class="required">*</span></label>
          <input v-model="form.email" class="form-input" :class="{ error: formErrors.email }" type="email" placeholder="example@cby.gov.ye" dir="ltr">
          <span v-if="formErrors.email" class="field-error" role="alert">{{ formErrors.email }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">{{ editingUser ? 'كلمة المرور (اتركها فارغة للإبقاء)' : 'كلمة المرور *' }}</label>
          <input v-model="form.password" class="form-input" :class="{ error: formErrors.password }" type="password" placeholder="8 أحرف على الأقل">
          <span v-if="formErrors.password" class="field-error" role="alert">{{ formErrors.password }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">الدور الوظيفي <span class="required">*</span></label>
          <select v-model="form.role" class="form-input" :class="{ error: formErrors.role }">
            <option value="" disabled>اختر الدور</option>
            <option v-for="r in allRoles" :key="r" :value="r">{{ ROLE_LABELS[r] }}</option>
          </select>
          <span v-if="formErrors.role" class="field-error" role="alert">{{ formErrors.role }}</span>
        </div>

        <div v-if="isBankRole" class="form-field">
          <label class="form-label">البنك <span class="required">*</span></label>
          <select v-model="form.bank_id" class="form-input" :class="{ error: formErrors.bank_id }">
            <option :value="null" disabled>اختر البنك</option>
            <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name_ar }}</option>
          </select>
          <span v-if="formErrors.bank_id" class="field-error" role="alert">{{ formErrors.bank_id }}</span>
        </div>

        <div class="form-field form-field-inline">
          <label class="form-label">نشط</label>
          <input v-model="form.is_active" type="checkbox" class="form-checkbox">
        </div>

        <div class="modal-actions">
          <button class="btn-secondary" :disabled="saving" @click="closeModal">إلغاء</button>
          <button class="btn-primary" :disabled="saving" @click="saveUser">
            {{ saving ? 'جارٍ الحفظ…' : 'حفظ' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  max-width: 1600px;
  margin: 0 auto;
}

/* Page header */
.page-header {
  margin-bottom: 8px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6c757d;
  margin-bottom: 8px;
}

.breadcrumb-sep { color: #cccccc; }
.breadcrumb-current { color: #1c222b; font-weight: 500; }

.page-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1c222b;
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 4px 0 0 0;
}

/* Stat cards */
.stat-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stat-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon--blue { background: #e8f0fb; color: #0066cc; }
.stat-icon--green { background: #e8f5e9; color: #1b5e20; }
.stat-icon--red { background: #fdecea; color: #c62828; }

.stat-value { font-size: 28px; font-weight: 700; color: #1c222b; line-height: 1; }
.stat-label { font-size: 13px; color: #6c757d; margin-top: 4px; }

/* Filter card */
.filter-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 16px;
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.search-input-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 40px;
  padding: 0 40px 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  outline: none;
  direction: rtl;
  box-sizing: border-box;
}

.search-input:focus { border-color: #0066cc; }

.filter-select {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  outline: none;
  cursor: pointer;
  min-width: 140px;
  direction: rtl;
  background: #ffffff;
}

.filter-select:focus { border-color: #0066cc; }

/* Card */
.card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  overflow: hidden;
}

/* Table */
.data-table {
  width: 100%;
  border-collapse: collapse;
  direction: rtl;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  text-align: right;
  font-size: 14px;
  border-bottom: 1px solid #f0f0f0;
}

.data-table th {
  background: #f8f9fa;
  color: #6c757d;
  font-weight: 600;
  font-size: 13px;
}

.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8f9fa; }

.col-actions {
  text-align: left;
  position: sticky;
  left: 0;
  background: inherit;
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  flex-shrink: 0;
}

.user-name { font-weight: 600; color: #1c222b; }
.user-email { font-size: 12px; color: #6c757d; text-align: left; }
.text-muted { color: #6c757d; }

.empty-row {
  text-align: center !important;
  color: #6c757d;
  padding: 40px !important;
}

/* Badges */
.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-role { background: #e8f0fb; color: #0066cc; }
.badge-active { background: #e8f5e9; color: #1b5e20; }
.badge-inactive { background: #f5f5f7; color: #8e8e93; }

/* Actions */
.actions-cell {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.btn-action {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  white-space: nowrap;
}

.btn-view { background: #f5f5f7; color: #1c222b; }
.btn-view:hover { background: #e8e8ec; }
.btn-edit { background: #f0f6ff; color: #0066cc; }
.btn-edit:hover { background: #dbeafe; }
.btn-deactivate { background: #fff0ef; color: #c62828; }
.btn-deactivate:hover { background: #ffe0de; }
.btn-activate { background: #e8f5e9; color: #1b5e20; }
.btn-activate:hover { background: #c8e6c9; }

/* State messages */
.state-message {
  padding: 40px;
  text-align: center;
  color: #6c757d;
  font-size: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.state-error { color: #c62828; }

.retry-btn { margin-top: 8px; }

/* Skeleton */
.skeleton-table { display: flex; flex-direction: column; }

.skeleton-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.skeleton-row:last-child { border-bottom: none; }

.skeleton-cell {
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 6px;
  height: 16px;
}

.skeleton-avatar { width: 36px; height: 36px; border-radius: 50%; }
.skeleton-name { width: 160px; }
.skeleton-role { width: 100px; }
.skeleton-badge { width: 60px; }
.skeleton-date { width: 120px; }
.skeleton-actions { width: 120px; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.modal {
  background: #ffffff;
  border-radius: 24px;
  padding: 32px;
  width: 480px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.modal-description {
  font-size: 13px;
  color: #6c757d;
  margin: -8px 0 0 0;
  line-height: 1.5;
}

.close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: #6c757d;
  cursor: pointer;
  padding: 4px;
}

.close-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* View fields */
.view-fields { display: flex; flex-direction: column; gap: 12px; }

.view-field {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.view-label {
  font-size: 13px;
  color: #6c757d;
  min-width: 130px;
  flex-shrink: 0;
}

.view-value { font-size: 14px; color: #1c222b; }

/* Form */
.form-field { display: flex; flex-direction: column; gap: 6px; }
.form-field-inline { flex-direction: row; align-items: center; gap: 12px; }
.form-label { font-size: 13px; color: #6c757d; font-weight: 500; }
.required { color: #c62828; }

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  direction: inherit;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.form-input:focus { border-color: #0066cc; }
.form-input.error { border-color: #c62828; }
.form-checkbox { width: 18px; height: 18px; cursor: pointer; }
.field-error { font-size: 12px; color: #c62828; }

.form-error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #c62828;
}

.modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 4px; }

/* Buttons */
.btn-primary {
  height: 44px;
  padding: 0 24px;
  background: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
  height: 44px;
  padding: 0 20px;
  background: transparent;
  color: #1c222b;
  border: 1px solid #cccccc;
  border-radius: 16px;
  font-size: 14px;
  cursor: pointer;
}

.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
