<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { WizardStep1Data } from '../../composables/useRequestWizard'
import { GOODS_TYPES, PAYMENT_TERMS } from '../../schemas/wizard.schema'
import { Currency } from '../../types/enums'
import { useMerchants } from '../../composables/useMerchants'
import type { Merchant } from '../../types/models'

const props = defineProps<{
  modelValue: WizardStep1Data
  errors: Partial<Record<keyof WizardStep1Data, string>>
  isDataEntry: boolean
  dataEntryMerchantName?: string
  dataEntryMerchantError?: string | null
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WizardStep1Data]
}>()

function update<K extends keyof WizardStep1Data>(key: K, val: WizardStep1Data[K]): void {
  emit('update:modelValue', { ...props.modelValue, [key]: val })
}

const CURRENCY_LABELS: Record<string, string> = {
  USD: 'USD دولار أمريكي',
  EUR: 'EUR يورو',
  SAR: 'SAR ريال سعودي',
  AED: 'AED درهم إماراتي',
  CNY: 'CNY يوان صيني',
}

const PAYMENT_LABELS: Record<string, string> = {
  LC: 'L/C اعتماد مستندي',
  TT: 'T/T تحويل بنكي مباشر',
  CAD: 'CAD نقداً عند التسليم',
}

const merchants = ref<Merchant[]>([])
const merchantsLoading = ref(false)
const merchantsError = ref(false)
const merchantSearch = ref('')

const { fetchMerchants } = useMerchants()

async function loadMerchants(): Promise<void> {
  merchantsLoading.value = true
  merchantsError.value = false
  try {
    merchants.value = await fetchMerchants()
  }
  catch {
    merchantsError.value = true
  }
  finally {
    merchantsLoading.value = false
  }
}

onMounted(() => {
  if (!props.isDataEntry) {
    loadMerchants()
  }
})

const filteredMerchants = computed(() => {
  const q = merchantSearch.value.trim().toLowerCase()
  if (!q) return merchants.value
  return merchants.value.filter(m => m.name.toLowerCase().includes(q))
})

const selectedMerchantName = computed(() => {
  if (props.isDataEntry && props.dataEntryMerchantName) {
    return props.dataEntryMerchantName
  }

  if (!props.modelValue.merchant_id) return ''
  return merchants.value.find(m => m.id === props.modelValue.merchant_id)?.name ?? ''
})

const notesLength = computed(() => props.modelValue.notes?.length ?? 0)

const errorCount = computed(() => Object.keys(props.errors).length)
</script>

