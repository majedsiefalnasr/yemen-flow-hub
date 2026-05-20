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
  <div class="line-chart" dir="rtl">
    <div v-if="!series.length || !labels.length" class="chart-empty">لا توجد بيانات</div>
    <svg v-else :viewBox="`0 0 ${width} ${height}`" class="chart-svg" aria-label="مخطط خطي">
      <!-- Y-axis grid lines -->
      <line
        v-for="tick in yTicks"
        :key="tick.label"
        :x1="padLeft"
        :y1="tick.y"
        :x2="width - padRight"
        :y2="tick.y"
        stroke="#e5e7eb"
        stroke-width="1"
      />
      <!-- Y-axis labels -->
      <text
        v-for="tick in yTicks"
        :key="`label-${tick.label}`"
        :x="padLeft - 4"
        :y="tick.y + 4"
        text-anchor="end"
        font-size="10"
        fill="#6c757d"
      >{{ tick.label }}</text>
      <!-- Series lines -->
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
      <!-- Data points -->
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
      <!-- X-axis labels (RTL: reverse order visually, but keep data order) -->
      <text
        v-for="(label, i) in labels"
        :key="`x-${label}`"
        :x="xPos(i)"
        :y="height - 8"
        text-anchor="middle"
        font-size="10"
        fill="#6c757d"
      >{{ label }}</text>
    </svg>
    <!-- Legend -->
    <div class="chart-legend">
      <div v-for="s in series" :key="`leg-${s.label}`" class="legend-item">
        <span class="legend-dot" :style="{ background: s.color }" />
        <span class="legend-label">{{ s.label }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.line-chart {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.chart-svg {
  width: 100%;
  height: auto;
  overflow: visible;
}
.chart-empty {
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  font-size: 14px;
}
.chart-legend {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #1c222b;
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.legend-label {
  white-space: nowrap;
}
</style>
