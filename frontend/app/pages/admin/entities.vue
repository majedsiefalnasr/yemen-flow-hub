<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { UserRole } from '../../types/enums'
import type { Bank } from '../../types/models'
import { useBanks } from '../../composables/useBanks'
import type { CreateBankPayload, UpdateBankPayload } from '../../composables/useBanks'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchBanks, createBank, updateBank } = useBanks()

const banks = ref<Bank[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const searchQuery = ref('')

const showModal = ref(false)
const editingBank = ref<Bank | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

const showViewModal = ref(false)
const viewingBank = ref<Bank | null>(null)

interface EntityForm {
  name_ar: string
  name_en: string
  code: string
  license_number: string
  is_active: boolean
}

const form = reactive<EntityForm>({
  name_ar: '',
  name_en: '',
  code: '',
  license_number: '',
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof EntityForm, string>>>({})

const totalCount = computed(() => banks.value.length)
const activeCount = computed(() => banks.value.filter(b => b.is_active).length)
const inactiveCount = computed(() => banks.value.filter(b => !b.is_active).length)

const filteredBanks = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return banks.value
  return banks.value.filter(b =>
    b.name_ar.toLowerCase().includes(q)
    || b.name_en.toLowerCase().includes(q)
    || b.code.toLowerCase().includes(q)
    || (b.license_number ?? '').toLowerCase().includes(q),
  )
})

const isFiltered = computed(() => searchQuery.value.trim().length > 0)
const isFilteredEmpty = computed(() => isFiltered.value && filteredBanks.value.length === 0)

function getBankInitials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map((w: string) => w[0]).join('').toUpperCase()
}

function getBankColor(id: number): string {
  const colors = ['#0066cc', '#5856d6', '#32ade6', '#1b5e20', '#f57f17', '#c62828']
  return colors[id % colors.length]!
}

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
  form.license_number = ''
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function openEdit(bank: Bank) {
  editingBank.value = bank
  form.name_ar = bank.name_ar
  form.name_en = bank.name_en
  form.code = bank.code
  form.license_number = bank.license_number ?? ''
  form.is_active = bank.is_active
  clearErrors()
  showModal.value = true
}

