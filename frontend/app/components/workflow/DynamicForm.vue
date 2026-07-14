<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import type { ResolvedFieldGroup, ResolvedFieldDefinition } from '@/types/models'
import { buildDynamicSchema } from '@/composables/useDynamicFormSchema'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'
import { useMerchantAutofill } from '@/composables/useMerchantAutofill'
import { findFieldKeyBySemanticTag } from '@/utils/findFieldKeyBySemanticTag'
import DynamicFormField from '@/components/workflow/DynamicFormField.vue'
import { Input } from '@/components/ui/input'
import { Field, FieldLabel, FieldError } from '@/components/ui/field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { AlertTriangle } from 'lucide-vue-next'

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

// Errors from the Zod schema populate form.errors as soon as the form is
// built, before the user touches anything. Only surface an error once its
// field has been edited, or once the user has attempted to validate the
// step (Next/Review/Submit) — never on initial render.
const touchedFields = reactive(new Set<string>())
const attemptedValidate = ref(false)

function fieldError(key: string): string | undefined {
  if (!touchedFields.has(key) && !attemptedValidate.value) return undefined
  return form.errors.value[key]
}

function fieldValue(key: string): unknown {
  return form.values[key]
}

async function setFieldValue(key: string, value: unknown) {
  touchedFields.add(key)
  form.setFieldValue(key, value)
  await form.validateField(key)
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

// When the user manually resolves a multi-company match, keep the autofill
// composable's selection (and therefore commercial_registration_number) in
// sync with the field the wizard actually submits.
function onCompanyFieldChange(value: unknown) {
  const numeric = typeof value === 'number' ? value : Number(value)
  merchantAutofill.selectedCompanyId.value = Number.isFinite(numeric) ? numeric : null
}

async function uploadFile(field: ResolvedFieldDefinition, file: File) {
  if (props.requestId === undefined) return
  const doc = await upload(props.requestId, file, field.id)
  const current = (fieldValue(field.key) as number[]) ?? []
  setFieldValue(field.key, [...current, doc.id])
}

// Merchant tax-number lookup + autofill (tax-number search / merchant
// autofill feature). Resolved purely by semantic tag, same pattern as the
// financing ledger bar — stays inert for any workflow schema that doesn't
// tag these fields, never hardcodes a field key or workflow id.
const taxNumberKey = computed(() =>
  findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_TAX_NUMBER'),
)
const merchantIdKey = computed(() => findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_ID'))
const companyIdKey = computed(() =>
  findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_COMPANY_ID'),
)
const taxCardExpiryKey = computed(() =>
  findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_TAX_CARD_EXPIRY'),
)
const commercialRegNumberKey = computed(() =>
  findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER'),
)
const commercialRegExpiryKey = computed(() =>
  findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY'),
)
const ownersKey = computed(() => findFieldKeyBySemanticTag(props.fieldGroups, 'MERCHANT_OWNERS'))
const hasMerchantAutofill = computed(
  () =>
    taxNumberKey.value !== null &&
    (merchantIdKey.value !== null ||
      companyIdKey.value !== null ||
      taxCardExpiryKey.value !== null ||
      commercialRegNumberKey.value !== null ||
      commercialRegExpiryKey.value !== null ||
      ownersKey.value !== null),
)
const merchantAutofillKeys = computed(
  () =>
    new Set([
      merchantIdKey.value,
      companyIdKey.value,
      taxCardExpiryKey.value,
      commercialRegNumberKey.value,
      commercialRegExpiryKey.value,
      ownersKey.value,
    ]),
)

const taxNumberValue = computed(() => {
  if (!taxNumberKey.value) return null
  const value = form.values[taxNumberKey.value]
  return typeof value === 'string' ? value : null
})

const merchantAutofill = useMerchantAutofill(taxNumberValue)

watch(
  () => merchantAutofill.autofillValues.value,
  (values) => {
    if (!hasMerchantAutofill.value) return
    const patch: Record<string, unknown> = {}
    if (merchantIdKey.value) patch[merchantIdKey.value] = values.merchantId ?? undefined
    if (companyIdKey.value) patch[companyIdKey.value] = values.companyId ?? undefined
    if (taxCardExpiryKey.value) patch[taxCardExpiryKey.value] = values.taxCardExpiry ?? undefined
    if (commercialRegNumberKey.value) {
      patch[commercialRegNumberKey.value] = values.commercialRegistrationNumber ?? undefined
    }
    if (commercialRegExpiryKey.value) {
      patch[commercialRegExpiryKey.value] = values.commercialRegistrationExpiry ?? undefined
    }
    if (ownersKey.value) patch[ownersKey.value] = values.ownersText ?? undefined
    for (const key of Object.keys(patch)) touchedFields.add(key)
    // One setValues() call, not four sequential setFieldValue() calls: vee-
    // validate's schema validation is debounced and shared across the whole
    // form, so four separate calls would each schedule their own validation
    // pass against a form.values snapshot that hadn't yet absorbed the other
    // three — an earlier pass resolving after a later one could leave a
    // stale "required" error on an already-filled field. setValues() applies
    // the patch atomically and triggers exactly one validation pass.
    form.setValues(patch)
    emit('update:modelValue', { ...props.modelValue, ...(form.values as Record<string, unknown>) })
  },
  { deep: true },
)

function merchantCompanyOptions(): Array<{ value: string | number; label: string }> {
  return merchantAutofill.companies.value.map((c) => ({ value: c.id, label: c.name }))
}

// Derived read-only, not a mutation of the Designer's is_editable metadata:
// matched merchant master-data fields lock while the match is active, and
// the company field locks specifically once auto-selected (single company).
function effectiveField(field: ResolvedFieldDefinition): ResolvedFieldDefinition {
  if (props.mode === 'readonly') {
    return { ...field, is_editable: false }
  }
  if (hasMerchantAutofill.value && merchantAutofillKeys.value.has(field.key)) {
    const isCompanyField = field.key === companyIdKey.value
    if (isCompanyField && merchantAutofill.status.value === 'matched') {
      return {
        ...field,
        is_editable: merchantAutofill.requiresCompanyChoice.value,
        dynamic_options: merchantCompanyOptions(),
      }
    }
    return { ...field, is_editable: !merchantAutofill.isMasterDataReadOnly.value }
  }
  return field
}

async function validate(): Promise<{ valid: boolean; values: Record<string, unknown> }> {
  attemptedValidate.value = true
  const result = await form.validate()
  if (hasMerchantAutofill.value && merchantAutofill.blocksContinue.value) {
    return { valid: false, values: form.values as Record<string, unknown> }
  }
  return { valid: result.valid, values: form.values as Record<string, unknown> }
}

defineExpose({ validate })
</script>

<template>
  <div class="flex flex-col gap-6 py-2">
    <div v-for="group in fieldGroups" :key="group.id" class="flex flex-col gap-4">
      <h3 class="text-foreground text-sm font-semibold">{{ group.label }}</h3>
      <template v-for="field in group.fields" :key="field.id">
        <template v-if="hasMerchantAutofill && field.key === taxNumberKey">
          <DynamicFormField
            v-if="field.is_visible"
            :field="effectiveField(field)"
            :model-value="fieldValue(field.key)"
            :error="fieldError(field.key)"
            @update:model-value="(value) => setFieldValue(field.key, value)"
          />
          <p
            v-if="merchantAutofill.status.value === 'loading'"
            class="text-muted-foreground flex items-center gap-2 text-xs"
          >
            <Spinner class="h-3.5 w-3.5" />
            جارٍ البحث…
          </p>
          <Card
            v-else-if="merchantAutofill.status.value === 'no_match'"
            class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
            role="status"
          >
            <CardContent class="flex items-center gap-3 pt-4 pb-4">
              <AlertTriangle
                class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]"
                aria-hidden="true"
              />
              <p class="text-foreground min-w-0 flex-1 text-sm">
                {{ merchantAutofill.noMatchMessage }}
              </p>
            </CardContent>
          </Card>
          <Alert
            v-else-if="merchantAutofill.status.value === 'error'"
            variant="destructive"
            role="alert"
          >
            <AlertTriangle class="h-4 w-4" />
            <AlertDescription>{{ merchantAutofill.errorMessage }}</AlertDescription>
          </Alert>
        </template>
        <DynamicFormField
          v-else-if="field.is_visible && field.type !== 'FILE'"
          :field="effectiveField(field)"
          :model-value="fieldValue(field.key)"
          :error="fieldError(field.key)"
          @update:model-value="
            (value) => {
              setFieldValue(field.key, value)
              if (hasMerchantAutofill && field.key === companyIdKey) onCompanyFieldChange(value)
            }
          "
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
          <FieldError v-if="fieldError(field.key)">
            {{ fieldError(field.key) }}
          </FieldError>
        </Field>
      </template>
    </div>
  </div>
</template>
