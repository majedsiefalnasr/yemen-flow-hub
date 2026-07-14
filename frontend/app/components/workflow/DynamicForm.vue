<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import type {
  ResolvedFieldGroup,
  ResolvedFieldDefinition,
  UploadLifecycleState,
} from '@/types/models'
import { buildDynamicSchema } from '@/composables/useDynamicFormSchema'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'
import { useTemporaryUploadLifecycle } from '@/composables/useTemporaryUploadLifecycle'
import { useMerchantAutofill } from '@/composables/useMerchantAutofill'
import { findFieldKeyBySemanticTag } from '@/utils/findFieldKeyBySemanticTag'
import DynamicFormField from '@/components/workflow/DynamicFormField.vue'
import { Input } from '@/components/ui/input'
import { Field, FieldLabel, FieldError } from '@/components/ui/field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { AlertTriangle, CheckCircle2, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

/**
 * FILE fields upload against one of two targets:
 * - 'request': an existing EngineRequest (view/act page) — POSTs to
 *   /engine-requests/{id}/documents, stores real document ids in the field.
 * - 'temporary': the pre-submission wizard, where no EngineRequest exists
 *   yet — POSTs to /temporary-uploads, stores opaque tokens in the field.
 *   The wizard's final atomic submit sends those tokens as upload_tokens and
 *   they never appear in engine_requests.data itself.
 */
export type UploadTarget =
  | { type: 'request'; requestId: number }
  | { type: 'temporary'; workflowVersionId: number; uploadSessionToken: string }

const props = defineProps<{
  fieldGroups: ResolvedFieldGroup[]
  modelValue: Record<string, unknown>
  mode: 'edit' | 'readonly'
  requestId?: number
  uploadTarget?: UploadTarget
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown>]
  'upload-tokens-change': [tokens: string[]]
  /**
   * True while any temporary upload in this form is not yet 'clean' —
   * uploading, scanning, or stuck in a terminal error (infected/failed/
   * upload_error) that the user hasn't removed yet.
   */
  'upload-pending-change': [pending: boolean]
}>()

const schema = computed(() => toTypedSchema(buildDynamicSchema(props.fieldGroups)))
// validateOnMount resolves errors against the schema immediately so the
// wizard can read `meta.valid` live to pre-disable Next (see currentStepValid
// below). Error *text* is still suppressed until a field is touched or the
// user attempts to advance (see fieldError).
const form = useForm({
  validationSchema: schema,
  initialValues: props.modelValue,
  validateOnMount: true,
})

const { upload } = useEngineRequestDocuments()
const uploadLifecycle = useTemporaryUploadLifecycle()

// Reactive pass/fail for the current step. vee-validate keeps `meta.valid`
// up to date as values change once validateOnMount has primed it. The
// merchant-autofill guard mirrors `validate()` — a pending/required merchant
// match blocks advancing regardless of field values. A non-clean upload also
// blocks advancing even on an OPTIONAL field: its token only enters the
// field value once clean (see the watcher below), so an infected/failed
// upload on an optional field would otherwise leave the field value empty —
// which validates fine on its own — and silently let the user skip past a
// rejected file instead of forcing them to remove or retry it.
const currentStepValid = computed(
  () =>
    form.meta.value.valid === true &&
    (!hasMerchantAutofill.value || !merchantAutofill.blocksContinue.value) &&
    !uploadLifecycle.hasBlockingUpload(),
)

