<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { RequestFormData } from '../../../types/models'
import { useRequestsStore } from '../../../stores/requests.store'
import RequestForm from '../../../components/forms/RequestForm.vue'
import LockedBanner from '../../../components/ui/LockedBanner.vue'

type LockedBannerVariant = 'locked' | 'readonly' | 'pending'

definePageMeta({
  middleware: ['auth'],
  requiredRoles: [UserRole.DATA_ENTRY],
})

const route = useRoute()
const router = useRouter()
const requestsStore = useRequestsStore()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)
const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)

const isEditable = computed(() => {
  const s = requestsStore.currentRequest?.status
  return s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL || s === RequestStatus.BANK_RETURNED
})

const initialValues = computed<Partial<RequestFormData> | undefined>(() => {
  const r = requestsStore.currentRequest
  if (!r) return undefined
  return {
    merchant_id: r.merchant?.id,
    currency: r.currency,
    amount: r.amount,
    supplier_name: r.supplier_name,
    goods_description: r.goods_description,
    port_of_entry: r.port_of_entry,
    notes: r.notes ?? '',
  }
})

const lockedBannerVariant = computed<LockedBannerVariant>(() => {
  switch (requestsStore.currentRequest?.status) {
    case RequestStatus.BANK_APPROVED:
    case RequestStatus.SUPPORT_APPROVED:
    case RequestStatus.SUPPORT_REJECTED:
    case RequestStatus.SWIFT_UPLOADED:
    case RequestStatus.EXECUTIVE_VOTING_CLOSED:
    case RequestStatus.EXECUTIVE_APPROVED:
    case RequestStatus.EXECUTIVE_REJECTED:
    case RequestStatus.CUSTOMS_DECLARATION_ISSUED:
    case RequestStatus.COMPLETED:
      return 'readonly'
    default:
      return 'pending'
  }
})

onMounted(async () => {
  if (Number.isNaN(id)) {
    await router.replace('/requests')
    return
  }

  await requestsStore.loadRequest(id)

  if (requestsStore.error || !requestsStore.currentRequest) {
    // Load failed — redirect to list; error already surfaced via toast if available
    await router.replace('/requests')
    return
  }

  if (!isEditable.value) {
    // Request exists but is not editable — redirect to detail page
    await router.replace(`/requests/${id}`)
  }
})

async function handleSubmit(data: RequestFormData) {
  try {
    await requestsStore.updateRequest(id, data)
    toast.value = { message: 'تم تحديث الطلب بنجاح.', type: 'success' }
    await router.push(`/requests/${id}`)
  }
  catch {
    toast.value = { message: requestsStore.error ?? 'تعذّر تحديث الطلب.', type: 'error' }
  }
}
</script>

<template>
  <div class="edit-request-page" dir="rtl">
    <div class="page-header">
      <h1 class="page-title">تعديل الطلب</h1>
      <NuxtLink to="/requests" class="back-link">← العودة إلى القائمة</NuxtLink>
    </div>

    <!-- Loading state -->
    <div v-if="requestsStore.loadingRequest" class="state-card">
      <span class="state-text">جاري التحميل...</span>
    </div>

    <!-- Ready: form rendered only after request is loaded and confirmed editable -->
    <template v-else-if="requestsStore.currentRequest && isEditable">
      <!-- Toast notification -->
      <div
        v-if="toast"
        class="toast"
        :class="toast.type === 'success' ? 'toast--success' : 'toast--error'"
        role="alert"
      >
        {{ toast.message }}
      </div>

      <RequestForm
        :initial-values="initialValues"
        :loading="requestsStore.saving"
        @submit="handleSubmit"
      >
        <template #actions>
          <button
            type="submit"
            class="btn-primary"
            :disabled="requestsStore.saving"
          >
            {{ requestsStore.saving ? 'جاري الحفظ...' : 'حفظ التعديلات' }}
          </button>
          <NuxtLink to="/requests" class="btn-secondary">إلغاء</NuxtLink>
        </template>
      </RequestForm>
    </template>

    <!-- Locked state (should not normally render — onMounted redirects away) -->
    <template v-else-if="requestsStore.currentRequest && !isEditable">
      <LockedBanner :variant="lockedBannerVariant" />
    </template>
  </div>
</template>

<style scoped>
.edit-request-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 800px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary, #1d1d1f);
  margin: 0;
}

.back-link {
  font-size: 14px;
  color: #0071e3;
  text-decoration: none;
}

.back-link:hover {
  text-decoration: underline;
}

.state-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  padding: 48px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.state-text {
  font-size: 15px;
  color: var(--color-text-secondary, #6e6e73);
}

.toast {
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
}

.toast--success {
  background: #e9f9ef;
  color: #1a7a3a;
  border: 1px solid #34c759;
}

.toast--error {
  background: #fff0f0;
  color: #c0392b;
  border: 1px solid #ff3b30;
}

.btn-primary {
  height: 44px;
  padding: 0 24px;
  background: #0071e3;
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 100ms;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary:not(:disabled):hover {
  opacity: 0.9;
}

.btn-secondary {
  height: 44px;
  padding: 0 20px;
  background: transparent;
  color: var(--color-text-primary, #1d1d1f);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  font-size: 15px;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: border-color 100ms;
}

.btn-secondary:hover {
  border-color: #0071e3;
}
</style>
