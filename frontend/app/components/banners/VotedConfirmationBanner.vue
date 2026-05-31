<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

defineProps<{
  vote: 'approve' | 'reject'
  votedAt?: string | null
}>()

function formatTime(iso: string | null | undefined): string {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('ar-YE', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <Alert  class="flex items-center gap-3 border-border bg-muted/30">
    <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-muted-foreground" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium text-foreground">
      <span v-if="votedAt">صوّتت {{ formatTime(votedAt) }} — </span>
      <span v-else>لقد صوّتت — </span>
      <span :class="vote === 'approve' ? 'text-[var(--color-text-success)] font-semibold' : 'text-rose-600 font-semibold'">
        {{ vote === 'approve' ? 'اعتمدت' : 'رفضت' }}
      </span>
    </AlertDescription>
    <Badge variant="secondary" class="flex-shrink-0">تم التصويت</Badge>
  </Alert>
</template>
