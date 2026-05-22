<script setup lang="ts">
import { useInactivityTimer } from '../../composables/useInactivityTimer'

const { isWarning, extend } = useInactivityTimer()
</script>

<template>
  <div
    v-if="isWarning"
    class="inactivity-banner"
    role="status"
    aria-live="polite"
    dir="rtl"
  >
    <span class="inactivity-banner__text">
      أنت على وشك الخروج بسبب عدم النشاط — يرجى التفاعل للبقاء
    </span>
    <button
      type="button"
      class="inactivity-banner__btn"
      @click="extend()"
    >
      متابعة الجلسة
    </button>
  </div>
</template>

<style scoped>
.inactivity-banner {
  position: sticky;
  top: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 24px;
  background-color: #fff8e1;
  border-bottom: 1px solid #f57f17;
  color: #f57f17;
  font-size: 14px;
  font-weight: 500;
  text-align: right;
}

.inactivity-banner__text {
  flex: 1;
}

.inactivity-banner__btn {
  flex-shrink: 0;
  padding: 6px 16px;
  background-color: #f57f17;
  color: #fff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 120ms ease;
}

.inactivity-banner__btn:hover {
  background-color: #e65100;
}
</style>
