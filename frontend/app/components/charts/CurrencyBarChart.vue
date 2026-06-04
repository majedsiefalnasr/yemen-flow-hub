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
  <div class="flex flex-col gap-2">
    <div
      v-if="!data.length"
      class="flex h-28 items-center justify-center text-sm text-[var(--color-text-subtle)]"
    >
      لا توجد بيانات
    </div>
    <div v-else class="flex flex-col gap-3" role="list" aria-label="مخطط تمويل العملات">
      <div
        v-for="item in data"
        :key="item.currency"
        class="flex items-center gap-2.5"
        role="listitem"
      >
        <span
          class="w-12 flex-shrink-0 text-end text-xs font-semibold text-[var(--color-text-primary)]"
          >{{ item.currency }}</span
        >
        <div class="h-3 flex-1 overflow-hidden rounded-full bg-[var(--color-surface-subtle)]">
          <div
            class="bg-primary h-full rounded-full transition-all duration-500"
            :style="{
              width: `${(item.amount / maxAmount) * 100}%`,
              minWidth: item.amount > 0 ? '2px' : '0',
            }"
          />
        </div>
        <span
          class="font-tabular-nums w-13 flex-shrink-0 text-start text-xs text-[var(--color-text-subtle)]"
          >{{ formatAmount(item.amount) }}</span
        >
      </div>
    </div>
  </div>
</template>
