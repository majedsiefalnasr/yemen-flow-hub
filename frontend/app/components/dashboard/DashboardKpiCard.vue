<script setup lang="ts">
import { computed } from 'vue'
import { Card } from '../ui/card'

const props = withDefaults(defineProps<{
  label: string
  value: number | string
  /** Semantic color variant */
  variant?: 'default' | 'green' | 'amber' | 'red' | 'indigo' | 'cyan' | 'blue'
  /** When true, adds a left/right accent border on the card */
  highlighted?: boolean
}>(), {
  variant: 'default',
})

const variantColorClass = computed(() => {
  const colorMap: Record<string, string> = {
    green: 'text-green-700',
    amber: 'text-amber-600',
    red: 'text-red-700',
    indigo: 'text-indigo-600',
    cyan: 'text-cyan-500',
    blue: 'text-blue-600',
  }
  return colorMap[props.variant] || 'text-gray-900'
})

const borderClass = computed(() => props.highlighted ? 'border-s-4' : '')
const borderColorClass = computed(() => props.highlighted ? variantColorClass.value : '')
</script>

<template>
  <Card class="border-0 p-4 shadow-card flex flex-col gap-1.5" :class="[borderClass, borderColorClass]">
    <slot name="icon" />
    <span class="text-2xl font-semibold leading-none" :class="variantColorClass">{{ value }}</span>
    <span class="text-xs text-gray-600">{{ label }}</span>
  </Card>
</template>
