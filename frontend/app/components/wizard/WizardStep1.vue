<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { WizardStep1Data } from '../../composables/useRequestWizard'
import { GOODS_TYPES, PAYMENT_TERMS } from '../../schemas/wizard.schema'
import { Currency } from '../../types/enums'
import { useMerchants } from '../../composables/useMerchants'
import type { Merchant } from '../../types/models'
import { Button } from '../ui/button'
import { Input } from '../ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxInput,
  ComboboxList,
  ComboboxItem,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItemIndicator,
  ComboboxTrigger,
} from '../ui/combobox'
import { Textarea } from '../ui/textarea'
import { Alert, AlertDescription } from '../ui/alert'
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSeparator,
  FieldSet,
} from '../ui/field'
import { AlertTriangle, Check, ChevronsUpDown, Lock, RotateCcw } from 'lucide-vue-next'

const props = defineProps<{
  modelValue: WizardStep1Data
  errors: Partial<Record<keyof WizardStep1Data, string>>
  isDataEntry: boolean
  dataEntryMerchantName?: string
  dataEntryMerchants?: Merchant[]
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
  USD: 'USD — دولار أمريكي',
  EUR: 'EUR — يورو',
  SAR: 'SAR — ريال سعودي',
  AED: 'AED — درهم إماراتي',
  CNY: 'CNY — يوان صيني',
}

const PAYMENT_LABELS: Record<string, string> = {
  LC: 'L/C — اعتماد مستندي',
  TT: 'T/T — تحويل بنكي مباشر',
  CAD: 'CAD — نقداً عند التسليم',
}

const bankMerchants = ref<Merchant[]>([])
const merchantsLoading = ref(false)
const merchantsError = ref(false)

const { fetchMerchants } = useMerchants()

async function loadMerchants(): Promise<void> {
  merchantsLoading.value = true
  merchantsError.value = false
  try {
    bankMerchants.value = await fetchMerchants()
  }
  catch {
    merchantsError.value = true
  }
  finally {
    merchantsLoading.value = false
  }
}

onMounted(() => {
  if (!props.isDataEntry) loadMerchants()
})

const merchantOptions = computed(() =>
  props.isDataEntry ? (props.dataEntryMerchants ?? []) : bankMerchants.value,
)

const selectedMerchant = computed(() =>
  merchantOptions.value.find(m => m.id === props.modelValue.merchant_id) ?? null,
)

// Single merchant auto-lock for DATA_ENTRY
const isLockedSingleMerchant = computed(() =>
  props.isDataEntry
  && merchantOptions.value.length === 1
  && Boolean(props.modelValue.merchant_id)
  && !props.dataEntryMerchantError,
)

const notesLength = computed(() => props.modelValue.notes?.length ?? 0)
const errorCount = computed(() => Object.keys(props.errors).length)
</script>

