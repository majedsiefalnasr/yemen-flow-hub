<script setup lang="ts">
import { Users } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

defineProps<{
  claimerName: string
}>()
</script>

<template>
  <Alert  class="flex items-center gap-3 border-warning bg-[var(--color-surface-warning)]">
    <Users class="h-5 w-5 flex-shrink-0 text-[var(--color-text-warning)]" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium">
      محجوز بواسطة <strong>{{ claimerName }}</strong> — يمكنك الاطلاع على الطلب فقط
    </AlertDescription>
    <Badge variant="secondary" class="flex-shrink-0 bg-[var(--color-surface-warning)]0 text-white hover:bg-[var(--color-surface-warning)]">قيد المراجعة</Badge>
  </Alert>
</template>
