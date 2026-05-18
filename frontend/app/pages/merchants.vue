<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { Bank, Merchant } from '../types/models'
import { useMerchants } from '../composables/useMerchants'
import { useBanks } from '../composables/useBanks'
import type { CreateMerchantPayload, UpdateMerchantPayload } from '../composables/useMerchants'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchMerchants, createMerchant, updateMerchant } = useMerchants()
const { fetchBanks } = useBanks()

const merchants = ref<Merchant[]>([])
const banks = ref<Bank[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const searchQuery = ref('')

const showModal = ref(false)
const editingMerchant = ref<Merchant | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface MerchantForm {
  name: string
  bank_id: number | null
  commercial_register: string
  tax_number: string
  owner_name: string
  phone: string
  email: string
  address: string
  is_active: boolean
}

const form = reactive<MerchantForm>({
  name: '',
  bank_id: null,
  commercial_register: '',
  tax_number: '',
  owner_name: '',
  phone: '',
  email: '',
  address: '',
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof MerchantForm, string>>>({})

const filteredMerchants = computed(() => {
  if (!searchQuery.value.trim()) return merchants.value
  const q = searchQuery.value.trim().toLowerCase()
  return merchants.value.filter(m =>
    m.name.toLowerCase().includes(q)
    || (m.commercial_register ?? '').toLowerCase().includes(q)
    || (m.owner_name ?? '').toLowerCase().includes(q),
  )
})

async function loadData() {
  loading.value = true
  error.value = null
  try {
    const [merchantsData, banksData] = await Promise.all([fetchMerchants(), fetchBanks()])
    merchants.value = merchantsData
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
  editingMerchant.value = null
  form.name = ''
  form.bank_id = null
  form.commercial_register = ''
  form.tax_number = ''
  form.owner_name = ''
  form.phone = ''
  form.email = ''
  form.address = ''
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function openEdit(merchant: Merchant) {
  editingMerchant.value = merchant
  form.name = merchant.name
  form.bank_id = merchant.bank_id
  form.commercial_register = merchant.commercial_register ?? ''
  form.tax_number = merchant.tax_number ?? ''
  form.owner_name = merchant.owner_name ?? ''
  form.phone = merchant.phone ?? ''
  form.email = merchant.email ?? ''
  form.address = merchant.address ?? ''
  form.is_active = merchant.is_active
  clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function clearErrors() {
  formErrors.name = undefined
  formErrors.bank_id = undefined
  formErrors.commercial_register = undefined
  formErrors.tax_number = undefined
  formErrors.email = undefined
  formError.value = null
}

function validateForm(): boolean {
  clearErrors()
  let valid = true
  if (!form.name.trim()) { formErrors.name = 'اسم التاجر مطلوب'; valid = false }
  if (!form.bank_id) { formErrors.bank_id = 'البنك مطلوب'; valid = false }
  if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    formErrors.email = 'البريد الإلكتروني غير صالح'
    valid = false
  }
  return valid
}

async function saveMerchant() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  try {
    if (editingMerchant.value) {
      const payload: UpdateMerchantPayload = {
        name: form.name.trim(),
        commercial_register: form.commercial_register.trim() || null,
        tax_number: form.tax_number.trim() || null,
        owner_name: form.owner_name.trim() || null,
        phone: form.phone.trim() || null,
        email: form.email.trim() || null,
        address: form.address.trim() || null,
        is_active: form.is_active,
      }
      const updated = await updateMerchant(editingMerchant.value.id, payload)
      const idx = merchants.value.findIndex(m => m.id === updated.id)
      if (idx !== -1) merchants.value[idx] = updated
    }
    else {
      const payload: CreateMerchantPayload = {
        name: form.name.trim(),
        bank_id: form.bank_id!,
        commercial_register: form.commercial_register.trim() || null,
        tax_number: form.tax_number.trim() || null,
        owner_name: form.owner_name.trim() || null,
        phone: form.phone.trim() || null,
        email: form.email.trim() || null,
        address: form.address.trim() || null,
        is_active: form.is_active,
      }
      const created = await createMerchant(payload)
      merchants.value.unshift(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.data?.errors) {
      const errs = e.data.errors
      if (errs.name?.[0]) formErrors.name = errs.name[0]
      if (errs.bank_id?.[0]) formErrors.bank_id = errs.bank_id[0]
      if (errs.commercial_register?.[0]) formErrors.commercial_register = errs.commercial_register[0]
      if (errs.tax_number?.[0]) formErrors.tax_number = errs.tax_number[0]
      if (errs.email?.[0]) formErrors.email = errs.email[0]
    }
    else {
      formError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
    }
  }
  finally {
    saving.value = false
  }
}

function bankLabel(merchant: Merchant): string {
  return merchant.bank_name ?? String(merchant.bank_id)
}

onMounted(loadData)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">إدارة التجار</h1>
      <button class="btn-primary" @click="openCreate">
        + إضافة تاجر
      </button>
    </div>

    <div class="search-bar">
      <input
        v-model="searchQuery"
        type="text"
        class="search-input"
        placeholder="بحث بالاسم أو السجل التجاري أو اسم المالك…"
      >
    </div>

    <div v-if="loading" class="state-message">جارٍ التحميل…</div>

    <div v-else-if="error" class="state-message state-error">
      {{ error }}
      <button class="btn-retry" @click="loadData">إعادة المحاولة</button>
    </div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>اسم التاجر</th>
            <th>السجل التجاري</th>
            <th>المالك</th>
            <th>البنك</th>
            <th>الهاتف</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="(filteredMerchants?.length ?? 0) === 0">
            <td colspan="7" class="empty-row">لا يوجد تجار مسجّلون.</td>
          </tr>
          <tr v-for="merchant in (filteredMerchants ?? [])" :key="merchant.id">
            <td class="name-cell">{{ merchant.name }}</td>
            <td class="mono-cell">{{ merchant.commercial_register ?? '—' }}</td>
            <td>{{ merchant.owner_name ?? '—' }}</td>
            <td>{{ bankLabel(merchant) }}</td>
            <td class="mono-cell">{{ merchant.phone ?? '—' }}</td>
            <td>
              <span :class="['badge', merchant.is_active ? 'badge-active' : 'badge-inactive']">
                {{ merchant.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td>
              <button class="btn-edit" @click="openEdit(merchant)">تعديل</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" dir="rtl">
        <h2 class="modal-title">{{ editingMerchant ? 'تعديل بيانات التاجر' : 'إضافة تاجر جديد' }}</h2>

        <div v-if="formError" class="form-error-banner">{{ formError }}</div>

        <div class="form-grid">
          <div class="form-field">
            <label class="form-label">اسم التاجر <span class="required">*</span></label>
            <input v-model="form.name" class="form-input" :class="{ error: formErrors.name }" type="text" placeholder="الاسم التجاري">
            <span v-if="formErrors.name" class="field-error">{{ formErrors.name }}</span>
          </div>

          <div v-if="!editingMerchant" class="form-field">
            <label class="form-label">البنك <span class="required">*</span></label>
            <select v-model="form.bank_id" class="form-input" :class="{ error: formErrors.bank_id }">
              <option :value="null" disabled>اختر البنك</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                {{ bank.name_ar }}
              </option>
            </select>
            <span v-if="formErrors.bank_id" class="field-error">{{ formErrors.bank_id }}</span>
          </div>

          <div class="form-field">
            <label class="form-label">السجل التجاري</label>
            <input v-model="form.commercial_register" class="form-input" :class="{ error: formErrors.commercial_register }" type="text" placeholder="رقم السجل التجاري">
            <span v-if="formErrors.commercial_register" class="field-error">{{ formErrors.commercial_register }}</span>
          </div>

          <div class="form-field">
            <label class="form-label">الرقم الضريبي</label>
            <input v-model="form.tax_number" class="form-input" :class="{ error: formErrors.tax_number }" type="text" placeholder="الرقم الضريبي">
            <span v-if="formErrors.tax_number" class="field-error">{{ formErrors.tax_number }}</span>
          </div>

          <div class="form-field">
            <label class="form-label">اسم المالك</label>
            <input v-model="form.owner_name" class="form-input" type="text" placeholder="الاسم الكامل">
          </div>

          <div class="form-field">
            <label class="form-label">رقم الهاتف</label>
            <input v-model="form.phone" class="form-input" type="tel" placeholder="+967…" dir="ltr">
          </div>

          <div class="form-field">
            <label class="form-label">البريد الإلكتروني</label>
            <input v-model="form.email" class="form-input" :class="{ error: formErrors.email }" type="email" placeholder="merchant@example.com" dir="ltr">
            <span v-if="formErrors.email" class="field-error">{{ formErrors.email }}</span>
          </div>

          <div class="form-field form-field-full">
            <label class="form-label">العنوان</label>
            <input v-model="form.address" class="form-input" type="text" placeholder="المدينة، الشارع…">
          </div>

          <div class="form-field form-field-inline">
            <input v-model="form.is_active" type="checkbox" class="form-checkbox">
            <label class="form-label">نشط</label>
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn-secondary" :disabled="saving" @click="closeModal">إلغاء</button>
          <button class="btn-primary" :disabled="saving" @click="saveMerchant">
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
  gap: 20px;
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

.search-bar {
  display: flex;
  gap: 12px;
}

.search-input {
  flex: 1;
  max-width: 400px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  font-size: 14px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
  direction: rtl;
}

.search-input:focus {
  border-color: #0071e3;
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
  padding: 12px 16px;
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

.name-cell {
  font-weight: 500;
}

.mono-cell {
  direction: ltr;
  text-align: right;
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
  padding: 3px 10px;
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

.btn-retry {
  display: block;
  margin: 12px auto 0;
  padding: 6px 16px;
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
  width: 560px;
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

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field-full {
  grid-column: 1 / -1;
}

.form-field-inline {
  flex-direction: row;
  align-items: center;
  gap: 8px;
  grid-column: 1 / -1;
}

.form-label {
  font-size: 13px;
  color: #6e6e73;
}

.required {
  color: #ff3b30;
}

.form-input {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #d2d2d7;
  border-radius: 10px;
  font-size: 14px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
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
