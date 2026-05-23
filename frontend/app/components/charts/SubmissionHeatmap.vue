<script setup lang="ts">
import { computed } from 'vue'

interface HeatCell {
  day: number
  slot: number
  count: number
}

const props = defineProps<{
  data: HeatCell[]
}>()

// 6 days (Sat=7, Sun=1, Mon=2, Tue=3, Wed=4, Thu=5) × 6 time slots (4-hour buckets 8–20)
const DAY_LABELS: Record<number, string> = {
  7: 'السبت',
  1: 'الأحد',
  2: 'الاثنين',
  3: 'الثلاثاء',
  4: 'الأربعاء',
  5: 'الخميس',
}
const DAYS = [7, 1, 2, 3, 4, 5]

const SLOT_LABELS: Record<number, string> = {
  8: '8ص-10ص',
  10: '10ص-12م',
  12: '12م-2م',
  14: '2م-4م',
  16: '4م-6م',
  18: '6م-8م',
}
const SLOTS = [8, 10, 12, 14, 16, 18]

const maxCount = computed(() => Math.max(...props.data.map((d) => d.count), 1))

function countFor(day: number, slot: number): number {
  const cell = props.data.find((d) => d.day === day && d.slot === slot)
  return cell?.count ?? 0
}

function intensity(day: number, slot: number): number {
  const c = countFor(day, slot)
  return c / maxCount.value
}
</script>

<template>
  <div class="flex flex-col gap-3" dir="rtl">
    <div v-if="!data.length" class="h-40 flex items-center justify-center text-gray-600 text-sm">لا توجد بيانات</div>
    <div v-else class="grid gap-1" style="grid-template-columns: 72px repeat(6, 1fr)">
      <div />
      <div v-for="slot in SLOTS" :key="`slot-${slot}`" class="text-xs text-gray-600 text-center px-0 py-0.5">
        {{ SLOT_LABELS[slot] }}
      </div>
      <template v-for="day in DAYS" :key="`day-${day}`">
        <div class="text-xs text-gray-900 flex items-center justify-end pl-2">{{ DAY_LABELS[day] }}</div>
        <div
          v-for="slot in SLOTS"
          :key="`cell-${day}-${slot}`"
          class="h-7 rounded border border-gray-200 cursor-default transition-opacity duration-200 hover:opacity-80"
          :style="{ background: `rgba(0, 102, 204, ${intensity(day, slot)})` }"
          :title="`${DAY_LABELS[day]} ${SLOT_LABELS[slot]}: ${countFor(day, slot)}`"
          :aria-label="`${DAY_LABELS[day]} ${SLOT_LABELS[slot]}: ${countFor(day, slot)} طلب`"
        />
      </template>
    </div>
    <div class="flex items-center gap-2 text-xs text-gray-600">
      <span class="whitespace-nowrap">أقل</span>
      <div class="flex-1 h-2 rounded max-w-32" style="background: linear-gradient(to left, rgba(0,102,204,1), rgba(0,102,204,0.1))" />
      <span class="whitespace-nowrap">أكثر</span>
    </div>
  </div>
</template>
