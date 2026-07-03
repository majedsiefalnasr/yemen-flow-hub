<script setup lang="ts">
import { ref } from 'vue'
import { Users } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DemoUserSwitcherSheet from '@/components/auth/DemoUserSwitcherSheet.vue'

const config = useRuntimeConfig()
const isEnabled = Boolean(config.public.demoUserSwitch)
const sheetOpen = ref(false)
</script>

<template>
  <template v-if="isEnabled">
    <Button
      variant="secondary"
      size="icon"
      class="fixed start-4 bottom-4 z-50 shadow-lg"
      aria-label="تبديل المستخدم السريع"
      @click="sheetOpen = true"
    >
      <Users class="h-5 w-5" />
    </Button>
    <DemoUserSwitcherSheet v-model:open="sheetOpen" />
  </template>
</template>
