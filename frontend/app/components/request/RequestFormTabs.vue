<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
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
import { useRequestsStore } from '@/stores/requests.store'

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
    Object.assign(values, defaultValues(next ?? null))
  },
  { immediate: true },
)

watch(
  values,
  () => {
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

function validateBasicTab(): boolean {
  return Boolean(
    values.trader_id && values.request_type && values.currency_source && values.payment_terms_mode,
  )
}

function validateInvoiceTab(): boolean {
  if (values.coverage_type === CoverageType.FULL) return Number(values.request_percentage) === 100
  if (values.coverage_type !== CoverageType.PARTIAL) return true
  const percentage = Number(values.request_percentage)
  return Number.isFinite(percentage) && percentage >= 5 && percentage < 100
}

function goNext() {
  if (activeTab.value === 'basic' && !validateBasicTab()) return
  if (activeTab.value === 'invoice' && !validateInvoiceTab()) return
  const index = tabs.findIndex((tab) => tab.value === activeTab.value)
  activeTab.value = tabs[Math.min(index + 1, tabs.length - 1)]?.value ?? 'basic'
}

function goPrevious() {
  const index = tabs.findIndex((tab) => tab.value === activeTab.value)
  activeTab.value = tabs[Math.max(index - 1, 0)]?.value ?? 'basic'
}

async function saveDraft() {
  const payload = buildPayload()
  if (isEditMode.value && props.requestId) {
    await requestsStore.updateRequest(props.requestId, payload)
  } else {
    const id = await requestsStore.createRequest(payload)
    await router.push(`/requests/${id}/edit`)
  }
  emit('clean')
  toast.success('تم حفظ المسودة')
}

async function submitForReview() {
  if (!validateBasicTab()) {
    activeTab.value = 'basic'
    return
  }
  if (!validateInvoiceTab()) {
    activeTab.value = 'invoice'
    return
  }
  if (!documentsComplete.value || !declarationAccepted.value) return
  const payload = buildPayload()
  let requestId = props.requestId ?? props.initialValues?.id ?? null
  if (requestId) {
    await requestsStore.updateRequest(requestId, payload)
  } else {
    requestId = await requestsStore.createRequest(payload)
  }
  await requestsStore.performAction(requestId, 'submit')
  emit('submitted')
  toast.success('تم تقديم الطلب للمراجعة')
  await router.push('/requests')
}

function buildPayload(): RequestFormData {
  const requestCurrency =
    values.request_currency || values.invoice_currency || values.currency || 'USD'
  const requestedAmount = Number(
    values.requested_amount ?? values.total_invoice_amount ?? values.amount ?? 1,
  )

  return {
    ...values,
    currency: requestCurrency,
    amount: Number.isFinite(requestedAmount) && requestedAmount > 0 ? requestedAmount : 1,
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
            <BasicInfoTab :model-value="values" @update:model-value="setValues" />
          </TabsContent>

          <TabsContent value="invoice" class="mt-6">
            <InvoiceTab :model-value="values" @update:model-value="setValues" />
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
              :disabled="requestsStore.saving || !documentsComplete || !declarationAccepted"
              :title="
                !documentsComplete
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
