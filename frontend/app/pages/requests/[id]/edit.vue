<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RefreshCw } from 'lucide-vue-next'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { RequestFormData } from '../../../types/models'
import { useRequestsStore } from '../../../stores/requests.store'
import RequestForm from '../../../components/forms/RequestForm.vue'
import CorrectionBanner from '../../../components/banners/CorrectionBanner.vue'
import LockedBanner from '../../../components/banners/LockedBanner.vue'
import PageHeader from '../../../components/layout/PageHeader.vue'
import { Alert, AlertDescription, AlertTitle, AlertAction } from '../../../components/ui/alert'
import { Button } from '../../../components/ui/button'
import { Card } from '../../../components/ui/card'
import { Skeleton } from '../../../components/ui/skeleton'
import { ROUTE_ROLE_MAP } from '../../../constants/workflow'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests/:id/edit'],
})

const route = useRoute()
const router = useRouter()
const requestsStore = useRequestsStore()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)

const frozen = ref(false)
const frozenReason = ref<string | null>(null)

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
  }
})

async function handleSubmit(data: RequestFormData) {
  if (frozen.value) return
  try {
    await requestsStore.updateRequest(id, data)
    await router.push(`/requests/${id}`)
  }
  catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 403 || status === 409) {
      frozen.value = true
      frozenReason.value = status === 409
        ? 'تغيّرت حالة الطلب أثناء التعديل. يرجى إعادة التحميل.'
        : 'ليس لديك صلاحية تعديل هذا الطلب في الحالة الحالية.'
    }
  }
}

function reload() {
  frozen.value = false
  frozenReason.value = null
  requestsStore.loadRequest(id)
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
      <AlertTitle>تعذّر حفظ التعديلات</AlertTitle>
      <AlertDescription>{{ frozenReason }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="reload">
          <RefreshCw class="h-4 w-4 me-1.5" />
          إعادة التحميل
        </Button>
      </AlertAction>
    </Alert>

    <!-- Editable wizard -->
    <template v-else-if="request && isEditable">
      <!-- CorrectionBanner pinned above the form for returned/rejected states -->
      <CorrectionBanner
        v-if="correctionVariant"
        :variant="correctionVariant"
        :reviewer-comment="request.reviewer_comment ?? null"
        :support-comment="request.support_comment ?? null"
        :rejection-reason="request.rejection_reason ?? null"
        class="mb-4"
      />

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
          <Button variant="outline" @click="router.push('/requests')">إلغاء</Button>
        </template>
      </RequestForm>
    </template>

    <!-- Locked (should not normally render — onMounted redirects away) -->
    <template v-else-if="request && !isEditable">
      <LockedBanner variant="readonly" />
    </template>
  </div>
</template>
