<script setup lang="ts">
import { computed } from 'vue'
import type { RequestStatus } from '../../types/enums'
import { STATUS_PROGRESS } from '../../constants/workflow'

const props = defineProps<{
  status: RequestStatus
}>()

const progress = computed(() => STATUS_PROGRESS[props.status] ?? 0)
</script>

<template>
  <div class="request-progress">
    <div class="progress-track" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-fill" :style="{ width: `${progress}%` }" />
    </div>
    <span class="progress-label">{{ progress }}%</span>
  </div>
</template>

<style scoped>
.request-progress {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 80px;
}

.progress-track {
  height: 6px;
  background: var(--color-border, #cccccc);
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #0066cc;
  border-radius: 3px;
  transition: width 200ms ease;
}

.progress-label {
  font-size: 10px;
  color: var(--color-text-secondary, #6c757d);
  font-variant-numeric: tabular-nums;
}
</style>
