<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus } from '../../../types/enums'
import type { RequestFormData } from '../../../types/models'
import { useRequestsStore } from '../../../stores/requests.store'
import RequestForm from '../../../components/forms/RequestForm.vue'
import LockedBanner from '../../../components/ui/LockedBanner.vue'

definePageMeta({
  middleware: ['auth'],
  requiredRoles: [UserRole.DATA_ENTRY],
})

const route = useRoute()
const router = useRouter()
const requestsStore = useRequestsStore()

const id = Number(route.params.id)
const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)

const isEditable = computed(() => {
  const s = requestsStore.currentRequest?.status
  return s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL
})

const initialValues = computed<Partial<RequestFormData> | undefined>(() => {
  const r = requestsStore.currentRequest
  if (!r) return undefined
  return {
    merchant_id: r.merchant?.id ?? null,
    currency: r.currency,
    amount: r.amount,
    supplier_name: r.supplier_name,
    goods_description: r.goods_description,
    port_of_entry: r.port_of_entry,
    notes: r.notes ?? '',
  }
})

onMounted(async () => {
  await requestsStore.loadRequest(id)
  if (requestsStore.currentRequest && !isEditable.value) {
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
      <NuxtLink :to="`/requests/${id}`" class="back-link">← العودة إلى الطلب</NuxtLink>
    </div>

    <!-- Loading state -->
    <div v-if="requestsStore.loading" class="state-card">
      <span class="state-text">جاري التحميل...</span>
    </div>

    <!-- Error state -->
    <div v-else-if="requestsStore.error && !requestsStore.currentRequest" class="state-card state-card--error">
      <span class="state-text">{{ requestsStore.error }}</span>
    </div>

    <template v-else-if="requestsStore.currentRequest">
      <!-- Locked state -->
      <LockedBanner
        v-if="!isEditable"
        :status="requestsStore.currentRequest.status"
      />

      <template v-else>
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
            <NuxtLink :to="`/requests/${id}`" class="btn-secondary">إلغاء</NuxtLink>
          </template>
        </RequestForm>
      </template>
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

.state-card--error {
  border-color: #ff3b30;
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
