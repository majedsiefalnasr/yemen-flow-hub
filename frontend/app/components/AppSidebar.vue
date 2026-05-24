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
  HelpCircle,
  Search,
} from 'lucide-vue-next'
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarFooter,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import NavMain from '@/components/NavMain.vue'
import NavSecondary from '@/components/NavSecondary.vue'
import NavUser from '@/components/NavUser.vue'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'

const props = withDefaults(defineProps<SidebarProps>(), {
  collapsible: 'offcanvas',
})

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const navMain = computed(() => [
  { title: 'اللوحة الرئيسية', url: '/', icon: LayoutDashboard },
  { title: 'طلبات التمويل', url: '/requests', icon: FileText },
  { title: 'تقديم طلب جديد', url: '/requests/new', icon: FilePlus2, roles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN] },
  { title: 'إدارة التجار', url: '/merchants', icon: Building2, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
  { title: 'إذن إصدار بيان جمركي', url: '/customs', icon: PackageCheck, roles: [UserRole.COMMITTEE_DIRECTOR] },
  { title: 'التقارير والتحليلات', url: '/reports', icon: BarChart3, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.BANK_ADMIN] },
  { title: 'التدقيق والامتثال', url: '/audit', icon: ScrollText, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
  { title: 'الإشعارات', url: '/notifications', icon: Bell },
  { title: 'إدارة البنوك', url: '/admin/entities', icon: Network, roles: [UserRole.CBY_ADMIN] },
  { title: 'مستخدمي النظام', url: '/admin/cby-staff', icon: UserCog, roles: [UserRole.CBY_ADMIN] },
  { title: 'قواعد المستندات', url: '/admin/workflow-docs', icon: FileCheck2, roles: [UserRole.CBY_ADMIN] },
  { title: 'الأدوار والصلاحيات', url: '/admin/roles', icon: KeyRound, roles: [UserRole.CBY_ADMIN] },
  { title: 'موظفو الجهة', url: '/staff', icon: Users, roles: [UserRole.BANK_ADMIN] },
].filter((item) => !item.roles || (user.value && item.roles.includes(user.value.role))))

const navSecondary = [
  { title: 'إعدادات النظام', url: '/settings', icon: Settings },
  { title: 'المساعدة', url: '#', icon: HelpCircle },
  { title: 'بحث', url: '#', icon: Search },
]

const userData = computed(() => ({
  name: user.value?.name ?? 'المستخدم',
  email: user.value?.email ?? 'user@example.com',
  avatar: '/avatars/default.jpg',
}))
</script>

<template>
  <Sidebar v-bind="props" collapsible="offcanvas">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton
            as-child
            class="data-[slot=sidebar-menu-button]:!p-1.5"
          >
            <NuxtLink to="/">
              <div class="grid h-8 w-8 place-items-center rounded-lg bg-blue-600 font-bold text-white text-sm">
                ب
              </div>
              <span class="text-base font-semibold">منصة الواردات</span>
            </NuxtLink>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>
    <SidebarContent>
      <NavMain :items="navMain" />
      <NavSecondary :items="navSecondary" class="mt-auto" />
    </SidebarContent>
    <SidebarFooter v-if="user">
      <NavUser :user="userData" />
    </SidebarFooter>
  </Sidebar>
</template>
