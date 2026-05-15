<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import type { RequestFormData, Merchant } from '../../types/models'
import { Currency } from '../../types/enums'
import { useMerchants } from '../../composables/useMerchants'
import { requestFormSchema } from '../../schemas/requestForm.schema'

const props = defineProps<{
  initialValues?: Partial<RequestFormData>
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [data: RequestFormData]
}>()

const { handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(requestFormSchema),
  initialValues: {
    merchant_id: props.initialValues?.merchant_id ?? undefined,
    currency: (props.initialValues?.currency as Currency | undefined) ?? Currency.USD,
    amount: props.initialValues?.amount ?? undefined,
    supplier_name: props.initialValues?.supplier_name ?? '',
    goods_description: props.initialValues?.goods_description ?? '',
    port_of_entry: props.initialValues?.port_of_entry ?? '',
    notes: props.initialValues?.notes ?? '',
  },
})

const { value: merchantId } = useField<number | undefined>('merchant_id')
const { value: currency } = useField<Currency>('currency')
const { value: amount } = useField<number | undefined>('amount')
const { value: supplierName } = useField<string>('supplier_name')
const { value: goodsDescription } = useField<string>('goods_description')
const { value: portOfEntry } = useField<string>('port_of_entry')
const { value: notes } = useField<string>('notes')

const merchants = ref<Merchant[]>([])
const merchantsLoading = ref(false)

const { fetchMerchants } = useMerchants()

onMounted(async () => {
  merchantsLoading.value = true
  try {
    merchants.value = await fetchMerchants()
  }
  catch {
    // Merchants load failure is non-fatal; user can retry
  }
  finally {
    merchantsLoading.value = false
  }
})

const onSubmit = handleSubmit((values) => {
  emit('submit', {
    merchant_id: values.merchant_id as number,
    currency: values.currency,
    amount: values.amount as number,
    supplier_name: values.supplier_name,
    goods_description: values.goods_description,
    port_of_entry: values.port_of_entry,
    notes: values.notes ?? '',
  })
})
</script>

