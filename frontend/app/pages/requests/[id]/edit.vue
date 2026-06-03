<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import type { NavigationGuardNext } from 'vue-router'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ChevronLeft, RefreshCw, Save } from 'lucide-vue-next'
import { UserRole, RequestStatus } from '../../../types/enums'
import { useAuthStore } from '../../../stores/auth.store'
import { useRequestsStore } from '../../../stores/requests.store'
import { useMerchants } from '../../../composables/useMerchants'
import type { WizardStep1Data, WizardStep2Data } from '../../../composables/useRequestWizard'
import WizardStep1 from '../../../components/wizard/WizardStep1.vue'
import WizardStep2 from '../../../components/wizard/WizardStep2.vue'
import CorrectionBanner from '../../../components/banners/CorrectionBanner.vue'
import LockedBanner from '../../../components/banners/LockedBanner.vue'
import DocumentChecklist from '../../../components/requests/DocumentChecklist.vue'
import PageHeader from '../../../components/layout/PageHeader.vue'
import { Alert, AlertDescription, AlertTitle, AlertAction } from '../../../components/ui/alert'
import { Button } from '../../../components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card'
import { Separator } from '../../../components/ui/separator'
import { Skeleton } from '../../../components/ui/skeleton'
import { ROUTE_ROLE_MAP } from '../../../constants/workflow'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '../../../components/ui/alert-dialog'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests/:id/edit'],
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const requestsStore = useRequestsStore()
const { fetchMerchants } = useMerchants()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)

const userRole = computed(() => auth.user?.role ?? UserRole.DATA_ENTRY)
const downloadingIds = ref<Set<number>>(new Set())
const downloadErrors = ref<Record<number, string>>({})
const frozen = ref(false)
const frozenReason = ref<string | null>(null)
const saveError = ref<string | null>(null)
const formDirty = ref(false)
const submitted = ref(false)
const showLeaveDialog = ref(false)
const pendingLeaveNext = ref<NavigationGuardNext | null>(null)

const request = computed(() => requestsStore.currentRequest)

// Form state split to match WizardStep1 + WizardStep2 shapes
const step1 = ref<WizardStep1Data>({
  goods_type: '',
  amount: null,
  currency: 'USD',
  payment_terms: '',
  due_date: '',
  merchant_id: null,
  notes: '',
})

const step2 = ref<WizardStep2Data>({
  supplier_name: '',
  invoice_number: '',
  origin_country: '',
  invoice_date: '',
  arrival_port: '',
  shipping_port: '',
  customs_office: '',
  bl_number: '',
})

const step1Errors = ref<Partial<Record<keyof WizardStep1Data, string>>>({})
const step2Errors = ref<Partial<Record<keyof WizardStep2Data, string>>>({})

// Merchant data for DataEntry users
const isDataEntry = computed(() => userRole.value === UserRole.DATA_ENTRY)
const dataEntryMerchants = ref<Awaited<ReturnType<typeof fetchMerchants>>>([])
const merchantName = ref('')

const isReturnedCorrection = computed(() => {
  const s = request.value?.status
  return s === RequestStatus.DRAFT_REJECTED_INTERNAL || s === RequestStatus.BANK_RETURNED || s === RequestStatus.SUPPORT_RETURNED
})

const submitLabel = computed(() => {
  if (requestsStore.saving) return 'جارٍ الحفظ...'
  if (requestsStore.performingAction) return 'جارٍ إعادة الإرسال...'
  return isReturnedCorrection.value ? 'إعادة الإرسال للمراجعة' : 'حفظ التعديلات'
})

const isEditable = computed(() => {
  const s = request.value?.status
  return s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL || s === RequestStatus.BANK_RETURNED || s === RequestStatus.SUPPORT_RETURNED
})

const correctionVariant = computed(() => {
  switch (request.value?.status) {
    case RequestStatus.BANK_RETURNED: return 'bank_returned' as const
    case RequestStatus.SUPPORT_RETURNED: return 'support_returned' as const
    case RequestStatus.DRAFT_REJECTED_INTERNAL: return 'draft_rejected' as const
    default: return undefined
  }
})

function populateFormFromRequest(): void {
  const r = request.value
  if (!r) return
  step1.value = {
    goods_type: r.goods_type ?? '',
    amount: r.amount ?? null,
    currency: (r.currency as string) ?? 'USD',
    payment_terms: r.payment_terms ?? '',
    due_date: r.due_date ? String(r.due_date).slice(0, 10) : '',
    merchant_id: r.merchant?.id ?? null,
    notes: r.notes ?? '',
  }
  step2.value = {
    supplier_name: r.supplier_name ?? '',
    invoice_number: r.invoice_number ?? '',
    origin_country: r.origin_country ?? '',
    invoice_date: r.invoice_date ? String(r.invoice_date).slice(0, 10) : '',
    arrival_port: r.arrival_port ?? '',
    shipping_port: r.shipping_port ?? '',
    customs_office: r.customs_office ?? '',
    bl_number: r.bl_number ?? '',
  }
  merchantName.value = r.merchant?.name ?? ''
}

