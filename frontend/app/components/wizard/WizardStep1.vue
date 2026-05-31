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
  <div class="flex flex-col gap-6" >
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
          <span class="text-[var(--color-text-error)]">*</span>
        </Label>
        <Select
          :model-value="modelValue.goods_type || ''"
          :disabled="loading"
          @update:model-value="(val) => update('goods_type', val)"
        >
          <SelectTrigger id="goods-type" :class="{ 'border-destructive': errors.goods_type }">
            <SelectValue placeholder="اختر نوع الواردات..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="t in GOODS_TYPES" :key="t" :value="t">{{ t }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.goods_type" class="text-sm text-[var(--color-text-error)]">{{ errors.goods_type }}</p>
      </div>

      <!-- المستورد -->
      <div class="flex flex-col gap-2">
        <Label for="merchant" class="text-sm">
          المستورد (التاجر)
          <span class="text-[var(--color-text-error)]">*</span>
        </Label>

        <!-- DATA_ENTRY: read-only -->
        <div
          v-if="isDataEntry"
          class="flex items-center gap-2 h-10 px-3 border border-border rounded-md bg-[var(--color-surface-subtle)] text-[var(--color-text-subtle)]"
          :class="{ 'border-destructive bg-[var(--color-surface-error)]': !!dataEntryMerchantError }"
        >
          <Lock class="h-4 w-4 flex-shrink-0" />
          <span class="text-sm">{{ selectedMerchantName || 'لم يتم تحديد التاجر بعد' }}</span>
        </div>
        <p v-if="isDataEntry && dataEntryMerchantError" class="text-sm text-[var(--color-text-error)]">{{ dataEntryMerchantError }}</p>

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
              <SelectTrigger id="merchant" :class="{ 'border-destructive': errors.merchant_id }">
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
        <p v-if="errors.merchant_id" class="text-sm text-[var(--color-text-error)]">{{ errors.merchant_id }}</p>
      </div>

      <!-- مبلغ التمويل -->
      <div class="flex flex-col gap-2">
        <Label for="amount" class="text-sm">
          مبلغ التمويل
          <span class="text-[var(--color-text-error)]">*</span>
        </Label>
        <Input
          id="amount"
          type="number"
          min="1000"
          step="1"
          :disabled="loading"
          :class="{ 'border-destructive': errors.amount }"
          :value="modelValue.amount ?? ''"
          placeholder="0"
          @input="update('amount', Number(($event.target as HTMLInputElement).value) || null)"
        />
        <p v-if="errors.amount" class="text-sm text-[var(--color-text-error)]">{{ errors.amount }}</p>
      </div>

      <!-- العملة -->
      <div class="flex flex-col gap-2">
        <Label for="currency" class="text-sm">
          العملة
          <span class="text-[var(--color-text-error)]">*</span>
        </Label>
        <Select
          :model-value="modelValue.currency || ''"
          :disabled="loading"
          @update:model-value="(val) => update('currency', val)"
        >
          <SelectTrigger id="currency" :class="{ 'border-destructive': errors.currency }">
            <SelectValue :placeholder="modelValue.currency" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="c in Object.values(Currency)" :key="c" :value="c">
              {{ CURRENCY_LABELS[c] ?? c }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.currency" class="text-sm text-[var(--color-text-error)]">{{ errors.currency }}</p>
      </div>

      <!-- شروط الدفع -->
      <div class="flex flex-col gap-2">
        <Label for="payment-terms" class="text-sm">
          شروط الدفع
          <span class="text-[var(--color-text-error)]">*</span>
        </Label>
        <Select
          :model-value="modelValue.payment_terms || ''"
          :disabled="loading"
          @update:model-value="(val) => update('payment_terms', val)"
        >
          <SelectTrigger id="payment-terms" :class="{ 'border-destructive': errors.payment_terms }">
            <SelectValue placeholder="اختر شروط الدفع..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">بدون شروط</SelectItem>
            <SelectItem v-for="t in PAYMENT_TERMS" :key="t" :value="t">{{ PAYMENT_LABELS[t] }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.payment_terms" class="text-sm text-[var(--color-text-error)]">{{ errors.payment_terms }}</p>
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
        <div class="flex justify-end text-xs text-[var(--color-text-subtle)]">{{ notesLength }}/500</div>
      </div>
    </div>
  </div>
</template>
