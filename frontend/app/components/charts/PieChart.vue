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
  <div class="flex flex-col items-center gap-4" >
    <div v-if="!data.length || total === 0" class="h-44 flex items-center justify-center text-gray-600 text-sm">لا توجد بيانات</div>
    <template v-else>
      <div class="w-44 h-44">
        <svg viewBox="0 0 42 42" class="w-full h-full" aria-label="مخطط دائري">
          <circle cx="21" cy="21" r="15.9155" fill="none" :stroke="'var(--muted)'" stroke-width="3" />
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
            :stroke-dashoffset="25 - slice.offset"
          />
          <text x="21" y="20.5" text-anchor="middle" font-size="5" font-weight="600" :fill="'var(--foreground)'">{{ total.toLocaleString('ar-EG') }}</text>
          <text x="21" y="25" text-anchor="middle" font-size="3.5" :fill="'var(--muted-foreground)'">إجمالي</text>
        </svg>
      </div>
      <div class="flex flex-col gap-2 w-full">
        <div v-for="slice in slices" :key="slice.label" class="flex items-center gap-2 text-sm text-gray-900">
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: slice.color }" />
          <span class="flex-1">{{ slice.label }}</span>
          <span class="text-xs text-gray-600 font-tabular-nums">{{ slice.pct.toFixed(1) }}%</span>
        </div>
      </div>
    </template>
  </div>
</template>
