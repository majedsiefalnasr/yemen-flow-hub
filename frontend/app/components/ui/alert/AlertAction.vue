<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div data-slot="alert-action" :class="cn('absolute end-2 top-2', props.class)">
    <slot />
  </div>
</template>
