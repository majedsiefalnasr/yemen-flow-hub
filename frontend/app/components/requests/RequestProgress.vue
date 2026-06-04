<script setup lang="ts">
import { computed } from 'vue'
import type { RequestStatus, UserRole } from '../../types/enums'
import { getStatusProgress } from '../../constants/workflow'

const props = defineProps<{
  status: RequestStatus
  role: UserRole
}>()

const progress = computed(() => getStatusProgress(props.status, props.role))
</script>

<template>
  <div class="flex min-w-20 flex-col gap-1">
    <div
      class="bg-border h-1.5 overflow-hidden rounded-full"
      role="progressbar"
      :aria-valuenow="progress"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      <div
        class="bg-primary h-full rounded-full transition-all"
        :style="{ width: `${progress}%` }"
      />
    </div>
    <span class="text-muted-foreground font-tabular-nums text-xs">{{ progress }}%</span>
  </div>
</template>
