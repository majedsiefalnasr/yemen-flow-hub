<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { UserRole } from '../../types/enums'
import { useRequests } from '../../composables/useRequests'
import RequestWizard from '../../components/wizard/RequestWizard.vue'

definePageMeta({
  middleware: ['auth'],
  requiredRoles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN],
})

const route = useRoute()
const cloneError = ref('')
const cloneSourceId = Number(route.query.clone_of)
const shouldAutoClone = Number.isInteger(cloneSourceId) && cloneSourceId > 0
const cloneLoading = ref(shouldAutoClone)

const { cloneRequest } = useRequests()

onMounted(async () => {
  if (!shouldAutoClone) return

  cloneLoading.value = true
  cloneError.value = ''
  try {
    window.history.replaceState(window.history.state, '', route.path)

    const newId = await cloneRequest(cloneSourceId)
    await navigateTo(`/requests/${newId}/edit`, { replace: true })
  }
  catch (err: unknown) {
    const status = (err as { statusCode?: number })?.statusCode
    if (status === 403) {
      cloneError.value = 'ليس لديك صلاحية نسخ هذا الطلب.'
    }
    else {
      cloneError.value = 'تعذّر نسخ الطلب. يرجى التحقق من الرابط والمحاولة مرة أخرى.'
    }
    cloneLoading.value = false
  }
})
</script>

<template>
  <div class="new-request-page" dir="rtl">
    <div v-if="cloneLoading" class="clone-redirect-banner" aria-busy="true">
      جارٍ تجهيز نسخة الطلب…
    </div>
    <div v-else-if="cloneError" class="clone-error-banner" role="alert">
      {{ cloneError }}
    </div>
    <RequestWizard v-else />
  </div>
</template>

<style scoped>
.new-request-page {
  padding: 24px;
}

.clone-redirect-banner {
  padding: 16px 20px;
  border-radius: 12px;
  background: #f0f7ff;
  color: #0066cc;
  font-size: 14px;
}

.clone-error-banner {
  padding: 16px 20px;
  border-radius: 12px;
  background: #fff5f5;
  color: #c62828;
  font-size: 14px;
}
</style>
