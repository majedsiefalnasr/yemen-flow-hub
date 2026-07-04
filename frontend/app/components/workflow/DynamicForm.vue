<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import type { ResolvedFieldGroup, ResolvedFieldDefinition } from '@/types/models'
import { buildDynamicSchema } from '@/composables/useDynamicFormSchema'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'
import DynamicFormField from '@/components/workflow/DynamicFormField.vue'
import { Input } from '@/components/ui/input'
import { Field, FieldLabel, FieldError } from '@/components/ui/field'

const props = defineProps<{
  fieldGroups: ResolvedFieldGroup[]
  modelValue: Record<string, unknown>
  mode: 'edit' | 'readonly'
  requestId?: number
}>()

const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>] }>()

const schema = computed(() => toTypedSchema(buildDynamicSchema(props.fieldGroups)))
const form = useForm({ validationSchema: schema, initialValues: props.modelValue })

const { upload } = useEngineRequestDocuments()

function effectiveField(field: ResolvedFieldDefinition): ResolvedFieldDefinition {
  if (props.mode === 'readonly') {
    return { ...field, is_editable: false }
  }
  return field
}

function fieldValue(key: string): unknown {
  return form.values[key]
}

function setFieldValue(key: string, value: unknown) {
  form.setFieldValue(key, value)
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

async function uploadFile(field: ResolvedFieldDefinition, file: File) {
  if (props.requestId === undefined) return
  const doc = await upload(props.requestId, file, field.id)
  const current = (fieldValue(field.key) as number[]) ?? []
  setFieldValue(field.key, [...current, doc.id])
}

async function validate(): Promise<{ valid: boolean; values: Record<string, unknown> }> {
  const result = await form.validate()
  return { valid: result.valid, values: form.values as Record<string, unknown> }
}

defineExpose({ validate })
</script>

<template>
  <div class="flex flex-col gap-6 py-2">
    <div v-for="group in fieldGroups" :key="group.id" class="flex flex-col gap-4">
      <h3 class="text-foreground text-sm font-semibold">{{ group.label }}</h3>
      <template v-for="field in group.fields" :key="field.id">
        <DynamicFormField
          v-if="field.is_visible && field.type !== 'FILE'"
          :field="effectiveField(field)"
          :model-value="fieldValue(field.key)"
          :error="form.errors.value[field.key]"
          @update:model-value="(value) => setFieldValue(field.key, value)"
        />
        <Field v-else-if="field.is_visible && field.type === 'FILE'">
          <FieldLabel :for="field.key">
            {{ field.label }}
            <span v-if="field.is_required" aria-hidden="true"> *</span>
          </FieldLabel>
          <Input
            :id="field.key"
            type="file"
            accept="application/pdf"
            :disabled="mode === 'readonly' || !field.is_editable"
            @change="
              (event: Event) => {
                const input = event.target as HTMLInputElement
                if (input.files?.[0]) uploadFile(field, input.files[0])
              }
            "
          />
          <FieldError v-if="form.errors.value[field.key]">
            {{ form.errors.value[field.key] }}
          </FieldError>
        </Field>
      </template>
    </div>
  </div>
</template>
