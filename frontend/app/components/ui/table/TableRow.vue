<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <tr
    data-slot="table-row"
    :class="cn('hover:bg-gray-50/50 data-[state=selected]:bg-gray-50 border-b transition-colors has-aria-expanded:bg-gray-50/50', props.class)"
  >
    <slot />
  </tr>
</template>