// Recompute and emit whenever any tracked upload's lifecycle state changes —
// the wizard needs both the current clean-token list (for the final submit
// payload) and whether anything is still unresolved (to gate Next/Submit).
watch(
  () => [...uploadLifecycle.entries.values()].map((e) => e.state),
  () => {
    emit('upload-tokens-change', uploadLifecycle.cleanTokens())
    emit('upload-pending-change', uploadLifecycle.hasBlockingUpload())
  },
  { deep: true },
)

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
  const target = props.uploadTarget
  if (target?.type === 'temporary') {
    // The field's own form value is intentionally NOT set here: the backend
    // bijection-checks upload_tokens against every token referenced inside
    // data[fieldKey], so a token must never enter the submitted field value
    // before its scan resolves to 'clean' (see the watcher below). Upload
    // progress/errors render from uploadLifecycle.entryFor(), not the form
    // value, in the meantime.
    await uploadLifecycle.uploadAndTrack(
      field.key,
      file,
      target.workflowVersionId,
      field.id,
      target.uploadSessionToken,
    )
    return
  }
  if (props.requestId === undefined) return
  const doc = await upload(props.requestId, file, field.id)
  const current = (fieldValue(field.key) as number[]) ?? []
  setFieldValue(field.key, [...current, doc.id])
}

function removeTemporaryUpload(field: ResolvedFieldDefinition) {
  uploadLifecycle.removeEntry(field.key)
  setFieldValue(field.key, [])
}

function uploadEntry(fieldKey: string) {
  return uploadLifecycle.entryFor(fieldKey)
}

const UPLOAD_STATUS_TEXT: Record<UploadLifecycleState, (fileName: string) => string> = {
  uploading: (name) => `جارٍ رفع ${name}…`,
  scan_pending: (name) => `جارٍ فحص ${name}…`,
  clean: (name) => `${name} — تم الفحص بنجاح`,
  infected: (name) => `${name} — مرفوض`,
  failed: (name) => `${name} — تعذّر الفحص`,
  upload_error: (name) => `${name} — تعذّر الرفع`,
}

function uploadStatusText(entry: { state: UploadLifecycleState; fileName: string }): string {
  return UPLOAD_STATUS_TEXT[entry.state](entry.fileName)
}

// Once a temporary upload resolves to 'clean', its token becomes the actual
// submitted field value — never sooner (see uploadFile above), and it's
// cleared again if the entry moves away from 'clean' (defensive; the
// composable never regresses a terminal state, but keeps the field value
// honest if it ever did).
watch(
  () => [...uploadLifecycle.entries.values()].map((e) => `${e.fieldKey}:${e.state}:${e.token}`),
  () => {
    for (const entry of uploadLifecycle.entries.values()) {
      const current = (fieldValue(entry.fieldKey) as string[]) ?? []
      if (entry.state === 'clean' && entry.token !== null) {
        if (!current.includes(entry.token)) {
          setFieldValue(entry.fieldKey, [...current, entry.token])
        }
      } else if (current.length > 0) {
        setFieldValue(entry.fieldKey, [])
      }
    }
  },
)

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
  if (uploadLifecycle.hasBlockingUpload()) {
    return { valid: false, values: form.values as Record<string, unknown> }
  }
  return { valid: result.valid, values: form.values as Record<string, unknown> }
}

defineExpose({ validate, currentStepValid })
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
            v-if="!uploadEntry(field.key) || uploadEntry(field.key)?.state === 'upload_error'"
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
          <div v-if="uploadEntry(field.key)" class="flex items-center gap-2 text-sm" role="status">
            <Spinner
              v-if="['uploading', 'scan_pending'].includes(uploadEntry(field.key)!.state)"
              class="h-3.5 w-3.5"
            />
            <CheckCircle2
              v-else-if="uploadEntry(field.key)!.state === 'clean'"
              class="h-4 w-4 text-[var(--severity-green)]"
              aria-hidden="true"
            />
            <AlertTriangle v-else class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
            <span class="text-muted-foreground min-w-0 flex-1 truncate">
              {{ uploadStatusText(uploadEntry(field.key)!) }}
            </span>
            <Button
              v-if="mode === 'edit' && field.is_editable"
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label="إزالة الملف"
              @click="removeTemporaryUpload(field)"
            >
              <X class="h-3.5 w-3.5" />
            </Button>
          </div>
          <FieldError v-if="uploadEntry(field.key)?.errorMessage">
            {{ uploadEntry(field.key)?.errorMessage }}
          </FieldError>
          <FieldError v-else-if="fieldError(field.key)">
            {{ fieldError(field.key) }}
          </FieldError>
        </Field>
      </template>
    </div>
  </div>
</template>
