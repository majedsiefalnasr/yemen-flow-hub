<script setup lang="ts">
import { FilePlus2 } from 'lucide-vue-next'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.store'
import { UserRole } from '../types/enums'
import { ROLE_LABELS, ROUTE_ROLE_MAP } from '../constants/workflow'
import { Button } from '../components/ui/button'
import PageHeader from '../components/layout/PageHeader.vue'
import DataEntryDashboard from '../components/dashboard/DataEntryDashboard.vue'
import BankReviewerDashboard from '../components/dashboard/BankReviewerDashboard.vue'
import BankAdminDashboard from '../components/dashboard/BankAdminDashboard.vue'
import SupportCommitteeDashboard from '../components/dashboard/SupportCommitteeDashboard.vue'
import SwiftOfficerDashboard from '../components/dashboard/SwiftOfficerDashboard.vue'
import ExecutiveDashboard from '../components/dashboard/ExecutiveDashboard.vue'
import CbyAdminDashboard from '../components/dashboard/CbyAdminDashboard.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/dashboard'],
})

const auth = useAuthStore()
const router = useRouter()

const role = computed(() => auth.user?.role)
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')

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

const showNewRequestAction = computed(() =>
  role.value != null && ROUTE_ROLE_MAP['/requests/new']?.includes(role.value),
)
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">
    <!-- Page header -->
    <PageHeader :title="`أهلاً، ${firstName}`" :subtitle="roleSubtitle">
      <template v-if="showNewRequestAction" #actions>
        <Button class="shrink-0" @click="router.push('/requests/new')">
          <FilePlus2 class="h-4 w-4" />
          طلب جديد
        </Button>
      </template>
    </PageHeader>

    <!-- Role-specific dashboard body -->
    <DataEntryDashboard v-if="role === UserRole.DATA_ENTRY" />
    <BankReviewerDashboard v-else-if="role === UserRole.BANK_REVIEWER" />
    <BankAdminDashboard v-else-if="role === UserRole.BANK_ADMIN" />
    <SupportCommitteeDashboard v-else-if="role === UserRole.SUPPORT_COMMITTEE" />
    <SwiftOfficerDashboard v-else-if="role === UserRole.SWIFT_OFFICER" />
    <ExecutiveDashboard v-else-if="role === UserRole.EXECUTIVE_MEMBER || role === UserRole.COMMITTEE_DIRECTOR" />
    <CbyAdminDashboard v-else-if="role === UserRole.CBY_ADMIN" />

    <!-- Unknown role -->
    <div
      v-else
      class="flex flex-col items-center gap-4 rounded-xl border border-border bg-background px-8 py-12 text-center"
      role="status"
    >
      <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-muted-foreground" aria-hidden="true">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
      </div>
      <p class="max-w-sm text-sm text-muted-foreground">
        لوحة التحكم غير متاحة للدور المحدد. يرجى التواصل مع المسؤول.
      </p>
    </div>
  </div>
</template>
