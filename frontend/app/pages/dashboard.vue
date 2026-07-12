<script setup lang="ts">
import { FilePlus2 } from 'lucide-vue-next'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.store'
import { useScreenPermissions } from '../composables/useScreenPermissions'
import { UserRole } from '../types/enums'
import { ROLE_LABELS, ROUTE_ROLE_MAP } from '../constants/workflow'
import { Button } from '../components/ui/button'
import PageHeader from '../components/layout/PageHeader.vue'
import BankAdminDashboard from '../components/dashboard/BankAdminDashboard.vue'
import MyWorkDashboard from '../components/dashboard/MyWorkDashboard.vue'
// The CBY governance dashboard is the SystemAdmin (platform) dashboard family.
import SystemAdminDashboard from '../components/dashboard/CbyAdminDashboard.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/dashboard'],
})

const auth = useAuthStore()
const router = useRouter()
const { can } = useScreenPermissions()

const role = computed(() => auth.user?.role)
const userName = computed(() => auth.user?.name ?? '')

// Phase D0 capability-family routing (backend enforces the same capabilities on
// its endpoints). Order: system governance → bank analytics → operational work.
// Workflow-executor roles hold neither analytics capability and fall through to
// the shared MyWorkDashboard, so a new dynamic executor role is served with no
// change here.
const dashboardFamily = computed<'system' | 'bank' | 'work'>(() => {
  if (can('system_dashboard', 'VIEW')) return 'system'
  if (can('bank_analytics', 'VIEW')) return 'bank'
  return 'work'
})

const ROLE_SUBTITLES: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'موظف إدخال البيانات بالبنك التجاري',
  [UserRole.BANK_REVIEWER]: 'مراجع داخلي بالبنك التجاري',
  [UserRole.BANK_ADMIN]: 'مسؤول البنك التجاري',
  [UserRole.SWIFT_OFFICER]: 'موظف السويفت بالبنك التجاري',
  [UserRole.SUPPORT_COMMITTEE]: 'عضو لجنة المساندة',
  [UserRole.EXECUTIVE_MEMBER]: 'عضو اللجنة التنفيذية',
  [UserRole.COMMITTEE_DIRECTOR]: 'مدير اللجنة التنفيذية',
  [UserRole.CBY_ADMIN]: 'مسؤول اللجنة الوطنية',
}

const roleSubtitle = computed(() =>
  role.value ? (ROLE_SUBTITLES[role.value] ?? ROLE_LABELS[role.value] ?? '') : '',
)

const showNewRequestAction = computed(
  () => role.value != null && ROUTE_ROLE_MAP['/workflows/new']?.includes(role.value),
)
</script>

<template>
  <div class="flex flex-col gap-6 py-2">
    <!-- Page header -->
    <PageHeader :title="`أهلاً، ${userName}`" :subtitle="roleSubtitle">
      <template v-if="showNewRequestAction" #actions>
        <Button class="shrink-0" @click="router.push('/workflows/new')">
          <FilePlus2 class="h-4 w-4" />
          إنشاء طلب
        </Button>
      </template>
    </PageHeader>

    <!-- Phase D0 dashboard-family routing — by capability, not role name.
         Governance/analytics families are capability-gated; every workflow user
         (incl. any new dynamic executor role) falls through to MyWorkDashboard. -->
    <SystemAdminDashboard v-if="dashboardFamily === 'system'" />
    <BankAdminDashboard v-else-if="dashboardFamily === 'bank'" />
    <MyWorkDashboard v-else />
  </div>
</template>
