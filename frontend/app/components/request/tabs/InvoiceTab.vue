<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { COVERAGE_TYPE_OPTIONS, INVOICE_TYPE_OPTIONS } from '@/constants/workflow'
import { CoverageType, InvoiceType } from '@/types/enums'
import type { RequestFormData } from '@/types/models'

const props = defineProps<{
  modelValue: Partial<RequestFormData>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<RequestFormData>]
  validation: [value: { tab: 'invoice'; hasError: boolean }]
}>()

const percentageError = computed(() => {
  if (props.modelValue.coverage_type !== CoverageType.PARTIAL) return null
  const value = Number(props.modelValue.request_percentage)
  if (!Number.isFinite(value) || value < 5 || value >= 100) {
    return 'النسبة الجزئية يجب أن تكون بين 5% و 100% (غير شاملة)'
  }
  return null
})

const percentageDisabled = computed(() => !props.modelValue.coverage_type)
const percentageReadonly = computed(() => props.modelValue.coverage_type === CoverageType.FULL)

function patch(values: Partial<RequestFormData>) {
  emit('update:modelValue', { ...props.modelValue, ...values })
}

function updateCoverage(value: CoverageType) {
  patch({
    coverage_type: value,
    request_percentage:
      value === CoverageType.FULL ? '100.00' : props.modelValue.request_percentage,
  })
  emit('validation', { tab: 'invoice', hasError: false })
}

function updateInvoiceType(value: InvoiceType) {
  patch({ invoice_type: value })
}
</script>

<template>
  <div class="grid gap-5">
    <div class="grid gap-4 md:grid-cols-2">
      <div class="grid gap-2">
        <Label>نوع التغطية</Label>
        <Select
          :model-value="modelValue.coverage_type ?? undefined"
          @update:model-value="(value) => updateCoverage(value as CoverageType)"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر نوع التغطية" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in COVERAGE_TYPE_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-2">
        <Label for="request-percentage">نسبة التمويل %</Label>
        <Input
          id="request-percentage"
          type="number"
          step="0.01"
          min="0"
          :disabled="percentageDisabled"
          :readonly="percentageReadonly"
          :model-value="modelValue.request_percentage ?? ''"
          :placeholder="percentageDisabled ? 'اختر نوع التغطية أولاً' : 'مثال: 25.50'"
          :class="{ 'bg-muted text-muted-foreground': percentageReadonly || percentageDisabled }"
          @update:model-value="(value) => patch({ request_percentage: String(value) })"
        />
        <p
          v-if="modelValue.coverage_type === CoverageType.FULL"
          class="text-muted-foreground text-xs"
        >
          التغطية الكاملة تستلزم نسبة 100%
        </p>
        <p v-if="percentageError" class="text-xs text-[var(--severity-red)]">
          {{ percentageError }}
        </p>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="grid gap-2">
        <Label for="request-currency">عملة الطلب</Label>
        <Input
          id="request-currency"
          :model-value="modelValue.request_currency ?? ''"
          placeholder="USD"
          @update:model-value="(value) => patch({ request_currency: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="requested-amount">المبلغ المطلوب</Label>
        <Input
          id="requested-amount"
          type="number"
          min="0"
          step="0.01"
          :model-value="modelValue.requested_amount ?? ''"
          @update:model-value="(value) => patch({ requested_amount: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="invoice-number">رقم الفاتورة</Label>
        <Input
          id="invoice-number"
          :model-value="modelValue.invoice_number ?? ''"
          @update:model-value="(value) => patch({ invoice_number: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label>نوع الفاتورة</Label>
        <Select
          :model-value="modelValue.invoice_type ?? undefined"
          @update:model-value="(value) => updateInvoiceType(value as InvoiceType)"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر نوع الفاتورة" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in INVOICE_TYPE_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="grid gap-2">
        <Label for="invoice-currency">عملة الفاتورة</Label>
        <Input
          id="invoice-currency"
          :model-value="modelValue.invoice_currency ?? ''"
          placeholder="USD"
          @update:model-value="(value) => patch({ invoice_currency: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="unit-of-measure">وحدة القياس</Label>
        <Input
          id="unit-of-measure"
          :model-value="modelValue.unit_of_measure ?? ''"
          @update:model-value="(value) => patch({ unit_of_measure: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="total-invoice-amount">إجمالي مبلغ الفاتورة</Label>
        <Input
          id="total-invoice-amount"
          type="number"
          min="0"
          step="0.01"
          :model-value="modelValue.total_invoice_amount ?? ''"
          @update:model-value="(value) => patch({ total_invoice_amount: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="exporting-company-name">اسم الشركة المصدِّرة</Label>
        <Input
          id="exporting-company-name"
          :model-value="modelValue.exporting_company_name ?? ''"
          @update:model-value="(value) => patch({ exporting_company_name: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="exporting-company-location">موقع الشركة المصدِّرة</Label>
        <Input
          id="exporting-company-location"
          :model-value="modelValue.exporting_company_location ?? ''"
          @update:model-value="(value) => patch({ exporting_company_location: String(value) })"
        />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="commodity">البضاعة / السلعة</Label>
      <Textarea
        id="commodity"
        :model-value="modelValue.commodity ?? ''"
        rows="3"
        @update:model-value="(value) => patch({ commodity: String(value) })"
      />
    </div>
  </div>
</template>
