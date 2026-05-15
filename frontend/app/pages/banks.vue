<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { Bank } from '../types/models'
import { useBanks } from '../composables/useBanks'
import type { CreateBankPayload, UpdateBankPayload } from '../composables/useBanks'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchBanks, createBank, updateBank } = useBanks()

const banks = ref<Bank[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const showModal = ref(false)
const editingBank = ref<Bank | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface BankForm {
  name_ar: string
  name_en: string
  code: string
  is_active: boolean
}

const form = reactive<BankForm>({
  name_ar: '',
  name_en: '',
  code: '',
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof BankForm, string>>>({})

async function loadBanks() {
  loading.value = true
  error.value = null
  try {
    banks.value = await fetchBanks()
  }
  catch {
    error.value = 'تعذّر تحميل قائمة البنوك.'
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
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function openEdit(bank: Bank) {
  editingBank.value = bank
  form.name_ar = bank.name_ar
  form.name_en = bank.name_en
  form.code = bank.code
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

async function saveBank() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  try {
    const payload: CreateBankPayload | UpdateBankPayload = {
      name_ar: form.name_ar.trim(),
      name_en: form.name_en.trim(),
      code: form.code.trim().toUpperCase(),
      is_active: form.is_active,
    }
    if (editingBank.value) {
      const updated = await updateBank(editingBank.value.id, payload)
      const idx = banks.value.findIndex(b => b.id === updated.id)
      if (idx !== -1) banks.value[idx] = updated
    }
    else {
      const created = await createBank(payload)
      banks.value.unshift(created)
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

onMounted(loadBanks)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">إدارة البنوك</h1>
      <button class="btn-primary" @click="openCreate">
        + إضافة بنك
      </button>
    </div>

    <div v-if="loading" class="state-message">جارٍ التحميل…</div>

    <div v-else-if="error" class="state-message state-error">{{ error }}</div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الاسم بالعربية</th>
            <th>الاسم بالإنجليزية</th>
            <th>الرمز</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="banks.length === 0">
            <td colspan="5" class="empty-row">لا توجد بنوك مسجّلة.</td>
          </tr>
          <tr v-for="bank in banks" :key="bank.id">
            <td>{{ bank.name_ar }}</td>
            <td>{{ bank.name_en }}</td>
            <td class="code-cell">{{ bank.code }}</td>
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
        <h2 class="modal-title">{{ editingBank ? 'تعديل بيانات البنك' : 'إضافة بنك جديد' }}</h2>

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

        <div class="form-field form-field-inline">
          <label class="form-label">نشط</label>
          <input v-model="form.is_active" type="checkbox" class="form-checkbox">
        </div>

        <div class="modal-actions">
          <button class="btn-secondary" :disabled="saving" @click="closeModal">إلغاء</button>
          <button class="btn-primary" :disabled="saving" @click="saveBank">
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

.code-cell {
  font-family: monospace;
  font-size: 13px;
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
  background: #0071e3;
  color: #fff;
  border: none;
  border-radius: 12px;
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
  border-radius: 12px;
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
  color: #0071e3;
  border: 1px solid #0071e3;
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
  color: #ff3b30;
}

/* Modal */
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
  border-radius: 16px;
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
  color: #ff3b30;
}

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  font-size: 14px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
  direction: inherit;
}

.form-input:focus {
  border-color: #0071e3;
}

.form-input.error {
  border-color: #ff3b30;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.field-error {
  font-size: 12px;
  color: #ff3b30;
}

.form-error-banner {
  background: #fff0ef;
  border: 1px solid #ff3b30;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #ff3b30;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 4px;
}
</style>
