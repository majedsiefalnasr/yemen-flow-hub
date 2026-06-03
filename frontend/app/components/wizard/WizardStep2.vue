<script setup lang="ts">
import { computed } from 'vue'
import type { WizardStep2Data } from '../../composables/useRequestWizard'
import { ARRIVAL_PORTS } from '../../schemas/wizard.schema'
import { Input } from '../ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select'
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

const COUNTRIES = [
  'الولايات المتحدة', 'المملكة المتحدة', 'الصين', 'الهند', 'الإمارات العربية المتحدة',
  'المملكة العربية السعودية', 'تركيا', 'ألمانيا', 'فرنسا', 'إيطاليا',
  'اليابان', 'كوريا الجنوبية', 'البرازيل', 'كندا', 'أستراليا',
  'باكستان', 'مصر', 'الأردن', 'لبنان', 'الكويت',
  'البحرين', 'عُمان', 'قطر', 'إيران', 'روسيا',
]

const props = defineProps<{
  modelValue: WizardStep2Data
  errors: Partial<Record<keyof WizardStep2Data, string>>
  autoFillChip: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WizardStep2Data]
  'arrival-port-change': [port: string]
  'clear-error': [key: keyof WizardStep2Data]
}>()

function update<K extends keyof WizardStep2Data>(key: K, val: WizardStep2Data[K]): void {
  emit('update:modelValue', { ...props.modelValue, [key]: val })
  if (props.errors[key]) emit('clear-error', key)
}

function onPortChange(port: string): void {
  emit('arrival-port-change', port)
}

</script>

