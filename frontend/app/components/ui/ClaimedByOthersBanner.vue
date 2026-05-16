<script setup lang="ts">
defineProps<{
  claimerName: string
}>()
</script>

<template>
  <div class="claimed-others-banner" role="alert" aria-live="polite" dir="rtl">
    <span class="claimed-icon" aria-hidden="true">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
      </svg>
    </span>
    <span class="claimed-message">
      محجوز بواسطة <strong>{{ claimerName }}</strong> — يمكنك الاطلاع على الطلب فقط
    </span>
    <span class="claimed-badge">قيد المراجعة</span>
  </div>
</template>

<style scoped>
.claimed-others-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fff9f0;
  border: 1px solid #ff9f0a33;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
}

.claimed-icon {
  display: flex;
  flex-shrink: 0;
  color: #ff9f0a;
}

.claimed-message {
  flex: 1;
  color: #7a4800;
}

.claimed-badge {
  padding: 3px 10px;
  background: #ff9f0a;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  border-radius: 20px;
  flex-shrink: 0;
}
</style>
