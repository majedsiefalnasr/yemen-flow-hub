<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <p
    data-slot="empty-description"
    :class="
      cn(
        'text-muted-foreground [&>a:hover]:text-primary max-w-full text-sm/relaxed break-words [&>a]:underline [&>a]:underline-offset-4',
        $attrs.class ?? '',
      )
    "
  >
    <slot />
  </p>
</template>
