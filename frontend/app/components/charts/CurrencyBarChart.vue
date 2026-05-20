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
  <div class="currency-bar-chart" dir="rtl">
    <div v-if="!data.length" class="chart-empty">لا توجد بيانات</div>
    <div v-else class="bars" role="list" aria-label="مخطط تمويل العملات">
      <div v-for="item in data" :key="item.currency" class="bar-row" role="listitem">
        <span class="currency-label">{{ item.currency }}</span>
        <div class="bar-track">
          <div
            class="bar-fill"
            :style="{ width: `${(item.amount / maxAmount) * 100}%` }"
          />
        </div>
        <span class="amount-label">{{ formatAmount(item.amount) }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.currency-bar-chart {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.chart-empty {
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  font-size: 14px;
}
.bars {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.bar-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.currency-label {
  width: 48px;
  font-size: 13px;
  font-weight: 600;
  color: #1c222b;
  flex-shrink: 0;
  text-align: right;
}
.bar-track {
  flex: 1;
  height: 12px;
  background: #f5f5f7;
  border-radius: 6px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  background: #0066cc;
  border-radius: 6px;
  transition: width 0.4s ease;
  min-width: 2px;
}
.amount-label {
  width: 52px;
  font-size: 12px;
  color: #6c757d;
  text-align: left;
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}
</style>
