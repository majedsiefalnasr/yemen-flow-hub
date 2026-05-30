<script setup lang="ts">
import type { Component } from 'vue'
import { ChevronLeft, Home } from 'lucide-vue-next'
import type { SidebarProps } from '@/components/ui/sidebar'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarRail,
  useSidebar,
} from '@/components/ui/sidebar'
import NavUser from '@/components/NavUser.vue'
import { buildOperationalNavBadges } from '@/composables/useNavBadges'
import { NAV_ITEMS } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useDashboardStore } from '@/stores/dashboard.store'
import { useNotificationsStore } from '@/stores/notifications.store'
import { ICONS } from '@/utils/icon-map'
import type { UserRole } from '@/types/enums'

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

export type NavCollapsible = {
  type: 'collapsible'
  title: string
  icon: Component
  roles?: UserRole[]
  can?: () => boolean
  items: NavLink[]
}

export type NavGroupItem = NavLink | NavCollapsible

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
  variant: 'inset',
  collapsible: 'icon',
})

// ── State ─────────────────────────────────────────────────────────────

const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
const notificationsStore = useNotificationsStore()
const route = useRoute()
const { state } = useSidebar()
const user = computed(() => authStore.user)
const lastBadgeRole = ref<UserRole | null>(null)

const brandInitial = computed(() => 'منصة الواردات'.trim().charAt(0))

const navBadgesByRoute = computed(() =>
  buildOperationalNavBadges({
    role: user.value?.role ?? null,
    stats: dashboardStore.stats,
    unreadCount: notificationsStore.unreadCount,
  }),
)

async function refreshOperationalBadges(forceDashboard = false) {
  if (!user.value?.role) return

  await notificationsStore.refreshUnreadCount()

  const roleChanged = lastBadgeRole.value !== user.value.role
  if (forceDashboard || roleChanged || !dashboardStore.stats) {
    await dashboardStore.loadStats()
    lastBadgeRole.value = user.value.role
  }
}

