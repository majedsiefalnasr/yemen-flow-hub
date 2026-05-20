<script setup lang="ts">
import { computed } from 'vue'

interface Slice {
  label: string
  value: number
  color: string
}

const props = defineProps<{
  data: Slice[]
}>()

const total = computed(() => props.data.reduce((s, d) => s + d.value, 0))

// SVG stroke-dasharray pie using r=15.9155 (circumference ≈ 100)
const r = 15.9155
const cx = 21
const cy = 21

const slices = computed(() => {
  let offset = 25 // start at top (12 o'clock position = 25% offset)
  return props.data.map((d) => {
    const pct = total.value > 0 ? (d.value / total.value) * 100 : 0
    const slice = { ...d, pct, offset }
    offset += pct
    return slice
  })
})
</script>

<template>
  <div class="pie-chart" dir="rtl">
    <div v-if="!data.length || total === 0" class="chart-empty">لا توجد بيانات</div>
    <template v-else>
      <div class="pie-wrapper">
        <svg viewBox="0 0 42 42" class="pie-svg" aria-label="مخطط دائري">
          <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#f5f5f7" stroke-width="3" />
          <circle
            v-for="(slice, i) in slices"
            :key="i"
            :cx="cx"
            :cy="cy"
            :r="r"
            fill="none"
            :stroke="slice.color"
            stroke-width="3"
            :stroke-dasharray="`${slice.pct} ${100 - slice.pct}`"
            :stroke-dashoffset="100 - slice.offset + 25"
          />
          <text x="21" y="20.5" text-anchor="middle" font-size="5" font-weight="600" fill="#1c222b">
            {{ total.toLocaleString('ar-EG') }}
          </text>
          <text x="21" y="25" text-anchor="middle" font-size="3.5" fill="#6c757d">إجمالي</text>
        </svg>
      </div>
      <div class="pie-legend">
        <div v-for="slice in slices" :key="slice.label" class="legend-item">
          <span class="legend-dot" :style="{ background: slice.color }" />
          <span class="legend-text">{{ slice.label }}</span>
          <span class="legend-pct">{{ slice.pct.toFixed(1) }}%</span>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.pie-chart {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}
.pie-wrapper {
  width: 180px;
  height: 180px;
}
.pie-svg {
  width: 100%;
  height: 100%;
}
.chart-empty {
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  font-size: 14px;
}
.pie-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #1c222b;
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.legend-text {
  flex: 1;
}
.legend-pct {
  color: #6c757d;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}
</style>
