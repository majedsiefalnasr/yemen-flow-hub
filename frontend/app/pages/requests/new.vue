<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { UserRole } from '../../types/enums'
import type { RequestFormData } from '../../types/models'
import { useRequestsStore } from '../../stores/requests.store'
import RequestForm from '../../components/forms/RequestForm.vue'

definePageMeta({
  middleware: ['auth'],
  requiredRoles: [UserRole.DATA_ENTRY],
})

const router = useRouter()
const requestsStore = useRequestsStore()

const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)

async function handleSubmit(data: RequestFormData) {
  try {
    const id = await requestsStore.createRequest(data)
    toast.value = { message: 'تم إنشاء الطلب بنجاح.', type: 'success' }
    await router.push(`/requests/${id}`)
  }
  catch {
    toast.value = { message: requestsStore.error ?? 'تعذّر إنشاء الطلب.', type: 'error' }
  }
}
</script>

<template>
  <div class="new-request-page" dir="rtl">
    <div class="page-header">
      <h1 class="page-title">تقديم طلب جديد</h1>
      <NuxtLink to="/requests" class="back-link">← العودة إلى القائمة</NuxtLink>
    </div>

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
      :loading="requestsStore.saving"
      @submit="handleSubmit"
    >
      <template #actions>
        <button
          type="submit"
          class="btn-primary"
          :disabled="requestsStore.saving"
        >
          {{ requestsStore.saving ? 'جاري الحفظ...' : 'حفظ وإرسال' }}
        </button>
        <NuxtLink to="/requests" class="btn-secondary">إلغاء</NuxtLink>
      </template>
    </RequestForm>
  </div>
</template>

<style scoped>
.new-request-page {
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
