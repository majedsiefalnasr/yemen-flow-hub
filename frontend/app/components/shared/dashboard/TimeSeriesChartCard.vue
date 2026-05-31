<script setup lang="ts">
import AnalyticsCard from './AnalyticsCard.vue'

withDefaults(defineProps<{
  title: string
  description?: string
  hasData?: boolean
  emptyText?: string
  cardClass?: string
  contentClass?: string
}>(), {
  hasData: true,
  emptyText: 'لا توجد بيانات للفترة المحددة',
})
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
    <slot v-if="hasData" />
    <div v-else class="py-10 text-center text-sm text-muted-foreground" role="status">
      {{ emptyText }}
    </div>
    <template #footer>
      <slot name="footer" />
    </template>
  </AnalyticsCard>
</template>
