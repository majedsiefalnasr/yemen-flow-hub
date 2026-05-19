<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { UserRole } from '../../types/enums'
import type { Bank, User } from '../../types/models'
import { useBanks } from '../../composables/useBanks'
import { useUsers } from '../../composables/useUsers'
import type { CreateBankPayload, UpdateBankPayload } from '../../composables/useBanks'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchBanks, createBank, updateBank } = useBanks()
const { fetchUsers } = useUsers()

const banks = ref<Bank[]>([])
const userCountsByBank = ref<Record<number, number>>({})
const loading = ref(false)
const error = ref<string | null>(null)

const showModal = ref(false)
const editingBank = ref<Bank | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface EntityForm {
  name_ar: string
  name_en: string
  code: string
  license_number: string
  entity_type: string
  is_active: boolean
}

const form = reactive<EntityForm>({
  name_ar: '',
  name_en: '',
  code: '',
  license_number: '',
  entity_type: 'تجاري',
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof EntityForm, string>>>({})

const ENTITY_TYPES = ['تجاري', 'إسلامي', 'متخصص', 'أجنبي', 'حكومي']

async function loadBanks() {
  loading.value = true
  error.value = null
  try {
    const [banksData, usersData] = await Promise.all([fetchBanks(), fetchUsers()])
    banks.value = banksData
    userCountsByBank.value = buildUserCounts(usersData)
  }
  catch {
    error.value = 'تعذّر تحميل قائمة الجهات.'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingBank.value = null
  form.name_ar = ''
  form.name_en = ''
  form.code = ''
  form.license_number = ''
  form.entity_type = 'تجاري'
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function buildUserCounts(users: User[]): Record<number, number> {
  return users.reduce<Record<number, number>>((counts, user) => {
    if (user.bank_id === null) return counts
    counts[user.bank_id] = (counts[user.bank_id] ?? 0) + 1
    return counts
  }, {})
}

function openEdit(bank: Bank) {
  editingBank.value = bank
  form.name_ar = bank.name_ar
  form.name_en = bank.name_en
  form.code = bank.code
  form.license_number = bank.license_number ?? ''
  form.entity_type = bank.entity_type ?? 'تجاري'
  form.is_active = bank.is_active
  clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function clearErrors() {
  formErrors.name_ar = undefined
  formErrors.name_en = undefined
  formErrors.code = undefined
  formError.value = null
}

function validateForm(): boolean {
  clearErrors()
  let valid = true
  if (!form.name_ar.trim()) { formErrors.name_ar = 'الاسم بالعربية مطلوب'; valid = false }
  if (!form.name_en.trim()) { formErrors.name_en = 'الاسم بالإنجليزية مطلوب'; valid = false }
  if (!form.code.trim()) { formErrors.code = 'الرمز مطلوب'; valid = false }
  return valid
}

async function saveEntity() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  const normalizedLicense = form.license_number.trim() || null
  const normalizedEntityType = form.entity_type.trim() || 'تجاري'
  try {
    if (editingBank.value) {
      const payload: UpdateBankPayload = {
        name: form.name_ar.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim().toUpperCase(),
        license_number: normalizedLicense,
        entity_type: normalizedEntityType,
        is_active: form.is_active,
      }
      const updated = await updateBank(editingBank.value.id, payload)
      const idx = banks.value.findIndex(b => b.id === updated.id)
      if (idx !== -1) {
        banks.value[idx] = {
          ...updated,
          license_number: normalizedLicense,
          entity_type: normalizedEntityType,
          user_count: userCountsByBank.value[updated.id] ?? 0,
        }
      }
    }
    else {
      const payload: CreateBankPayload = {
        name: form.name_ar.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim().toUpperCase(),
        license_number: normalizedLicense,
        entity_type: normalizedEntityType,
        is_active: form.is_active,
      }
      const created = await createBank(payload)
      banks.value.unshift({
        ...created,
        license_number: normalizedLicense,
        entity_type: normalizedEntityType,
        user_count: 0,
      })
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.data?.errors) {
      const errs = e.data.errors
      if (errs.name_ar?.[0]) formErrors.name_ar = errs.name_ar[0]
      if (errs.name_en?.[0]) formErrors.name_en = errs.name_en[0]
      if (errs.code?.[0]) formErrors.code = errs.code[0]
    }
    else {
      formError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
    }
  }
  finally {
    saving.value = false
  }
}

function entityTypeLabel(bank: Bank): string {
  return bank.entity_type?.trim() || 'تجاري'
}

function licenseLabel(bank: Bank): string {
  return bank.license_number?.trim() || '—'
}

function userCountLabel(bank: Bank): number {
  const countedUsers = userCountsByBank.value[bank.id]
  if (typeof countedUsers === 'number') {
    return countedUsers
  }
  return bank.user_count ?? 0
}

onMounted(loadBanks)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">إدارة الجهات</h1>
      <button class="btn-primary" @click="openCreate">+ إضافة جهة</button>
    </div>

    <div v-if="loading" class="state-message">جارٍ التحميل…</div>
    <div v-else-if="error" class="state-message state-error">{{ error }}</div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>اسم الجهة</th>
            <th>نوع الجهة</th>
            <th>رقم الترخيص</th>
            <th>الرمز</th>
            <th>عدد المستخدمين</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="banks.length === 0">
            <td colspan="7" class="empty-row" data-empty-state-variant="entities">لا توجد جهات مسجّلة.</td>
          </tr>
          <tr v-for="bank in banks" :key="bank.id">
            <td>
              <div class="entity-name">{{ bank.name_ar }}</div>
              <div class="entity-name-en">{{ bank.name_en }}</div>
            </td>
            <td>
              <span class="badge badge-type">{{ entityTypeLabel(bank) }}</span>
            </td>
            <td class="license-cell">{{ licenseLabel(bank) }}</td>
            <td class="code-cell">{{ bank.code }}</td>
            <td class="count-cell">{{ userCountLabel(bank) }}</td>
            <td>
              <span :class="['badge', bank.is_active ? 'badge-active' : 'badge-inactive']">
                {{ bank.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td>
              <button class="btn-edit" @click="openEdit(bank)">تعديل</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" dir="rtl">
        <h2 class="modal-title">{{ editingBank ? 'تعديل بيانات الجهة' : 'إضافة جهة جديدة' }}</h2>

        <div v-if="formError" class="form-error-banner">{{ formError }}</div>

        <div class="form-field">
          <label class="form-label">الاسم بالعربية <span class="required">*</span></label>
          <input v-model="form.name_ar" class="form-input" :class="{ error: formErrors.name_ar }" type="text" placeholder="مثال: البنك التجاري اليمني">
          <span v-if="formErrors.name_ar" class="field-error">{{ formErrors.name_ar }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">الاسم بالإنجليزية <span class="required">*</span></label>
          <input v-model="form.name_en" class="form-input" :class="{ error: formErrors.name_en }" type="text" placeholder="e.g. Yemen Commercial Bank">
          <span v-if="formErrors.name_en" class="field-error">{{ formErrors.name_en }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">الرمز <span class="required">*</span></label>
          <input v-model="form.code" class="form-input" :class="{ error: formErrors.code }" type="text" maxlength="20" placeholder="مثال: YCB">
          <span v-if="formErrors.code" class="field-error">{{ formErrors.code }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">رقم الترخيص</label>
          <input v-model="form.license_number" class="form-input" type="text" maxlength="50" placeholder="مثال: LIC-2026-001">
        </div>

        <div class="form-field">
          <label class="form-label">نوع الجهة</label>
          <select v-model="form.entity_type" class="form-input">
            <option v-for="t in ENTITY_TYPES" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>

        <div class="form-field form-field-inline">
          <label class="form-label">نشط</label>
          <input v-model="form.is_active" type="checkbox" class="form-checkbox">
        </div>

        <div class="modal-actions">
          <button class="btn-secondary" :disabled="saving" @click="closeModal">إلغاء</button>
          <button class="btn-primary" :disabled="saving" @click="saveEntity">
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

.entity-name {
  font-weight: 500;
  color: var(--color-text-primary);
}

.entity-name-en {
  font-size: 12px;
  color: var(--color-text-secondary);
  direction: ltr;
  text-align: right;
}

.code-cell {
  font-family: monospace;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.license-cell {
  font-family: monospace;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.count-cell {
  font-weight: 600;
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

.badge-type {
  background: #e8f0fe;
  color: #1a56db;
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
