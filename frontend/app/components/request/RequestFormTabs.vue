<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import * as z from 'zod'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import BasicInfoTab from '@/components/request/tabs/BasicInfoTab.vue'
import DocumentsTab from '@/components/request/tabs/DocumentsTab.vue'
import InvoiceTab from '@/components/request/tabs/InvoiceTab.vue'
import ShippingTab from '@/components/request/tabs/ShippingTab.vue'
import WorkflowHistoryTab from '@/components/request/tabs/WorkflowHistoryTab.vue'
import CorrectionBanner from '@/components/banners/CorrectionBanner.vue'
import { CoverageType, RequestStatus } from '@/types/enums'
import type { ImportRequest, RequestFormData } from '@/types/models'
import { FINANCING_ADVISORY_MESSAGE } from '@/composables/useFinancingLedger'
import { useRequestsStore } from '@/stores/requests.store'
import { extractApiFieldErrors } from '@/utils/apiErrors'

/** Supported transaction currencies (must match backend `currency` whitelist). */
const SUPPORTED_CURRENCIES = ['USD', 'EUR', 'SAR', 'AED', 'CNY'] as const

/**
 * Zod schema for the new-model request form (architecture: VeeValidate + Zod
 * + backend FormRequest). Validates across all tabs; tab validators below run
 * focused slices of it and surface inline errors (code-review 17-C decision #5).
 */
const requestFormSchema = z
  .object({
    trader_id: z.number({ message: 'التاجر مطلوب' }).int().positive('التاجر مطلوب'),
    request_type: z.string().min(1, 'نوع الطلب مطلوب'),
    currency_source: z.string().min(1, 'مصدر العملة مطلوب'),
    payment_terms_mode: z.string().min(1, 'طريقة الدفع مطلوبة'),
    coverage_type: z.nativeEnum(CoverageType).nullable().optional(),
    request_percentage: z.coerce.number().gt(0).max(100).nullable().optional(),
    request_currency: z.enum(SUPPORTED_CURRENCIES).nullable().optional(),
    invoice_currency: z.enum(SUPPORTED_CURRENCIES).nullable().optional(),
    requested_amount: z.coerce.number().positive().nullable().optional(),
    total_invoice_amount: z.coerce.number().positive().nullable().optional(),
  })
  .superRefine((data, ctx) => {
    if (data.coverage_type === CoverageType.FULL && Number(data.request_percentage) !== 100) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['request_percentage'],
        message: 'التغطية الكاملة تتطلب نسبة 100%',
      })
    }
    if (
      data.coverage_type === CoverageType.PARTIAL &&
      !(Number(data.request_percentage) >= 5 && Number(data.request_percentage) < 100)
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['request_percentage'],
        message: 'التغطية الجزئية تتطلب نسبة بين 5 و أقل من 100',
      })
    }
  })

const props = defineProps<{
  requestId?: number | null
  initialValues?: ImportRequest | null
}>()

const emit = defineEmits<{
  dirty: []
  clean: []
  submitted: []
}>()

const router = useRouter()
const requestsStore = useRequestsStore()
const activeTab = ref('basic')
const visitedTabs = ref(new Set(['basic']))

const values = reactive<Partial<RequestFormData>>(defaultValues())
const documentsComplete = ref(false)
const declarationAccepted = ref(false)
const financingAdvisoryBlocked = ref(false)
const formErrors = ref<Record<string, string | undefined>>({})
// Suppress the spurious `dirty` emit caused by the immediate initialValues
// watcher assigning into `values` on mount (code-review 17-C).
let hydrating = true

const tabs = [
  { value: 'basic', label: 'بيانات أساسية' },
  { value: 'invoice', label: 'بيانات الفاتورة' },
  { value: 'shipping', label: 'بيانات الشحن' },
  { value: 'documents', label: 'الوثائق' },
  { value: 'history', label: 'سجل سير العمل' },
]

const isEditMode = computed(() => props.requestId != null)
const isReturnedCorrection = computed(() =>
  [
    RequestStatus.BANK_RETURNED,
    RequestStatus.SUPPORT_RETURNED,
    RequestStatus.DRAFT_REJECTED_INTERNAL,
  ].includes(props.initialValues?.status as RequestStatus),
)
const correctionVariant = computed(() => {
  switch (props.initialValues?.status) {
    case RequestStatus.BANK_RETURNED:
      return 'bank_returned' as const
    case RequestStatus.SUPPORT_RETURNED:
      return 'support_returned' as const
    case RequestStatus.DRAFT_REJECTED_INTERNAL:
      return 'draft_rejected' as const
    default:
      return undefined
  }
})

