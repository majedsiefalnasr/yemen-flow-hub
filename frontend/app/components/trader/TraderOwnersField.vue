<script setup lang="ts">
import { Plus, ShieldCheck, Trash2 } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type { TraderOwner } from '@/types/trader'
import { addTraderOwnerRow, isMajorOwner, removeTraderOwnerRow } from '@/types/trader'

const props = defineProps<{
  modelValue: TraderOwner[]
  errors?: Record<string, string | undefined>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: TraderOwner[]]
}>()

function updateOwner(index: number, patch: Partial<TraderOwner>): void {
  const next = props.modelValue.map((owner, rowIndex) =>
    rowIndex === index ? { ...owner, ...patch } : owner,
  )
  emit('update:modelValue', next)
}

/**
 * Parse a percentage input that may contain Arabic-Indic digits (٠-٩),
 * Eastern Arabic-Indic (۰-۹), or thousands separators. Returns NaN for
 * non-numeric input so an invalid value surfaces as a validation error
 * instead of silently collapsing to 0 (code-review 17-B).
 */
function parsePercentage(raw: unknown): number {
  const text = String(raw ?? '').trim()
  if (text === '') return Number.NaN

  const normalized = text
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[,٬\s]/g, '')

  return /^\d*\.?\d+$/.test(normalized) ? Number(normalized) : Number.NaN
}

function addRow(): void {
  emit('update:modelValue', addTraderOwnerRow(props.modelValue))
}

function removeRow(index: number): void {
  emit('update:modelValue', removeTraderOwnerRow(props.modelValue, index))
}
</script>

<template>
  <Field>
    <div class="flex items-center justify-between gap-3">
      <div>
        <FieldLabel>الملاك</FieldLabel>
        <FieldDescription>المالك بنسبة 25% أو أكثر يتطلب الجنسية ورقم الهوية.</FieldDescription>
      </div>
      <Button type="button" variant="outline" size="sm" @click="addRow">
        <Plus class="me-2 h-4 w-4" aria-hidden="true" />
        إضافة مالك
      </Button>
    </div>

    <div class="mt-3 flex flex-col gap-3">
      <Card
        v-for="(owner, index) in modelValue"
        :key="owner.id ?? index"
        class="border shadow-none"
        :class="isMajorOwner(owner) ? 'border-s-4 border-s-[var(--severity-amber)]' : ''"
      >
        <CardContent class="space-y-3 p-3">
          <div class="flex items-center justify-between gap-3">
            <Badge
              v-if="isMajorOwner(owner)"
              class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"
            >
              <ShieldCheck class="me-1 h-3.5 w-3.5" aria-hidden="true" />
              مالك رئيسي
            </Badge>
            <span v-else class="text-muted-foreground text-sm">مالك عادي</span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              :aria-label="`حذف المالك ${index + 1}`"
              @click="removeRow(index)"
            >
              <Trash2 class="h-4 w-4" aria-hidden="true" />
            </Button>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-1">
              <FieldLabel :for="`owner-name-${index}`">اسم المالك</FieldLabel>
              <Input
                :id="`owner-name-${index}`"
                :model-value="owner.full_name"
                placeholder="أدخل اسم المالك"
                @update:model-value="updateOwner(index, { full_name: String($event) })"
              />
              <p v-if="errors?.[`owners.${index}.full_name`]" class="text-destructive text-sm">
                {{ errors[`owners.${index}.full_name`] }}
              </p>
            </div>

            <div class="space-y-1">
              <FieldLabel :for="`owner-percentage-${index}`">نسبة الملكية</FieldLabel>
              <Input
                :id="`owner-percentage-${index}`"
                type="number"
                min="0"
                max="100"
                step="0.01"
                :model-value="owner.ownership_percentage"
                placeholder="0"
                @update:model-value="
                  updateOwner(index, { ownership_percentage: parsePercentage($event) })
                "
              />
              <p
                v-if="errors?.[`owners.${index}.ownership_percentage`]"
                class="text-destructive text-sm"
              >
                {{ errors[`owners.${index}.ownership_percentage`] }}
              </p>
            </div>

            <div class="space-y-1">
              <FieldLabel :for="`owner-nationality-${index}`">
                الجنسية
                <span v-if="isMajorOwner(owner)" class="text-destructive">*</span>
              </FieldLabel>
              <Input
                :id="`owner-nationality-${index}`"
                :model-value="owner.nationality ?? ''"
                placeholder="مثال: يمني"
                @update:model-value="updateOwner(index, { nationality: String($event) })"
              />
              <p v-if="errors?.[`owners.${index}.nationality`]" class="text-destructive text-sm">
                {{ errors[`owners.${index}.nationality`] }}
              </p>
            </div>

            <div class="space-y-1">
              <FieldLabel :for="`owner-identification-${index}`">
                رقم الهوية
                <span v-if="isMajorOwner(owner)" class="text-destructive">*</span>
              </FieldLabel>
              <Input
                :id="`owner-identification-${index}`"
                :model-value="owner.identification_number ?? ''"
                placeholder="أدخل رقم الهوية"
                @update:model-value="updateOwner(index, { identification_number: String($event) })"
              />
              <p
                v-if="errors?.[`owners.${index}.identification_number`]"
                class="text-destructive text-sm"
              >
                {{ errors[`owners.${index}.identification_number`] }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </Field>
</template>
