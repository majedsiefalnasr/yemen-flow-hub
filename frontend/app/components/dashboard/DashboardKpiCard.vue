<script setup lang="ts">
import { computed } from 'vue'
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'

const props = withDefaults(defineProps<{
  label: string
  value: number | string
  icon?: any
  state?: 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'default'
  trend?: { direction: 'up' | 'down' | 'neutral'; value: string }
  subLabel?: string
  href?: string
  /** @deprecated use state instead */
  variant?: 'default' | 'green' | 'amber' | 'red' | 'indigo' | 'cyan' | 'blue'
  highlighted?: boolean
}>(), {
  state: 'default',
})

const stateColorClass = computed(() => {
  const stateMap: Record<string, string> = {
    success: 'text-[var(--severity-green)]',
    warning: 'text-[var(--severity-amber)]',
    danger: 'text-[var(--severity-red)]',
    info: 'text-[var(--swift)]',
    neutral: 'text-muted-foreground',
    default: 'text-foreground',
  }
  const legacyMap: Record<string, string> = {
    green: 'text-[var(--severity-green)]',
    amber: 'text-[var(--severity-amber)]',
    red: 'text-[var(--severity-red)]',
    indigo: 'text-[var(--voting)]',
    cyan: 'text-[var(--swift)]',
    blue: 'text-primary',
  }
  if (props.variant && props.variant !== 'default') return legacyMap[props.variant] || 'text-foreground'
  return stateMap[props.state] || 'text-foreground'
})

const trendBadgeClass = computed(() => {
  if (!props.trend) return ''
  if (props.trend.direction === 'up') return 'bg-[color-mix(in_srgb,var(--severity-green)_12%,transparent)] text-[var(--severity-green)]'
  if (props.trend.direction === 'down') return 'bg-[color-mix(in_srgb,var(--severity-red)_12%,transparent)] text-[var(--severity-red)]'
  return 'bg-muted text-muted-foreground'
})

const borderClass = computed(() => props.highlighted ? 'border-s-4' : '')
const borderColorClass = computed(() => props.highlighted ? stateColorClass.value.replace('text-', 'border-') : '')
</script>

<template>
  <component :is="href ? 'a' : 'div'" :href="href" class="block">
    <Card class="flex flex-col gap-2 border-0 p-4 shadow-card" :class="[borderClass, borderColorClass]">
      <div class="flex items-start justify-between gap-2">
        <slot name="icon">
          <component v-if="icon" :is="icon" class="h-5 w-5 text-muted-foreground" />
        </slot>
        <Badge
          v-if="trend"
          variant="secondary"
          class="flex items-center gap-1 text-xs font-medium"
          :class="trendBadgeClass"
        >
          <TrendingUp v-if="trend.direction === 'up'" class="h-3 w-3" />
          <TrendingDown v-else-if="trend.direction === 'down'" class="h-3 w-3" />
          <Minus v-else class="h-3 w-3" />
          {{ trend.value }}
        </Badge>
      </div>
      <span class="text-2xl font-semibold leading-none" :class="stateColorClass">{{ value }}</span>
      <span class="text-xs text-muted-foreground">{{ label }}</span>
      <span v-if="subLabel" class="text-xs text-muted-foreground/70">{{ subLabel }}</span>
    </Card>
  </component>
</template>
