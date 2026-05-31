<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

withDefaults(defineProps<{
  title: string
  description?: string
  contentClass?: string
  cardClass?: string
  state?: 'default' | 'loading' | 'empty' | 'error'
  emptyText?: string
  errorText?: string
}>(), {
  state: 'default',
  emptyText: 'لا توجد بيانات',
  errorText: 'تعذّر تحميل البيانات',
})
</script>

<template>
  <Card class="border-0 shadow" :class="cardClass">
    <CardHeader class="pb-2">
      <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
          <CardTitle class="text-sm font-semibold">{{ title }}</CardTitle>
          <CardDescription v-if="description" class="text-xs">{{ description }}</CardDescription>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
          <slot name="actions" />
        </div>
      </div>
    </CardHeader>
    <CardContent :class="contentClass ?? 'p-4'">
      <template v-if="state === 'loading'">
        <slot name="loading">
          <div class="space-y-2" role="status" aria-live="polite">
            <Skeleton class="h-5 w-2/3" />
            <Skeleton class="h-16 w-full" />
          </div>
        </slot>
      </template>
      <template v-else-if="state === 'empty'">
        <slot name="empty">
          <div class="py-8 text-center text-sm text-muted-foreground">{{ emptyText }}</div>
        </slot>
      </template>
      <template v-else-if="state === 'error'">
        <slot name="error">
          <div class="py-8 text-center text-sm text-[var(--severity-red)]">{{ errorText }}</div>
        </slot>
      </template>
      <slot v-else />
    </CardContent>
    <div v-if="$slots.footer" class="px-4 pb-4">
      <slot name="footer" />
    </div>
  </Card>
</template>
