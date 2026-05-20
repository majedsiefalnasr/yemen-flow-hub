<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Merchant } from '../../types/models'
import Dialog from '../ui/dialog/Dialog.vue'
import DialogContent from '../ui/dialog/DialogContent.vue'
import DialogFooter from '../ui/dialog/DialogFooter.vue'
import DialogHeader from '../ui/dialog/DialogHeader.vue'
import DialogOverlay from '../ui/dialog/DialogOverlay.vue'
import DialogTitle from '../ui/dialog/DialogTitle.vue'

const BUSINESS_TYPE_OPTIONS = [
  { value: 'import', label: 'استيراد' },
  { value: 'export', label: 'تصدير' },
  { value: 'retail', label: 'تجارة تجزئة' },
  { value: 'wholesale', label: 'تجارة جملة' },
  { value: 'manufacturing', label: 'تصنيع' },
  { value: 'services', label: 'خدمات' },
]

const schema = toTypedSchema(z.object({
  name: z.string().trim().min(1, 'اسم التاجر مطلوب'),
  commercial_register: z.string().trim().min(1, 'رقم السجل التجاري مطلوب'),
  tax_number: z.string().trim().min(1, 'الرقم الضريبي مطلوب'),
  phone: z.string().optional().default(''),
  address: z.string().optional().default(''),
  business_type: z.string().optional().default(''),
  is_active: z.string().optional().default('true'),
  bank_id: z.string().optional().default(''),
}))

interface BankOption {
  id: number
  name: string
}

const props = defineProps<{
  merchant: Merchant | null
  saving: boolean
  serverError: string | null
  requiresBankSelection: boolean
  bankOptions: BankOption[]
  defaultBankId: number | null
  lockedBankName: string | null
}>()

const emit = defineEmits<{
  save: [data: {
    name: string
    commercial_register: string
    tax_number: string
    phone: string | null
    address: string | null
    business_type: string | null
    is_active: boolean | undefined
    bank_id: number | null
  }]
  close: []
}>()

const {
  handleSubmit,
  errors,
  defineField,
  resetForm,
  meta,
  setFieldError,
} = useForm({
  validationSchema: schema,
  validateOnMount: true,
})

const [name, nameAttrs] = defineField('name')
const [commercial_register, commercialRegisterAttrs] = defineField('commercial_register')
const [tax_number, taxNumberAttrs] = defineField('tax_number')
const [phone, phoneAttrs] = defineField('phone')
const [address, addressAttrs] = defineField('address')
const [business_type, businessTypeAttrs] = defineField('business_type')
const [is_active, isActiveAttrs] = defineField('is_active')
const [bank_id, bankIdAttrs] = defineField('bank_id')

const isEditMode = computed(() => !!props.merchant)
const isBankRequiredForCreate = computed(() => props.requiresBankSelection && !props.merchant)
const showLockedBankField = computed(() => !props.requiresBankSelection && !!props.lockedBankName)
const isSaveDisabled = computed(() => (
  props.saving
  || !meta.value.valid
  || (isBankRequiredForCreate.value && !bank_id.value)
))

watch(() => props.merchant, (merchant) => {
  if (merchant) {
    resetForm({
      values: {
        name: merchant.name,
        commercial_register: merchant.commercial_register ?? '',
        tax_number: merchant.tax_number ?? '',
        phone: merchant.phone ?? '',
        address: merchant.address ?? '',
        business_type: merchant.business_type ?? '',
        is_active: merchant.is_active ? 'true' : 'false',
        bank_id: merchant.bank_id ? String(merchant.bank_id) : '',
      },
    })
  }
  else {
    resetForm({
      values: {
        name: '',
        commercial_register: '',
        tax_number: '',
        phone: '',
        address: '',
        business_type: '',
        is_active: 'true',
        bank_id: props.defaultBankId ? String(props.defaultBankId) : '',
      },
    })
  }
}, { immediate: true })

watch(() => props.defaultBankId, (newValue) => {
  if (!props.merchant) {
    resetForm({
      values: {
        name: name.value ?? '',
        commercial_register: commercial_register.value ?? '',
        tax_number: tax_number.value ?? '',
        phone: phone.value ?? '',
        address: address.value ?? '',
        business_type: business_type.value ?? '',
        is_active: is_active.value ?? 'true',
        bank_id: newValue ? String(newValue) : '',
      },
    })
  }
})

function requestClose() {
  if (!props.saving) {
    emit('close')
  }
}

function onDialogOpenChange(open: boolean) {
  if (!open) {
    requestClose()
  }
}

const onSubmit = handleSubmit((values) => {
  if (isBankRequiredForCreate.value && !values.bank_id) {
    setFieldError('bank_id', 'اختيار البنك مطلوب')
    return
  }

  emit('save', {
    name: values.name.trim(),
    commercial_register: values.commercial_register.trim(),
    tax_number: values.tax_number.trim(),
    phone: values.phone?.trim() || null,
    address: values.address?.trim() || null,
    business_type: values.business_type?.trim() || null,
    is_active: isEditMode.value ? values.is_active === 'true' : undefined,
    bank_id: values.bank_id ? Number(values.bank_id) : null,
  })
})
</script>

