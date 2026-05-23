<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { ApiError, User } from '../types/models'
import { useUsers } from '../composables/useUsers'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'
import StaffModal from '../components/staff/StaffModal.vue'
import EmptyState from '../components/shared/EmptyState.vue'
import type { CreateUserPayload, UpdateUserPayload } from '../composables/useUsers'

definePageMeta({
  middleware: 'role',
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

const isFiltered = computed(() =>
  searchQuery.value.trim() !== '' || roleFilter.value !== '' || statusFilter.value !== '',
)
const isEmpty = computed(() => !loading.value && !error.value && staff.value.length === 0)
const isFilteredEmpty = computed(() => !loading.value && !error.value && staff.value.length > 0 && filteredStaff.value.length === 0)

function getAvatarInitials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function getAvatarColor(id: number): string {
  const colors = ['#0066cc', '#5856d6', '#32ade6', '#1b5e20', '#f57f17', '#c62828']
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
    } else {
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
    if (idx !== -1) {
      staff.value[idx] = updated
    }
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

onMounted(loadStaff)
</script>

<template>
  <div class="page" dir="rtl">
    <!-- Page header -->
    <div class="page-header">
      <div class="page-header-content">
        <nav class="breadcrumbs">
          <span class="breadcrumb-item">لوحة التحكم</span>
          <span class="breadcrumb-sep">/</span>
          <span class="breadcrumb-item breadcrumb-current">موظفو الجهة</span>
        </nav>
        <div class="page-title-row">
          <div>
            <h1 class="page-title">موظفو الجهة</h1>
            <p class="page-subtitle">{{ auth.user?.bank_name_ar ?? auth.user?.bank_name_en ?? 'إدارة موظفي البنك' }}</p>
          </div>
          <button class="btn-primary" @click="openCreate">
            موظف جديد
          </button>
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
          <div class="stat-label">إجمالي الموظفين</div>
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

    <!-- Search and filter card -->
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
      <select v-model="roleFilter" class="filter-select">
        <option value="">جميع الأدوار</option>
        <option value="DATA_ENTRY">{{ ROLE_LABELS[UserRole.DATA_ENTRY] }}</option>
        <option value="BANK_REVIEWER">{{ ROLE_LABELS[UserRole.BANK_REVIEWER] }}</option>
      </select>
      <select v-model="statusFilter" class="filter-select">
        <option value="">جميع الحالات</option>
        <option value="active">نشط</option>
        <option value="inactive">غير نشط</option>
      </select>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card">
      <div class="skeleton-table">
        <div v-for="i in 4" :key="i" class="skeleton-row">
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
      <button class="btn-secondary retry-btn" @click="loadStaff">
        إعادة المحاولة
      </button>
    </div>

    <!-- Empty state -->
    <EmptyState v-else-if="isEmpty" variant="staff">
      <button class="btn-primary" @click="openCreate">
        إضافة أول موظف
      </button>
    </EmptyState>

    <!-- Filtered empty state -->
    <div v-else-if="isFilteredEmpty" class="card filtered-empty">
      <p>لا توجد نتائج.</p>
    </div>

    <!-- Staff table -->
    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الموظف</th>
            <th>الدور</th>
            <th>الحالة</th>
            <th>تاريخ الانضمام</th>
            <th class="col-actions">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in filteredStaff" :key="member.id">
            <td>
              <div class="staff-cell">
                <div
                  class="avatar"
                  :style="{ background: getAvatarColor(member.id) }"
                >
                  {{ getAvatarInitials(member.name) }}
                </div>
                <div>
                  <div class="staff-name">{{ member.name }}</div>
                  <div class="staff-email" dir="ltr">{{ member.email }}</div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge badge-role">{{ ROLE_LABELS[member.role] ?? member.role }}</span>
            </td>
            <td>
              <span :class="['badge', member.is_active ? 'badge-active' : 'badge-inactive']">
                {{ member.is_active ? 'نشط' : 'غير نشط' }}
              </span>
            </td>
            <td class="text-muted">{{ formatJoinDate(member.created_at) }}</td>
            <td class="col-actions">
              <div class="actions-cell">
                <button class="btn-action btn-edit" @click="openEdit(member)">تعديل</button>
                <button
                  v-if="member.is_active"
                  class="btn-action btn-deactivate"
                  @click="openDeactivate(member)"
                >
                  إلغاء التفعيل
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Staff modal (add / edit) -->
    <StaffModal
      v-if="showModal"
      :staff="editingStaff"
      :saving="saving"
      :server-error="serverError"
      @save="handleSave"
      @close="closeModal"
    />

    <!-- Deactivate confirm dialog -->
    <div v-if="showDeactivateConfirm" class="dialog-backdrop" @click.self="closeDeactivate">
      <div class="dialog" dir="rtl">
        <h3 class="dialog-title">تأكيد إلغاء التفعيل</h3>
        <p class="dialog-body">
          هل أنت متأكد من إلغاء تفعيل حساب <strong>{{ deactivatingStaff?.name }}</strong>؟
          لن يتمكن من تسجيل الدخول بعد ذلك.
        </p>
        <div class="dialog-actions">
          <button class="btn-secondary" :disabled="deactivating" @click="closeDeactivate">إلغاء</button>
          <button class="btn-danger" :disabled="deactivating" @click="confirmDeactivate">
            {{ deactivating ? 'جارٍ التعطيل…' : 'تعطيل' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 1600px;
  margin: 0 auto;
}

/* Page header */
.page-header {
  margin-bottom: 24px;
}

.page-header-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6c757d;
}

.breadcrumb-sep {
  color: #cccccc;
}

.breadcrumb-current {
  color: #1c222b;
  font-weight: 500;
}

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
  margin-bottom: 16px;
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

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #1c222b;
  line-height: 1;
}

.stat-label {
  font-size: 13px;
  color: #6c757d;
  margin-top: 4px;
}

/* Filter card */
.filter-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 16px;
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;
}

.search-input-wrap {
  position: relative;
  flex: 1;
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

.search-input:focus {
  border-color: #0066cc;
}

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
}

.filter-select:focus {
  border-color: #0066cc;
}

/* Card */
.card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  overflow: hidden;
}