<template>
  <div class="flex flex-col gap-0">
    <!-- Error banner -->
    <Alert v-if="errorCount > 0" variant="destructive" class="mb-6">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription>
        يوجد {{ errorCount }} {{ errorCount === 1 ? 'حقل يحتاج' : 'حقول تحتاج' }} إلى تصحيح قبل المتابعة.
      </AlertDescription>
    </Alert>

    <FieldGroup>
      <!-- Section 1: Basic request info -->
      <FieldSet>
        <FieldLegend>معلومات الطلب الأساسية</FieldLegend>
        <FieldDescription>حدد نوع الواردات والمستورد والمبلغ المطلوب تمويله</FieldDescription>

        <FieldGroup>
          <!-- نوع الواردات -->
          <Field>
            <FieldLabel for="goods-type">نوع الواردات <span class="text-destructive">*</span></FieldLabel>
            <Select
              :model-value="modelValue.goods_type || ''"
              :disabled="loading"
              @update:model-value="(val) => update('goods_type', String(val ?? ''))"
            >
              <SelectTrigger id="goods-type" :class="{ 'border-destructive': errors.goods_type }">
                <SelectValue placeholder="اختر نوع الواردات..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in GOODS_TYPES" :key="t" :value="t">{{ t }}</SelectItem>
              </SelectContent>
            </Select>
            <FieldError v-if="errors.goods_type">{{ errors.goods_type }}</FieldError>
          </Field>

          <!-- المستورد -->
          <Field>
            <FieldLabel for="merchant-combobox">المستورد (التاجر) <span class="text-destructive">*</span></FieldLabel>

            <!-- DATA_ENTRY: single merchant locked -->
            <template v-if="isLockedSingleMerchant">
              <div class="flex items-center gap-2 h-10 px-3 border border-border rounded-md bg-muted text-muted-foreground">
                <Lock class="h-4 w-4 flex-shrink-0" />
                <span class="text-sm">{{ selectedMerchant?.name ?? 'لم يتم تحديد التاجر بعد' }}</span>
              </div>
            </template>

            <!-- Combobox for BANK_ADMIN or DATA_ENTRY with multiple merchants -->
            <template v-else>
              <Alert v-if="merchantsError" variant="destructive" class="mb-2">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription class="flex items-center justify-between gap-2">
                  <span>تعذر تحميل قائمة التجار. أعد المحاولة.</span>
                  <Button type="button" variant="outline" size="sm" class="whitespace-nowrap" @click="loadMerchants">
                    <RotateCcw class="h-3 w-3 me-1" />إعادة المحاولة
                  </Button>
                </AlertDescription>
              </Alert>
              <Combobox
                v-else
                :model-value="selectedMerchant"
                by="id"
                :disabled="merchantsLoading || loading"
                @update:model-value="(m) => update('merchant_id', m?.id ?? null)"
              >
                <ComboboxAnchor :class="{ 'border-destructive': errors.merchant_id }">
                  <ComboboxInput
                    id="merchant-combobox"
                    :placeholder="merchantsLoading ? 'جارٍ تحميل القائمة...' : 'ابحث أو اختر المستورد...'"
                    :display-value="(m: any) => m?.name ?? ''"
                    class="w-full"
                  />
                  <ComboboxTrigger>
                    <ChevronsUpDown class="h-4 w-4 text-muted-foreground" />
                  </ComboboxTrigger>
                </ComboboxAnchor>
                <ComboboxList>
                  <ComboboxEmpty>لا توجد نتائج</ComboboxEmpty>
                  <ComboboxGroup>
                    <ComboboxItem
                      v-for="m in merchantOptions"
                      :key="m.id"
                      :value="m"
                    >
                      {{ m.name }}
                      <ComboboxItemIndicator>
                        <Check class="h-4 w-4" />
                      </ComboboxItemIndicator>
                    </ComboboxItem>
                  </ComboboxGroup>
                </ComboboxList>
              </Combobox>
            </template>

            <FieldDescription v-if="isDataEntry && dataEntryMerchantError">{{ dataEntryMerchantError }}</FieldDescription>
            <FieldError v-if="errors.merchant_id">{{ errors.merchant_id }}</FieldError>
          </Field>

          <!-- المبلغ والعملة side by side -->
          <div class="grid grid-cols-2 gap-4">
            <Field>
              <FieldLabel for="amount">مبلغ التمويل <span class="text-destructive">*</span></FieldLabel>
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
              <FieldError v-if="errors.amount">{{ errors.amount }}</FieldError>
            </Field>

            <Field>
              <FieldLabel for="currency">العملة <span class="text-destructive">*</span></FieldLabel>
              <Select
                :model-value="modelValue.currency || ''"
                :disabled="loading"
                @update:model-value="(val) => update('currency', String(val ?? '') as WizardStep1Data['currency'])"
              >
                <SelectTrigger id="currency" :class="{ 'border-destructive': errors.currency }">
                  <SelectValue placeholder="اختر..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="c in Object.values(Currency)" :key="c" :value="c">{{ CURRENCY_LABELS[c] ?? c }}</SelectItem>
                </SelectContent>
              </Select>
              <FieldError v-if="errors.currency">{{ errors.currency }}</FieldError>
            </Field>
          </div>
        </FieldGroup>
      </FieldSet>

      <FieldSeparator />

      <!-- Section 2: Payment & schedule -->
      <FieldSet>
        <FieldLegend>شروط الدفع والجدول الزمني</FieldLegend>
        <FieldDescription>حدد طريقة الدفع والتواريخ المرتبطة بالصفقة</FieldDescription>

        <FieldGroup>
          <Field>
            <FieldLabel for="payment-terms">شروط الدفع <span class="text-destructive">*</span></FieldLabel>
            <Select
              :model-value="modelValue.payment_terms || ''"
              :disabled="loading"
              @update:model-value="(val) => update('payment_terms', String(val ?? '') as WizardStep1Data['payment_terms'])"
            >
              <SelectTrigger id="payment-terms" :class="{ 'border-destructive': errors.payment_terms }">
                <SelectValue placeholder="اختر شروط الدفع..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in PAYMENT_TERMS" :key="t" :value="t">{{ PAYMENT_LABELS[t] ?? t }}</SelectItem>
              </SelectContent>
            </Select>
            <FieldError v-if="errors.payment_terms">{{ errors.payment_terms }}</FieldError>
          </Field>

          <Field>
            <FieldLabel for="due-date">تاريخ الاستحقاق المتوقع</FieldLabel>
            <Input
              id="due-date"
              type="date"
              :disabled="loading"
              :value="modelValue.due_date ?? ''"
              @input="update('due_date', ($event.target as HTMLInputElement).value || '')"
            />
            <FieldDescription>اختياري — التاريخ المتوقع لاستحقاق الدفع</FieldDescription>
          </Field>
        </FieldGroup>
      </FieldSet>

      <FieldSeparator />

      <!-- Section 3: Notes -->
      <FieldSet>
        <FieldLegend>ملاحظات إضافية</FieldLegend>

        <FieldGroup>
          <Field>
            <FieldLabel for="notes">ملاحظات</FieldLabel>
            <Textarea
              id="notes"
              :value="modelValue.notes ?? ''"
              :disabled="loading"
              rows="3"
              maxlength="500"
              placeholder="أي معلومات إضافية تتعلق بالطلب..."
              class="resize-none"
              @input="update('notes', ($event.target as HTMLTextAreaElement).value)"
            />
            <FieldDescription class="flex justify-between">
              <span>اختياري</span>
              <span>{{ notesLength }}/500</span>
            </FieldDescription>
          </Field>
        </FieldGroup>
      </FieldSet>
    </FieldGroup>
  </div>
</template>
