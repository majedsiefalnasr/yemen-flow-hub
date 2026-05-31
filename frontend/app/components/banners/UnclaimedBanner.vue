<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'

defineEmits<{
  claim: []
}>()
</script>

<template>
  <Alert  class="flex items-center gap-3 border-[var(--color-border-warning)] bg-[var(--color-surface-warning)]">
    <AlertTriangle class="h-5 w-5 flex-shrink-0 text-[var(--color-text-warning)]" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium text-foreground">
      هذا الطلب جاهز للمطالبة — لم يُحجز بعد من أي عضو
    </AlertDescription>
    <button
      class="flex-shrink-0 px-3 py-1.5 bg-[var(--voting)] text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-opacity"
      @click="$emit('claim')"
    >
      مطالبة بالطلب
    </button>
  </Alert>
</template>
