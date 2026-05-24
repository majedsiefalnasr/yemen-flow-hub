<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.store'
import { UserRole } from '../types/enums'
import { ROLE_LABELS, ROUTE_ROLE_MAP } from '../constants/workflow'
import AppSidebar from '../components/AppSidebar.vue'
import DataEntryDashboard from '../components/dashboard/DataEntryDashboard.vue'
import BankReviewerDashboard from '../components/dashboard/BankReviewerDashboard.vue'
import BankAdminDashboard from '../components/dashboard/BankAdminDashboard.vue'
import SupportCommitteeDashboard from '../components/dashboard/SupportCommitteeDashboard.vue'
import SwiftOfficerDashboard from '../components/dashboard/SwiftOfficerDashboard.vue'
import ExecutiveDashboard from '../components/dashboard/ExecutiveDashboard.vue'
import CbyAdminDashboard from '../components/dashboard/CbyAdminDashboard.vue'
import { SidebarProvider, SidebarInset } from '../components/ui/sidebar'

const auth = useAuthStore()
const router = useRouter()

const role = computed(() => auth.user?.role)

/** First word of the user's full name — matches Lovable greeting intent */
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')

/** Role subtitle shown under the greeting */
const ROLE_SUBTITLES: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'موظف إدخال البيانات بالبنك التجاري',
  [UserRole.BANK_REVIEWER]: 'مراجع داخلي بالبنك التجاري',
  [UserRole.BANK_ADMIN]: 'مسؤول البنك التجاري',
  [UserRole.SWIFT_OFFICER]: 'موظف السويفت بالبنك التجاري',
  [UserRole.SUPPORT_COMMITTEE]: 'عضو لجنة المساندة',
  [UserRole.EXECUTIVE_MEMBER]: 'عضو اللجنة التنفيذية',
  [UserRole.COMMITTEE_DIRECTOR]: 'مدير اللجنة التنفيذية',
  [UserRole.CBY_ADMIN]: 'مسؤول (CBY)',
}

const roleSubtitle = computed(() =>
  role.value ? (ROLE_SUBTITLES[role.value] ?? ROLE_LABELS[role.value] ?? '') : '',
)

/** Show "طلب جديد" only when the route is allowed for the current production role. */
const showNewRequestAction = computed(() =>
  role.value != null && ROUTE_ROLE_MAP['/requests/new']?.includes(role.value),
)
</script>

<template>
  <SidebarProvider :style="{ '--sidebar-width': 'calc(var(--spacing) * 72)', '--header-height': 'calc(var(--spacing) * 12)' }">
    <AppSidebar variant="inset" />
    <SidebarInset>
      <div class="dashboard-page" dir="rtl">

        <!-- Page header — Lovable PageHeader intent: greeting + role subtitle + optional action -->
        <div class="page-header">
          <div class="page-header__text">
            <h1 class="page-header__greeting">
              أهلاً، {{ firstName }}
              <span class="page-header__wave" aria-hidden="true">👋</span>
            </h1>
            <p class="page-header__subtitle">{{ roleSubtitle }}</p>
          </div>
          <button
            v-if="showNewRequestAction"
            class="page-header__action"
            @click="router.push('/requests/new')"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            طلب جديد
          </button>
        </div>

        <!-- Role-specific dashboard body -->
        <DataEntryDashboard v-if="role === UserRole.DATA_ENTRY" />
        <BankReviewerDashboard v-else-if="role === UserRole.BANK_REVIEWER" />
        <BankAdminDashboard v-else-if="role === UserRole.BANK_ADMIN" />
        <SupportCommitteeDashboard v-else-if="role === UserRole.SUPPORT_COMMITTEE" />
        <SwiftOfficerDashboard v-else-if="role === UserRole.SWIFT_OFFICER" />
        <ExecutiveDashboard v-else-if="role === UserRole.EXECUTIVE_MEMBER || role === UserRole.COMMITTEE_DIRECTOR" />
        <CbyAdminDashboard v-else-if="role === UserRole.CBY_ADMIN" />

        <!-- Unknown role: no emoji, uses project empty-state style -->
        <div v-else class="unknown-role-card" role="status">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
            <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <p class="unknown-role-card__text">لوحة التحكم غير متاحة للدور المحدد. يرجى التواصل مع المسؤول.</p>
        </div>

      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<style scoped>
.dashboard-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Page header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-header__text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-header__greeting {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-header__wave {
  font-style: normal;
}

.page-header__subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

.page-header__action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  background: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: background 0.15s;
}

.page-header__action:hover {
  background: #0052a3;
}

/* Unknown role */
.unknown-role-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 48px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.unknown-role-card__text {
  font-size: 15px;
  color: #6c757d;
  margin: 0;
  max-width: 400px;
}

@media (max-width: 600px) {
  .page-header {
    flex-direction: column;
  }
  .page-header__action {
    align-self: flex-start;
  }
  .page-header__greeting {
    font-size: 22px;
  }
}
</style>