watch(
  () => props.initialValues,
  (next) => {
    hydrating = true
    Object.assign(values, defaultValues(next ?? null))
    // When editing an existing request, all tabs hold data and must be
    // directly reachable (do not trap behind sequential visiting).
    if (props.requestId != null || next?.id != null) {
      visitedTabs.value = new Set(tabs.map((tab) => tab.value))
    }
    // Release the dirty guard after the assignment settles.
    void Promise.resolve().then(() => {
      hydrating = false
    })
  },
  { immediate: true },
)

watch(
  values,
  () => {
    if (hydrating) return
    emit('dirty')
  },
  { deep: true },
)

watch(activeTab, (tab) => visitedTabs.value.add(tab))

function defaultValues(source?: ImportRequest | null): Partial<RequestFormData> {
  return {
    merchant_id: source?.merchant?.id ?? null,
    currency: source?.currency ?? 'USD',
    amount: source?.amount ?? 1,
    supplier_name: source?.supplier_name ?? null,
    goods_description: source?.goods_description ?? null,
    port_of_entry: source?.port_of_entry ?? null,
    notes: source?.notes ?? null,
    goods_type: source?.goods_type ?? null,
    payment_terms: source?.payment_terms ?? null,
    due_date: source?.due_date ?? null,
    invoice_number: source?.invoice_number ?? null,
    invoice_date: source?.invoice_date ?? null,
    origin_country: source?.origin_country ?? null,
    arrival_port: source?.arrival_port ?? null,
    shipping_port: source?.shipping_port ?? null,
    customs_office: source?.customs_office ?? null,
    bl_number: source?.bl_number ?? null,
    trader_id: source?.trader_id ?? null,
    request_type: source?.request_type ?? null,
    currency_source: source?.currency_source ?? null,
    payment_terms_mode: source?.payment_terms_mode ?? null,
    trader_snapshot_name: source?.trader_snapshot_name ?? null,
    trader_snapshot_tax_number: source?.trader_snapshot_tax_number ?? null,
    trader_snapshot_tax_card_expiry: source?.trader_snapshot_tax_card_expiry ?? null,
    trader_snapshot_commercial_registration_number:
      source?.trader_snapshot_commercial_registration_number ?? null,
    trader_snapshot_commercial_registration_expiry:
      source?.trader_snapshot_commercial_registration_expiry ?? null,
    coverage_type: source?.coverage_type ?? null,
    request_percentage: source?.request_percentage ?? null,
    request_currency: source?.request_currency ?? null,
    requested_amount: source?.requested_amount ?? null,
    invoice_type: source?.invoice_type ?? null,
    invoice_currency: source?.invoice_currency ?? null,
    unit_of_measure: source?.unit_of_measure ?? null,
    total_invoice_amount: source?.total_invoice_amount ?? null,
    commodity: source?.commodity ?? null,
    exporting_company_name: source?.exporting_company_name ?? null,
    exporting_company_location: source?.exporting_company_location ?? null,
    country_of_origin: source?.country_of_origin ?? null,
    port_of_loading: source?.port_of_loading ?? null,
    port_of_arrival: source?.port_of_arrival ?? null,
    incoterm: source?.incoterm ?? null,
    final_destination: source?.final_destination ?? null,
    shipping_date: source?.shipping_date ?? null,
    arrival_date: source?.arrival_date ?? null,
  }
}

function setValues(next: Partial<RequestFormData>) {
  Object.assign(values, next)
}

/** Validate one or more schema fields; record inline errors; return validity. */
function validateFields(fields: string[]): boolean {
  const result = requestFormSchema.safeParse(values)
  const next: Record<string, string | undefined> = { ...formErrors.value }
  for (const field of fields) next[field] = undefined
  let valid = true

  if (!result.success) {
    for (const issue of result.error.issues) {
      const field = String(issue.path[0] ?? '')
      if (fields.includes(field)) {
        next[field] ??= issue.message
        valid = false
      }
    }
  }

  formErrors.value = next
  return valid
}

