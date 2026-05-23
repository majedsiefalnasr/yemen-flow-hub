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
    goods_type: props.initialValues?.goods_type ?? '',
    payment_terms: (props.initialValues?.payment_terms ?? '') as '' | 'LC' | 'TT' | 'CAD',
    due_date: props.initialValues?.due_date ?? '',
    supplier_name: props.initialValues?.supplier_name ?? '',
    goods_description: props.initialValues?.goods_description ?? '',
    port_of_entry: props.initialValues?.port_of_entry ?? '',
    notes: props.initialValues?.notes ?? '',
    invoice_number: props.initialValues?.invoice_number ?? '',
    invoice_date: props.initialValues?.invoice_date ?? '',
    origin_country: props.initialValues?.origin_country ?? '',
    arrival_port: props.initialValues?.arrival_port ?? '',
    shipping_port: props.initialValues?.shipping_port ?? '',
    customs_office: props.initialValues?.customs_office ?? '',
    bl_number: props.initialValues?.bl_number ?? '',
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
      goods_type: next.goods_type ?? '',
      payment_terms: (next.payment_terms ?? '') as '' | 'LC' | 'TT' | 'CAD',
      due_date: next.due_date ?? '',
      supplier_name: next.supplier_name ?? '',
      goods_description: next.goods_description ?? '',
      port_of_entry: next.port_of_entry ?? '',
      notes: next.notes ?? '',
      invoice_number: next.invoice_number ?? '',
      invoice_date: next.invoice_date ?? '',
      origin_country: next.origin_country ?? '',
      arrival_port: next.arrival_port ?? '',
      shipping_port: next.shipping_port ?? '',
      customs_office: next.customs_office ?? '',
      bl_number: next.bl_number ?? '',
    })
  },
  { deep: true },
)

const merchants = ref<Merchant[]>([])
const merchantsLoading = ref(false)
const merchantsError = ref(false)

const { fetchMerchants } = useMerchants()

function onCurrencyChange(event: Event) {
  setValues({ currency: (event.target as HTMLSelectElement).value as Currency })
}

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
      goods_type: v.goods_type || null,
      payment_terms: v.payment_terms || null,
      due_date: v.due_date || null,
      supplier_name: v.supplier_name,
      goods_description: v.goods_description,
      port_of_entry: v.port_of_entry,
      notes: v.notes ?? '',
      invoice_number: v.invoice_number || null,
      invoice_date: v.invoice_date || null,
      origin_country: v.origin_country || null,
      arrival_port: v.arrival_port || null,
      shipping_port: v.shipping_port || null,
      customs_office: v.customs_office || null,
      bl_number: v.bl_number || null,
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
            @change="onCurrencyChange"
          >
            <option v-for="c in Object.values(Currency)" :key="c" :value="c">
              {{ c }}
            </option>
          </select>
          <span v-if="errors.currency" class="field-error" role="alert">{{ errors.currency }}</span>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group field-group--flex">
          <label class="field-label" for="goods-type">نوع البضائع</label>
          <input
            id="goods-type"
            :value="values.goods_type"
            type="text"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.goods_type }"
            placeholder="مثال: مواد غذائية"
            @input="(e) => setValues({ goods_type: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.goods_type" class="field-error" role="alert">{{ errors.goods_type }}</span>
        </div>

        <div class="field-group field-group--flex">
          <label class="field-label" for="payment-terms">شروط الدفع</label>
          <select
            id="payment-terms"
            :value="values.payment_terms"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.payment_terms }"
            @change="(e) => setValues({ payment_terms: (e.target as HTMLSelectElement).value as '' | 'LC' | 'TT' | 'CAD' })"
          >
            <option value="">اختر شروط الدفع</option>
            <option value="LC">LC</option>
            <option value="TT">TT</option>
            <option value="CAD">CAD</option>
          </select>
          <span v-if="errors.payment_terms" class="field-error" role="alert">{{ errors.payment_terms }}</span>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="due-date">تاريخ الاستحقاق</label>
        <input
          id="due-date"
          :value="values.due_date"
          type="date"
          class="form-input"
          :disabled="loading"
          :class="{ 'form-input--error': errors.due_date }"
          @input="(e) => setValues({ due_date: (e.target as HTMLInputElement).value })"
        />
        <span v-if="errors.due_date" class="field-error" role="alert">{{ errors.due_date }}</span>
      </div>
    </section>

    <section class="form-section">
      <h2 class="section-title">بيانات الشحنة والفاتورة</h2>

      <div class="field-row">
        <div class="field-group field-group--flex">
          <label class="field-label" for="invoice-number">رقم الفاتورة</label>
          <input
            id="invoice-number"
            :value="values.invoice_number"
            type="text"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.invoice_number }"
            @input="(e) => setValues({ invoice_number: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.invoice_number" class="field-error" role="alert">{{ errors.invoice_number }}</span>
        </div>

        <div class="field-group field-group--flex">
          <label class="field-label" for="invoice-date">تاريخ الفاتورة</label>
          <input
            id="invoice-date"
            :value="values.invoice_date"
            type="date"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.invoice_date }"
            @input="(e) => setValues({ invoice_date: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.invoice_date" class="field-error" role="alert">{{ errors.invoice_date }}</span>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group field-group--flex">
          <label class="field-label" for="origin-country">بلد المنشأ</label>
          <input
            id="origin-country"
            :value="values.origin_country"
            type="text"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.origin_country }"
            @input="(e) => setValues({ origin_country: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.origin_country" class="field-error" role="alert">{{ errors.origin_country }}</span>
        </div>

        <div class="field-group field-group--flex">
          <label class="field-label" for="arrival-port">ميناء الوصول</label>
          <input
            id="arrival-port"
            :value="values.arrival_port"
            type="text"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.arrival_port }"
            @input="(e) => setValues({ arrival_port: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.arrival_port" class="field-error" role="alert">{{ errors.arrival_port }}</span>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group field-group--flex">
          <label class="field-label" for="shipping-port">ميناء الشحن</label>
          <input
            id="shipping-port"
            :value="values.shipping_port"
            type="text"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.shipping_port }"
            @input="(e) => setValues({ shipping_port: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.shipping_port" class="field-error" role="alert">{{ errors.shipping_port }}</span>
        </div>

        <div class="field-group field-group--flex">
          <label class="field-label" for="customs-office">المكتب الجمركي</label>
          <input
            id="customs-office"
            :value="values.customs_office"
            type="text"
            class="form-input"
            :disabled="loading"
            :class="{ 'form-input--error': errors.customs_office }"
            @input="(e) => setValues({ customs_office: (e.target as HTMLInputElement).value })"
          />
          <span v-if="errors.customs_office" class="field-error" role="alert">{{ errors.customs_office }}</span>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="bl-number">رقم بوليصة الشحن</label>
        <input
          id="bl-number"
          :value="values.bl_number"
          type="text"
          class="form-input"
          :disabled="loading"
          :class="{ 'form-input--error': errors.bl_number }"
          @input="(e) => setValues({ bl_number: (e.target as HTMLInputElement).value })"
        />
        <span v-if="errors.bl_number" class="field-error" role="alert">{{ errors.bl_number }}</span>
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
