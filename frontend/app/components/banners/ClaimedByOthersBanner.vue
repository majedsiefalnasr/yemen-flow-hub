<script setup lang="ts">
import { Users } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

defineProps<{
  claimerName: string
}>()
</script>

<template>
  <Alert class="border-warning flex items-center gap-3 bg-[var(--color-surface-warning)]">
    <Users class="h-5 w-5 flex-shrink-0 text-[var(--color-text-warning)]" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium">
      الطلب محجوز للمراجع <strong>{{ claimerName }}</strong
      >. يمكنك الاطلاع فقط حتى يحرر الحجز.
    </AlertDescription>
    <Badge
      variant="secondary"
      class="bg-[var(--color-surface-warning)]0 flex-shrink-0 text-white hover:bg-[var(--color-surface-warning)]"
      >قيد المراجعة</Badge
    >
  </Alert>
</template>