function validateBasicTab(): boolean {
  return validateFields(['trader_id', 'request_type', 'currency_source', 'payment_terms_mode'])
}

function validateInvoiceTab(): boolean {
  return validateFields([
    'coverage_type',
    'request_percentage',
    'request_currency',
    'invoice_currency',
    'requested_amount',
  ])
}

function goNext() {
  if (activeTab.value === 'basic' && !validateBasicTab()) {
    toast.error('يرجى إكمال الحقول المطلوبة في هذه الخطوة.')
    return
  }
  if (activeTab.value === 'invoice' && !validateInvoiceTab()) {
    toast.error('يرجى تصحيح بيانات الفاتورة قبل المتابعة.')
    return
  }
  const index = tabs.findIndex((tab) => tab.value === activeTab.value)
  activeTab.value = tabs[Math.min(index + 1, tabs.length - 1)]?.value ?? 'basic'
}

function goPrevious() {
  const index = tabs.findIndex((tab) => tab.value === activeTab.value)
  activeTab.value = tabs[Math.max(index - 1, 0)]?.value ?? 'basic'
}

/** Surface backend validation errors inline and route to the first error tab. */
function handleSaveError(err: unknown, fallback: string): void {
  const fieldErrors = extractApiFieldErrors(err)
  if (Object.keys(fieldErrors).length > 0) {
    formErrors.value = { ...formErrors.value, ...fieldErrors }
    const first = Object.keys(fieldErrors)[0] ?? ''
    if (
      ['request_percentage', 'coverage_type', 'request_currency'].some((f) => first.includes(f))
    ) {
      activeTab.value = 'invoice'
    } else if (['trader', 'request_type', 'payment'].some((f) => first.includes(f))) {
      activeTab.value = 'basic'
    }
  }
  toast.error(fallback)
}

async function saveDraft() {
  const payload = buildPayload()
  try {
    if (isEditMode.value && props.requestId) {
      await requestsStore.updateRequest(props.requestId, payload)
    } else {
      const id = await requestsStore.createRequest(payload)
      await router.push(`/requests/${id}/edit`)
    }
    emit('clean')
    toast.success('تم حفظ المسودة')
  } catch (err) {
    handleSaveError(err, 'تعذّر حفظ المسودة. حاول مجدداً.')
  }
}

function extractErrorCode(err: unknown): string | null {
  if (typeof err !== 'object' || err === null) return null
  const data = (err as { data?: { error_code?: string } }).data
  return data?.error_code ?? null
}

async function submitForReview() {
  if (!validateBasicTab()) {
    activeTab.value = 'basic'
    toast.error('يرجى إكمال الحقول المطلوبة في البيانات الأساسية.')
    return
  }
  if (!validateInvoiceTab()) {
    activeTab.value = 'invoice'
    toast.error('يرجى تصحيح بيانات الفاتورة.')
    return
  }
  if (!documentsComplete.value || !declarationAccepted.value) {
    activeTab.value = 'documents'
    toast.error('يرجى إرفاق الوثائق الإلزامية وقبول الإقرار.')
    return
  }

  // Advisory only — backend FINANCING_LIMIT_EXCEEDED remains authoritative (Story 17-D.2).
  if (financingAdvisoryBlocked.value) {
    toast.error(FINANCING_ADVISORY_MESSAGE)
    activeTab.value = 'invoice'
    return
  }

  const payload = buildPayload()
  let requestId = props.requestId ?? props.initialValues?.id ?? null
  try {
    if (requestId) {
      await requestsStore.updateRequest(requestId, payload)
    } else {
      requestId = await requestsStore.createRequest(payload)
    }
  } catch (err) {
    handleSaveError(err, 'تعذّر حفظ الطلب قبل التقديم.')
    return
  }

  try {
    await requestsStore.performAction(requestId, 'submit')
  } catch (err) {
    if (extractErrorCode(err) === 'FINANCING_LIMIT_EXCEEDED') {
      toast.error('تم تجاوز السقف التمويلي العالمي لهذه الفاتورة.')
      activeTab.value = 'invoice'
      return
    }
    handleSaveError(err, 'تعذّر تقديم الطلب للمراجعة.')
    return
  }

  emit('submitted')
  toast.success('تم تقديم الطلب للمراجعة')
  await router.push('/requests')
}

