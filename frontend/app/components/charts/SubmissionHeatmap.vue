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
  <div class="heatmap" dir="rtl">
    <div v-if="!data.length" class="chart-empty">لا توجد بيانات</div>
    <div v-else class="heatmap-grid">
      <!-- Corner -->
      <div class="corner-cell" />
      <!-- Slot headers (top row) -->
      <div v-for="slot in SLOTS" :key="`slot-${slot}`" class="slot-header">
        {{ SLOT_LABELS[slot] }}
      </div>
      <!-- Rows per day -->
      <template v-for="day in DAYS" :key="`day-${day}`">
        <div class="day-header">{{ DAY_LABELS[day] }}</div>
        <div
          v-for="slot in SLOTS"
          :key="`cell-${day}-${slot}`"
          class="heat-cell"
          :style="{ background: `rgba(0, 102, 204, ${intensity(day, slot)})` }"
          :title="`${DAY_LABELS[day]} ${SLOT_LABELS[slot]}: ${countFor(day, slot)}`"
          :aria-label="`${DAY_LABELS[day]} ${SLOT_LABELS[slot]}: ${countFor(day, slot)} طلب`"
        />
      </template>
    </div>
    <!-- Intensity scale -->
    <div class="heatmap-scale">
      <span class="scale-label">أقل</span>
      <div class="scale-bar" />
      <span class="scale-label">أكثر</span>
    </div>
  </div>
</template>

<style scoped>
.heatmap {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.chart-empty {
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  font-size: 14px;
}
.heatmap-grid {
  display: grid;
  grid-template-columns: 72px repeat(6, 1fr);
  gap: 4px;
}
.corner-cell {
  /* empty top-left */
}
.slot-header {
  font-size: 10px;
  color: #6c757d;
  text-align: center;
  padding: 2px 0;
}
.day-header {
  font-size: 12px;
  color: #1c222b;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding-left: 8px;
}
.heat-cell {
  height: 28px;
  border-radius: 4px;
  background: rgba(0, 102, 204, 0);
  border: 1px solid #e5e7eb;
  cursor: default;
  transition: opacity 0.2s;
}
.heat-cell:hover {
  opacity: 0.8;
}
.heatmap-scale {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: #6c757d;
}
.scale-bar {
  flex: 1;
  height: 8px;
  border-radius: 4px;
  background: linear-gradient(to left, rgba(0,102,204,1), rgba(0,102,204,0.1));
  max-width: 140px;
}
.scale-label {
  white-space: nowrap;
}
</style>
