<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'

type MetricTone = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'voting' | 'muted'
type MetricTrendDirection = 'up' | 'down' | 'neutral'

const props = withDefaults(defineProps<{
  label: string
  value: number | string
  icon?: Component
  tone?: MetricTone
  trend?: { direction: MetricTrendDirection, value: string }
  description?: string
  previousLabel?: string
  href?: string
  /** Whether this card's filter is currently active — highlights border with primary ring color */
  active?: boolean
  clickable?: boolean
  dataTestid?: string
}>(), {
  tone: 'default',
  clickable: true,
})

const emit = defineEmits<{
  click: [event: MouseEvent | KeyboardEvent]
}>()

const iconToneClass = computed(() => {
  const map: Record<MetricTone, string> = {
    default: 'text-muted-foreground bg-muted',
    success: 'text-[var(--severity-green)] bg-[var(--severity-green)]/10',
    warning: 'text-[var(--severity-amber)] bg-[var(--severity-amber)]/10',
    danger: 'text-[var(--severity-red)] bg-[var(--severity-red)]/10',
    info: 'text-[var(--info)] bg-[var(--info)]/10',
    voting: 'text-[var(--voting)] bg-[var(--voting)]/10',
    muted: 'text-muted-foreground bg-muted',
  }
  return map[props.tone]
})

const normalizedTrend = computed(() => props.trend ?? { direction: 'neutral' as const, value: '0%' })

const trendClass = computed(() => {
  if (normalizedTrend.value.direction === 'up') return 'bg-[color-mix(in_srgb,var(--severity-green)_12%,transparent)] text-[var(--severity-green)]'
  if (normalizedTrend.value.direction === 'down') return 'bg-[color-mix(in_srgb,var(--severity-red)_12%,transparent)] text-[var(--severity-red)]'
  return 'bg-muted text-muted-foreground'
})

function onCardClick(event: MouseEvent | KeyboardEvent) {
  if (!props.clickable) return
  emit('click', event)
}
</script>

<template>
  <component
    :is="href ? 'a' : 'div'"
    :href="href"
    class="block"
    :data-testid="dataTestid"
  >
    <Card
      class="border-0 p-4 shadow flex flex-col gap-1.5 transition-all"
      :class="[
        clickable ? 'cursor-pointer hover:shadow-md focus-visible:outline-none' : '',
        active ? 'ring-2 ring-ring ring-offset-0' : '',
      ]"
      :role="clickable ? 'button' : undefined"
      :tabindex="clickable ? 0 : undefined"
      :aria-label="`${label}: ${value}`"
      @click="onCardClick"
      @keydown.enter="onCardClick"
      @keydown.space.prevent="onCardClick"
    >
      <div class="flex items-start justify-between gap-2">
        <slot name="icon">
          <div v-if="icon" class="h-9 w-9 rounded flex items-center justify-center" :class="iconToneClass">
            <component :is="icon" class="h-5 w-5" aria-hidden="true" />
          </div>
        </slot>
        <Badge variant="secondary" class="flex items-center gap-1 text-xs font-medium" :class="trendClass">
          <TrendingUp v-if="normalizedTrend.direction === 'up'" class="h-3 w-3" />
          <TrendingDown v-else-if="normalizedTrend.direction === 'down'" class="h-3 w-3" />
          <Minus v-else class="h-3 w-3" />
          {{ normalizedTrend.value }}
        </Badge>
      </div>
      <span class="text-xs text-muted-foreground">{{ label }}</span>
      <span class="text-2xl font-semibold leading-none text-foreground">{{ value }}</span>
      <span class="text-xs text-muted-foreground/80">{{ description ?? previousLabel ?? '—' }}</span>
      <slot name="footer" />
    </Card>
  </component>
</template>
