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
  <Alert dir="rtl" class="flex items-center gap-3 border-[#5856d6]/30 bg-[#5856d6]/5">
    <Vote class="h-5 w-5 flex-shrink-0 text-[#5856d6]" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium text-foreground">
      <span class="font-semibold text-[#5856d6]">صوتك مطلوب على هذه الجلسة</span>
      <span v-if="votesCast != null && totalVoters != null" class="text-muted-foreground font-normal ms-2">
        — {{ votesCast }}/{{ totalVoters }} صوتوا حتى الآن
      </span>
    </AlertDescription>
    <Badge class="flex-shrink-0 bg-[#5856d6] text-white hover:bg-[#5856d6]">قيد التصويت</Badge>
  </Alert>
</template>
