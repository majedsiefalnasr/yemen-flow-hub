<script setup lang="ts">
import { useThemingStore } from '@/stores/theming.store'

withDefaults(defineProps<{
  /** TransitionGroup tag */
  tag?: string
  /** CSS class applied to each entering item */
  enterClass?: string
  /** Duration in ms — ignored when reduced motion is active */
  duration?: number
}>(), {
  tag: 'ul',
  duration: 200,
})

const themingStore = useThemingStore()
const reduced = computed(() => themingStore.prefersReducedMotion)
</script>

<template>
  <TransitionGroup
    :tag="tag"
    :name="reduced ? undefined : 'animated-list'"
    :css="!reduced"
    v-bind="$attrs"
  >
    <slot />
  </TransitionGroup>
</template>

<style scoped>
.animated-list-enter-active,
.animated-list-leave-active {
  transition: opacity v-bind('`${duration}ms`') ease, transform v-bind('`${duration}ms`') ease;
}
.animated-list-enter-from {
  opacity: 0;
  transform: translateY(-4px);
}
.animated-list-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
.animated-list-move {
  transition: transform v-bind('`${duration}ms`') ease;
}
</style>
