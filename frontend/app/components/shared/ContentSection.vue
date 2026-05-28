<script setup lang="ts">
defineProps<{
  /** Section heading */
  title?: string
  /** Optional description below heading */
  description?: string
}>()
</script>

<template>
  <section class="space-y-4">
    <div v-if="title || description" class="border-b pb-3">
      <h3 v-if="title" class="text-base font-semibold">{{ title }}</h3>
      <p v-if="description" class="mt-0.5 text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <div>
      <slot />
    </div>
  </section>
</template>