<template>
  <div class="flex flex-col gap-0">
    <FieldGroup>
      <!-- Section 1: Supplier -->
      <FieldSet>
        <FieldLegend>بيانات المورد</FieldLegend>
        <FieldDescription>معلومات المورد الخارجي المصدّر للبضاعة</FieldDescription>

        <FieldGroup>
          <Field>
            <FieldLabel for="supplier-name">اسم المورد <span class="text-destructive">*</span></FieldLabel>
            <Input
              id="supplier-name"
              type="text"
              :disabled="loading"
              :aria-invalid="!!errors.supplier_name"
              :class="{ 'border-destructive': errors.supplier_name }"
              :value="modelValue.supplier_name ?? ''"
              placeholder="مثال: Cargill Trading Inc."
              @input="update('supplier_name', ($event.target as HTMLInputElement).value)"
            />
            <FieldError v-if="errors.supplier_name">{{ errors.supplier_name }}</FieldError>
          </Field>

          <Field>
            <FieldLabel for="origin-country">بلد المنشأ <span class="text-destructive">*</span></FieldLabel>
            <Select
              :model-value="modelValue.origin_country || ''"
              :disabled="loading"
              @update:model-value="(val) => update('origin_country', String(val ?? ''))"
            >
              <SelectTrigger id="origin-country" :class="{ 'border-destructive': errors.origin_country }" :aria-invalid="!!errors.origin_country">
                <SelectValue placeholder="اختر بلد المنشأ..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</SelectItem>
              </SelectContent>
            </Select>
            <FieldError v-if="errors.origin_country">{{ errors.origin_country }}</FieldError>
          </Field>
        </FieldGroup>
      </FieldSet>

      <FieldSeparator />

      <!-- Section 2: Invoice -->
      <FieldSet>
        <FieldLegend>بيانات الفاتورة</FieldLegend>
        <FieldDescription>رقم وتاريخ الفاتورة التجارية للشحنة</FieldDescription>

        <FieldGroup>
          <div class="grid grid-cols-2 gap-4">
            <Field>
              <FieldLabel for="invoice-number">رقم الفاتورة <span class="text-destructive">*</span></FieldLabel>
              <Input
                id="invoice-number"
                type="text"
                :disabled="loading"
                :aria-invalid="!!errors.invoice_number"
              :class="{ 'border-destructive': errors.invoice_number }"
                :value="modelValue.invoice_number ?? ''"
                placeholder="INV-2025-XXXX"
                @input="update('invoice_number', ($event.target as HTMLInputElement).value)"
              />
              <FieldError v-if="errors.invoice_number">{{ errors.invoice_number }}</FieldError>
            </Field>

            <Field>
              <FieldLabel for="invoice-date">تاريخ الفاتورة <span class="text-destructive">*</span></FieldLabel>
              <Input
                id="invoice-date"
                type="date"
                :disabled="loading"
                :aria-invalid="!!errors.invoice_date"
              :class="{ 'border-destructive': errors.invoice_date }"
                :value="modelValue.invoice_date ?? ''"
                @input="update('invoice_date', ($event.target as HTMLInputElement).value)"
              />
              <FieldError v-if="errors.invoice_date">{{ errors.invoice_date }}</FieldError>
            </Field>
          </div>
        </FieldGroup>
      </FieldSet>

      <FieldSeparator />

      <!-- Section 3: Shipping -->
      <FieldSet>
        <FieldLegend>بيانات الشحن والجمارك</FieldLegend>
        <FieldDescription>تفاصيل مسار الشحنة والمنفذ الجمركي المختص</FieldDescription>

        <FieldGroup>
          <Field>
            <FieldLabel for="arrival-port">ميناء الوصول <span class="text-destructive">*</span></FieldLabel>
            <Select
              :model-value="modelValue.arrival_port || ''"
              :disabled="loading"
              @update:model-value="(val) => { const port = String(val ?? ''); update('arrival_port', port); onPortChange(port) }"
            >
              <SelectTrigger id="arrival-port" :class="{ 'border-destructive': errors.arrival_port }" :aria-invalid="!!errors.arrival_port">
                <SelectValue placeholder="اختر ميناء الوصول..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="p in ARRIVAL_PORTS" :key="p" :value="p">{{ p }}</SelectItem>
              </SelectContent>
            </Select>
            <FieldError v-if="errors.arrival_port">{{ errors.arrival_port }}</FieldError>
          </Field>

          <div class="grid grid-cols-2 gap-4">
            <Field>
              <FieldLabel for="shipping-port">ميناء الشحن</FieldLabel>
              <Input
                id="shipping-port"
                type="text"
                :disabled="loading"
                :value="modelValue.shipping_port ?? ''"
                placeholder="Port of Houston, USA"
                @input="update('shipping_port', ($event.target as HTMLInputElement).value)"
              />
              <FieldDescription>اختياري</FieldDescription>
            </Field>

            <Field>
              <FieldLabel for="bl-number">رقم بوليصة الشحن</FieldLabel>
              <Input
                id="bl-number"
                type="text"
                :disabled="loading"
                :value="modelValue.bl_number ?? ''"
                placeholder="BL-XXXX-XXXX"
                @input="update('bl_number', ($event.target as HTMLInputElement).value)"
              />
              <FieldDescription>اختياري</FieldDescription>
            </Field>
          </div>

          <Field>
            <FieldLabel for="customs-office">
              الجمارك المختصة
              <span
                v-if="autoFillChip"
                class="inline-block text-xs font-normal bg-primary/10 text-primary border border-border rounded-full px-2 py-0.5 ms-2"
                aria-live="polite"
              >
                تم التعبئة التلقائية
              </span>
            </FieldLabel>
            <Select
              :model-value="modelValue.customs_office || ''"
              :disabled="loading"
              @update:model-value="(val) => update('customs_office', String(val ?? ''))"
            >
              <SelectTrigger id="customs-office">
                <SelectValue placeholder="اختر الجمارك المختصة..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="جمارك عدن">جمارك عدن</SelectItem>
                <SelectItem value="جمارك الحديدة">جمارك الحديدة</SelectItem>
                <SelectItem value="جمارك المكلا">جمارك المكلا</SelectItem>
              </SelectContent>
            </Select>
            <FieldDescription>تُحدَّد تلقائياً بناءً على ميناء الوصول عند الإمكان</FieldDescription>
          </Field>
        </FieldGroup>
      </FieldSet>
    </FieldGroup>
  </div>
</template>
