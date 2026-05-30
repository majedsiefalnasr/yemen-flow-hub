<script setup lang="ts">
import { computed } from 'vue'
import type { WizardStep2Data } from '../../composables/useRequestWizard'
import { ARRIVAL_PORTS } from '../../schemas/wizard.schema'
import { Input } from '../ui/input'
import { Label } from '../ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select'
import { Alert, AlertDescription } from '../ui/alert'
import { AlertTriangle } from 'lucide-vue-next'

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
}>()

function update<K extends keyof WizardStep2Data>(key: K, val: WizardStep2Data[K]): void {
  emit('update:modelValue', { ...props.modelValue, [key]: val })
}

function onPortChange(port: string): void {
  emit('arrival-port-change', port)
}

const countrySearch = computed(() => '')
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

    <h2 class="text-2xl font-bold">بيانات المورد والشحنة</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <!-- اسم المورد -->
      <div class="flex flex-col gap-2">
        <Label for="supplier-name" class="text-sm">
          اسم المورد
          <span class="text-red-700">*</span>
        </Label>
        <Input
          id="supplier-name"
          type="text"
          :disabled="loading"
          :class="{ 'border-destructive': errors.supplier_name }"
          :value="modelValue.supplier_name ?? ''"
          placeholder="مثال: Cargill Trading Inc."
          @input="update('supplier_name', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="errors.supplier_name" class="text-sm text-red-700">{{ errors.supplier_name }}</p>
      </div>

      <!-- بلد المنشأ -->
      <div class="flex flex-col gap-2">
        <Label for="origin-country" class="text-sm">
          بلد المنشأ
          <span class="text-red-700">*</span>
        </Label>
        <Select
          :model-value="modelValue.origin_country || ''"
          :disabled="loading"
          @update:model-value="(val) => update('origin_country', val)"
        >
          <SelectTrigger id="origin-country" :class="{ 'border-destructive': errors.origin_country }">
            <SelectValue placeholder="اختر بلد المنشأ..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.origin_country" class="text-sm text-red-700">{{ errors.origin_country }}</p>
      </div>

      <!-- رقم الفاتورة -->
      <div class="flex flex-col gap-2">
        <Label for="invoice-number" class="text-sm">
          رقم الفاتورة
          <span class="text-red-700">*</span>
        </Label>
        <Input
          id="invoice-number"
          type="text"
          :disabled="loading"
          :class="{ 'border-destructive': errors.invoice_number }"
          :value="modelValue.invoice_number ?? ''"
          placeholder="INV-2025-XXXX"
          @input="update('invoice_number', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="errors.invoice_number" class="text-sm text-red-700">{{ errors.invoice_number }}</p>
      </div>

      <!-- تاريخ الفاتورة -->
      <div class="flex flex-col gap-2">
        <Label for="invoice-date" class="text-sm">
          تاريخ الفاتورة
          <span class="text-red-700">*</span>
        </Label>
        <Input
          id="invoice-date"
          type="date"
          :disabled="loading"
          :class="{ 'border-destructive': errors.invoice_date }"
          :value="modelValue.invoice_date ?? ''"
          @input="update('invoice_date', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="errors.invoice_date" class="text-sm text-red-700">{{ errors.invoice_date }}</p>
      </div>

      <!-- ميناء الوصول -->
      <div class="flex flex-col gap-2">
        <Label for="arrival-port" class="text-sm">
          ميناء الوصول
          <span class="text-red-700">*</span>
        </Label>
        <Select
          :model-value="modelValue.arrival_port || ''"
          :disabled="loading"
          @update:model-value="(val) => { update('arrival_port', val); onPortChange(val) }"
        >
          <SelectTrigger id="arrival-port" :class="{ 'border-destructive': errors.arrival_port }">
            <SelectValue placeholder="اختر ميناء الوصول..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="p in ARRIVAL_PORTS" :key="p" :value="p">{{ p }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.arrival_port" class="text-sm text-red-700">{{ errors.arrival_port }}</p>
      </div>

      <!-- ميناء الشحن (optional) -->
      <div class="flex flex-col gap-2">
        <Label for="shipping-port" class="text-sm">ميناء الشحن</Label>
        <Input
          id="shipping-port"
          type="text"
          :disabled="loading"
          :value="modelValue.shipping_port ?? ''"
          placeholder="Port of Houston, USA"
          @input="update('shipping_port', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- رقم بوليصة الشحن (optional) -->
      <div class="flex flex-col gap-2">
        <Label for="bl-number" class="text-sm">رقم بوليصة الشحن</Label>
        <Input
          id="bl-number"
          type="text"
          :disabled="loading"
          :value="modelValue.bl_number ?? ''"
          placeholder="BL-XXXX-XXXX"
          @input="update('bl_number', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- الجمارك المختصة -->
      <div class="flex flex-col gap-2">
        <Label for="customs-office" class="text-sm">
          الجمارك المختصة
          <span v-if="autoFillChip" class="inline-block text-xs font-normal bg-primary/10 text-primary border border-gray-200 rounded-full px-2 py-1 ms-2" aria-live="polite">تم التعبئة التلقائية</span>
        </Label>
        <Select
          :model-value="modelValue.customs_office || ''"
          :disabled="loading"
          @update:model-value="(val) => update('customs_office', val)"
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
      </div>
    </div>
  </div>
</template>

