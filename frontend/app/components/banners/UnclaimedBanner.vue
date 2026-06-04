<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
</script>

<template>
  <Alert
    class="flex items-center gap-3 border-[var(--color-border-warning)] bg-[var(--color-surface-warning)]"
  >
    <Loader2
      class="h-5 w-5 flex-shrink-0 animate-spin text-[var(--color-text-warning)]"
      aria-hidden="true"
    />
    <AlertDescription class="text-foreground flex-1 text-sm font-medium">
      جارٍ حجز الطلب تلقائياً…
    </AlertDescription>
  </Alert>
</template>