<template>
  <div class="step-content" dir="rtl">
    <!-- Error banner -->
    <div v-if="errorCount > 0" class="error-banner" role="alert">
      <span class="error-banner__icon">⚠</span>
      <span>يوجد {{ errorCount }} {{ errorCount === 1 ? 'حقل يحتاج' : 'حقول تحتاج' }} إلى تصحيح قبل المتابعة.</span>
    </div>

    <h2 class="section-title">معلومات الطلب الأساسية</h2>

    <div class="fields-grid">
      <!-- نوع الواردات -->
      <div class="field-group" :class="{ 'field-group--error': errors.goods_type }">
        <label class="field-label" for="goods-type">نوع الواردات <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.goods_type" class="field-error-icon" aria-hidden="true">⚠</span>
          <select
            id="goods-type"
            class="form-input"
            :class="{ 'form-input--error': errors.goods_type }"
            :value="modelValue.goods_type"
            :disabled="loading"
            @change="update('goods_type', ($event.target as HTMLSelectElement).value)"
          >
            <option value="" disabled>اختر نوع الواردات...</option>
            <option v-for="t in GOODS_TYPES" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <span v-if="errors.goods_type" class="field-error" role="alert">{{ errors.goods_type }}</span>
      </div>

      <!-- المستورد -->
      <div class="field-group" :class="{ 'field-group--error': errors.merchant_id }">
        <label class="field-label" for="merchant">المستورد (التاجر) <span class="req">*</span></label>

        <!-- DATA_ENTRY: read-only -->
        <div
          v-if="isDataEntry"
          class="merchant-readonly"
          :class="{ 'merchant-readonly--error': !!dataEntryMerchantError }"
        >
          <span class="lock-icon" aria-hidden="true">🔒</span>
          <span>{{ selectedMerchantName || 'لم يتم تحديد التاجر بعد' }}</span>
        </div>
        <span v-if="isDataEntry && dataEntryMerchantError" class="field-error" role="alert">{{ dataEntryMerchantError }}</span>

        <!-- BANK_ADMIN: searchable select -->
        <template v-else>
          <div v-if="merchantsError" class="merchant-error" role="alert">
            <span>تعذّر تحميل قائمة التجار.</span>
            <button type="button" class="retry-btn" @click="loadMerchants">إعادة المحاولة</button>
          </div>
          <template v-else>
            <input
              v-model="merchantSearch"
              type="text"
              class="form-input search-input"
              placeholder="ابحث عن تاجر..."
              :disabled="merchantsLoading || loading"
            />
            <div class="field-input-wrap">
              <span v-if="errors.merchant_id" class="field-error-icon" aria-hidden="true">⚠</span>
              <select
                id="merchant"
                class="form-input"
                :class="{ 'form-input--error': errors.merchant_id }"
                :value="modelValue.merchant_id ?? ''"
                :disabled="merchantsLoading || loading"
                @change="update('merchant_id', Number(($event.target as HTMLSelectElement).value) || null)"
              >
                <option value="" disabled>{{ merchantsLoading ? 'جاري التحميل...' : 'اختر المستورد...' }}</option>
                <option v-for="m in filteredMerchants" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
          </template>
        </template>
        <span v-if="errors.merchant_id" class="field-error" role="alert">{{ errors.merchant_id }}</span>
      </div>

      <!-- مبلغ التمويل -->
      <div class="field-group" :class="{ 'field-group--error': errors.amount }">
        <label class="field-label" for="amount">مبلغ التمويل <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.amount" class="field-error-icon" aria-hidden="true">⚠</span>
          <input
            id="amount"
            type="number"
            min="1000"
            step="1"
            class="form-input"
            :class="{ 'form-input--error': errors.amount }"
            :value="modelValue.amount ?? ''"
            :disabled="loading"
            placeholder="0"
            @input="update('amount', Number(($event.target as HTMLInputElement).value) || null)"
          />
        </div>
        <span v-if="errors.amount" class="field-error" role="alert">{{ errors.amount }}</span>
      </div>

      <!-- العملة -->
      <div class="field-group" :class="{ 'field-group--error': errors.currency }">
        <label class="field-label" for="currency">العملة <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.currency" class="field-error-icon" aria-hidden="true">⚠</span>
          <select
            id="currency"
            class="form-input"
            :class="{ 'form-input--error': errors.currency }"
            :value="modelValue.currency"
            :disabled="loading"
            @change="update('currency', ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="c in Object.values(Currency)" :key="c" :value="c">{{ CURRENCY_LABELS[c] ?? c }}</option>
          </select>
        </div>
        <span v-if="errors.currency" class="field-error" role="alert">{{ errors.currency }}</span>
      </div>

      <!-- شروط الدفع -->
      <div class="field-group" :class="{ 'field-group--error': errors.payment_terms }">
        <label class="field-label" for="payment-terms">شروط الدفع <span class="req">*</span></label>
        <div class="field-input-wrap">
          <span v-if="errors.payment_terms" class="field-error-icon" aria-hidden="true">⚠</span>
          <select
            id="payment-terms"
            class="form-input"
            :class="{ 'form-input--error': errors.payment_terms }"
            :value="modelValue.payment_terms"
            :disabled="loading"
            @change="update('payment_terms', ($event.target as HTMLSelectElement).value)"
          >
            <option value="" disabled>اختر شروط الدفع...</option>
            <option v-for="t in PAYMENT_TERMS" :key="t" :value="t">{{ PAYMENT_LABELS[t] }}</option>
          </select>
        </div>
        <span v-if="errors.payment_terms" class="field-error" role="alert">{{ errors.payment_terms }}</span>
      </div>

      <!-- تاريخ الاستحقاق (optional) -->
      <div class="field-group">
        <label class="field-label" for="due-date">تاريخ الاستحقاق المتوقع</label>
        <input
          id="due-date"
          type="date"
          class="form-input"
          :value="modelValue.due_date ?? ''"
          :disabled="loading"
          @input="update('due_date', ($event.target as HTMLInputElement).value || '')"
        />
      </div>

      <!-- ملاحظات -->
      <div class="field-group field-group--full">
        <label class="field-label" for="notes">ملاحظات إضافية</label>
        <textarea
          id="notes"
          class="form-input form-textarea"
          :value="modelValue.notes ?? ''"
          :disabled="loading"
          rows="3"
          maxlength="500"
          placeholder="أي معلومات إضافية تتعلق بالطلب..."
          @input="update('notes', ($event.target as HTMLTextAreaElement).value)"
        />
        <span class="char-counter">{{ notesLength }}/500</span>
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

.error-banner__icon {
  font-size: 16px;
}

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

.field-group--full {
  grid-column: span 2;
}

.field-label {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
}

.req {
  color: #c62828;
}

.field-input-wrap {
  position: relative;
}

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

.form-input:focus {
  border-color: #0066cc;
  border-width: 2px;
}

.form-input--error {
  border: 2px solid #c62828;
}

.form-input:disabled {
  background: #f5f5f5;
  color: #8e8e93;
  cursor: not-allowed;
}

.form-textarea {
  height: auto;
  padding: 10px 12px;
  resize: vertical;
  min-height: 80px;
}

.char-counter {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 12px;
  color: #6c757d;
  text-align: left;
}

.field-error {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  color: #c62828;
}

/* Merchant readonly */
.merchant-readonly {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 44px;
  padding: 0 12px;
  background: #f5f5f5;
  border: 1px solid #cccccc;
  border-radius: 12px;
  color: #1c222b;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  cursor: not-allowed;
}

.merchant-readonly--error {
  border-color: #c62828;
  background: #ffebee;
}

.lock-icon {
  font-size: 14px;
}

.search-input {
  margin-bottom: 6px;
}

/* Merchant error */
.merchant-error {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #ffebee;
  border: 1px solid #ffcdd2;
  border-radius: 12px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #c62828;
}

.retry-btn {
  padding: 4px 12px;
  border: 1px solid #0066cc;
  border-radius: 8px;
  background: transparent;
  color: #0066cc;
  font-size: 13px;
  cursor: pointer;
  font-family: inherit;
}

@media (max-width: 600px) {
  .fields-grid {
    grid-template-columns: 1fr;
  }
  .field-group--full {
    grid-column: span 1;
  }
}
</style>
