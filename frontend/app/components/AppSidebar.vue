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
} from 'lucide-vue-next'
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarFooter,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from '@/components/ui/sidebar'
import NavUser from '@/components/NavUser.vue'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'

const props = withDefaults(defineProps<SidebarProps>(), {
  collapsible: 'icon',
})

const authStore = useAuthStore()
const user = computed(() => authStore.user)

// Derive sidebar brand initial from the platform name first letter
const brandInitial = computed(() => {
  const name = 'منصة الواردات'
  return name.trim().charAt(0)
})

const navMain = computed(() => [
  {
    title: 'الرئيسية',
    items: [
      { title: 'اللوحة الرئيسية', url: '/', icon: LayoutDashboard },
      { title: 'طلبات التمويل', url: '/requests', icon: FileText },
      { title: 'الإشعارات', url: '/notifications', icon: Bell },
    ],
  },
  {
    title: 'التمويل',
    items: [
      { title: 'تقديم طلب جديد', url: '/requests/new', icon: FilePlus2, roles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN] },
      { title: 'إدارة التجار', url: '/merchants', icon: Building2, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
      { title: 'إذن إصدار بيان جمركي', url: '/customs', icon: PackageCheck, roles: [UserRole.COMMITTEE_DIRECTOR] },
    ].filter(item => !item.roles || (user.value && item.roles.includes(user.value.role))),
  },
  {
    title: 'الإدارة',
    items: [
      { title: 'التقارير والتحليلات', url: '/reports', icon: BarChart3, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.BANK_ADMIN] },
      { title: 'التدقيق والامتثال', url: '/audit', icon: ScrollText, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
      { title: 'إدارة البنوك', url: '/admin/entities', icon: Network, roles: [UserRole.CBY_ADMIN] },
      { title: 'مستخدمي النظام', url: '/admin/cby-staff', icon: UserCog, roles: [UserRole.CBY_ADMIN] },
      { title: 'قواعد المستندات', url: '/admin/workflow-docs', icon: FileCheck2, roles: [UserRole.CBY_ADMIN] },
      { title: 'الأدوار والصلاحيات', url: '/admin/roles', icon: KeyRound, roles: [UserRole.CBY_ADMIN] },
      { title: 'موظفو الجهة', url: '/staff', icon: Users, roles: [UserRole.BANK_ADMIN] },
    ].filter(item => !item.roles || (user.value && item.roles.includes(user.value.role))),
  },
  {
    title: 'الأخرى',
    items: [
      { title: 'إعدادات النظام', url: '/settings', icon: Settings },
      { title: 'المساعدة', url: '#', icon: HelpCircle },
    ],
  },
])

const userData = computed(() => ({
  name: user.value?.name ?? 'المستخدم',
  email: user.value?.email ?? 'user@example.com',
  avatar: '/avatars/default.jpg',
}))
</script>

<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <div class="flex items-center gap-3">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">
          {{ brandInitial }}
        </div>
        <div class="flex flex-col gap-0.5 leading-none min-w-0">
          <span class="text-sm font-semibold truncate">منصة الواردات</span>
          <span class="text-xs text-muted-foreground truncate">البنك المركزي اليمني</span>
        </div>
      </div>
    </SidebarHeader>

    <SidebarContent>
      <SidebarGroup v-for="group in navMain" :key="group.title">
        <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
        <SidebarGroupContent>
          <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.url">
              <SidebarMenuButton as-child>
                <NuxtLink :to="item.url" class="flex items-center gap-2">
                  <component :is="item.icon" class="h-4 w-4" />
                  <span>{{ item.title }}</span>
                </NuxtLink>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroupContent>
      </SidebarGroup>
    </SidebarContent>

    <SidebarFooter v-if="user">
      <NavUser :user="userData" />
    </SidebarFooter>

    <SidebarRail />
  </Sidebar>
</template>
