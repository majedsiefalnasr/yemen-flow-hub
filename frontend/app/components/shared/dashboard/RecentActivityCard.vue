<script setup lang="ts">
import AnalyticsCard from './AnalyticsCard.vue'

withDefaults(
  defineProps<{
    title: string
    description?: string
    hasItems?: boolean
    emptyText?: string
    cardClass?: string
    contentClass?: string
  }>(),
  {
    hasItems: true,
    emptyText: 'لا توجد عناصر حديثة',
  },
)
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
    <slot v-if="hasItems" />
    <div v-else class="text-muted-foreground py-10 text-center text-sm" role="status">
      {{ emptyText }}
    </div>
  </AnalyticsCard>
</template>