<template>
  <form class="request-form" dir="rtl" novalidate @submit.prevent="onSubmit">

    <!-- Importer / Merchant Section -->
    <section class="form-section">
      <h2 class="section-title">بيانات المستورد</h2>

      <div class="field-group">
        <label class="field-label" for="merchant-select">
          المستورد <span class="required-mark">*</span>
        </label>
        <select
          id="merchant-select"
          v-model="merchantId"
          class="form-input"
          :disabled="merchantsLoading || loading"
          :class="{ 'form-input--error': errors.merchant_id }"
        >
          <option :value="undefined" disabled>
            {{ merchantsLoading ? 'جاري التحميل...' : 'اختر المستورد...' }}
          </option>
          <option
            v-for="m in merchants"
            :key="m.id"
            :value="m.id"
          >
            {{ m.name }}
          </option>
        </select>
        <span v-if="errors.merchant_id" class="field-error" role="alert">{{ errors.merchant_id }}</span>
      </div>
    </section>

    <!-- Supplier / Exporter Section -->
    <section class="form-section">
      <h2 class="section-title">بيانات المورد / المُصدِّر</h2>

      <div class="field-group">
        <label class="field-label" for="supplier-name">
          اسم المورد <span class="required-mark">*</span>
        </label>
        <input
          id="supplier-name"
          v-model="supplierName"
          type="text"
          class="form-input"
          :disabled="loading"
          :class="{ 'form-input--error': errors.supplier_name }"
          placeholder="أدخل اسم المورد"
        />
        <span v-if="errors.supplier_name" class="field-error" role="alert">{{ errors.supplier_name }}</span>
      </div>
    </section>

    <!-- Goods Section -->
    <section class="form-section">
      <h2 class="section-title">بيانات البضائع</h2>

      <div class="field-group">
        <label class="field-label" for="goods-description">
          وصف البضائع <span class="required-mark">*</span>
        </label>
        <textarea
          id="goods-description"
          v-model="goodsDescription"
          class="form-input form-textarea"
          :disabled="loading"
          :class="{ 'form-input--error': errors.goods_description }"
          placeholder="أدخل وصفاً تفصيلياً للبضائع"
          rows="3"
        />
        <span v-if="errors.goods_description" class="field-error" role="alert">{{ errors.goods_description }}</span>
      </div>

      <div class="field-group">
        <label class="field-label" for="port-of-entry">
          ميناء الدخول <span class="required-mark">*</span>
        </label>
        <input
          id="port-of-entry"
          v-model="portOfEntry"
          type="text"
          class="form-input"
          :disabled="loading"
          :class="{ 'form-input--error': errors.port_of_entry }"
          placeholder="مثال: ميناء عدن"
        />
        <span v-if="errors.port_of_entry" class="field-error" role="alert">{{ errors.port_of_entry }}</span>
      </div>
    </section>

    <!-- Financial Section -->
    <section class="form-section">
      <h2 class="section-title">البيانات المالية</h2>

      <div class="field-row">
        <div class="field-group field-group--flex">
          <label class="field-label" for="amount">
            المبلغ <span class="required-mark">*</span>
          </label>
          <input
            id="amount"
            v-model.number="amount"
            type="number"
            min="1"
            step="0.01"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.amount }"
            placeholder="0.00"
          />
          <span v-if="errors.amount" class="field-error" role="alert">{{ errors.amount }}</span>
        </div>

        <div class="field-group field-group--flex">
          <label class="field-label" for="currency">
            العملة <span class="required-mark">*</span>
          </label>
          <select
            id="currency"
            v-model="currency"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.currency }"
          >
            <option value="USD">USD — دولار أمريكي</option>
            <option value="EUR">EUR — يورو</option>
            <option value="SAR">SAR — ريال سعودي</option>
            <option value="AED">AED — درهم إماراتي</option>
            <option value="CNY">CNY — يوان صيني</option>
          </select>
          <span v-if="errors.currency" class="field-error" role="alert">{{ errors.currency }}</span>
        </div>
      </div>
    </section>

    <!-- Notes Section -->
    <section class="form-section">
      <h2 class="section-title">ملاحظات</h2>

      <div class="field-group">
        <label class="field-label" for="notes">ملاحظات إضافية</label>
        <textarea
          id="notes"
          v-model="notes"
          class="form-input form-textarea"
          :disabled="loading"
          placeholder="أي ملاحظات إضافية (اختياري)"
          rows="3"
        />
      </div>
    </section>

    <!-- Actions -->
    <div class="form-actions">
      <slot name="actions">
        <button type="submit" class="btn-primary" :disabled="loading">
          {{ loading ? 'جاري الحفظ...' : 'حفظ الطلب' }}
        </button>
      </slot>
    </div>
  </form>
</template>

<style scoped>
.request-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-section {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-primary, #1d1d1f);
  margin: 0 0 4px;
}

.field-row {
  display: flex;
  gap: 16px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-group--flex {
  flex: 1;
}

.field-label {
  font-size: 13px;
  font-weight: 400;
  color: var(--color-text-secondary, #6e6e73);
}

.required-mark {
  color: #ff3b30;
}

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  background: var(--color-surface, #fff);
  color: var(--color-text-primary, #1d1d1f);
  font-size: 15px;
  font-family: inherit;
  outline: none;
  transition: border-color 100ms;
  text-align: right;
}

.form-input:focus {
  border-color: #0071e3;
  border-width: 1.5px;
}

.form-input:disabled {
  background: #f5f5f7;
  color: #8e8e93;
  border-color: #8e8e93;
  cursor: not-allowed;
}

.form-input--error {
  border-color: #ff3b30;
}

.form-textarea {
  height: auto;
  padding: 10px 12px;
  resize: vertical;
  min-height: 88px;
}

.field-error {
  font-size: 13px;
  color: #ff3b30;
}

.form-actions {
  display: flex;
  justify-content: flex-start;
  gap: 12px;
}

.btn-primary {
  height: 44px;
  padding: 0 24px;
  background: #0071e3;
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 100ms;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary:not(:disabled):hover {
  opacity: 0.9;
}

@media (max-width: 600px) {
  .field-row {
    flex-direction: column;
  }
}
</style>
