<script setup lang="ts">
import { computed, ref } from 'vue'
import { Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  CURRENCY_SOURCE_OPTIONS,
  PAYMENT_TERMS_MODE_OPTIONS,
  REQUEST_TYPE_OPTIONS,
} from '@/constants/workflow'
import { useRequests } from '@/composables/useRequests'
import type { RequestFormData, TraderLookupResult } from '@/types/models'

const props = defineProps<{
  modelValue: Partial<RequestFormData>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<RequestFormData>]
}>()

const { lookupTrader } = useRequests()
const taxNumber = ref(props.modelValue.trader_snapshot_tax_number ?? '')
const lookupLoading = ref(false)
const lookupError = ref<string | null>(null)
const lookupResult = ref<TraderLookupResult | null>(null)

const snapshot = computed(() => ({
  name: props.modelValue.trader_snapshot_name ?? lookupResult.value?.trader.trader_name ?? '',
  taxNumber:
    props.modelValue.trader_snapshot_tax_number ?? lookupResult.value?.trader.tax_number ?? '',
  taxCardExpiry:
    props.modelValue.trader_snapshot_tax_card_expiry ??
    lookupResult.value?.trader.tax_card_expiry ??
    '',
  commercialRegistrationNumber:
    props.modelValue.trader_snapshot_commercial_registration_number ??
    lookupResult.value?.trader.commercial_registration_number ??
    '',
  commercialRegistrationExpiry:
    props.modelValue.trader_snapshot_commercial_registration_expiry ??
    lookupResult.value?.trader.commercial_registration_expiry ??
    '',
}))

function patch(values: Partial<RequestFormData>) {
  emit('update:modelValue', { ...props.modelValue, ...values })
}

async function performLookup() {
  lookupError.value = null
  lookupLoading.value = true
  try {
    const result = await lookupTrader(taxNumber.value)
    lookupResult.value = result
    if (!result) {
      patch({
        trader_id: null,
        trader_snapshot_name: null,
        trader_snapshot_tax_number: null,
        trader_snapshot_tax_card_expiry: null,
        trader_snapshot_commercial_registration_number: null,
        trader_snapshot_commercial_registration_expiry: null,
      })
      lookupError.value =
        'لم يتم العثور على تاجر بهذا الرقم — يرجى مراجعة البيانات أو تسجيل تاجر جديد'
      return
    }

    patch({
      trader_id: result.trader.id,
      trader_snapshot_name: result.trader.trader_name,
      trader_snapshot_tax_number: result.trader.tax_number,
      trader_snapshot_tax_card_expiry: result.trader.tax_card_expiry,
      trader_snapshot_commercial_registration_number: result.trader.commercial_registration_number,
      trader_snapshot_commercial_registration_expiry: result.trader.commercial_registration_expiry,
    })
  } finally {
    lookupLoading.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="grid gap-3">
      <Label for="tax-number">رقم الوعاء الضريبي</Label>
      <div class="flex gap-2">
        <Input
          id="tax-number"
          v-model="taxNumber"
          placeholder="أدخل رقم الوعاء الضريبي"
          class="flex-1"
          @keyup.enter="performLookup"
        />
        <Button type="button" :disabled="lookupLoading || !taxNumber.trim()" @click="performLookup">
          <Search class="me-2 h-4 w-4" />
          بحث
        </Button>
      </div>
    </div>

    <div v-if="lookupLoading" class="grid gap-3 md:grid-cols-2">
      <Skeleton v-for="n in 5" :key="n" class="h-12 rounded-md" />
    </div>

    <Alert v-else-if="lookupError" variant="destructive">
      <AlertDescription>
        {{ lookupError }}
        <NuxtLink to="/traders/new" class="font-medium underline">تسجيل تاجر جديد</NuxtLink>
      </AlertDescription>
    </Alert>

    <div
      v-else-if="snapshot.name"
      class="border-border grid gap-4 rounded-lg border p-4 md:grid-cols-2"
    >
      <div>
        <p class="text-muted-foreground text-xs">اسم التاجر</p>
        <p class="font-medium">{{ snapshot.name }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs">رقم الوعاء الضريبي</p>
        <p class="font-mono">{{ snapshot.taxNumber }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs">تاريخ انتهاء البطاقة الضريبية</p>
        <p>{{ snapshot.taxCardExpiry || '—' }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs">رقم السجل التجاري</p>
        <p>{{ snapshot.commercialRegistrationNumber || '—' }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs">تاريخ انتهاء السجل التجاري</p>
        <p>{{ snapshot.commercialRegistrationExpiry || '—' }}</p>
      </div>
    </div>

    <div v-if="lookupResult?.companies?.length" class="border-border rounded-lg border p-4">
      <p class="mb-2 text-sm font-medium">الشركات المرتبطة</p>
      <ul class="text-muted-foreground list-inside list-disc text-sm">
        <li v-for="company in lookupResult.companies" :key="company.id ?? company.company_name">
          {{ company.company_name }}
        </li>
      </ul>
    </div>

    <div v-if="lookupResult?.owners?.length" class="border-border rounded-lg border p-4">
      <p class="mb-2 text-sm font-medium">الملاك الرئيسيون</p>
      <ul class="text-muted-foreground list-inside list-disc text-sm">
        <li
          v-for="owner in lookupResult.owners.filter((item) => item.ownership_percentage >= 25)"
          :key="owner.id ?? owner.full_name"
        >
          {{ owner.full_name }} — {{ owner.ownership_percentage }}%
        </li>
      </ul>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="grid gap-2">
        <Label>نوع الطلب</Label>
        <Select
          :model-value="modelValue.request_type ?? undefined"
          @update:model-value="(value) => patch({ request_type: value as any })"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر نوع الطلب" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in REQUEST_TYPE_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-2">
        <Label>مصدر العملة</Label>
        <Select
          :model-value="modelValue.currency_source ?? undefined"
          @update:model-value="(value) => patch({ currency_source: value as any })"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر مصدر العملة" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in CURRENCY_SOURCE_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-2">
        <Label>شروط الدفع</Label>
        <Select
          :model-value="modelValue.payment_terms_mode ?? undefined"
          @update:model-value="(value) => patch({ payment_terms_mode: value as any })"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر شروط الدفع" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in PAYMENT_TERMS_MODE_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="notes">ملاحظات إضافية</Label>
      <Textarea
        id="notes"
        :model-value="modelValue.notes ?? ''"
        rows="3"
        placeholder="أضف ملاحظات تشغيلية عند الحاجة"
        @update:model-value="(value) => patch({ notes: String(value) })"
      />
    </div>
  </div>
</template>
