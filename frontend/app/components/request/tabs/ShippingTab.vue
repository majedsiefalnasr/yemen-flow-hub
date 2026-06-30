<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { INCOTERM_OPTIONS, PORT_OF_ARRIVAL_OPTIONS } from '@/constants/workflow'
import { Incoterm, PortOfArrival } from '@/types/enums'
import type { RequestFormData } from '@/types/models'

const props = defineProps<{
  modelValue: Partial<RequestFormData>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<RequestFormData>]
}>()

const dateWarning = computed(() => {
  if (!props.modelValue.shipping_date || !props.modelValue.arrival_date) return false
  return props.modelValue.shipping_date > props.modelValue.arrival_date
})

function patch(values: Partial<RequestFormData>) {
  emit('update:modelValue', { ...props.modelValue, ...values })
}
</script>

<template>
  <div class="grid gap-5">
    <div class="grid gap-4 md:grid-cols-2">
      <div class="grid gap-2">
        <Label for="country-of-origin">بلد المنشأ</Label>
        <Input
          id="country-of-origin"
          :model-value="modelValue.country_of_origin ?? ''"
          @update:model-value="(value) => patch({ country_of_origin: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="port-of-loading">ميناء الشحن</Label>
        <Input
          id="port-of-loading"
          :model-value="modelValue.port_of_loading ?? ''"
          @update:model-value="(value) => patch({ port_of_loading: String(value) })"
        />
      </div>
      <div class="grid gap-2">
        <Label>ميناء الوصول</Label>
        <Select
          :model-value="modelValue.port_of_arrival ?? undefined"
          @update:model-value="(value) => patch({ port_of_arrival: value as PortOfArrival })"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر ميناء الوصول" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in PORT_OF_ARRIVAL_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="grid gap-2">
        <Label>شروط الشحن الدولية</Label>
        <Select
          :model-value="modelValue.incoterm ?? undefined"
          @update:model-value="(value) => patch({ incoterm: value as Incoterm })"
        >
          <SelectTrigger>
            <SelectValue placeholder="اختر شرط الشحن" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in INCOTERM_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }} ({{ option.hint }})
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="grid gap-2">
        <Label for="shipping-date">تاريخ الشحن</Label>
        <Input
          id="shipping-date"
          type="date"
          :model-value="modelValue.shipping_date ?? ''"
          @update:model-value="(value) => patch({ shipping_date: String(value) || null })"
        />
      </div>
      <div class="grid gap-2">
        <Label for="arrival-date">تاريخ الوصول المتوقع</Label>
        <Input
          id="arrival-date"
          type="date"
          :model-value="modelValue.arrival_date ?? ''"
          @update:model-value="(value) => patch({ arrival_date: String(value) || null })"
        />
      </div>
      <div class="grid gap-2 md:col-span-2">
        <Label for="final-destination">الوجهة النهائية</Label>
        <Input
          id="final-destination"
          :model-value="modelValue.final_destination ?? ''"
          @update:model-value="(value) => patch({ final_destination: String(value) })"
        />
      </div>
    </div>

    <p v-if="dateWarning" class="flex items-center gap-2 text-sm text-[var(--severity-warning)]">
      <AlertTriangle class="h-4 w-4" />
      تاريخ الشحن يجب أن يكون قبل تاريخ الوصول
    </p>
  </div>
</template>