onMounted(() => {
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

// ── Nav construction ──────────────────────────────────────────────────

const NAV_GROUP_DEFS: Array<{ title: string; routes: string[]; navGroupStyle?: 'operational' | 'analytics' }> = [
  { title: 'الرئيسية', routes: ['/dashboard', '/requests', '/notifications'], navGroupStyle: 'operational' },
  { title: 'العمليات', routes: ['/requests/new', '/customs'], navGroupStyle: 'operational' },
  {
    title: 'الإدارة',
    routes: ['/merchants', '/staff', '/banks', '/reports', '/audit', '/admin/entities', '/admin/cby-staff', '/admin/workflow-docs', '/admin/roles'],
    navGroupStyle: 'analytics',
  },
  { title: 'الأخرى', routes: ['/settings', '/settings/system', '/settings/user'] },
]

const navGroups = computed<NavGroupDef[]>(() => {
  const role = user.value?.role
  if (!role) return []

  const allowedLinks = NAV_ITEMS
    .filter(item => item.roles.includes(role))
    .map<NavLink>(item => ({
      type: 'link',
      title: item.label,
      url: item.route,
      icon: ICONS[item.icon] ?? Home,
      roles: item.roles,
      badge: navBadgesByRoute.value[item.route],
    }))

  return NAV_GROUP_DEFS
    .map(group => ({
      title: group.title,
      navGroupStyle: group.navGroupStyle,
      items: allowedLinks.filter(link => group.routes.includes(link.url)) as NavGroupItem[],
    }))
    .filter(group => group.items.length > 0)
})

const userData = computed(() => ({
  name: user.value?.name ?? 'المستخدم',
  email: user.value?.email ?? 'user@example.com',
  avatar: '/avatars/default.jpg',
}))

// ── Helpers ───────────────────────────────────────────────────────────

function isActiveRoute(url: string) {
  return route.path === url || (url !== '/dashboard' && route.path.startsWith(`${url}/`))
}

function isCollapsibleActive(item: NavCollapsible) {
  return item.items.some(sub => isActiveRoute(sub.url))
}

/** Active indicator bar class — analytics groups get a gradient per DESIGN.md */
function activeIndicatorClass(navGroupStyle?: 'operational' | 'analytics') {
  return navGroupStyle === 'analytics'
    ? 'bg-gradient-to-b from-indigo-500 to-purple-500'
    : 'bg-primary'
}
</script>

<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <div class="flex items-center gap-3">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">
          {{ brandInitial }}
        </div>
        <div v-if="state === 'expanded'" class="min-w-0 flex-1">
          <div class="truncate text-sm font-semibold leading-none">منصة الواردات</div>
          <div class="mt-1 truncate text-xs text-muted-foreground">البنك المركزي اليمني</div>
        </div>
      </div>
    </SidebarHeader>

    <SidebarContent>
      <SidebarGroup v-for="group in navGroups" :key="group.title">
        <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
        <SidebarGroupContent>
          <SidebarMenu>
            <template v-for="item in group.items" :key="item.type === 'link' ? item.url : item.title">

              <!-- Branch 1: Leaf link ─────────────────────────────── -->
              <SidebarMenuItem v-if="item.type === 'link'">
                <SidebarMenuButton
                  as-child
                  :is-active="isActiveRoute(item.url)"
                  :tooltip="state === 'collapsed' ? item.title : undefined"
                  class="data-[active=true]:bg-primary data-[active=true]:font-semibold data-[active=true]:text-primary-foreground data-[active=true]:hover:bg-primary/90 data-[active=true]:hover:text-primary-foreground [&[data-active=true]_svg]:text-primary-foreground"
                >
                  <NuxtLink :to="item.url" class="relative flex items-center gap-2">
                    <span
                      v-if="isActiveRoute(item.url)"
                      :class="['pointer-events-none absolute inset-y-1 end-0 w-1 rounded-s-full', activeIndicatorClass(group.navGroupStyle)]"
                      aria-hidden="true"
                    />
                    <component :is="item.icon" class="h-4 w-4" />
                    <span>{{ item.title }}</span>
                    <span
                      v-if="item.badge"
                      class="ms-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground"
                    >
                      {{ item.badge > 99 ? '99+' : item.badge }}
                    </span>
                  </NuxtLink>
                </SidebarMenuButton>
              </SidebarMenuItem>

              <!-- Branch 2: Inline collapsible (sidebar open)        -->
              <!-- Branch 3: Collapsed-flyout via tooltip (sidebar icon mode) -->
              <Collapsible
                v-else-if="item.type === 'collapsible'"
                as-child
                :default-open="isCollapsibleActive(item)"
                class="group/collapsible"
              >
                <SidebarMenuItem>
                  <CollapsibleTrigger as-child>
                    <SidebarMenuButton
                      :is-active="isCollapsibleActive(item)"
                      :tooltip="state === 'collapsed' ? item.title : undefined"
                      class="data-[active=true]:bg-primary/10 data-[active=true]:font-semibold data-[active=true]:text-primary"
                    >
                      <component :is="item.icon" class="h-4 w-4" />
                      <span>{{ item.title }}</span>
                      <!-- ChevronLeft (→ in RTL) rotates to ↓ when open -->
                      <ChevronLeft
                        class="ms-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:-rotate-90"
                      />
                    </SidebarMenuButton>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <SidebarMenuSub>
                      <SidebarMenuSubItem v-for="sub in item.items" :key="sub.url">
                        <SidebarMenuSubButton as-child :is-active="isActiveRoute(sub.url)">
                          <NuxtLink :to="sub.url" class="flex items-center gap-2">
                            <component v-if="sub.icon" :is="sub.icon" class="h-3.5 w-3.5" />
                            <span>{{ sub.title }}</span>
                          </NuxtLink>
                        </SidebarMenuSubButton>
                      </SidebarMenuSubItem>
                    </SidebarMenuSub>
                  </CollapsibleContent>
                </SidebarMenuItem>
              </Collapsible>

            </template>
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
