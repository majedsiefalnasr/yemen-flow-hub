<script setup lang="ts">
import { UserCog } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth.store'
import { ROLE_LABELS } from '@/constants/workflow'

const config = useRuntimeConfig()
const authStore = useAuthStore()
const user = computed(() => authStore.user)
</script>

<template>
  <Button
    v-if="config.public.demoMode && user"
    variant="ghost"
    size="icon"
    title="عرض توضيحي"
    disabled
  >
    <UserCog class="h-5 w-5 opacity-40" />
  </Button>
</template>
