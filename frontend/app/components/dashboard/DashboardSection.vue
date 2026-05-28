<script setup lang="ts">
const props = withDefaults(defineProps<{
  /** Section heading */
  title?: string
  /** Columns in the KPI grid */
  columns?: 2 | 3 | 4
}>(), {
  columns: 4,
})

const gridClass = computed(() => {
  const map: Record<number, string> = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  }
  return map[props.columns]
})
</script>

<template>
  <section class="mb-6 space-y-3">
    <h2 v-if="title" class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
      {{ title }}
    </h2>
    <div :class="['grid gap-4', gridClass]">
      <slot />
    </div>
  </section>
</template>
