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

// Filters
const filterRole = ref<UserRole | ''>('')
const filterBank = ref<number | ''>('')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')

const filteredUsers = computed(() => {
  return users.value.filter((u) => {
    if (filterRole.value && u.role !== filterRole.value) return false
    if (filterBank.value !== '' && u.bank_id !== filterBank.value) return false
    if (filterStatus.value === 'active' && !u.is_active) return false
    if (filterStatus.value === 'inactive' && u.is_active) return false
    return true
  })
})

// Modal
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

function entityLabel(user: User): string {
  const directLabel = user.bank_name_ar ?? user.bank_name_en ?? user.bank_name ?? null
  if (directLabel && directLabel.trim().length > 0) {
    return directLabel
  }

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

  return new Intl.DateTimeFormat('ar-YE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(parsed)
}

onMounted(loadData)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">مستخدمو النظام</h1>
      <button class="btn-primary" @click="openCreate">+ إضافة مستخدم نظام</button>
    </div>

    <!-- Filters -->
    <div class="filters-row">
      <select v-model="filterRole" class="filter-select" data-testid="filter-role">
        <option value="">جميع الأدوار</option>
        <option v-for="role in allRoles" :key="role" :value="role">{{ ROLE_LABELS[role] }}</option>
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

    <div v-if="loading" class="state-message">جارٍ التحميل…</div>
    <div v-else-if="error" class="state-message state-error">{{ error }}</div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الاسم</th>
            <th>البريد الإلكتروني</th>
            <th>الدور</th>
            <th>الجهة</th>
            <th>الحالة</th>
            <th>آخر ظهور</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredUsers.length === 0">
            <td colspan="7" class="empty-row" data-empty-state-variant="cby-staff">لا يوجد مستخدمون مطابقون للفلتر.</td>
          </tr>
          <tr v-for="user in filteredUsers" :key="user.id">
            <td>{{ user.name }}</td>
            <td class="email-cell">{{ user.email }}</td>
            <td>
              <span class="badge badge-role">{{ ROLE_LABELS[user.role] ?? user.role }}</span>
            </td>
            <td>{{ entityLabel(user) }}</td>
            <td>
              <span :class="['badge', user.is_active ? 'badge-active' : 'badge-inactive']">
                {{ user.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td class="last-seen-cell">{{ lastSeenLabel(user) }}</td>
            <td>
              <button class="btn-edit" @click="openEdit(user)">تعديل</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" dir="rtl">
        <h2 class="modal-title">{{ editingUser ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم نظام' }}</h2>

        <div v-if="formError" class="form-error-banner">{{ formError }}</div>

        <div class="form-field">
          <label class="form-label">الاسم <span class="required">*</span></label>
          <input v-model="form.name" class="form-input" :class="{ error: formErrors.name }" type="text" placeholder="الاسم الكامل">
          <span v-if="formErrors.name" class="field-error">{{ formErrors.name }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">البريد الإلكتروني <span class="required">*</span></label>
          <input v-model="form.email" class="form-input" :class="{ error: formErrors.email }" type="email" placeholder="example@cby.gov.ye">
          <span v-if="formErrors.email" class="field-error">{{ formErrors.email }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">كلمة المرور {{ editingUser ? '(اتركها فارغة للإبقاء)' : '*' }}</label>
          <input v-model="form.password" class="form-input" :class="{ error: formErrors.password }" type="password" placeholder="8 أحرف على الأقل">
          <span v-if="formErrors.password" class="field-error">{{ formErrors.password }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">الدور الوظيفي <span class="required">*</span></label>
          <select v-model="form.role" class="form-input" :class="{ error: formErrors.role }">
            <option value="" disabled>اختر الدور</option>
            <option v-for="role in allRoles" :key="role" :value="role">{{ ROLE_LABELS[role] }}</option>
          </select>
          <span v-if="formErrors.role" class="field-error">{{ formErrors.role }}</span>
        </div>

        <div v-if="isBankRole" class="form-field">
          <label class="form-label">البنك <span class="required">*</span></label>
          <select v-model="form.bank_id" class="form-input" :class="{ error: formErrors.bank_id }">
            <option :value="null" disabled>اختر البنك</option>
            <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name_ar }}</option>
          </select>
          <span v-if="formErrors.bank_id" class="field-error">{{ formErrors.bank_id }}</span>
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
  gap: 24px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.filters-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-select {
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  font-size: 13px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
  cursor: pointer;
  min-width: 160px;
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  overflow: hidden;
}

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
}

.data-table th {
  background: #f5f5f7;
  color: var(--color-text-secondary);
  font-weight: 500;
  border-bottom: 1px solid var(--color-border);
}

.data-table td {
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text-primary);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.email-cell {
  direction: ltr;
  text-align: right;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.last-seen-cell {
  white-space: nowrap;
  font-size: 12px;
  color: var(--color-text-secondary);
}

.empty-row {
  text-align: center !important;
  color: var(--color-text-secondary);
  padding: 32px !important;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-role {
  background: #f0f0f3;
  color: #6e6e73;
}

.badge-active {
  background: #e6f9ec;
  color: #1a7a35;
}

.badge-inactive {
  background: #f0f0f3;
  color: #8e8e93;
}

.btn-primary {
  height: 44px;
  padding: 0 20px;
  background: #0066cc;
  color: #fff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  height: 44px;
  padding: 0 20px;
  background: transparent;
  color: var(--color-text-primary);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  font-size: 14px;
  cursor: pointer;
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-edit {
  padding: 6px 14px;
  background: transparent;
  color: #0066cc;
  border: 1px solid #0066cc;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}

.state-message {
  text-align: center;
  color: var(--color-text-secondary);
  padding: 32px;
}

.state-error {
  color: #c62828;
}

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
  background: var(--color-surface);
  border-radius: 24px;
  padding: 32px;
  width: 480px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.modal-title {
  font-size: 20px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field-inline {
  flex-direction: row;
  align-items: center;
  gap: 12px;
}

.form-label {
  font-size: 13px;
  color: #6e6e73;
}

.required {
  color: #c62828;
}

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
  direction: inherit;
}

.form-input:focus {
  border-color: #0066cc;
}

.form-input.error {
  border-color: #c62828;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.field-error {
  font-size: 12px;
  color: #c62828;
}

.form-error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #c62828;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 4px;
}
</style>
