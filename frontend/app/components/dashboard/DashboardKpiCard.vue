<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import { TrendingDown, TrendingUp } from 'lucide-vue-next'
import { Card } from '../ui/card'

const props = withDefaults(defineProps<{
  label: string
  value: number | string
  /** Semantic color variant */
  variant?: 'default' | 'green' | 'amber' | 'red' | 'indigo' | 'cyan' | 'blue'
  /** When true, adds a leading accent border on the card */
  highlighted?: boolean
  /** Optional icon component */
  icon?: Component
  /** Loading / error state */
  state?: 'idle' | 'loading' | 'error'
  /** Trend value: positive = up, negative = down */
  trend?: number
  /** Secondary label shown below value */
  subLabel?: string
  /** When set, wraps the card in a NuxtLink */
  href?: string
}>(), {
  variant: 'default',
  state: 'idle',
})

const variantColorClass = computed(() => {
  const colorMap: Record<string, string> = {
    green: 'text-[var(--severity-green)]',
    amber: 'text-[var(--severity-amber)]',
    red: 'text-[var(--severity-red)]',
    indigo: 'text-[var(--voting)]',
    cyan: 'text-[var(--swift)]',
    blue: 'text-primary',
  }
  return colorMap[props.variant] || 'text-foreground'
})

const borderClass = computed(() => props.highlighted ? 'border-s-4' : '')
const borderColorClass = computed(() => props.highlighted ? variantColorClass.value.replace('text-', 'border-') : '')

const trendIsUp = computed(() => (props.trend ?? 0) > 0)
const trendIsDown = computed(() => (props.trend ?? 0) < 0)
const trendAbs = computed(() => Math.abs(props.trend ?? 0))
</script>

<template>
  <component
    :is="href ? 'NuxtLink' : 'div'"
    :to="href"
    :class="['block', href ? 'transition-opacity hover:opacity-80' : '']"
  >
    <Card
      class="border-0 p-4 shadow flex flex-col gap-1.5"
      :class="[borderClass, borderColorClass]"
    >
      <!-- Loading skeleton -->
      <template v-if="state === 'loading'">
        <div class="h-4 w-4 animate-pulse rounded bg-muted" />
        <div class="h-7 w-16 animate-pulse rounded bg-muted" />
        <div class="h-3 w-24 animate-pulse rounded bg-muted" />
      </template>

      <!-- Error state -->
      <template v-else-if="state === 'error'">
        <span class="text-sm text-muted-foreground">—</span>
        <span class="text-xs text-destructive">{{ label }}</span>
      </template>

      <!-- Normal content -->
      <template v-else>
        <div v-if="icon || $slots.icon" class="flex items-center gap-1.5">
          <component :is="icon" v-if="icon" class="h-4 w-4 opacity-60" :class="variantColorClass" />
          <slot v-else name="icon" />
        </div>

        <span class="text-2xl font-semibold leading-none" :class="variantColorClass">{{ value }}</span>

        <span class="text-xs text-muted-foreground">{{ label }}</span>

        <span v-if="subLabel" class="text-[11px] text-muted-foreground/70">{{ subLabel }}</span>

        <div v-if="trend !== undefined" class="mt-0.5 flex items-center gap-1 text-xs">
          <TrendingUp v-if="trendIsUp" class="h-3 w-3 text-[var(--severity-green)]" />
          <TrendingDown v-else-if="trendIsDown" class="h-3 w-3 text-[var(--severity-red)]" />
          <span
            :class="[
              trendIsUp ? 'text-[var(--severity-green)]' : '',
              trendIsDown ? 'text-[var(--severity-red)]' : 'text-muted-foreground',
            ]"
          >
            {{ trendAbs }}%
          </span>
        </div>
      </template>
    </Card>
  </component>
</template>