function buildPayload(): RequestFormData {
  // Only derive the canonical currency from a value that is a supported code;
  // never smuggle an unvalidated free-text currency into the whitelisted column.
  const candidate = values.request_currency || values.invoice_currency || values.currency
  const requestCurrency = (SUPPORTED_CURRENCIES as readonly string[]).includes(candidate ?? '')
    ? (candidate as string)
    : 'USD'

  // Derive the legacy amount only from a real numeric value; do not fabricate a
  // bogus amount=1 that masks a missing input (backend enforces the real rule).
  const amountSource = values.requested_amount ?? values.total_invoice_amount ?? values.amount
  const amount = Number(amountSource)

  return {
    ...values,
    currency: requestCurrency,
    amount: Number.isFinite(amount) && amount > 0 ? amount : null,
    supplier_name: values.exporting_company_name || values.supplier_name || null,
    goods_description: values.commodity || values.goods_description || null,
    port_of_entry:
      values.port_of_arrival || values.final_destination || values.port_of_entry || null,
  } as RequestFormData
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <CorrectionBanner
      v-if="isReturnedCorrection && correctionVariant"
      :variant="correctionVariant"
      :reviewer-comment="initialValues?.bank_return_comment ?? null"
      :support-comment="initialValues?.support_return_comment ?? null"
      :rejection-reason="initialValues?.bank_reject_comment ?? null"
    />

    <Card class="border-0 shadow">
      <Tabs v-model="activeTab" dir="rtl">
        <CardContent class="p-6">
          <TabsList class="grid w-full grid-cols-5">
            <TabsTrigger
              v-for="tab in tabs"
              :key="tab.value"
              :value="tab.value"
              :disabled="tab.value !== 'basic' && !visitedTabs.has(tab.value)"
            >
              {{ tab.label }}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="basic" class="mt-6">
            <BasicInfoTab
              :model-value="values"
              :errors="formErrors"
              @update:model-value="setValues"
            />
          </TabsContent>

          <TabsContent value="invoice" class="mt-6">
            <InvoiceTab
              :model-value="values"
              :errors="formErrors"
              :exclude-request-id="requestId ?? initialValues?.id ?? null"
              @update:model-value="setValues"
              @advisory-block="financingAdvisoryBlocked = $event"
            />
          </TabsContent>

          <TabsContent value="shipping" class="mt-6">
            <ShippingTab :model-value="values" @update:model-value="setValues" />
          </TabsContent>

          <TabsContent value="documents" class="mt-6">
            <DocumentsTab
              :request-id="requestId ?? initialValues?.id ?? null"
              @completeness="documentsComplete = $event"
            />
            <div class="border-border mt-4 flex items-center gap-2 rounded-lg border p-3">
              <Checkbox id="request-declaration" v-model:checked="declarationAccepted" />
              <Label for="request-declaration" class="text-sm">
                أقر بصحة البيانات والوثائق المرفوعة لهذا الطلب
              </Label>
            </div>
          </TabsContent>

          <TabsContent value="history" class="mt-6">
            <WorkflowHistoryTab :request-id="requestId ?? null" />
          </TabsContent>
        </CardContent>

        <CardFooter class="border-border flex items-center justify-between gap-3 border-t p-4">
          <Button
            type="button"
            variant="outline"
            :disabled="activeTab === 'basic'"
            @click="goPrevious"
          >
            ← السابق
          </Button>
          <div class="flex gap-2">
            <Button
              type="button"
              variant="secondary"
              :disabled="requestsStore.saving"
              @click="saveDraft"
            >
              حفظ كمسودة
            </Button>
            <Button
              v-if="activeTab !== 'documents'"
              type="button"
              :disabled="requestsStore.saving"
              @click="goNext"
            >
              التالي →
            </Button>
            <Button
              v-else
              type="button"
              :disabled="
                requestsStore.saving ||
                !documentsComplete ||
                !declarationAccepted ||
                financingAdvisoryBlocked
              "
              :title="
                financingAdvisoryBlocked
                  ? FINANCING_ADVISORY_MESSAGE
                  : !documentsComplete
                    ? 'أكمل رفع المستندات الإلزامية أولاً'
                    : !declarationAccepted
                      ? 'وافق على الإقرار أولاً'
                      : undefined
              "
              @click="submitForReview"
            >
              إرسال للمراجعة
            </Button>
          </div>
        </CardFooter>
      </Tabs>
    </Card>
  </div>
</template>
