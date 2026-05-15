<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useForm } from 'vee-validate'
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

const { handleSubmit, errors, setValues, values } = useForm({
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

// Reactively hydrate form when initialValues arrive asynchronously (edit mode)
watch(
  () => props.initialValues,
  (next) => {
    if (!next) return
    setValues({
      merchant_id: next.merchant_id ?? undefined,
      currency: (next.currency as Currency | undefined) ?? Currency.USD,
      amount: next.amount ?? undefined,
      supplier_name: next.supplier_name ?? '',
      goods_description: next.goods_description ?? '',
      port_of_entry: next.port_of_entry ?? '',
      notes: next.notes ?? '',
    })
  },
  { deep: true },
)

const merchants = ref<Merchant[]>([])
const merchantsLoading = ref(false)
const merchantsError = ref(false)

const { fetchMerchants } = useMerchants()

async function loadMerchants() {
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

onMounted(loadMerchants)

const onSubmit = handleSubmit((v) => {
  emit('submit', {
    merchant_id: v.merchant_id as number,
    currency: v.currency,
    amount: v.amount as number,
    supplier_name: v.supplier_name,
    goods_description: v.goods_description,
    port_of_entry: v.port_of_entry,
    notes: v.notes ?? '',
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

        <!-- Merchant fetch error with retry -->
        <div v-if="merchantsError" class="merchant-error" role="alert">
          <span class="merchant-error-text">تعذّر تحميل قائمة التجار.</span>
          <button type="button" class="retry-inline-btn" @click="loadMerchants">
            إعادة المحاولة
          </button>
        </div>

        <template v-else>
          <select
            id="merchant-select"
            :value="values.merchant_id"
            class="form-input"
            :disabled="merchantsLoading || loading"
            :class="{ 'form-input--error': errors.merchant_id }"
            @change="(e) => setValues({ merchant_id: Number((e.target as HTMLSelectElement).value) || undefined })"
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
        </template>
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
          :value="values.supplier_name"
          type="text"
          class="form-input"
          :disabled="loading"
          :class="{ 'form-input--error': errors.supplier_name }"
          placeholder="أدخل اسم المورد"
          @input="(e) => setValues({ supplier_name: (e.target as HTMLInputElement).value })"
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
          :value="values.goods_description"
          class="form-input form-textarea"
          :disabled="loading"
          :class="{ 'form-input--error': errors.goods_description }"
          placeholder="أدخل وصفاً تفصيلياً للبضائع"
          rows="3"
          @input="(e) => setValues({ goods_description: (e.target as HTMLTextAreaElement).value })"
        />
        <span v-if="errors.goods_description" class="field-error" role="alert">{{ errors.goods_description }}</span>
      </div>

      <div class="field-group">
        <label class="field-label" for="port-of-entry">
          ميناء الدخول <span class="required-mark">*</span>
        </label>
        <input
          id="port-of-entry"
          :value="values.port_of_entry"
          type="text"
          class="form-input"
          :disabled="loading"
          :class="{ 'form-input--error': errors.port_of_entry }"
          placeholder="مثال: ميناء عدن"
          @input="(e) => setValues({ port_of_entry: (e.target as HTMLInputElement).value })"
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
            :value="values.amount"
            type="number"
            min="0.01"
            step="0.01"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.amount }"
            placeholder="0.00"
            @input="(e) => setValues({ amount: Number((e.target as HTMLInputElement).value) || undefined })"
          />
          <span v-if="errors.amount" class="field-error" role="alert">{{ errors.amount }}</span>
        </div>

        <div class="field-group field-group--flex">
          <label class="field-label" for="currency">
            العملة <span class="required-mark">*</span>
          </label>
          <select
            id="currency"
            :value="values.currency"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.currency }"
            @change="(e) => setValues({ currency: (e.target as HTMLSelectElement).value })"
          >
            <option v-for="c in Object.values(Currency)" :key="c" :value="c">
              {{ c }}
            </option>
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
          :value="values.notes"
          class="form-input form-textarea"
          :disabled="loading"
          placeholder="أي ملاحظات إضافية (اختياري)"
          rows="3"
          @input="(e) => setValues({ notes: (e.target as HTMLTextAreaElement).value })"
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

.merchant-error {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #fff0f0;
  border: 1px solid #ff3b30;
  border-radius: 12px;
}

.merchant-error-text {
  font-size: 14px;
  color: #c0392b;
  flex: 1;
}

.retry-inline-btn {
  height: 32px;
  padding: 0 14px;
  background: transparent;
  color: #0071e3;
  border: 1px solid #0071e3;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition: background 100ms;
}

.retry-inline-btn:hover {
  background: #0071e31a;
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
