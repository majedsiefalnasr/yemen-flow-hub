<script setup lang="ts">
import type { Component } from 'vue'
import { Home } from 'lucide-vue-next'
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  useSidebar,
} from '@/components/ui/sidebar'
import type { SidebarProps } from './ui/sidebar/types'
import { buildOperationalNavBadges } from '@/composables/useNavBadges'
import { NAV_ITEMS } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useDashboardWorkStore } from '@/stores/dashboardWork.store'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useOrgStore } from '@/stores/org.store'
import { ICONS } from '@/utils/icon-map'
import { UserRole } from '@/types/enums'

// ── Discriminated-union nav model ─────────────────────────────────────

export type NavLink = {
  type: 'link'
  title: string
  url: string
  icon: Component
  /** Allowed roles — omit to show to all authenticated users */
  roles?: UserRole[]
  /** Runtime permission hook — hide item when returns false */
  can?: () => boolean
  /** API-sourced badge count — only set when backend provides real counts */
  badge?: number
}

export type NavGroupItem = NavLink

export type NavGroupDef = {
  title: string
  items: NavGroupItem[]
  /**
   * 'operational' → solid --primary (#0066cc) active indicator (default)
   * 'analytics'   → gradient indicator for BANK_ADMIN / CBY_ADMIN analytics groups
   */
  navGroupStyle?: 'operational' | 'analytics'
}

// ── Props ─────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<SidebarProps>(), {
  side: 'right',
  variant: 'sidebar',
  collapsible: 'icon',
})

// ── State ─────────────────────────────────────────────────────────────

const authStore = useAuthStore()
const dashboardWorkStore = useDashboardWorkStore()
const notificationsStore = useNotificationsStore()
const orgStore = useOrgStore()
const route = useRoute()
const { state, setOpen } = useSidebar()
const user = computed(() => authStore.user)
const lastBadgeRole = ref<UserRole | null>(null)

const brandInitial = computed(() => orgStore.platformName.trim().charAt(0))

// The /workflows badge is the shared actionable-work count (D0): count = badge =
// dashboard actionable = /my-queue. Analytics users have no actionable work, so
// they get no workflow badge.
const navBadgesByRoute = computed(() =>
  buildOperationalNavBadges({
    actionableCount: dashboardWorkStore.work?.actionable.count ?? null,
    unreadCount: notificationsStore.unreadCount,
  }),
)

async function refreshOperationalBadges(forceDashboard = false) {
  if (!user.value?.role) return

  await notificationsStore.refreshUnreadCount()

  const roleChanged = lastBadgeRole.value !== user.value.role
  if (forceDashboard || roleChanged || !dashboardWorkStore.work) {
    await dashboardWorkStore.loadWork()
    lastBadgeRole.value = user.value.role
  }
}

onMounted(() => {
  orgStore.loadSettings()
  void refreshOperationalBadges(true)
})

watch(
  () => user.value?.role,
  (nextRole, prevRole) => {
    if (!nextRole) return
    if (nextRole === prevRole && lastBadgeRole.value === nextRole) return
    lastBadgeRole.value = null
    void refreshOperationalBadges(true)
  },
)

watch(
  () => props.collapsible,
  (value) => {
    if (value === 'none') {
      // A fixed sidebar must stay expanded; avoid persisting icon/offcanvas state.
      setOpen(true)
    }
  },
  { immediate: true },
)

// ── Nav construction ──────────────────────────────────────────────────

const NAV_GROUP_DEFS: Array<{
  title: string
  routes: string[]
  navGroupStyle?: 'operational' | 'analytics'
}> = [
  {
    title: 'الرئيسية',
    routes: ['/dashboard', '/notifications', '/reports', '/audit'],
    navGroupStyle: 'operational',
  },
  {
    title: 'العمليات',
    routes: ['/workflows', '/workflows/new', '/customs', '/staff'],
    navGroupStyle: 'operational',
  },
  {
    title: 'الإدارة',
    routes: [
      '/admin/orgs',
      '/admin/banks',
      '/merchants',
      '/admin/staff',
      '/admin/teams',
      '/admin/roles',
    ],
    navGroupStyle: 'analytics',
  },
  {
    title: 'النظام',
    routes: [
      '/admin/workflows',
      '/admin/screen-permissions',
      '/admin/reference-data',
      '/admin/settings',
      '/settings',
    ],
    navGroupStyle: 'analytics',
  },
]

const navGroups = computed<NavGroupDef[]>(() => {
  const role = user.value?.role
  if (!role) return []

  const allowedLinks = NAV_ITEMS.filter((item) => item.roles.includes(role)).map<NavLink>(
    (item) => ({
      type: 'link',
      title: item.label,
      url: item.route,
      icon: ICONS[item.icon] ?? Home,
      roles: item.roles,
      badge: navBadgesByRoute.value[item.route],
    }),
  )

  return NAV_GROUP_DEFS.map((group) => {
    const items = allowedLinks.filter((link) => group.routes.includes(link.url)) as NavGroupItem[]
    return {
      title: group.title,
      navGroupStyle: group.navGroupStyle,
      items,
    }
  }).filter((group) => group.items.length > 0)
})

// ── Helpers ───────────────────────────────────────────────────────────

function isActiveRoute(url: string) {
  const path = url.split('?')[0] ?? url
  return route.path === path || (path !== '/dashboard' && route.path.startsWith(`${path}/`))
}
</script>

<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <div class="flex items-center gap-3">
        <div
          class="font-section grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg text-sm leading-none font-semibold"
        >
          <img
            v-if="orgStore.brandLogoDataUrl"
            :src="orgStore.brandLogoDataUrl"
            alt="Logo"
            class="h-full w-full object-contain"
          />
          <span v-else>{{ brandInitial }}</span>
        </div>
        <div v-if="state === 'expanded'" class="min-w-0 flex-1">
          <div class="font-section truncate text-sm leading-5 font-semibold">
            {{ orgStore.platformName }}
          </div>
          <div class="text-muted-foreground mt-0.5 truncate text-xs leading-5">
            {{ orgStore.authority }}
          </div>
        </div>
      </div>
    </SidebarHeader>

    <SidebarContent>
      <SidebarGroup v-for="group in navGroups" :key="group.title">
        <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
        <SidebarGroupContent>
          <SidebarMenu>
            <template v-for="item in group.items" :key="item.url">
              <SidebarMenuItem>
                <SidebarMenuButton
                  as-child
                  :is-active="isActiveRoute(item.url)"
                  :tooltip="state === 'collapsed' ? item.title : undefined"
                  class="data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground data-[active=true]:font-semibold"
                >
                  <NuxtLink :to="item.url" class="flex items-center gap-2">
                    <component :is="item.icon" class="h-4 w-4" />
                    <span>{{ item.title }}</span>
                    <span
                      v-if="item.badge"
                      class="bg-primary text-primary-foreground ms-auto flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs leading-none font-medium tabular-nums"
                    >
                      {{ item.badge > 99 ? '99+' : item.badge }}
                    </span>
                  </NuxtLink>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </template>
          </SidebarMenu>
        </SidebarGroupContent>
      </SidebarGroup>
    </SidebarContent>

    <SidebarRail v-if="props.collapsible !== 'none'" />
  </Sidebar>
</template>
