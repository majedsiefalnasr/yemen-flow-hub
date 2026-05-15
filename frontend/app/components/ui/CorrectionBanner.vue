<script setup lang="ts">
defineProps<{
  rejectionReason?: string | null
}>()
</script>

<template>
  <div class="correction-banner" role="status" aria-live="polite" dir="rtl">
    <span class="correction-icon" aria-hidden="true">⚠️</span>
    <div class="correction-body">
      <span class="correction-message">تم إرجاع الطلب للتصحيح من المراجعة الداخلية — يرجى مراجعة الملاحظات وتعديل الطلب.</span>
      <span v-if="rejectionReason" class="correction-reason">سبب الإرجاع: {{ rejectionReason }}</span>
    </div>
  </div>
</template>

<style scoped>
.correction-banner {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  background: #fff7ed;
  border: 1px solid #ff9f0a55;
  border-radius: 12px;
  color: #9a5a00;
  font-size: 15px;
  font-weight: 500;
}

.correction-icon {
  font-size: 18px;
  flex-shrink: 0;
  margin-top: 1px;
}

.correction-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.correction-message {
  line-height: 1.5;
}

.correction-reason {
  font-size: 13px;
  color: #6b3d00;
  font-weight: 400;
}
</style>
