<script setup lang="ts">
import type { SidebarProps } from '@/components/ui/sidebar'
import {
  Home,
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
import { NAV_ITEMS } from '@/constants/workflow'
import { ICONS } from '@/utils/icon-map'

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

const allowedNavItems = computed(() => {
  const role = user.value?.role
  if (!role) return []

  return NAV_ITEMS
    .filter(item => item.roles.includes(role))
    .map(item => ({
      title: item.label,
      url: item.route,
      icon: ICONS[item.icon] ?? Home,
    }))
})

const NAV_GROUPS: Array<{ title: string, routes: string[] }> = [
  { title: 'الرئيسية', routes: ['/dashboard', '/requests', '/notifications'] },
  { title: 'العمليات', routes: ['/requests/new', '/customs'] },
  { title: 'الإدارة', routes: ['/merchants', '/staff', '/reports', '/audit', '/admin/entities', '/admin/cby-staff', '/admin/workflow-docs', '/admin/roles'] },
  { title: 'الأخرى', routes: ['/settings'] },
]

const navMain = computed(() =>
  NAV_GROUPS
    .map(group => ({
      title: group.title,
      items: allowedNavItems.value.filter(item => group.routes.includes(item.url)),
    }))
    .filter(group => group.items.length > 0),
)

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
