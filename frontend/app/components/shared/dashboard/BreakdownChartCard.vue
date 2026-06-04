<script setup lang="ts">
import AnalyticsCard from './AnalyticsCard.vue'

withDefaults(
  defineProps<{
    title: string
    description?: string
    hasData?: boolean
    emptyText?: string
    cardClass?: string
    contentClass?: string
  }>(),
  {
    hasData: true,
    emptyText: 'لا توجد بيانات للفترة المحددة',
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
    <div v-if="hasData" class="space-y-3">
      <slot />
      <div v-if="$slots.legend" class="space-y-2">
        <slot name="legend" />
      </div>
    </div>
    <div v-else class="text-muted-foreground py-10 text-center text-sm" role="status">
      {{ emptyText }}
    </div>
  </AnalyticsCard>
</template>
