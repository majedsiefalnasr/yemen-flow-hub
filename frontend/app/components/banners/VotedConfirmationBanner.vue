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
  <Alert class="border-border bg-muted/30 flex items-center gap-3">
    <CheckCircle2 class="text-muted-foreground h-5 w-5 flex-shrink-0" aria-hidden="true" />
    <AlertDescription class="text-foreground flex-1 text-sm font-medium">
      <span v-if="votedAt">صوّتت {{ formatTime(votedAt) }} — </span>
      <span v-else>لقد صوّتت — </span>
      <span
        :class="
          vote === 'approve'
            ? 'font-semibold text-[var(--color-text-success)]'
            : 'font-semibold text-rose-600'
        "
      >
        {{ vote === 'approve' ? 'اعتمدت' : 'رفضت' }}
      </span>
    </AlertDescription>
    <Badge variant="secondary" class="flex-shrink-0">تم التصويت</Badge>
  </Alert>
</template>
