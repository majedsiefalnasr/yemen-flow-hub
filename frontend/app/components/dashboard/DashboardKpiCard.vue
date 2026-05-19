<script setup lang="ts">
defineProps<{
  label: string
  value: number | string
  /** Semantic color variant */
  variant?: 'default' | 'green' | 'amber' | 'red' | 'indigo' | 'cyan' | 'blue'
  /** When true, adds a left/right accent border on the card */
  highlighted?: boolean
}>()
</script>

<template>
  <div
    class="kpi-card"
    :class="[
      variant && variant !== 'default' ? `kpi-card--${variant}` : '',
      highlighted ? 'kpi-card--highlighted' : '',
    ]"
  >
    <slot name="icon" />
    <span class="kpi-card__value">{{ value }}</span>
    <span class="kpi-card__label">{{ label }}</span>
  </div>
</template>

<style scoped>
.kpi-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.kpi-card--highlighted {
  border-inline-start: 4px solid currentColor;
}

.kpi-card__value {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  line-height: 1;
}

.kpi-card__label {
  font-size: 13px;
  color: #6c757d;
}

/* Variant value colors */
.kpi-card--green .kpi-card__value  { color: #1b5e20; }
.kpi-card--amber .kpi-card__value  { color: #f57f17; }
.kpi-card--red .kpi-card__value    { color: #c62828; }
.kpi-card--indigo .kpi-card__value { color: #5856d6; }
.kpi-card--cyan .kpi-card__value   { color: #32ade6; }
.kpi-card--blue .kpi-card__value   { color: #0066cc; }
</style>
