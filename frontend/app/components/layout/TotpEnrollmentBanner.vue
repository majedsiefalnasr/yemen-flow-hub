<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { X, ShieldAlert } from 'lucide-vue-next'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth.store'

const authStore = useAuthStore()
const router = useRouter()

const dismissed = ref(
  typeof sessionStorage !== 'undefined' && sessionStorage.getItem('totp-banner-dismissed') === '1',
)

const showBanner = computed(
  () => !dismissed.value && authStore.user !== null && authStore.user.totp_enabled === false,
)

function dismiss() {
  sessionStorage.setItem('totp-banner-dismissed', '1')
  dismissed.value = true
}

function goToTotpSetup() {
  dismiss()
  router.push('/settings?section=security')
}
</script>

<template>
  <Alert
    v-if="showBanner"
    role="status"
    aria-live="polite"
    class="mb-4"
  >
    <ShieldAlert class="h-4 w-4" />
    <AlertTitle>عزّز أمان حسابك</AlertTitle>
    <AlertDescription>
      لتعزيز أمان حسابك، يُنصح بإعداد تطبيق المصادقة الثنائية.
      <Button variant="link" size="sm" class="ms-2 h-auto p-0" @click="goToTotpSetup">
        إعداد الآن
      </Button>
    </AlertDescription>
    <AlertAction>
      <Button variant="ghost" size="icon-sm" aria-label="إغلاق" @click="dismiss">
        <X class="h-4 w-4" />
      </Button>
    </AlertAction>
  </Alert>
</template>
