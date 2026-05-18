<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { UserRole } from '../../types/enums'
import type { DocumentType } from '../../types/models'
import { useDocumentTypes } from '../../composables/useDocumentTypes'
import type { CreateDocumentTypePayload, UpdateDocumentTypePayload } from '../../composables/useDocumentTypes'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchDocumentTypes, createDocumentType, updateDocumentType } = useDocumentTypes()

const documentTypes = ref<DocumentType[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const showModal = ref(false)
const editingType = ref<DocumentType | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface DocTypeForm {
  slug: string
  name_ar: string
  name_en: string
  is_required: boolean
  is_active: boolean
  sort_order: number
}

const form = reactive<DocTypeForm>({
  slug: '',
  name_ar: '',
  name_en: '',
  is_required: false,
  is_active: true,
  sort_order: 0,
})

const formErrors = reactive<Partial<Record<keyof DocTypeForm, string>>>({})

async function loadTypes() {
  loading.value = true
  error.value = null
  try {
    documentTypes.value = await fetchDocumentTypes()
  }
  catch {
    error.value = 'تعذّر تحميل قواعد المستندات.'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingType.value = null
  form.slug = ''
  form.name_ar = ''
  form.name_en = ''
  form.is_required = false
  form.is_active = true
  form.sort_order = documentTypes.value.length
  clearErrors()
  showModal.value = true
}

function openEdit(docType: DocumentType) {
  editingType.value = docType
  form.slug = docType.slug
  form.name_ar = docType.name_ar
  form.name_en = docType.name_en
  form.is_required = docType.is_required
  form.is_active = docType.is_active
  form.sort_order = docType.sort_order
  clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function clearErrors() {
  formErrors.slug = undefined
  formErrors.name_ar = undefined
  formErrors.name_en = undefined
  formError.value = null
}

function validateForm(): boolean {
  clearErrors()
  let valid = true
  if (!editingType.value && !form.slug.trim()) { formErrors.slug = 'المعرّف (slug) مطلوب'; valid = false }
  if (!form.name_ar.trim()) { formErrors.name_ar = 'الاسم بالعربية مطلوب'; valid = false }
  if (!form.name_en.trim()) { formErrors.name_en = 'الاسم بالإنجليزية مطلوب'; valid = false }
  return valid
}

async function saveDocType() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  try {
    if (editingType.value) {
      const payload: UpdateDocumentTypePayload = {
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        is_required: form.is_required,
        is_active: form.is_active,
        sort_order: form.sort_order,
      }
      const updated = await updateDocumentType(editingType.value.id, payload)
      const idx = documentTypes.value.findIndex(d => d.id === updated.id)
      if (idx !== -1) documentTypes.value[idx] = updated
    }
    else {
      const payload: CreateDocumentTypePayload = {
        slug: form.slug.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        is_required: form.is_required,
        is_active: form.is_active,
        sort_order: form.sort_order,
      }
      const created = await createDocumentType(payload)
      documentTypes.value.push(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.data?.errors) {
      const errs = e.data.errors
      if (errs.slug?.[0]) formErrors.slug = errs.slug[0]
      if (errs.name_ar?.[0]) formErrors.name_ar = errs.name_ar[0]
      if (errs.name_en?.[0]) formErrors.name_en = errs.name_en[0]
    }
    else {
      formError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
    }
  }
  finally {
    saving.value = false
  }
}

onMounted(loadTypes)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">قواعد المستندات</h1>
        <p class="page-subtitle">إدارة أنواع المستندات المطلوبة في سير العمل</p>
      </div>
      <button class="btn-primary" @click="openCreate">
        + إضافة نوع مستند
      </button>
    </div>

    <div v-if="loading" class="state-card">
      <div class="spinner" />
      <span>جارٍ التحميل…</span>
    </div>

    <div v-else-if="error" class="state-card state-error">
      {{ error }}
      <button class="btn-retry" @click="loadTypes">إعادة المحاولة</button>
    </div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الترتيب</th>
            <th>الاسم (عربي)</th>
            <th>الاسم (إنجليزي)</th>
            <th>المعرّف</th>
            <th>مطلوب</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="documentTypes.length === 0">
            <td colspan="7" class="empty-row">لا توجد أنواع مستندات مضافة.</td>
          </tr>
          <tr v-for="docType in documentTypes" :key="docType.id">
            <td class="order-cell">{{ docType.sort_order }}</td>
            <td class="name-cell">{{ docType.name_ar }}</td>
            <td class="name-cell-en">{{ docType.name_en }}</td>
            <td class="slug-cell">{{ docType.slug }}</td>
            <td>
              <span :class="['badge', docType.is_required ? 'badge-required' : 'badge-optional']">
                {{ docType.is_required ? 'إلزامي' : 'اختياري' }}
              </span>
            </td>
            <td>
              <span :class="['badge', docType.is_active ? 'badge-active' : 'badge-inactive']">
                {{ docType.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td>
              <button class="btn-edit" @click="openEdit(docType)">تعديل</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" dir="rtl">
        <h2 class="modal-title">{{ editingType ? 'تعديل نوع المستند' : 'إضافة نوع مستند جديد' }}</h2>

        <div v-if="formError" class="form-error-banner">{{ formError }}</div>

        <div v-if="!editingType" class="form-field">
          <label class="form-label">المعرّف (slug) <span class="required">*</span></label>
          <input v-model="form.slug" class="form-input" :class="{ error: formErrors.slug }" type="text" placeholder="مثال: commercial_invoice" dir="ltr">
          <span v-if="formErrors.slug" class="field-error">{{ formErrors.slug }}</span>
          <span class="field-hint">معرّف فريد بالأحرف اللاتينية والشرطة السفلية فقط. لا يمكن تعديله لاحقاً.</span>
        </div>

        <div class="form-field">
          <label class="form-label">الاسم بالعربية <span class="required">*</span></label>
          <input v-model="form.name_ar" class="form-input" :class="{ error: formErrors.name_ar }" type="text" placeholder="مثال: الفاتورة التجارية">
          <span v-if="formErrors.name_ar" class="field-error">{{ formErrors.name_ar }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">الاسم بالإنجليزية <span class="required">*</span></label>
          <input v-model="form.name_en" class="form-input" :class="{ error: formErrors.name_en }" type="text" placeholder="e.g. Commercial Invoice" dir="ltr">
          <span v-if="formErrors.name_en" class="field-error">{{ formErrors.name_en }}</span>
        </div>

        <div class="form-field">
          <label class="form-label">الترتيب</label>
          <input v-model.number="form.sort_order" class="form-input" type="number" min="0" placeholder="0">
        </div>

        <div class="form-row">
          <div class="form-field form-field-inline">
            <input v-model="form.is_required" type="checkbox" class="form-checkbox">
            <label class="form-label">إلزامي</label>
          </div>
          <div class="form-field form-field-inline">
            <input v-model="form.is_active" type="checkbox" class="form-checkbox">
            <label class="form-label">نشط</label>
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn-secondary" :disabled="saving" @click="closeModal">إلغاء</button>
          <button class="btn-primary" :disabled="saving" @click="saveDocType">
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
  align-items: flex-start;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

.state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 48px 24px;
  color: var(--color-text-secondary);
  text-align: center;
}

.state-error {
  color: #ff3b30;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border);
  border-top-color: #0071e3;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.btn-retry {
  padding: 8px 20px;
  background: transparent;
  color: #0071e3;
  border: 1px solid #0071e3;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
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

.order-cell {
  font-size: 13px;
  color: var(--color-text-secondary);
  width: 60px;
}

.name-cell {
  font-weight: 500;
}

.name-cell-en {
  direction: ltr;
  text-align: right;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.slug-cell {
  direction: ltr;
  text-align: right;
  font-size: 12px;
  color: var(--color-text-secondary);
  font-family: monospace;
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

.badge-required {
  background: #fff3e0;
  color: #a05b00;
}

.badge-optional {
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
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 18px;
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
  gap: 8px;
}

.form-row {
  display: flex;
  gap: 24px;
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

.field-hint {
  font-size: 11px;
  color: #8e8e93;
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
