<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <span
    data-slot="menubar-shortcut"
    :class="cn('text-gray-600 group-focus/menubar-item:text-accent-foreground text-xs tracking-widest ms-auto', props.class)"
  >
    <slot />
  </span>
</template>
