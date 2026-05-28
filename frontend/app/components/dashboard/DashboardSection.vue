<script setup lang="ts">
defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <section class="space-y-4">
    <div v-if="title || description || $slots.actions" class="flex items-center justify-between gap-4">
      <div class="min-w-0">
        <h2 v-if="title" class="text-base font-semibold leading-none tracking-tight">{{ title }}</h2>
        <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <slot />
    </div>
  </section>
</template>
