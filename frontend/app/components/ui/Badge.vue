<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'default' | 'secondary' | 'destructive'
}>(), {
  variant: 'default',
})

const badgeClass = computed(() => `badge badge--${props.variant}`)
</script>

<template>
  <span :class="badgeClass">
    <slot />
  </span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid transparent;
  white-space: nowrap;
  font-size: 12px;
  font-weight: 500;
  line-height: 1;
}

.badge--default {
  background: rgba(0, 102, 204, 0.08);
  border-color: rgba(0, 102, 204, 0.16);
  color: #0066cc;
}

.badge--secondary {
  background: #f5f5f7;
  border-color: #e5e7eb;
  color: #1c222b;
}

.badge--destructive {
  background: rgba(198, 40, 40, 0.08);
  border-color: rgba(198, 40, 40, 0.16);
  color: #c62828;
}
</style>
