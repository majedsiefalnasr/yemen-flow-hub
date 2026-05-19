<script setup lang="ts">
import { computed } from 'vue'
import type { WizardStep2Data } from '../../composables/useRequestWizard'
import { ARRIVAL_PORTS } from '../../schemas/wizard.schema'

const COUNTRIES = [
  'الولايات المتحدة', 'المملكة المتحدة', 'الصين', 'الهند', 'الإمارات العربية المتحدة',
  'المملكة العربية السعودية', 'تركيا', 'ألمانيا', 'فرنسا', 'إيطاليا',
  'اليابان', 'كوريا الجنوبية', 'البرازيل', 'كندا', 'أستراليا',
  'باكستان', 'مصر', 'الأردن', 'لبنان', 'الكويت',
  'البحرين', 'عُمان', 'قطر', 'إيران', 'روسيا',
]

const props = defineProps<{
  modelValue: WizardStep2Data
  errors: Partial<Record<keyof WizardStep2Data, string>>
  autoFillChip: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WizardStep2Data]
  'arrival-port-change': [port: string]
}>()

function update<K extends keyof WizardStep2Data>(key: K, val: WizardStep2Data[K]): void {
  emit('update:modelValue', { ...props.modelValue, [key]: val })
}

function onPortChange(port: string): void {
  emit('arrival-port-change', port)
}

const countrySearch = computed(() => '')
const errorCount = computed(() => Object.keys(props.errors).length)
</script>

