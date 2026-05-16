<script setup lang="ts">
// No props — banner is shown whenever the current user is the active claim holder
</script>

<template>
  <div class="active-review-banner" role="status" aria-live="polite" dir="rtl">
    <span class="active-icon" aria-hidden="true">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </svg>
    </span>
    <span class="active-message">أنت المراجع النشط — الطلب محجوز لك حالياً</span>
    <span class="active-badge">مراجعة نشطة</span>
  </div>
</template>

<style scoped>
.active-review-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f0fff4;
  border: 1px solid #34c75933;
  border-radius: 12px;
  color: #34c759;
  font-size: 15px;
  font-weight: 500;
}

.active-icon {
  display: flex;
  flex-shrink: 0;
}

.active-message {
  flex: 1;
  color: #1d5c2e;
}

.active-badge {
  padding: 3px 10px;
  background: #34c759;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  border-radius: 20px;
  flex-shrink: 0;
}
</style>
