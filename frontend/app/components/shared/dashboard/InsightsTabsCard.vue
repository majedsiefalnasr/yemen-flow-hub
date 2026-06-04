<script setup lang="ts">
import AnalyticsCard from './AnalyticsCard.vue'

defineProps<{
  title: string
  description?: string
  cardClass?: string
  contentClass?: string
}>()
</script>

<template>
  <AnalyticsCard
    :title="title"
    :description="description"
    :card-class="cardClass"
    :content-class="contentClass ?? 'p-4'"
  >
    <template #actions>
      <slot name="actions" />
    </template>
    <div class="grid grid-cols-[minmax(0,1fr)_18rem] gap-4 max-xl:grid-cols-1">
      <div class="min-w-0">
        <slot />
      </div>
      <aside v-if="$slots.aside" class="space-y-3">
        <slot name="aside" />
      </aside>
    </div>
  </AnalyticsCard>
</template>
