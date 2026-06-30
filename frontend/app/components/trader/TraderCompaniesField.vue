<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type { TraderCompany } from '@/types/trader'
import { addTraderCompanyRow, removeTraderCompanyRow } from '@/types/trader'

const props = defineProps<{
  modelValue: TraderCompany[]
  errors?: Record<string, string | undefined>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: TraderCompany[]]
}>()

function updateCompany(index: number, value: string): void {
  const next = props.modelValue.map((company, rowIndex) =>
    rowIndex === index ? { ...company, company_name: value } : company,
  )
  emit('update:modelValue', next)
}

function addRow(): void {
  emit('update:modelValue', addTraderCompanyRow(props.modelValue))
}

function removeRow(index: number): void {
  emit('update:modelValue', removeTraderCompanyRow(props.modelValue, index))
}
</script>

<template>
  <Field>
    <div class="flex items-center justify-between gap-3">
      <div>
        <FieldLabel>الشركات المرتبطة</FieldLabel>
        <FieldDescription>يمكن إضافة أكثر من شركة مرتبطة بالتاجر.</FieldDescription>
      </div>
      <Button type="button" variant="outline" size="sm" @click="addRow">
        <Plus class="me-2 h-4 w-4" aria-hidden="true" />
        إضافة شركة
      </Button>
    </div>

    <div class="mt-3 flex flex-col gap-3">
      <Card
        v-for="(company, index) in modelValue"
        :key="company.id ?? index"
        class="border shadow-none"
      >
        <CardContent class="flex items-start gap-3 p-3">
          <div class="flex-1 space-y-1">
            <FieldLabel :for="`company-${index}`">اسم الشركة</FieldLabel>
            <Input
              :id="`company-${index}`"
              :model-value="company.company_name"
              placeholder="أدخل اسم الشركة"
              @update:model-value="updateCompany(index, String($event))"
            />
            <p v-if="errors?.[`companies.${index}.company_name`]" class="text-destructive text-sm">
              {{ errors[`companies.${index}.company_name`] }}
            </p>
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :aria-label="`حذف الشركة ${index + 1}`"
            @click="removeRow(index)"
          >
            <Trash2 class="h-4 w-4" aria-hidden="true" />
          </Button>
        </CardContent>
      </Card>
    </div>
  </Field>
</template>
