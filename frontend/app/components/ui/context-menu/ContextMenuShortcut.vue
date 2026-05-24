<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <span
    data-slot="context-menu-shortcut"
    :class="cn('text-gray-600 group-focus/context-menu-item:text-accent-foreground ms-auto text-xs tracking-widest', props.class)"
  >
    <slot />
  </span>
</template>
