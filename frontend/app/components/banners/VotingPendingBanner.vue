<script setup lang="ts">
import { Vote } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

defineProps<{
  votesCast?: number
  totalVoters?: number
}>()
</script>

<template>
  <Alert class="flex items-center gap-3 border-[var(--voting)]/30 bg-[var(--voting)]/5">
    <Vote class="h-5 w-5 flex-shrink-0 text-[var(--voting)]" aria-hidden="true" />
    <AlertDescription class="text-foreground flex-1 text-sm font-medium">
      <span class="font-semibold text-[var(--voting)]">صوتك مطلوب على هذه الجلسة</span>
      <span
        v-if="votesCast != null && totalVoters != null"
        class="text-muted-foreground ms-2 font-normal"
      >
        صوّت {{ votesCast }} من {{ totalVoters }} حتى الآن
      </span>
    </AlertDescription>
    <Badge class="flex-shrink-0 bg-[var(--voting)] text-white hover:bg-[var(--voting)]"
      >قيد التصويت</Badge
    >
  </Alert>
</template>
