<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth.store'
import { UserRole } from '../types/enums'
import { ROLE_QUEUE_TITLES } from '../constants/workflow'
import DataEntryDashboard from '../components/dashboard/DataEntryDashboard.vue'
import BankReviewerDashboard from '../components/dashboard/BankReviewerDashboard.vue'
import SupportCommitteeDashboard from '../components/dashboard/SupportCommitteeDashboard.vue'
import SwiftOfficerDashboard from '../components/dashboard/SwiftOfficerDashboard.vue'

const auth = useAuthStore()

const queueTitle = computed(() =>
  auth.user ? (ROLE_QUEUE_TITLES[auth.user.role] ?? 'لوحة التحكم') : 'لوحة التحكم',
)

const role = computed(() => auth.user?.role)
</script>

<template>
  <div class="dashboard-page">
    <div class="dashboard-header">
      <h1 class="dashboard-title">{{ queueTitle }}</h1>
      <p class="dashboard-subtitle">مرحباً، {{ auth.user?.name }}</p>
    </div>

    <DataEntryDashboard v-if="role === UserRole.DATA_ENTRY" />
    <BankReviewerDashboard v-else-if="role === UserRole.BANK_REVIEWER" />
    <SupportCommitteeDashboard v-else-if="role === UserRole.SUPPORT_COMMITTEE" />
    <SwiftOfficerDashboard v-else-if="role === UserRole.SWIFT_OFFICER" />

    <!-- Placeholder for other roles (Story 4+) -->
    <div v-else class="placeholder-card">
      <span class="placeholder-icon" aria-hidden="true">🚧</span>
      <p class="placeholder-text">هذه اللوحة قيد الإنشاء وستكون جاهزة في المرحلة القادمة.</p>
    </div>
  </div>
</template>

<style scoped>
.dashboard-page {
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
  color: var(--color-text-primary, #1d1d1f);
  margin: 0;
}

.dashboard-subtitle {
  font-size: 15px;
  color: var(--color-text-secondary, #6e6e73);
  margin: 0;
}

.placeholder-card {
  background-color: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  padding: 48px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.placeholder-icon { font-size: 40px; }

.placeholder-text {
  font-size: 15px;
  color: var(--color-text-secondary, #6e6e73);
  margin: 0;
  max-width: 400px;
}
</style>
