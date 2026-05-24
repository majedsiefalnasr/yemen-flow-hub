<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <span
    data-slot="command-shortcut"
    :class="cn('text-gray-600 group-data-selected/command-item:text-gray-900 ms-auto text-xs tracking-widest', props.class)"
  >
    <slot />
  </span>
</template>