<template>
  <div class="step-content" dir="rtl">
    <!-- Error banner -->
    <div v-if="errorCount > 0" class="error-banner" role="alert">
      <span class="error-banner__icon">⚠</span>
      <span>يوجد {{ errorCount }} {{ errorCount === 1 ? 'حقل يحتاج' : 'حقول تحتاج' }} إلى تصحيح قبل المتابعة.</span>
    </div>

    <h2 class="section-title">بيانات المورد والشحنة</h2>

    <div class="fields-grid">
      <!-- اسم المورد -->
      <div class="field-group" :class="{ 'field-group--error': errors.supplier_name }">
        <label class="field-label" for="supplier-name">اسم المورد <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.supplier_name" class="field-error-icon" aria-hidden="true">⚠</span>
          <input
            id="supplier-name"
            type="text"
            class="form-input"
            :class="{ 'form-input--error': errors.supplier_name }"
            :value="modelValue.supplier_name"
            :disabled="loading"
            placeholder="مثال: Cargill Trading Inc."
            @input="update('supplier_name', ($event.target as HTMLInputElement).value)"
          />
        </div>
        <span v-if="errors.supplier_name" class="field-error" role="alert">{{ errors.supplier_name }}</span>
      </div>

      <!-- رقم الفاتورة -->
      <div class="field-group" :class="{ 'field-group--error': errors.invoice_number }">
        <label class="field-label" for="invoice-number">رقم الفاتورة <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.invoice_number" class="field-error-icon" aria-hidden="true">⚠</span>
          <input
            id="invoice-number"
            type="text"
            class="form-input"
            :class="{ 'form-input--error': errors.invoice_number }"
            :value="modelValue.invoice_number"
            :disabled="loading"
            placeholder="INV-2025-XXXX"
            @input="update('invoice_number', ($event.target as HTMLInputElement).value)"
          />
        </div>
        <span v-if="errors.invoice_number" class="field-error" role="alert">{{ errors.invoice_number }}</span>
      </div>

      <!-- بلد المنشأ -->
      <div class="field-group" :class="{ 'field-group--error': errors.origin_country }">
        <label class="field-label" for="origin-country">بلد المنشأ <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.origin_country" class="field-error-icon" aria-hidden="true">⚠</span>
          <select
            id="origin-country"
            class="form-input"
            :class="{ 'form-input--error': errors.origin_country }"
            :value="modelValue.origin_country"
            :disabled="loading"
            @change="update('origin_country', ($event.target as HTMLSelectElement).value)"
          >
            <option value="" disabled>اختر بلد المنشأ...</option>
            <option v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <span v-if="errors.origin_country" class="field-error" role="alert">{{ errors.origin_country }}</span>
      </div>

      <!-- تاريخ الفاتورة -->
      <div class="field-group" :class="{ 'field-group--error': errors.invoice_date }">
        <label class="field-label" for="invoice-date">تاريخ الفاتورة <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.invoice_date" class="field-error-icon" aria-hidden="true">⚠</span>
          <input
            id="invoice-date"
            type="date"
            class="form-input"
            :class="{ 'form-input--error': errors.invoice_date }"
            :value="modelValue.invoice_date"
            :disabled="loading"
            @input="update('invoice_date', ($event.target as HTMLInputElement).value)"
          />
        </div>
        <span v-if="errors.invoice_date" class="field-error" role="alert">{{ errors.invoice_date }}</span>
      </div>

      <!-- ميناء الوصول -->
      <div class="field-group" :class="{ 'field-group--error': errors.arrival_port }">
        <label class="field-label" for="arrival-port">ميناء الوصول <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.arrival_port" class="field-error-icon" aria-hidden="true">⚠</span>
          <select
            id="arrival-port"
            class="form-input"
            :class="{ 'form-input--error': errors.arrival_port }"
            :value="modelValue.arrival_port"
            :disabled="loading"
            @change="onPortChange(($event.target as HTMLSelectElement).value)"
          >
            <option value="" disabled>اختر ميناء الوصول...</option>
            <option v-for="p in ARRIVAL_PORTS" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <span v-if="errors.arrival_port" class="field-error" role="alert">{{ errors.arrival_port }}</span>
      </div>

      <!-- ميناء الشحن (optional) -->
      <div class="field-group">
        <label class="field-label" for="shipping-port">ميناء الشحن</label>
        <input
          id="shipping-port"
          type="text"
          class="form-input"
          :value="modelValue.shipping_port ?? ''"
          :disabled="loading"
          placeholder="Port of Houston, USA"
          @input="update('shipping_port', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- الجمارك المختصة -->
      <div class="field-group field-group--full">
        <label class="field-label" for="customs-office">
          الجمارك المختصة
          <span v-if="autoFillChip" class="autofill-chip" aria-live="polite">تم التعبئة التلقائية</span>
        </label>
        <select
          id="customs-office"
          class="form-input"
          :value="modelValue.customs_office ?? ''"
          :disabled="loading"
          @change="update('customs_office', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">اختر الجمارك المختصة...</option>
          <option value="جمارك عدن">جمارك عدن</option>
          <option value="جمارك الحديدة">جمارك الحديدة</option>
          <option value="جمارك المكلا">جمارك المكلا</option>
        </select>
      </div>

      <!-- رقم بوليصة الشحن (optional) -->
      <div class="field-group">
        <label class="field-label" for="bl-number">رقم بوليصة الشحن</label>
        <input
          id="bl-number"
          type="text"
          class="form-input"
          :value="modelValue.bl_number ?? ''"
          :disabled="loading"
          placeholder="BL-XXXX-XXXX"
          @input="update('bl_number', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.step-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff8e1;
  border: 1px solid #ffe082;
  border-radius: 12px;
  padding: 12px 16px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #f57f17;
}

.error-banner__icon { font-size: 16px; }

.section-title {
  font-family: 'Tajawal', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: #1c222b;
  margin: 0;
}

.fields-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-group--full { grid-column: span 2; }

.field-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
}

.req { color: #c62828; }

.field-input-wrap { position: relative; }

.field-error-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #c62828;
  font-size: 14px;
  pointer-events: none;
  z-index: 1;
}

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  background: #ffffff;
  color: #1c222b;
  font-size: 14px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  outline: none;
  text-align: right;
  width: 100%;
  box-sizing: border-box;
  transition: border-color 150ms;
}

.form-input:focus { border-color: #0066cc; border-width: 2px; }
.form-input--error { border: 2px solid #c62828; }
.form-input:disabled { background: #f5f5f5; color: #8e8e93; cursor: not-allowed; }

.field-error {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  color: #c62828;
}

.autofill-chip {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 12px;
  font-weight: 400;
  background: #e3f2fd;
  color: #0066cc;
  border: 1px solid #bbdefb;
  border-radius: 9999px;
  padding: 2px 8px;
  animation: fadeOut 2s forwards;
}

@keyframes fadeOut {
  0% { opacity: 1; }
  70% { opacity: 1; }
  100% { opacity: 0; }
}

@media (max-width: 600px) {
  .fields-grid { grid-template-columns: 1fr; }
  .field-group--full { grid-column: span 1; }
}
</style>
