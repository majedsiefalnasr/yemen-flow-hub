<script setup lang="ts">
import { watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Merchant } from '../../types/models'

const BUSINESS_TYPE_OPTIONS = [
  { value: 'import', label: 'استيراد' },
  { value: 'export', label: 'تصدير' },
  { value: 'retail', label: 'تجارة تجزئة' },
  { value: 'wholesale', label: 'تجارة جملة' },
  { value: 'manufacturing', label: 'تصنيع' },
  { value: 'services', label: 'خدمات' },
]

const schema = toTypedSchema(z.object({
  name: z.string().min(1, 'اسم التاجر مطلوب'),
  commercial_register: z.string().min(1, 'رقم السجل التجاري مطلوب'),
  tax_number: z.string().min(1, 'الرقم الضريبي مطلوب'),
  address: z.string().optional().default(''),
  business_type: z.string().optional().default(''),
}))

const props = defineProps<{
  merchant: Merchant | null
  saving: boolean
  serverError: string | null
}>()

const emit = defineEmits<{
  save: [data: {
    name: string
    commercial_register: string
    tax_number: string
    address: string | null
    business_type: string | null
  }]
  close: []
}>()

const { handleSubmit, errors, defineField, resetForm, meta } = useForm({ validationSchema: schema })

const [name, nameAttrs] = defineField('name')
const [commercial_register, commercialRegisterAttrs] = defineField('commercial_register')
const [tax_number, taxNumberAttrs] = defineField('tax_number')
const [address, addressAttrs] = defineField('address')
const [business_type, businessTypeAttrs] = defineField('business_type')

watch(() => props.merchant, (merchant) => {
  if (merchant) {
    resetForm({
      values: {
        name: merchant.name,
        commercial_register: merchant.commercial_register ?? '',
        tax_number: merchant.tax_number ?? '',
        address: merchant.address ?? '',
        business_type: '',
      },
    })
  }
  else {
    resetForm({ values: { name: '', commercial_register: '', tax_number: '', address: '', business_type: '' } })
  }
}, { immediate: true })

const onSubmit = handleSubmit((values) => {
  emit('save', {
    name: values.name,
    commercial_register: values.commercial_register,
    tax_number: values.tax_number,
    address: values.address?.trim() || null,
    business_type: values.business_type?.trim() || null,
  })
})
</script>

<template>
  <div class="modal-backdrop" role="dialog" aria-modal="true" :aria-label="props.merchant ? 'تعديل بيانات التاجر' : 'إضافة تاجر جديد'" @click.self="emit('close')">
    <div class="modal" dir="rtl">
      <div class="modal-header">
        <h2 class="modal-title">{{ props.merchant ? 'تعديل بيانات التاجر' : 'إضافة تاجر جديد' }}</h2>
        <button class="close-btn" aria-label="إغلاق" @click="emit('close')">✕</button>
      </div>

      <div v-if="props.serverError" class="server-error-banner" role="alert">
        {{ props.serverError }}
      </div>

      <form class="modal-form" @submit.prevent="onSubmit">
        <div class="form-grid">
          <div class="form-field form-field-full">
            <label class="form-label" for="merchant-name">اسم التاجر <span class="required">*</span></label>
            <input
              id="merchant-name"
              v-model="name"
              v-bind="nameAttrs"
              type="text"
              class="form-input"
              :class="{ 'input-error': errors.name }"
              placeholder="الاسم التجاري أو اسم المؤسسة"
            >
            <span v-if="errors.name" class="field-error" role="alert">{{ errors.name }}</span>
          </div>

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

          <div class="form-field">
            <label class="form-label" for="tax-number">الرقم الضريبي <span class="required">*</span></label>
            <input
              id="tax-number"
              v-model="tax_number"
              v-bind="taxNumberAttrs"
              type="text"
              class="form-input"
              :class="{ 'input-error': errors.tax_number }"
              placeholder="TX-99999"
              dir="ltr"
            >
            <span v-if="errors.tax_number" class="field-error" role="alert">{{ errors.tax_number }}</span>
          </div>

          <div class="form-field">
            <label class="form-label" for="business-type">نوع النشاط</label>
            <select
              id="business-type"
              v-model="business_type"
              v-bind="businessTypeAttrs"
              class="form-input"
            >
              <option value="">اختر نوع النشاط</option>
              <option v-for="opt in BUSINESS_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="form-field form-field-full">
            <label class="form-label" for="address">العنوان</label>
            <input
              id="address"
              v-model="address"
              v-bind="addressAttrs"
              type="text"
              class="form-input"
              placeholder="المدينة، الشارع…"
            >
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-secondary" :disabled="props.saving" @click="emit('close')">
            إلغاء
          </button>
          <button type="submit" class="btn-primary" :disabled="props.saving || !meta.valid">
            {{ props.saving ? 'جارٍ الحفظ…' : 'حفظ' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
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
