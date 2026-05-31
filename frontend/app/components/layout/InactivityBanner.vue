<script setup lang="ts">
import { Button } from '../ui/button'
import { Alert, AlertDescription } from '../ui/alert'

defineProps<{
  visible: boolean
}>()

const emit = defineEmits<{
  extend: []
}>()
</script>

<template>
  <Alert v-if="visible" role="status" aria-live="polite"  class="sticky top-0 z-50 flex items-center justify-between gap-4 border-b border-warning bg-[var(--color-surface-warning)]">
    <AlertDescription class="flex-1 text-sm font-medium text-[var(--color-text-warning)]">
      أنت على وشك الخروج بسبب عدم النشاط — يرجى التفاعل للبقاء
    </AlertDescription>
    <Button size="sm" class="flex-shrink-0 bg-[var(--color-surface-warning)] text-white hover:opacity-90" @click="emit('extend')">
      متابعة الجلسة
    </Button>
  </Alert>
</template>
