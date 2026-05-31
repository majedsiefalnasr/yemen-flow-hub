<script setup lang="ts">
import { computed } from 'vue'

interface Series {
  label: string
  values: number[]
  color: string
}

const props = defineProps<{
  labels: string[]
  series: Series[]
}>()

const width = 600
const height = 220
const padLeft = 30
const padRight = 10
const padTop = 16
const padBottom = 36

const maxValue = computed(() => {
  let max = 0
  for (const s of props.series) {
    for (const v of s.values) {
      if (v > max) max = v
    }
  }
  return max || 1
})

function xPos(index: number): number {
  const count = props.labels.length
  if (count <= 1) return padLeft + (width - padLeft - padRight) / 2
  return padLeft + (index / (count - 1)) * (width - padLeft - padRight)
}

function yPos(value: number): number {
  return padTop + (height - padTop - padBottom) * (1 - value / maxValue.value)
}

function points(values: number[]): string {
  return values.map((v, i) => `${xPos(i)},${yPos(v)}`).join(' ')
}

const yTicks = computed(() => {
  const steps = 4
  return Array.from({ length: steps + 1 }, (_, i) => ({
    y: yPos((maxValue.value * i) / steps),
    label: Math.round((maxValue.value * i) / steps),
  })).reverse()
})
</script>

<template>
  <div class="flex flex-col gap-3" >
    <div v-if="!series.length || !labels.length || series.some((s) => s.values.length !== labels.length)" class="h-44 flex items-center justify-center text-[var(--color-text-subtle)] text-sm">لا توجد بيانات</div>
    <svg v-else :viewBox="`0 0 ${width} ${height}`" class="w-full h-auto overflow-visible" aria-label="مخطط خطي">
      <line
        v-for="tick in yTicks"
        :key="tick.label"
        :x1="padLeft"
        :y1="tick.y"
        :x2="width - padRight"
        :y2="tick.y"
        :stroke="'var(--border)'"
        stroke-width="1"
      />
      <text
        v-for="tick in yTicks"
        :key="`label-${tick.label}`"
        :x="padLeft - 4"
        :y="tick.y + 4"
        text-anchor="end"
        font-size="10"
        :fill="'var(--muted-foreground)'"
      >{{ tick.label }}</text>
      <polyline
        v-for="s in series"
        :key="s.label"
        :points="points(s.values)"
        :stroke="s.color"
        fill="none"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <template v-for="s in series" :key="`dots-${s.label}`">
        <circle
          v-for="(v, i) in s.values"
          :key="i"
          :cx="xPos(i)"
          :cy="yPos(v)"
          r="3"
          :fill="s.color"
        />
      </template>
      <text
        v-for="(label, i) in labels"
        :key="`x-${label}`"
        :x="xPos(i)"
        :y="height - 8"
        text-anchor="middle"
        font-size="10"
        :fill="'var(--muted-foreground)'"
      >{{ label }}</text>
    </svg>
    <div class="flex gap-4 justify-center flex-wrap">
      <div v-for="s in series" :key="`leg-${s.label}`" class="flex items-center gap-1.5 text-sm text-[var(--color-text-primary)]">
        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: s.color }" />
        <span>{{ s.label }}</span>
      </div>
    </div>
  </div>
</template>
