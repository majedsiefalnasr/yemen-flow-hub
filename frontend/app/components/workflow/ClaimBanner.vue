<script setup lang="ts">
import { Lock } from 'lucide-vue-next'
import { Alert, AlertDescription } from '@/components/ui/alert'

defineProps<{
  holderName: string
}>()
</script>

<template>
  <Alert
    variant="default"
    role="status"
    class="flex items-start gap-3 border-[var(--locked)]/30 bg-[var(--locked)]/10"
  >
    <Lock class="mt-0.5 h-5 w-5 flex-shrink-0 text-[var(--locked)]" aria-hidden="true" />
    <AlertDescription class="text-sm font-medium text-[var(--locked)]">
      {{ holderName }} يعمل على هذا الطلب الآن
    </AlertDescription>
  </Alert>
</template>
