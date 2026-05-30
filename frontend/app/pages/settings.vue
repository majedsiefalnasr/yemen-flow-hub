<script setup lang="ts">
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'

definePageMeta({ middleware: ['auth', 'role'] })

const authStore = useAuthStore()
const router = useRouter()

onMounted(() => {
  if (authStore.user?.role === UserRole.CBY_ADMIN) {
    router.replace('/settings/system')
  } else {
    router.replace('/settings/user')
  }
})
</script>

<template>
  <div class="flex min-h-[60vh] items-center justify-center">
    <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
  </div>
</template>
