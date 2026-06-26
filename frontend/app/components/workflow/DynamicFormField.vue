<script setup lang="ts">
import { computed } from 'vue'
import type { ResolvedFieldDefinition } from '@/types/models'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { Field, FieldLabel, FieldError, FieldDescription } from '@/components/ui/field'

const props = defineProps<{
  field: ResolvedFieldDefinition
  modelValue: unknown
  error?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const selectOptions = computed(() => {
  if (props.field.type === 'DYNAMIC_SELECT') {
    return props.field.dynamic_options ?? []
  }
  return props.field.options ?? []
})

function onInput(value: unknown) {
  emit('update:modelValue', value)
}
</script>

<template>
  <Field>
    <FieldLabel :for="field.key">
      {{ field.label }}
      <span v-if="field.is_required" aria-hidden="true"> *</span>
    </FieldLabel>

    <Input
      v-if="field.type === 'TEXT'"
      :id="field.key"
      :model-value="(modelValue as string) ?? ''"
      :placeholder="field.placeholder ?? undefined"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    />

    <Textarea
      v-else-if="field.type === 'TEXTAREA'"
      :id="field.key"
      :model-value="(modelValue as string) ?? ''"
      :placeholder="field.placeholder ?? undefined"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    />

    <Input
      v-else-if="field.type === 'NUMBER' || field.type === 'CURRENCY'"
      :id="field.key"
      type="number"
      :model-value="String((modelValue as number) ?? '')"
      :placeholder="field.placeholder ?? undefined"
      :disabled="!field.is_editable"
      @update:model-value="(v: string | number) => onInput(v === '' ? undefined : Number(v))"
    />

    <Input
      v-else-if="field.type === 'DATE'"
      :id="field.key"
      type="date"
      :model-value="(modelValue as string) ?? ''"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    />

    <Select
      v-else-if="field.type === 'SELECT' || field.type === 'DYNAMIC_SELECT'"
      :model-value="String(modelValue ?? '')"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    >
      <SelectTrigger :id="field.key">
        <SelectValue :placeholder="field.placeholder ?? 'اختر…'" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="option in selectOptions"
          :key="String(option.value)"
          :value="String(option.value)"
        >
          {{ option.label }}
        </SelectItem>
      </SelectContent>
    </Select>

    <div v-else-if="field.type === 'CHECKBOX'" class="flex items-center gap-2">
      <Checkbox
        :id="field.key"
        :checked="(modelValue as boolean) ?? false"
        :disabled="!field.is_editable"
        @update:checked="onInput"
      />
    </div>

    <FieldDescription v-if="field.help_text">{{ field.help_text }}</FieldDescription>
    <FieldError v-if="error">{{ error }}</FieldError>
  </Field>
</template>
