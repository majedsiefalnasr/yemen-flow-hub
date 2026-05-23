<script setup lang="ts">
import type { SidebarProps } from '@/components/ui/sidebar'
import {
  LayoutDashboard,
  FileText,
  FilePlus2,
  Building2,
  PackageCheck,
  BarChart3,
  ScrollText,
  Bell,
  Network,
  UserCog,
  FileCheck2,
  KeyRound,
  Users,
  Settings,
} from 'lucide-vue-next'
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import NavMain from '@/components/NavMain.vue'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import { ScrollArea } from '@/components/ui/scroll-area'

const props = withDefaults(defineProps<SidebarProps>(), {
  collapsible: 'icon',
})

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const navMain = computed(() => [
  { to: '/', label: 'اللوحة الرئيسية', icon: LayoutDashboard },
  { to: '/requests', label: 'طلبات التمويل', icon: FileText },
  { to: '/requests/new', label: 'تقديم طلب جديد', icon: FilePlus2, roles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN] },
  { to: '/merchants', label: 'إدارة التجار', icon: Building2, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
  { to: '/customs', label: 'إذن إصدار بيان جمركي', icon: PackageCheck, roles: [UserRole.COMMITTEE_DIRECTOR] },
  { to: '/reports', label: 'التقارير والتحليلات', icon: BarChart3, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.BANK_ADMIN] },
  { to: '/audit', label: 'التدقيق والامتثال', icon: ScrollText, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
  { to: '/notifications', label: 'الإشعارات', icon: Bell },
  { to: '/admin/entities', label: 'إدارة البنوك', icon: Network, roles: [UserRole.CBY_ADMIN] },
  { to: '/admin/cby-staff', label: 'مستخدمي النظام', icon: UserCog, roles: [UserRole.CBY_ADMIN] },
  { to: '/admin/workflow-docs', label: 'قواعد المستندات', icon: FileCheck2, roles: [UserRole.CBY_ADMIN] },
  { to: '/admin/roles', label: 'الأدوار والصلاحيات', icon: KeyRound, roles: [UserRole.CBY_ADMIN] },
  { to: '/staff', label: 'موظفو الجهة', icon: Users, roles: [UserRole.BANK_ADMIN] },
  { to: '/settings', label: 'إعدادات النظام', icon: Settings, roles: [UserRole.CBY_ADMIN] },
].filter((item) => !item.roles || (user.value && item.roles.includes(user.value.role))))
</script>

<template>
  <Sidebar v-bind="props" class="border-e border-sidebar-border">
    <SidebarHeader class="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
      <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sidebar-primary font-bold text-sidebar-primary-foreground">
        ب م
      </div>
      <div
        class="leading-tight group-data-[collapsible=icon]:hidden"
      >
        <div class="font-bold">منصة الواردات</div>
        <div class="text-[11px] text-sidebar-foreground/60">البنك المركزي اليمني</div>
      </div>
    </SidebarHeader>
    <ScrollArea class="flex-1">
      <SidebarContent class="p-3">
        <NavMain :items="navMain" />
      </SidebarContent>
    </ScrollArea>
    <SidebarRail />
  </Sidebar>
</template>
