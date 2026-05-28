<script setup lang="ts">
import ErrorState from '@/components/shared/ErrorState.vue'

const props = defineProps<{
  error: {
    statusCode?: number
    statusMessage?: string
    message?: string
  }
}>()

const code = computed(() => props.error.statusCode ?? 500)
</script>

<template>
  <main
    dir="rtl"
    class="min-h-screen bg-background text-foreground"
  >
    <ErrorState
      :code="code"
      :show-retry="code >= 500"
      back-to="/"
      back-label="العودة للرئيسية"
    />
  </main>
</template>