<template>
  <Dialog :open="true" @update:open="onDialogOpenChange">
    <div class="modal-layer">
      <DialogOverlay class="modal-backdrop" @click="requestClose" />
      <DialogContent
        class="modal"
        dir="rtl"
        :aria-label="isEditMode ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد'"
      >
        <DialogHeader class="modal-header">
          <div>
            <DialogTitle class="modal-title">
              {{ isEditMode ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد' }}
            </DialogTitle>
            <p class="modal-description">الحقول المعلّمة بـ * إلزامية.</p>
          </div>
          <button class="close-btn" aria-label="إغلاق" :disabled="props.saving" @click="requestClose">✕</button>
        </DialogHeader>

        <div v-if="props.serverError" class="server-error-banner" role="alert">
          {{ props.serverError }}
        </div>

        <form class="modal-form" @submit.prevent="onSubmit">
          <div class="form-grid">
            <!-- Bank selector for CBY Admin creating a new merchant -->
            <div v-if="isBankRequiredForCreate" class="form-field form-field-full">
              <label class="form-label" for="bank-id">البنك التابع له <span class="required">*</span></label>
              <select
                id="bank-id"
                v-model="bank_id"
                v-bind="bankIdAttrs"
                class="form-input"
                :class="{ 'input-error': errors.bank_id }"
              >
                <option value="">اختر البنك</option>
                <option v-for="bank in props.bankOptions" :key="bank.id" :value="String(bank.id)">
                  {{ bank.name }}
                </option>
              </select>
              <span v-if="errors.bank_id" class="field-error" role="alert">{{ errors.bank_id }}</span>
            </div>

            <div v-else-if="showLockedBankField" class="form-field form-field-full">
              <label class="form-label" for="locked-bank-name">البنك التابع له <span class="required">*</span></label>
              <input
                id="locked-bank-name"
                :value="props.lockedBankName ?? ''"
                type="text"
                class="form-input form-input-locked"
                readonly
                disabled
              >
              <span class="field-hint">مرتبط بالبنك المسجل على حسابك.</span>
            </div>

            <!-- Name -->
            <div class="form-field form-field-full">
              <label class="form-label" for="merchant-name">اسم التاجر / الشركة <span class="required">*</span></label>
              <input
                id="merchant-name"
                v-model="name"
                v-bind="nameAttrs"
                type="text"
                class="form-input"
                :class="{ 'input-error': errors.name }"
                placeholder="مثال: شركة الكميم للأدوية"
              >
              <span v-if="errors.name" class="field-error" role="alert">{{ errors.name }}</span>
            </div>

            <!-- Commercial register -->
            <div class="form-field">
              <label class="form-label" for="commercial-register">رقم السجل التجاري <span class="required">*</span></label>
              <input
                id="commercial-register"
                v-model="commercial_register"
                v-bind="commercialRegisterAttrs"
                type="text"
                class="form-input"
                :class="{ 'input-error': errors.commercial_register }"
                placeholder="CR-12345"
                dir="ltr"
              >
              <span v-if="errors.commercial_register" class="field-error" role="alert">{{ errors.commercial_register }}</span>
            </div>

            <!-- Tax number -->
            <div class="form-field">
              <label class="form-label" for="tax-number">الرقم الضريبي <span class="required">*</span></label>
              <input
                id="tax-number"
                v-model="tax_number"
                v-bind="taxNumberAttrs"
                type="text"
                class="form-input"
                :class="{ 'input-error': errors.tax_number }"
                placeholder="4123456"
                dir="ltr"
              >
              <span v-if="errors.tax_number" class="field-error" role="alert">{{ errors.tax_number }}</span>
            </div>

            <!-- Phone -->
            <div class="form-field">
              <label class="form-label" for="merchant-phone">هاتف التواصل</label>
              <input
                id="merchant-phone"
                v-model="phone"
                v-bind="phoneAttrs"
                type="text"
                class="form-input"
                placeholder="+9677…"
                dir="ltr"
              >
            </div>

            <!-- Business type -->
            <div class="form-field">
              <label class="form-label" for="business-type">القطاع / النشاط</label>
              <select
                id="business-type"
                v-model="business_type"
                v-bind="businessTypeAttrs"
                class="form-input"
              >
                <option value="">اختر القطاع</option>
                <option v-for="opt in BUSINESS_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Status — edit mode only -->
            <div v-if="isEditMode" class="form-field">
              <label class="form-label" for="merchant-status">الحالة</label>
              <select
                id="merchant-status"
                v-model="is_active"
                v-bind="isActiveAttrs"
                class="form-input"
              >
                <option value="true">نشط</option>
                <option value="false">موقوف</option>
              </select>
            </div>

            <!-- Address -->
            <div class="form-field form-field-full">
              <label class="form-label" for="address">العنوان</label>
              <input
                id="address"
                v-model="address"
                v-bind="addressAttrs"
                type="text"
                class="form-input"
                placeholder="المدينة – الشارع"
              >
            </div>
          </div>

          <DialogFooter class="modal-actions">
            <button type="button" class="btn-secondary" :disabled="props.saving" @click="requestClose">
              إلغاء
            </button>
            <button type="submit" class="btn-primary" :disabled="isSaveDisabled">
              <template v-if="props.saving">جارٍ الحفظ…</template>
              <template v-else-if="isEditMode">حفظ التعديلات</template>
              <template v-else>حفظ التاجر</template>
            </button>
          </DialogFooter>
        </form>
      </DialogContent>
    </div>
  </Dialog>
</template>

<style scoped>
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
  width: 560px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0 0 4px;
}

.modal-description {
  font-size: 13px;
  color: #6c757d;
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
  flex-shrink: 0;
}

.close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.server-error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #c62828;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
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

.input-error {
  border-color: #c62828;
}

.field-error {
  font-size: 12px;
  color: #c62828;
}

.field-hint {
  font-size: 12px;
  color: #6c757d;
}

.form-input-locked {
  background: #f8f9fa;
  color: #6c757d;
  cursor: not-allowed;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

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
</style>
