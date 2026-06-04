<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import type { WizardStep1Data } from '../../composables/useRequestWizard'
import { GOODS_TYPES, PAYMENT_TERMS } from '../../schemas/wizard.schema'
import { Currency } from '../../types/enums'
import { useMerchants } from '../../composables/useMerchants'
import type { Merchant } from '../../types/models'
import { Button } from '../ui/button'
import { Input } from '../ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '../ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '../ui/popover'
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
// Alert/AlertTriangle kept for merchant load error banner
import { cn } from '@/lib/utils'
import { useFormFieldNav } from '@/composables/useFormFieldNav'

const { onFieldKeydown, enterKeyHint } = useFormFieldNav()

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
  'clear-error': [key: keyof WizardStep1Data]
}>()

function update<K extends keyof WizardStep1Data>(key: K, val: WizardStep1Data[K]): void {
  emit('update:modelValue', { ...props.modelValue, [key]: val })
  if (props.errors[key]) emit('clear-error', key)
}

// Local ref for notes — decouples the textarea from the parent re-render cycle so
// cursor position and Arabic IME composition are never disrupted.
const notesLocal = ref(props.modelValue.notes ?? '')

// Sync from parent (e.g. when a saved draft is loaded into the wizard).
watch(
  () => props.modelValue.notes,
  (incoming) => {
    if ((incoming ?? '') !== notesLocal.value) notesLocal.value = incoming ?? ''
  },
)

// Push local changes up to parent state.
watch(notesLocal, (val) => update('notes', val))

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

const merchantOpen = ref(false)

function selectMerchant(merchantId: string) {
  const id = Number(merchantId)
  update('merchant_id', id === props.modelValue.merchant_id ? null : id)
  merchantOpen.value = false
}

const notesLength = computed(() => notesLocal.value.length)
</script>

<template>
  <div class="flex flex-col gap-0">
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
              <SelectTrigger id="goods-type" :class="{ 'border-destructive': errors.goods_type }" :aria-invalid="!!errors.goods_type">
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

            <!-- Popover+Command combobox for BANK_ADMIN or multi-merchant DATA_ENTRY -->
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
              <Popover v-else v-model:open="merchantOpen">
                <PopoverTrigger as-child>
                  <Button
                    id="merchant-combobox"
                    type="button"
                    variant="outline"
                    role="combobox"
                    :aria-expanded="merchantOpen"
                    :disabled="merchantsLoading || loading"
                    :aria-invalid="!!errors.merchant_id"
                    :class="cn(
                      'w-full justify-between font-normal',
                      errors.merchant_id ? 'border-destructive' : '',
                      !selectedMerchant ? 'text-muted-foreground' : '',
                    )"
                  >
                    {{ merchantsLoading ? 'جارٍ تحميل القائمة...' : (selectedMerchant?.name ?? 'ابحث أو اختر المستورد...') }}
                    <ChevronsUpDown class="h-4 w-4 opacity-50 flex-shrink-0" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[--radix-popover-trigger-width] p-0" align="start">
                  <Command>
                    <CommandInput placeholder="ابحث عن تاجر..." class="h-9" />
                    <CommandList>
                      <CommandEmpty>لا توجد نتائج</CommandEmpty>
                      <CommandGroup>
                        <CommandItem
                          v-for="m in merchantOptions"
                          :key="m.id"
                          :value="String(m.id)"
                          @select="(ev) => selectMerchant(ev.detail.value as string)"
                        >
                          {{ m.name }}
                          <Check
                            :class="cn('ms-auto h-4 w-4', modelValue.merchant_id === m.id ? 'opacity-100' : 'opacity-0')"
                          />
                        </CommandItem>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
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
                :aria-invalid="!!errors.amount"
                :class="{ 'border-destructive': errors.amount }"
                :value="modelValue.amount ?? ''"
                :enterkeyhint="enterKeyHint"
                placeholder="0"
                @input="update('amount', Number(($event.target as HTMLInputElement).value) || null)"
                @keydown="onFieldKeydown"
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
                <SelectTrigger id="currency" :class="{ 'border-destructive': errors.currency }" :aria-invalid="!!errors.currency">
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
              <SelectTrigger id="payment-terms" :class="{ 'border-destructive': errors.payment_terms }" :aria-invalid="!!errors.payment_terms">
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
              :aria-invalid="!!errors.due_date"
              :class="{ 'border-destructive': errors.due_date }"
              :value="modelValue.due_date ?? ''"
              @input="update('due_date', ($event.target as HTMLInputElement).value || '')"
            />
            <FieldError v-if="errors.due_date">{{ errors.due_date }}</FieldError>
            <FieldDescription v-else>اختياري — التاريخ المتوقع لاستحقاق الدفع</FieldDescription>
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
              v-model="notesLocal"
              :disabled="loading"
              rows="3"
              maxlength="500"
              placeholder="أي معلومات إضافية تتعلق بالطلب..."
              class="resize-none"
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
