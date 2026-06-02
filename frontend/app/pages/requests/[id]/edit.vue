<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { NavigationGuardNext } from 'vue-router'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { RefreshCw } from 'lucide-vue-next'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { RequestFormData } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'
import { useRequestsStore } from '../../../stores/requests.store'
import RequestForm from '../../../components/forms/RequestForm.vue'
import CorrectionBanner from '../../../components/banners/CorrectionBanner.vue'
import LockedBanner from '../../../components/banners/LockedBanner.vue'
import DocumentChecklist from '../../../components/requests/DocumentChecklist.vue'
import PageHeader from '../../../components/layout/PageHeader.vue'
import { Alert, AlertDescription, AlertTitle, AlertAction } from '../../../components/ui/alert'
import { Button } from '../../../components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card'
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

const isEditable = computed(() => {
  const s = request.value?.status
  return (
    s === RequestStatus.DRAFT
    || s === RequestStatus.DRAFT_REJECTED_INTERNAL
    || s === RequestStatus.BANK_RETURNED
    || s === RequestStatus.SUPPORT_RETURNED
  )
})

const correctionVariant = computed(() => {
  switch (request.value?.status) {
    case RequestStatus.BANK_RETURNED: return 'bank_returned' as const
    case RequestStatus.SUPPORT_RETURNED: return 'support_returned' as const
    case RequestStatus.DRAFT_REJECTED_INTERNAL: return 'draft_rejected' as const
    default: return undefined
  }
})

const initialValues = computed<Partial<RequestFormData> | undefined>(() => {
  const r = request.value
  if (!r) return undefined
  return {
    merchant_id: r.merchant?.id,
    currency: r.currency,
    amount: r.amount,
    supplier_name: r.supplier_name,
    goods_description: r.goods_description,
    port_of_entry: r.port_of_entry,
    notes: r.notes ?? '',
    goods_type: r.goods_type ?? '',
    payment_terms: r.payment_terms ?? '',
    due_date: r.due_date ? String(r.due_date).slice(0, 10) : '',
    invoice_number: r.invoice_number ?? '',
    invoice_date: r.invoice_date ? String(r.invoice_date).slice(0, 10) : '',
    origin_country: r.origin_country ?? '',
    arrival_port: r.arrival_port ?? '',
    shipping_port: r.shipping_port ?? '',
    customs_office: r.customs_office ?? '',
    bl_number: r.bl_number ?? '',
  }
})

onMounted(async () => {
  if (Number.isNaN(id)) {
    await router.replace('/requests')
    return
  }

  await requestsStore.loadRequest(id)

  if (requestsStore.error || !request.value) {
    await router.replace('/requests')
    return
  }

  if (!isEditable.value) {
    await router.replace(`/requests/${id}`)
    return
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
  if (formDirty.value && !submitted.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onUnmounted(() => window.removeEventListener('beforeunload', onBeforeUnload))

async function handleSubmit(data: RequestFormData) {
  if (frozen.value) return
  saveError.value = null
  try {
    await requestsStore.updateRequest(id, data)
    submitted.value = true
    await router.push(`/requests/${id}`)
  }
  catch (err: unknown) {
    const res = (err as { response?: { status?: number; _data?: { errors?: Record<string, string[]>; message?: string } } })?.response
    const status = res?.status
    if (status === 403 || status === 409) {
      frozen.value = true
      frozenReason.value = status === 409
        ? 'تغيرت حالة الطلب أثناء التعديل. حدّث البيانات ثم راجعها مرة أخرى.'
        : 'لا تملك صلاحية تعديل هذا الطلب في حالته الحالية.'
    }
    else if (status === 422) {
      const fieldErrors = res?._data?.errors
      saveError.value = fieldErrors
        ? Object.values(fieldErrors).flat().join(' · ')
        : (res?._data?.message ?? 'البيانات المدخلة غير صالحة.')
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
  try {
    await requestsStore.uploadDocument(id, file)
  }
  catch {
    // uploadError surfaced via store
  }
}

async function downloadDocument(docId: number, filename: string) {
  if (downloadingIds.value.has(docId)) return
  downloadingIds.value = new Set([...downloadingIds.value, docId])
  delete downloadErrors.value[docId]
  try {
    const response = await $fetch<Blob>(`/api/documents/${docId}/download`, {
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

    <!-- Editable wizard -->
    <template v-else-if="request && isEditable">
      <!-- CorrectionBanner pinned above the form for returned/rejected states -->
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

      <RequestForm
        :initial-values="initialValues"
        :loading="requestsStore.saving"
        @submit="handleSubmit"
      >
        <template #actions>
          <Button
            type="submit"
            :disabled="requestsStore.saving || frozen"
          >
            {{ requestsStore.saving ? 'جارٍ الحفظ...' : 'حفظ التعديلات' }}
          </Button>
          <Button type="button" variant="outline" @click="router.push('/requests')">إلغاء</Button>
        </template>
      </RequestForm>

      <!-- Documents section: review & replace attachments -->
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

    <!-- Locked (should not normally render — onMounted redirects away) -->
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
