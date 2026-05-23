<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { WizardStep1Data } from '../../composables/useRequestWizard'
import { GOODS_TYPES, PAYMENT_TERMS } from '../../schemas/wizard.schema'
import { Currency } from '../../types/enums'
import { useMerchants } from '../../composables/useMerchants'
import type { Merchant } from '../../types/models'
import { Button } from '../ui/button'
import { Input } from '../ui/input'
import { Label } from '../ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select'
import { Textarea } from '../ui/textarea'
import { Alert, AlertDescription } from '../ui/alert'
import { AlertTriangle, Lock, RotateCcw } from 'lucide-vue-next'

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
  <div class="flex flex-col gap-6" dir="rtl">
    <!-- Error banner -->
    <Alert v-if="errorCount > 0" variant="destructive">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription>
        يوجد {{ errorCount }} {{ errorCount === 1 ? 'حقل يحتاج' : 'حقول تحتاج' }} إلى تصحيح قبل المتابعة.
      </AlertDescription>
    </Alert>

    <h2 class="text-2xl font-bold">معلومات الطلب الأساسية</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <!-- نوع الواردات -->
      <div class="flex flex-col gap-2">
        <Label for="goods-type" class="text-sm">
          نوع الواردات
          <span class="text-red-600">*</span>
        </Label>
        <Select
          :model-value="modelValue.goods_type || ''"
          :disabled="loading"
          @update:model-value="(val) => update('goods_type', val)"
        >
          <SelectTrigger id="goods-type" :class="{ 'border-red-600': errors.goods_type }">
            <SelectValue placeholder="اختر نوع الواردات..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="t in GOODS_TYPES" :key="t" :value="t">{{ t }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.goods_type" class="text-sm text-red-600">{{ errors.goods_type }}</p>
      </div>

      <!-- المستورد -->
      <div class="flex flex-col gap-2">
        <Label for="merchant" class="text-sm">
          المستورد (التاجر)
          <span class="text-red-600">*</span>
        </Label>

        <!-- DATA_ENTRY: read-only -->
        <div
          v-if="isDataEntry"
          class="flex items-center gap-2 h-10 px-3 border border-border rounded-md bg-muted text-muted-foreground"
          :class="{ 'border-red-600 bg-red-50': !!dataEntryMerchantError }"
        >
          <Lock class="h-4 w-4 flex-shrink-0" />
          <span class="text-sm">{{ selectedMerchantName || 'لم يتم تحديد التاجر بعد' }}</span>
        </div>
        <p v-if="isDataEntry && dataEntryMerchantError" class="text-sm text-red-600">{{ dataEntryMerchantError }}</p>

        <!-- BANK_ADMIN: searchable select -->
        <template v-else>
          <Alert v-if="merchantsError" variant="destructive" class="mb-2">
            <AlertTriangle class="h-4 w-4" />
            <AlertDescription>
              <div class="flex items-center justify-between gap-2">
                <span>تعذّر تحميل قائمة التجار.</span>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="loadMerchants"
                  class="whitespace-nowrap"
                >
                  <RotateCcw class="h-3 w-3 me-1" />
                  إعادة المحاولة
                </Button>
              </div>
            </AlertDescription>
          </Alert>
          <template v-else>
            <Input
              v-model="merchantSearch"
              type="text"
              placeholder="ابحث عن تاجر..."
              :disabled="merchantsLoading || loading"
              class="mb-2"
            />
            <Select
              :model-value="String(modelValue.merchant_id ?? '')"
              :disabled="merchantsLoading || loading"
              @update:model-value="(val) => update('merchant_id', val ? Number(val) : null)"
            >
              <SelectTrigger id="merchant" :class="{ 'border-red-600': errors.merchant_id }">
                <SelectValue :placeholder="merchantsLoading ? 'جاري التحميل...' : 'اختر المستورد...'" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="m in filteredMerchants" :key="m.id" :value="String(m.id)">
                  {{ m.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </template>
        </template>
        <p v-if="errors.merchant_id" class="text-sm text-red-600">{{ errors.merchant_id }}</p>
      </div>

      <!-- مبلغ التمويل -->
      <div class="flex flex-col gap-2">
        <Label for="amount" class="text-sm">
          مبلغ التمويل
          <span class="text-red-600">*</span>
        </Label>
        <Input
          id="amount"
          type="number"
          min="1000"
          step="1"
          :disabled="loading"
          :class="{ 'border-red-600': errors.amount }"
          :value="modelValue.amount ?? ''"
          placeholder="0"
          @input="update('amount', Number(($event.target as HTMLInputElement).value) || null)"
        />
        <p v-if="errors.amount" class="text-sm text-red-600">{{ errors.amount }}</p>
      </div>

      <!-- العملة -->
      <div class="flex flex-col gap-2">
        <Label for="currency" class="text-sm">
          العملة
          <span class="text-red-600">*</span>
        </Label>
        <Select
          :model-value="modelValue.currency || ''"
          :disabled="loading"
          @update:model-value="(val) => update('currency', val)"
        >
          <SelectTrigger id="currency" :class="{ 'border-red-600': errors.currency }">
            <SelectValue :placeholder="modelValue.currency" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="c in Object.values(Currency)" :key="c" :value="c">
              {{ CURRENCY_LABELS[c] ?? c }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.currency" class="text-sm text-red-600">{{ errors.currency }}</p>
      </div>

      <!-- شروط الدفع -->
      <div class="flex flex-col gap-2">
        <Label for="payment-terms" class="text-sm">
          شروط الدفع
          <span class="text-red-600">*</span>
        </Label>
        <Select
          :model-value="modelValue.payment_terms || ''"
          :disabled="loading"
          @update:model-value="(val) => update('payment_terms', val)"
        >
          <SelectTrigger id="payment-terms" :class="{ 'border-red-600': errors.payment_terms }">
            <SelectValue placeholder="اختر شروط الدفع..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">بدون شروط</SelectItem>
            <SelectItem v-for="t in PAYMENT_TERMS" :key="t" :value="t">{{ PAYMENT_LABELS[t] }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.payment_terms" class="text-sm text-red-600">{{ errors.payment_terms }}</p>
      </div>

      <!-- تاريخ الاستحقاق (optional) -->
      <div class="flex flex-col gap-2">
        <Label for="due-date" class="text-sm">تاريخ الاستحقاق المتوقع</Label>
        <Input
          id="due-date"
          type="date"
          :disabled="loading"
          :value="modelValue.due_date ?? ''"
          @input="update('due_date', ($event.target as HTMLInputElement).value || '')"
        />
      </div>

      <!-- ملاحظات -->
      <div class="col-span-1 sm:col-span-2 flex flex-col gap-2">
        <Label for="notes" class="text-sm">ملاحظات إضافية</Label>
        <Textarea
          id="notes"
          :value="modelValue.notes ?? ''"
          :disabled="loading"
          rows="3"
          maxlength="500"
          placeholder="أي معلومات إضافية تتعلق بالطلب..."
          @input="update('notes', ($event.target as HTMLTextAreaElement).value)"
        />
        <div class="flex justify-end text-xs text-muted-foreground">{{ notesLength }}/500</div>
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
