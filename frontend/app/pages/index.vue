<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth.store'

const auth = useAuthStore()

const ROLE_GREETINGS: Record<string, string> = {
  DATA_ENTRY: 'طلبات التمويل الخاصة بك',
  BANK_REVIEWER: 'الطلبات المعلقة للمراجعة',
  SWIFT_OFFICER: 'الطلبات الجاهزة لرفع SWIFT',
  SUPPORT_COMMITTEE: 'الطلبات في انتظار لجنة الدعم',
  EXECUTIVE_MEMBER: 'جلسات التصويت الفعّالة',
  COMMITTEE_DIRECTOR: 'القرارات التنفيذية المعلقة',
  CBY_ADMIN: 'لوحة إدارة النظام',
}

const queueTitle = computed(() =>
  auth.user ? (ROLE_GREETINGS[auth.user.role] ?? 'لوحة التحكم') : 'لوحة التحكم',
)
</script>

<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <h1 class="dashboard-title">{{ queueTitle }}</h1>
      <p class="dashboard-subtitle">
        مرحباً، {{ auth.user?.name }} — هذا الدور: <strong>{{ auth.user?.role }}</strong>
      </p>
    </div>

    <div class="placeholder-card">
      <span class="placeholder-icon">🚧</span>
      <p class="placeholder-text">هذه الصفحة قيد الإنشاء. ستتوفر بيانات الطوابير في القصة التالية.</p>
    </div>
  </div>
</template>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.dashboard-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dashboard-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.dashboard-subtitle {
  font-size: 15px;
  color: var(--color-text-secondary);
  margin: 0;
}

.placeholder-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 48px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.placeholder-icon {
  font-size: 40px;
}

.placeholder-text {
  font-size: 15px;
  color: var(--color-text-secondary);
  margin: 0;
  max-width: 400px;
}
</style>
