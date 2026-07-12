<script setup lang="ts">
import { FilePlus2 } from 'lucide-vue-next'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.store'
import { useScreenPermissions } from '../composables/useScreenPermissions'
import { UserRole } from '../types/enums'
import { ROLE_LABELS, ROUTE_ROLE_MAP } from '../constants/workflow'
import { Button } from '../components/ui/button'
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

// Phase D0 capability-family routing (mirrors dashboard.vue; backend enforces the
// same capabilities). system governance → bank analytics → operational work.
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
  [UserRole.CBY_ADMIN]: 'مسؤول (CBY)',
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
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex flex-col gap-1">
        <h1
          class="font-heading text-foreground text-2xl leading-8 font-semibold sm:text-3xl sm:leading-10"
        >
          أهلاً، {{ userName }}
        </h1>
        <p class="font-section text-muted-foreground text-sm leading-5">{{ roleSubtitle }}</p>
      </div>
      <Button v-if="showNewRequestAction" class="shrink-0" @click="router.push('/workflows/new')">
        <FilePlus2 class="h-4 w-4" />
        إنشاء طلب
      </Button>
    </div>

    <!-- Phase D0 dashboard-family routing — by capability, not role name. -->
    <SystemAdminDashboard v-if="dashboardFamily === 'system'" />
    <BankAdminDashboard v-else-if="dashboardFamily === 'bank'" />
    <MyWorkDashboard v-else />
  </div>
</template>
