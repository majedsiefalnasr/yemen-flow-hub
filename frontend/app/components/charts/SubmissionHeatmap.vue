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
  <div class="flex flex-col gap-3">
    <div
      v-if="!data.length"
      class="text-muted-foreground flex h-40 items-center justify-center text-sm"
    >
      لا توجد بيانات
    </div>
    <div v-else class="grid gap-1" style="grid-template-columns: 5rem repeat(6, 1fr)">
      <div />
      <div
        v-for="slot in SLOTS"
        :key="`slot-${slot}`"
        class="text-muted-foreground px-0 py-0.5 text-center text-xs"
      >
        {{ SLOT_LABELS[slot] }}
      </div>
      <template v-for="day in DAYS" :key="`day-${day}`">
        <div class="text-foreground flex items-center justify-end ps-2 text-xs">
          {{ DAY_LABELS[day] }}
        </div>
        <div
          v-for="slot in SLOTS"
          :key="`cell-${day}-${slot}`"
          class="border-border h-7 cursor-default rounded border transition-opacity duration-200 hover:opacity-80"
          :style="{
            background: `color-mix(in srgb, var(--color-brand) ${Math.round(intensity(day, slot) * 100)}%, transparent)`,
          }"
          :title="`${DAY_LABELS[day]} ${SLOT_LABELS[slot]}: ${countFor(day, slot)}`"
          :aria-label="`${DAY_LABELS[day]} ${SLOT_LABELS[slot]}: ${countFor(day, slot)} طلب`"
        />
      </template>
    </div>
    <div class="text-muted-foreground flex items-center gap-2 text-xs">
      <span class="whitespace-nowrap">أقل</span>
      <div
        class="h-2 max-w-32 flex-1 rounded"
        style="
          background: linear-gradient(
            to left,
            var(--color-brand),
            color-mix(in srgb, var(--color-brand) 10%, transparent)
          );
        "
      />
      <span class="whitespace-nowrap">أكثر</span>
    </div>
  </div>
</template>
