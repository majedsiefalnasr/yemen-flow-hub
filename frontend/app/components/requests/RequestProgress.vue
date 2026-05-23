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
  <div class="flex flex-col gap-1 min-w-20">
    <div class="h-1.5 bg-gray-300 rounded-full overflow-hidden" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
      <div class="h-full bg-blue-600 rounded-full transition-all" :style="{ width: `${progress}%` }" />
    </div>
    <span class="text-xs text-gray-600 font-tabular-nums">{{ progress }}%</span>
  </div>
</template>
