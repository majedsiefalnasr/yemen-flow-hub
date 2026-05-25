<script setup lang="ts">
import { computed } from 'vue'

interface CurrencyBar {
  currency: string
  amount: number
}

const props = defineProps<{
  data: CurrencyBar[]
}>()

const maxAmount = computed(() => Math.max(...props.data.map((d) => d.amount), 1))

function formatAmount(v: number): string {
  if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`
  if (v >= 1_000) return `${(v / 1_000).toFixed(1)}K`
  return v.toFixed(0)
}
</script>

<template>
  <div class="flex flex-col gap-2" dir="rtl">
    <div v-if="!data.length" class="h-28 flex items-center justify-center text-gray-600 text-sm">لا توجد بيانات</div>
    <div v-else class="flex flex-col gap-3" role="list" aria-label="مخطط تمويل العملات">
      <div v-for="item in data" :key="item.currency" class="flex items-center gap-2.5" role="listitem">
        <span class="w-12 text-xs font-semibold text-gray-900 flex-shrink-0 text-end">{{ item.currency }}</span>
        <div class="flex-1 h-3 bg-gray-50 rounded-full overflow-hidden">
          <div
            class="h-full bg-primary rounded-full transition-all duration-500"
            :style="{ width: `${(item.amount / maxAmount) * 100}%`, minWidth: item.amount > 0 ? '2px' : '0' }"
          />
        </div>
        <span class="w-13 text-xs text-gray-600 flex-shrink-0 text-start font-tabular-nums">{{ formatAmount(item.amount) }}</span>
      </div>
    </div>
  </div>
</template>
