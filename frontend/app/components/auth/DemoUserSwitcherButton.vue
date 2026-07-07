<script setup lang="ts">
import { ref } from 'vue'
import { Users } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DemoUserSwitcherDialog from '@/components/auth/DemoUserSwitcherDialog.vue'

const config = useRuntimeConfig()
const isEnabled = Boolean(config.public.demoEnabled)
const dialogOpen = ref(false)
</script>

<template>
  <template v-if="isEnabled">
    <Button
      variant="secondary"
      class="fixed end-4 bottom-4 z-50 shadow-lg"
      aria-label="تبديل المستخدم السريع"
      @click="dialogOpen = true"
    >
      <Users class="h-4 w-4" />
      تبديل المستخدم
    </Button>
    <DemoUserSwitcherDialog v-model:open="dialogOpen" />
  </template>
</template>
