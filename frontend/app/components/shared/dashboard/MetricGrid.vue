<script setup lang="ts">
const props = withDefaults(defineProps<{
  columns?: 3 | 4 | 5 | 6
}>(), {
  columns: 4,
})

const columnsClassMap: Record<number, string> = {
  3: 'grid-cols-3 max-lg:grid-cols-2 max-md:grid-cols-1',
  4: 'grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1',
  5: 'grid-cols-5 max-xl:grid-cols-3 max-md:grid-cols-2 max-sm:grid-cols-1',
  6: 'grid-cols-6 max-xl:grid-cols-3 max-md:grid-cols-2',
}
</script>

<template>
  <div class="grid gap-4" :class="columnsClassMap[props.columns]">
    <slot />
  </div>
</template>