function openView(bank: Bank) {
  viewingBank.value = bank
  showViewModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function closeViewModal() {
  showViewModal.value = false
  viewingBank.value = null
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
  try {
    if (editingBank.value) {
      const payload: UpdateBankPayload = {
        name: form.name_ar.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim().toUpperCase(),
        license_number: normalizedLicense,
        is_active: form.is_active,
      }
      const updated = await updateBank(editingBank.value.id, payload)
      const idx = banks.value.findIndex(b => b.id === updated.id)
      if (idx !== -1) {
        banks.value[idx] = { ...banks.value[idx]!, ...updated, license_number: normalizedLicense }
      }
    }
    else {
      const payload: CreateBankPayload = {
        name: form.name_ar.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim().toUpperCase(),
        license_number: normalizedLicense,
        is_active: form.is_active,
      }
      const created = await createBank(payload)
      banks.value.unshift({ ...created, license_number: normalizedLicense })
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>; message?: string } }
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

async function toggleActivation(bank: Bank) {
  try {
    const payload: UpdateBankPayload = {
      name: bank.name_ar,
      name_ar: bank.name_ar,
      name_en: bank.name_en,
      code: bank.code,
      is_active: !bank.is_active,
    }
    const updated = await updateBank(bank.id, payload)
    const idx = banks.value.findIndex(b => b.id === updated.id)
    if (idx !== -1) {
      banks.value[idx] = { ...banks.value[idx]!, is_active: !bank.is_active }
    }
  }
  catch (err: unknown) {
    const e = err as { data?: { message?: string } }
    const msg = e.data?.message ?? 'تعذّر تغيير حالة التفعيل.'
    error.value = msg
  }
}

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(loadBanks)
</script>

<template>
  <div class="page" dir="rtl">
    <!-- Breadcrumbs -->
    <nav class="breadcrumbs" aria-label="breadcrumb">
      <span class="breadcrumb-item">الرئيسية</span>
      <span class="breadcrumb-sep">/</span>
      <span class="breadcrumb-item breadcrumb-active">إدارة البنوك</span>
    </nav>

    <!-- Page header -->
    <div class="page-title-row">
      <div class="page-title-group">
        <h1 class="page-title">إدارة البنوك التجارية</h1>
        <p class="page-subtitle">إنشاء بنوك جديدة، عرض البيانات، تعديلها وتغيير حالة التفعيل</p>
      </div>
      <button class="btn-primary" @click="openCreate">
        <span class="btn-icon">+</span> بنك جديد
      </button>
    </div>

    <!-- Stat cards -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3" /><path d="M3 9h18" /></svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ totalCount }}</div>
          <div class="stat-label">إجمالي البنوك</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-green">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ activeCount }}</div>
          <div class="stat-label">نشط</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-gray">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" /><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" /></svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ inactiveCount }}</div>
          <div class="stat-label">غير نشط</div>
        </div>
      </div>
    </div>

    <!-- Filter card -->
    <div class="filter-card">
      <div class="search-input-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="بحث بالاسم أو رقم الترخيص أو رمز البنك..."
          dir="rtl"
        >
      </div>
    </div>

    <!-- Loading skeletons -->
    <div v-if="loading" class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الجهة</th>
            <th>رقم الترخيص</th>
            <th>الرمز</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="n in 4" :key="n" class="skeleton-row">
            <td><div class="skeleton skeleton-wide" /></td>
            <td><div class="skeleton skeleton-mid" /></td>
            <td><div class="skeleton skeleton-short" /></td>
            <td><div class="skeleton skeleton-short" /></td>
            <td><div class="skeleton skeleton-mid" /></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="state-message state-error" role="alert">
      {{ error }}
      <button class="retry-btn" @click="loadBanks">إعادة المحاولة</button>
    </div>

    <!-- Table -->
    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الجهة</th>
            <th>رقم الترخيص</th>
            <th>الرمز</th>
            <th>الحالة</th>
            <th class="actions-col">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <!-- Empty state -->
          <tr v-if="banks.length === 0">
            <td colspan="5" class="empty-row" data-empty-state-variant="entities">لا توجد بنوك مسجّلة.</td>
          </tr>
          <!-- Filtered empty -->
          <tr v-else-if="isFilteredEmpty">
            <td colspan="5" class="empty-row">لا توجد نتائج.</td>
          </tr>
          <tr v-for="bank in filteredBanks" :key="bank.id">
            <td>
              <div class="bank-cell">
                <div
                  class="bank-avatar"
                  :style="{ background: getBankColor(bank.id) }"
                  aria-hidden="true"
                >
                  {{ getBankInitials(bank.name_ar) }}
                </div>
                <div class="bank-info">
                  <div class="bank-name">{{ bank.name_ar }}</div>
                  <div class="bank-name-en" dir="ltr">{{ bank.name_en }}</div>
                </div>
              </div>
            </td>
            <td class="mono-cell">{{ bank.license_number ?? '—' }}</td>
            <td class="mono-cell">{{ bank.code }}</td>
            <td>
              <span :class="['badge', bank.is_active ? 'badge-active' : 'badge-inactive']">
                {{ bank.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td class="actions-col">
              <div class="action-btns">
                <button class="btn-action btn-view" :aria-label="`عرض ${bank.name_ar}`" @click="openView(bank)">
                  عرض
                </button>
                <button class="btn-action btn-edit" :aria-label="`تعديل ${bank.name_ar}`" @click="openEdit(bank)">
                  تعديل
                </button>
                <button
                  :class="['btn-action', bank.is_active ? 'btn-deactivate' : 'btn-activate']"
                  :aria-label="bank.is_active ? `إيقاف ${bank.name_ar}` : `تفعيل ${bank.name_ar}`"
                  @click="toggleActivation(bank)"
                >
                  {{ bank.is_active ? 'إيقاف' : 'تفعيل' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- View modal -->
    <div v-if="showViewModal && viewingBank" class="modal-layer">
      <div class="modal-backdrop" @click="closeViewModal" />
      <div class="modal" dir="rtl" role="dialog" :aria-label="`بيانات ${viewingBank.name_ar}`">
        <div class="modal-header">
          <h2 class="modal-title">بيانات البنك</h2>
          <button class="close-btn" aria-label="إغلاق" @click="closeViewModal">✕</button>
        </div>
        <div class="view-fields">
          <div class="view-row">
            <span class="view-label">اسم البنك</span>
            <span class="view-value">{{ viewingBank.name_ar }}</span>
          </div>
          <div class="view-row">
            <span class="view-label">الاسم بالإنجليزية</span>
            <span class="view-value" dir="ltr">{{ viewingBank.name_en }}</span>
          </div>
          <div class="view-row">
            <span class="view-label">الرمز</span>
            <span class="view-value mono-cell">{{ viewingBank.code }}</span>
          </div>
          <div class="view-row">
            <span class="view-label">رقم الترخيص</span>
            <span class="view-value mono-cell">{{ viewingBank.license_number ?? '—' }}</span>
          </div>
          <div class="view-row">
            <span class="view-label">الحالة</span>
            <span :class="['badge', viewingBank.is_active ? 'badge-active' : 'badge-inactive']">
              {{ viewingBank.is_active ? 'نشط' : 'موقوف' }}
            </span>
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="closeViewModal">إغلاق</button>
        </div>
      </div>
    </div>

    <!-- Add / edit modal -->
    <div v-if="showModal" class="modal-layer">
      <div class="modal-backdrop" @click="closeModal" />
      <div class="modal" dir="rtl" role="dialog" :aria-label="editingBank ? 'تعديل بيانات البنك' : 'إضافة بنك جديد'">
        <div class="modal-header">
          <h2 class="modal-title">{{ editingBank ? 'تعديل بيانات البنك' : 'إضافة بنك جديد' }}</h2>
          <button class="close-btn" aria-label="إغلاق" :disabled="saving" @click="closeModal">✕</button>
        </div>

        <div v-if="formError" class="form-error-banner" role="alert">{{ formError }}</div>

        <div class="form-grid">
          <div class="form-field form-field-full">
            <label class="form-label" for="bank-name-ar">اسم البنك <span class="required">*</span></label>
            <input
              id="bank-name-ar"
              v-model="form.name_ar"
              class="form-input"
              :class="{ error: formErrors.name_ar }"
              type="text"
              placeholder="مثال: البنك التجاري اليمني"
            >
            <span v-if="formErrors.name_ar" class="field-error" role="alert">{{ formErrors.name_ar }}</span>
          </div>

          <div class="form-field form-field-full">
            <label class="form-label" for="bank-name-en">الاسم بالإنجليزية <span class="required">*</span></label>
            <input
              id="bank-name-en"
              v-model="form.name_en"
              class="form-input"
              :class="{ error: formErrors.name_en }"
              type="text"
              dir="ltr"
              placeholder="e.g. Yemen Commercial Bank"
            >
            <span v-if="formErrors.name_en" class="field-error" role="alert">{{ formErrors.name_en }}</span>
          </div>

          <div class="form-field">
            <label class="form-label" for="bank-code">الرمز <span class="required">*</span></label>
            <input
              id="bank-code"
              v-model="form.code"
              class="form-input"
              :class="{ error: formErrors.code }"
              type="text"
              maxlength="20"
              dir="ltr"
              placeholder="مثال: BNK-001"
            >
            <span v-if="formErrors.code" class="field-error" role="alert">{{ formErrors.code }}</span>
          </div>

          <div class="form-field">
            <label class="form-label" for="bank-license">رقم الترخيص</label>
            <input
              id="bank-license"
              v-model="form.license_number"
              class="form-input"
              type="text"
              maxlength="50"
              dir="ltr"
              placeholder="مثال: YBRDYESA"
            >
          </div>

          <div class="form-field form-field-full">
            <div class="status-toggle-row">
              <div>
                <div class="form-label">الحالة</div>
                <div class="status-toggle-hint">{{ form.is_active ? 'نشط' : 'موقوف' }}</div>
              </div>
              <div class="toggle-btns">
                <button
                  type="button"
                  :class="['toggle-btn', form.is_active ? 'toggle-btn-active' : '']"
                  @click="form.is_active = true"
                >
                  نشط
                </button>
                <button
                  type="button"
                  :class="['toggle-btn', !form.is_active ? 'toggle-btn-inactive' : '']"
                  @click="form.is_active = false"
                >
                  موقوف
                </button>
              </div>
            </div>
          </div>

          <!-- Production-governance note: admin account fields (admin name/email) shown in Lovable
               are NOT backed by BankResource or StoreBankRequest — intentionally omitted. -->
        </div>

        <div class="modal-actions">
          <button class="btn-secondary" :disabled="saving" @click="closeModal">إلغاء</button>
          <button class="btn-primary" :disabled="saving" @click="saveEntity">
            {{ saving ? 'جارٍ الحفظ…' : (editingBank ? 'حفظ التعديلات' : 'إضافة') }}
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

.breadcrumb-active {
  color: #1c222b;
  font-weight: 500;
}

.page-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-title-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 44px;
  padding: 0 20px;
  background: #0066cc;
  color: #fff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-icon {
  font-size: 18px;
  line-height: 1;
}

/* Stat cards */
.stat-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 20px;
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

.stat-icon-blue { background: #e8f0fe; color: #0066cc; }
.stat-icon-green { background: #e6f9ec; color: #1b5e20; }
.stat-icon-gray { background: #f0f0f3; color: #8e8e93; }

.stat-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #1c222b;
  line-height: 1;
}

.stat-label {
  font-size: 13px;
  color: #6c757d;
}

/* Filter card */
.filter-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  gap: 12px;
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
  color: #8e8e93;
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 40px;
  padding: 0 38px 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  box-sizing: border-box;
  font-family: inherit;
}

.search-input:focus {
  border-color: #0066cc;
}

/* Card / table */
.card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
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
  color: #6c757d;
  font-weight: 500;
  border-bottom: 1px solid #cccccc;
  white-space: nowrap;
}

.data-table td {
  border-bottom: 1px solid #cccccc;
  color: #1c222b;
  vertical-align: middle;
}

.data-table tr:last-child td {
  border-bottom: none;
}

/* Bank cell */
.bank-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.bank-avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  flex-shrink: 0;
}

.bank-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bank-name {
  font-weight: 500;
  color: #1c222b;
  font-size: 14px;
}

.bank-name-en {
  font-size: 12px;
  color: #6c757d;
}

.mono-cell {
  font-family: monospace;
  font-size: 13px;
  color: #6c757d;
}

.actions-col {
  white-space: nowrap;
  text-align: left;
}

/* Badges */
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

/* Action buttons */
.action-btns {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: flex-start;
}

.btn-action {
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
  border: 1px solid transparent;
}

.btn-view {
  color: #0066cc;
  border-color: #0066cc;
  background: transparent;
}

.btn-edit {
  color: #1c222b;
  border-color: #cccccc;
  background: transparent;
}

.btn-deactivate {
  color: #c62828;
  border-color: #c62828;
  background: transparent;
}

.btn-activate {
  color: #1b5e20;
  border-color: #1b5e20;
  background: transparent;
}

/* Empty row */
.empty-row {
  text-align: center !important;
  color: #6c757d;
  padding: 40px !important;
}

/* State messages */
.state-message {
  text-align: center;
  color: #6c757d;
  padding: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.state-error {
  color: #c62828;
}

.retry-btn {
  padding: 8px 20px;
  border: 1px solid #c62828;
  border-radius: 12px;
  color: #c62828;
  background: transparent;
  font-size: 14px;
  cursor: pointer;
}

/* Skeleton */
.skeleton-row td {
  padding: 18px 16px;
}

.skeleton {
  height: 14px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f0f3 25%, #e4e4e7 50%, #f0f0f3 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.skeleton-wide { width: 160px; }
.skeleton-mid { width: 90px; }
.skeleton-short { width: 60px; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Modal */
.modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
}

.modal {
  position: relative;
  z-index: 1;
  background: #ffffff;
  border-radius: 24px;
  padding: 32px;
  width: 520px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
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

.close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: #6c757d;
  cursor: pointer;
  line-height: 1;
  padding: 4px;
}

.close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* View modal fields */
.view-fields {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.view-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
}

.view-label {
  font-size: 13px;
  color: #6c757d;
  min-width: 130px;
  flex-shrink: 0;
}

.view-value {
  font-size: 14px;
  color: #1c222b;
}

/* Form */
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

.form-label {
  font-size: 13px;
  color: #6c757d;
  font-weight: 500;
}

.required {
  color: #c62828;
}

.form-input {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.form-input:focus {
  border-color: #0066cc;
}

.form-input.error {
  border-color: #c62828;
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

/* Status toggle */
.status-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border: 1px solid #cccccc;
  border-radius: 12px;
}

.status-toggle-hint {
  font-size: 12px;
  color: #6c757d;
  margin-top: 2px;
}

.toggle-btns {
  display: flex;
  border: 1px solid #cccccc;
  border-radius: 10px;
  overflow: hidden;
}

.toggle-btn {
  padding: 6px 14px;
  border: none;
  background: transparent;
  font-size: 13px;
  cursor: pointer;
  color: #6c757d;
}

.toggle-btn-active {
  background: #0066cc;
  color: #ffffff;
}

.toggle-btn-inactive {
  background: #f0f0f3;
  color: #1c222b;
}

/* Modal actions */
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-secondary {
  height: 44px;
  padding: 0 24px;
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

@media (max-width: 600px) {
  .stat-cards {
    grid-template-columns: 1fr;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .page-title-row {
    flex-direction: column;
  }
}
</style>
