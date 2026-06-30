<script setup lang="ts">
import { useThemingStore } from '@/stores/theming.store'

const themingStore = useThemingStore()

defineProps<{
  items?: any[]
}>()
</script>

<template>
  <TransitionGroup
    tag="ul"
    :name="themingStore.prefersReducedMotion ? '' : 'animated-list'"
    class="space-y-2"
  >
    <slot />
  </TransitionGroup>
</template>

<style scoped>
.animated-list-enter-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.animated-list-leave-active {
  transition: all 0.2s ease-in;
}
.animated-list-enter-from {
  opacity: 0;
  transform: scale(0.9) translateY(-8px);
}
.animated-list-leave-to {
  opacity: 0;
  transform: scale(0.9);
}
@media (prefers-reduced-motion: reduce) {
  .animated-list-enter-active,
  .animated-list-leave-active {
    transition: none;
  }
  .animated-list-enter-from,
  .animated-list-leave-to {
    opacity: 1;
    transform: none;
  }
}
</style>