.filtered-empty {
  padding: 40px;
  text-align: center;
  color: #6c757d;
  font-size: 14px;
}

/* Table */
.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  text-align: right;
  border-bottom: 1px solid #f0f0f0;
  font-size: 14px;
  color: #1c222b;
}

.data-table th {
  background: #f8f9fa;
  font-weight: 600;
  color: #6c757d;
  font-size: 13px;
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.data-table tbody tr:hover {
  background: #f8f9fa;
}

.col-actions {
  text-align: left;
  position: sticky;
  left: 0;
  background: inherit;
}

/* Staff cell with avatar */
.staff-cell {
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

.staff-name {
  font-weight: 600;
  color: #1c222b;
}

.staff-email {
  font-size: 12px;
  color: #6c757d;
  text-align: left;
}

.text-muted {
  color: #6c757d;
}

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-role {
  background: #e8f0fb;
  color: #0066cc;
}

.badge-active {
  background: #e8f5e9;
  color: #1b5e20;
}

.badge-inactive {
  background: #f5f5f7;
  color: #8e8e93;
}

/* Actions */
.actions-cell {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.btn-action {
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.btn-edit {
  background: #f0f6ff;
  color: #0066cc;
}

.btn-edit:hover {
  background: #dbeafe;
}

.btn-deactivate {
  background: #fff0ef;
  color: #c62828;
}

.btn-deactivate:hover {
  background: #ffe0de;
}

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

.state-error {
  color: #c62828;
}

.retry-btn {
  margin-top: 8px;
}

/* Skeleton */
.skeleton-table {
  display: flex;
  flex-direction: column;
}

.skeleton-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.skeleton-row:last-child {
  border-bottom: none;
}

.skeleton-cell {
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 6px;
  height: 16px;
}

.skeleton-avatar { width: 36px; height: 36px; border-radius: 50%; }
.skeleton-name { width: 180px; }
.skeleton-role { width: 100px; }
.skeleton-badge { width: 60px; }
.skeleton-date { width: 120px; }
.skeleton-actions { width: 80px; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Deactivate confirm dialog */
.dialog-backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
}

.dialog {
  background: #ffffff;
  border-radius: 24px;
  padding: 32px;
  width: 440px;
  max-width: 90vw;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.dialog-title {
  font-size: 18px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.dialog-body {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  line-height: 1.6;
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

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

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

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

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-danger {
  height: 44px;
  padding: 0 20px;
  background: #c62828;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