onMounted(async () => {
  if (Number.isNaN(id)) { await router.replace('/requests'); return }

  await requestsStore.loadRequest(id)

  if (requestsStore.error || !request.value) { await router.replace('/requests'); return }
  if (!isEditable.value) { await router.replace(`/requests/${id}`); return }

  populateFormFromRequest()

  if (isDataEntry.value) {
    try {
      dataEntryMerchants.value = await fetchMerchants({ bank_id: auth.user?.bank_id ?? undefined, is_active: true })
    }
    catch { /* non-fatal */ }
  }

  formDirty.value = true
  requestsStore.loadDocuments(id)
})

onBeforeRouteLeave((_to, _from, next) => {
  if (!formDirty.value || submitted.value) return next()
  pendingLeaveNext.value = next
  showLeaveDialog.value = true
})

function cancelLeave() {
  pendingLeaveNext.value?.(false)
  pendingLeaveNext.value = null
  showLeaveDialog.value = false
}

function confirmLeave() {
  submitted.value = true
  pendingLeaveNext.value?.()
  pendingLeaveNext.value = null
  showLeaveDialog.value = false
}

function onBeforeUnload(e: BeforeUnloadEvent) {
  if (formDirty.value && !submitted.value) { e.preventDefault(); e.returnValue = '' }
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onUnmounted(() => window.removeEventListener('beforeunload', onBeforeUnload))

async function handleSubmit() {
  if (frozen.value) return
  saveError.value = null
  step1Errors.value = {}
  step2Errors.value = {}

  const data = {
    merchant_id: step1.value.merchant_id,
    currency: step1.value.currency,
    amount: step1.value.amount,
    goods_type: step1.value.goods_type,
    payment_terms: step1.value.payment_terms,
    due_date: step1.value.due_date || null,
    notes: step1.value.notes || null,
    supplier_name: step2.value.supplier_name,
    invoice_number: step2.value.invoice_number,
    invoice_date: step2.value.invoice_date || null,
    origin_country: step2.value.origin_country,
    arrival_port: step2.value.arrival_port,
    shipping_port: step2.value.shipping_port || null,
    customs_office: step2.value.customs_office || null,
    bl_number: step2.value.bl_number || null,
    goods_description: step1.value.goods_type,
    port_of_entry: step2.value.arrival_port,
  }

  try {
    await requestsStore.updateRequest(id, data as any)
    if (isReturnedCorrection.value) await requestsStore.performAction(id, 'submit')
    submitted.value = true
    await router.push(`/requests/${id}`)
  }
  catch (err: unknown) {
    const res = (err as any)?.response
    const status = res?.status
    if (status === 403 || status === 409) {
      frozen.value = true
      frozenReason.value = status === 409
        ? 'تغيرت حالة الطلب أثناء التعديل. حدّث البيانات ثم راجعها مرة أخرى.'
        : 'لا تملك صلاحية تعديل هذا الطلب في حالته الحالية.'
    }
    else if (status === 422) {
      const fieldErrors = res?._data?.errors as Record<string, string[]> | undefined
      if (fieldErrors) {
        // Route field errors back to the correct step
        const s1Keys: (keyof WizardStep1Data)[] = ['goods_type', 'amount', 'currency', 'payment_terms', 'due_date', 'merchant_id', 'notes']
        const s2Keys: (keyof WizardStep2Data)[] = ['supplier_name', 'invoice_number', 'origin_country', 'invoice_date', 'arrival_port', 'shipping_port', 'customs_office', 'bl_number']
        for (const [field, msgs] of Object.entries(fieldErrors)) {
          const msg = msgs[0] ?? ''
          if (s1Keys.includes(field as any)) step1Errors.value = { ...step1Errors.value, [field]: msg }
          else if (s2Keys.includes(field as any)) step2Errors.value = { ...step2Errors.value, [field]: msg }
        }
      }
      saveError.value = res?._data?.message ?? 'البيانات المدخلة غير صالحة.'
    }
    else {
      saveError.value = 'تعذّر تحديث الطلب. حاول مرة أخرى.'
    }
  }
}

function reload() {
  frozen.value = false
  frozenReason.value = null
  requestsStore.loadRequest(id)
}

async function handleUploadDocument(file: File) {
  try { await requestsStore.uploadDocument(id, file) }
  catch { /* uploadError surfaced via store */ }
}

async function downloadDocument(docId: number, filename: string) {
  if (downloadingIds.value.has(docId)) return
  downloadingIds.value = new Set([...downloadingIds.value, docId])
  delete downloadErrors.value[docId]
  try {
    const config = useRuntimeConfig()
    const response = await $fetch<Blob>(`/api/documents/${docId}/download`, {
      baseURL: config.public.apiBase as string,
      responseType: 'blob',
      credentials: 'include',
    })
    const url = URL.createObjectURL(response)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }
  catch {
    downloadErrors.value = { ...downloadErrors.value, [docId]: 'تعذر تنزيل الملف الآن. أعد المحاولة بعد قليل.' }
  }
  finally {
    downloadingIds.value = new Set([...downloadingIds.value].filter(x => x !== docId))
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="تعديل الطلب"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/' },
        { label: 'الطلبات', to: '/requests' },
        { label: 'تعديل' },
      ]"
    />

    <!-- Loading -->
    <template v-if="requestsStore.loadingRequest">
      <Card class="border-0 p-6 shadow space-y-4">
        <Skeleton class="h-6 w-48" />
        <Skeleton class="h-4 w-full" />
        <Skeleton class="h-4 w-3/4" />
      </Card>
    </template>

    <!-- Frozen (403/409) -->
    <Alert v-else-if="frozen" variant="destructive" role="alert" class="mb-4">
      <AlertTitle>تعذر حفظ التعديلات</AlertTitle>
      <AlertDescription>{{ frozenReason }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="reload">
          <RefreshCw class="h-4 w-4 me-1.5" />
          تحديث البيانات
        </Button>
      </AlertAction>
    </Alert>

    <!-- Editable form -->
    <template v-else-if="request && isEditable">
      <CorrectionBanner
        v-if="correctionVariant"
        :variant="correctionVariant"
        :reviewer-comment="request.bank_return_comment ?? null"
        :support-comment="request.support_return_comment ?? null"
        :rejection-reason="request.bank_reject_comment ?? null"
        class="mb-4"
      />

      <Alert v-if="saveError" variant="destructive" role="alert" class="mb-4">
        <AlertTitle>تعذّر حفظ التعديلات</AlertTitle>
        <AlertDescription>{{ saveError }}</AlertDescription>
      </Alert>

      <!-- Form using wizard step components -->
      <Card class="border-0 shadow">
        <CardContent class="p-6">
          <!-- Step 1: basic info -->
          <WizardStep1
            v-model="step1"
            :errors="step1Errors"
            :is-data-entry="isDataEntry"
            :data-entry-merchant-name="merchantName"
            :data-entry-merchants="dataEntryMerchants"
            :loading="requestsStore.saving"
          />

          <Separator class="my-8" />

          <!-- Step 2: supplier & shipping -->
          <WizardStep2
            v-model="step2"
            :errors="step2Errors"
            :auto-fill-chip="false"
            :loading="requestsStore.saving"
          />

          <Separator class="my-8" />

          <!-- Actions -->
          <div class="flex items-center gap-3 pt-2">
            <Button
              :disabled="requestsStore.saving || requestsStore.performingAction || frozen"
              @click="handleSubmit"
            >
              <Save class="h-4 w-4 me-1.5" />
              {{ submitLabel }}
            </Button>
            <Button variant="outline" @click="router.push(`/requests/${id}`)">
              <ChevronLeft class="h-4 w-4 me-1" />
              إلغاء والعودة
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Documents section -->
      <Card class="border-0 shadow mt-4">
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-semibold">المستندات المرفوعة</CardTitle>
          <CardDescription class="text-xs">
            راجع المستندات المرفقة وارفع بدائل محدّثة عند الحاجة (PDF فقط)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <DocumentChecklist
            :documents="requestsStore.documents"
            :customs-declaration="null"
            :user-role="userRole"
            :request-status="request.status"
            :loading="requestsStore.loadingDocuments || !requestsStore.documentsLoaded"
            :error="requestsStore.documentsError"
            :uploading-document="requestsStore.uploading"
            :upload-error="requestsStore.uploadError"
            :downloading-ids="downloadingIds"
            :download-errors="downloadErrors"
            :customs-downloading="false"
            :customs-download-error="null"
            @download="downloadDocument"
            @upload="handleUploadDocument"
          />
        </CardContent>
      </Card>
    </template>

    <!-- Locked fallback -->
    <template v-else-if="request && !isEditable">
      <LockedBanner variant="readonly" />
    </template>

    <AlertDialog :open="showLeaveDialog">
      <AlertDialogContent @escape-key-down="cancelLeave">
        <AlertDialogHeader>
          <AlertDialogTitle>مغادرة صفحة التعديل؟</AlertDialogTitle>
          <AlertDialogDescription>
            لديك تعديلات غير محفوظة على هذا الطلب. إذا غادرت الآن ستفقد آخر التغييرات غير المحفوظة.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="cancelLeave">البقاء في الصفحة</AlertDialogCancel>
          <AlertDialogAction
            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            @click="confirmLeave"
          >
            مغادرة بدون حفظ
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
