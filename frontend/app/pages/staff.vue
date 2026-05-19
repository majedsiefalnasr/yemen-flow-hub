<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { User } from '../types/models'
import { useUsers } from '../composables/useUsers'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'
import StaffModal from '../components/staff/StaffModal.vue'
import type { CreateUserPayload, UpdateUserPayload } from '../composables/useUsers'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.BANK_ADMIN],
})

const { fetchUsers, createUser, updateUser } = useUsers()
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

const isEmpty = computed(() => !loading.value && !error.value && staff.value.length === 0)

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
  try {
    if (editingStaff.value) {
      const payload: UpdateUserPayload = {
        name: data.name,
        email: data.email,
        role: data.role,
        bank_id: auth.user?.bank_id ?? null,
        is_active: editingStaff.value.is_active,
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
        bank_id: auth.user?.bank_id ?? null,
        is_active: true,
      }
      const created = await createUser(payload)
      staff.value.unshift(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { message?: string } }
    serverError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
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
    const payload: UpdateUserPayload = {
      name: target.name,
      email: target.email,
      role: target.role,
      bank_id: auth.user?.bank_id ?? null,
      is_active: false,
    }
    const updated = await updateUser(target.id, payload)
    const idx = staff.value.findIndex(s => s.id === updated.id)
    if (idx !== -1) staff.value[idx] = updated
    closeDeactivate()
  }
  catch {
    // deactivation failure — close and reload
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
    <div class="page-header">
      <h1 class="page-title">إدارة الموظفين</h1>
      <button class="btn-primary" @click="openCreate">
        + إضافة موظف
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card">
      <div class="skeleton-table">
        <div v-for="i in 4" :key="i" class="skeleton-row">
          <div class="skeleton-cell skeleton-name" />
          <div class="skeleton-cell skeleton-role" />
          <div class="skeleton-cell skeleton-dept" />
          <div class="skeleton-cell skeleton-badge" />
          <div class="skeleton-cell skeleton-date" />
          <div class="skeleton-cell skeleton-actions" />
        </div>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="state-message state-error">
      {{ error }}
    </div>

    <!-- Empty state -->
    <div v-else-if="isEmpty" class="empty-state">
      <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <polyline points="16 11 18 13 22 9" />
        </svg>
      </div>
      <h2 class="empty-title">لا يوجد موظفون مسجّلون</h2>
      <p class="empty-subtitle">ابدأ بإضافة أول موظف في بنكك لمنحه صلاحيات الدخول للنظام.</p>
      <button class="btn-primary" @click="openCreate">
        إضافة أول موظف
      </button>
    </div>

    <!-- Staff table -->
    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الاسم</th>
            <th>الدور</th>
            <th>القسم</th>
            <th>الحالة</th>
            <th>تاريخ الإنضمام</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in staff" :key="member.id">
            <td>
              <div class="staff-name">{{ member.name }}</div>
              <div class="staff-email">{{ member.email }}</div>
            </td>
            <td>
              <span class="badge badge-role">{{ ROLE_LABELS[member.role] ?? member.role }}</span>
            </td>
            <td class="text-muted">—</td>
            <td>
              <span :class="['badge', member.is_active ? 'badge-active' : 'badge-inactive']">
                {{ member.is_active ? 'نشط' : 'غير نشط' }}
              </span>
            </td>
            <td class="text-muted">{{ formatJoinDate((member as any).created_at) }}</td>
            <td>
              <div class="actions-cell">
                <button class="btn-action btn-edit" @click="openEdit(member)">تعديل</button>
                <button
                  v-if="member.is_active"
                  class="btn-action btn-deactivate"
                  @click="openDeactivate(member)"
                >
                  تعطيل
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
        <h3 class="dialog-title">تأكيد تعطيل الموظف</h3>
        <p class="dialog-body">
          هل أنت متأكد من تعطيل حساب <strong>{{ deactivatingStaff?.name }}</strong>؟
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

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1c222b;
  margin: 0;
}

.card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  overflow: hidden;
}

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

.staff-name {
  font-weight: 600;
  color: #1c222b;
}

.staff-email {
  font-size: 12px;
  color: #6c757d;
  direction: ltr;
  text-align: right;
}

.text-muted {
  color: #6c757d;
}

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

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  gap: 16px;
}

.empty-icon {
  width: 80px;
  height: 80px;
  background: #f5f5f7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-title {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.empty-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  text-align: center;
  max-width: 360px;
}

/* Error state */
.state-message {
  padding: 24px;
  text-align: center;
  color: #6c757d;
  font-size: 14px;
}

.state-error {
  color: #c62828;
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

.skeleton-name { width: 180px; }
.skeleton-role { width: 100px; }
.skeleton-dept { width: 80px; }
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

/* Primary button */
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
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Secondary button */
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

/* Danger button */
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
