<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'staff'
  title?: string
  description?: string
}>(), {
  variant: 'staff',
  title: 'لا يوجد موظفون مسجّلون',
  description: 'ابدأ بإضافة أول موظف في بنكك لمنحه صلاحيات الدخول للنظام.',
})
</script>

<template>
  <div class="empty-state" :data-empty-state-variant="props.variant">
    <div class="empty-icon">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <polyline points="16 11 18 13 22 9" />
      </svg>
    </div>
    <h2 class="empty-title">{{ props.title }}</h2>
    <p class="empty-subtitle">{{ props.description }}</p>
    <div class="empty-action">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  gap: 16px;
}

.empty-icon {
  width: 80px;
  height: 80px;
  background: #f5f5f7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-title {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.empty-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  text-align: center;
  max-width: 360px;
}

.empty-action {
  display: flex;
  justify-content: center;
}
</style>
