<script setup lang="ts">
import { Separator } from '@/components/ui/separator'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    maxWidth?: boolean
  }>(),
  {
    maxWidth: true,
  },
)
</script>

<template>
  <div class="space-y-6">
    <div v-if="title || description">
      <h3 v-if="title" class="text-lg font-medium">{{ title }}</h3>
      <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
    </div>
    <Separator />
    <div :class="maxWidth ? 'max-w-xl' : ''">
      <slot />
    </div>
  </div>
</template>
