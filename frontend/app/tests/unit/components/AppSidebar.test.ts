// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { computed, defineComponent, h, nextTick, ref } from 'vue'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { NAV_ITEMS } from '../../../constants/workflow'
import { roleHasSurface } from '../../../constants/role-surfaces'
import { UserRole } from '../../../types/enums'
import AppSidebar from '../../../components/AppSidebar.vue'

const authUser = ref({
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  role: UserRole.DATA_ENTRY as UserRole,
})
const currentPath = ref('/dashboard')
const refreshUnreadCount = vi.fn(async () => {})
const loadStats = vi.fn(async () => {})

function passthrough(name: string) {
  return defineComponent({
    name,
    setup(_, { slots, attrs }) {
      return () => h('div', attrs, slots.default?.())
    },
  })
}

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get user() {
      return authUser.value
    },
  }),
}))

vi.mock('../../../stores/dashboard.store', () => ({
  useDashboardStore: () => ({
    stats: null,
    loadStats,
  }),
}))

vi.mock('../../../stores/notifications.store', () => ({
  useNotificationsStore: () => ({
    unreadCount: 0,
    refreshUnreadCount,
  }),
}))

vi.mock('../../../components/ui/sidebar', () => ({
  Sidebar: passthrough('Sidebar'),
  SidebarContent: passthrough('SidebarContent'),
  SidebarFooter: passthrough('SidebarFooter'),
  SidebarGroup: passthrough('SidebarGroup'),
  SidebarGroupContent: passthrough('SidebarGroupContent'),
  SidebarGroupLabel: passthrough('SidebarGroupLabel'),
  SidebarHeader: passthrough('SidebarHeader'),
  SidebarMenu: passthrough('SidebarMenu'),
  SidebarMenuButton: passthrough('SidebarMenuButton'),
  SidebarMenuItem: passthrough('SidebarMenuItem'),
  SidebarMenuSub: passthrough('SidebarMenuSub'),
  SidebarMenuSubButton: passthrough('SidebarMenuSubButton'),
  SidebarMenuSubItem: passthrough('SidebarMenuSubItem'),
  SidebarRail: passthrough('SidebarRail'),
  useSidebar: () => ({ state: ref<'expanded' | 'collapsed'>('expanded') }),
}))

vi.mock('../../../components/ui/collapsible', () => ({
  Collapsible: passthrough('Collapsible'),
  CollapsibleContent: passthrough('CollapsibleContent'),
  CollapsibleTrigger: passthrough('CollapsibleTrigger'),
}))

vi.mock('../../../components/NavUser.vue', () => ({
  default: defineComponent({
    name: 'NavUser',
    template: '<div data-testid="nav-user-stub">nav-user</div>',
  }),
}))

vi.stubGlobal('useRoute', () => ({
  get path() {
    return currentPath.value
  },
}))

function visibleRoutes(role: UserRole): string[] {
  return NAV_ITEMS.filter((item) => item.roles.includes(role)).map((item) => item.route)
}

async function mountedSidebarTextForRole(role: UserRole): Promise<string> {
  authUser.value = { ...authUser.value, role }
  currentPath.value = '/dashboard'

  const wrapper = mount(AppSidebar, {
    global: {
      stubs: {
        NuxtLink: {
          props: ['to'],
          template: '<a :data-to="to"><slot /></a>',
        },
      },
    },
  })
  await nextTick()
  const text = wrapper.text()
  wrapper.unmount()
  return text
}

describe('AppSidebar navigation contract', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('uses the active canonical sidebar source (frontend/app/components/AppSidebar.vue contract)', () => {
    const routes = visibleRoutes(UserRole.CBY_ADMIN)
    expect(routes).toContain('/dashboard')
    expect(routes).toContain('/requests')
  })

  it('does not expose director external-FX nav for CBY_ADMIN', () => {
    const cbyRoutes = visibleRoutes(UserRole.CBY_ADMIN)
    expect(cbyRoutes).not.toContain('/customs')
  })

  it('exposes external-FX nav for COMMITTEE_DIRECTOR only', () => {
    const directorRoutes = visibleRoutes(UserRole.COMMITTEE_DIRECTOR)
    expect(directorRoutes).toContain('/customs')

    for (const role of Object.values(UserRole)) {
      if (role === UserRole.COMMITTEE_DIRECTOR) continue
      expect(visibleRoutes(role)).not.toContain('/customs')
    }
  })

  it('hides reports from DATA_ENTRY and BANK_REVIEWER', () => {
    expect(visibleRoutes(UserRole.DATA_ENTRY)).not.toContain('/reports')
    expect(visibleRoutes(UserRole.BANK_REVIEWER)).not.toContain('/reports')
  })

  it('keeps role-surface contract aligned for key forbidden surfaces', () => {
    expect(roleHasSurface(UserRole.EXECUTIVE_MEMBER, 'action.voting.close_finalize')).toBe(false)
    expect(roleHasSurface(UserRole.COMMITTEE_DIRECTOR, 'action.voting.close_finalize')).toBe(true)
    expect(roleHasSurface(UserRole.SWIFT_OFFICER, 'action.swift_upload')).toBe(true)
    expect(roleHasSurface(UserRole.CBY_ADMIN, 'action.swift_upload')).toBe(false)
  })

  it('mounts only allowed nav labels for every role (forbidden labels are not rendered)', async () => {
    for (const role of Object.values(UserRole)) {
      const renderedText = await mountedSidebarTextForRole(role)
      const allowedItems = NAV_ITEMS.filter((item) => item.roles.includes(role))
      const forbiddenItems = NAV_ITEMS.filter((item) => !item.roles.includes(role))

      for (const item of allowedItems) {
        expect(renderedText, `Role ${role} should render ${item.route}`).toContain(item.label)
      }
      for (const item of forbiddenItems) {
        expect(renderedText, `Role ${role} should not render ${item.route}`).not.toContain(
          item.label,
        )
      }
    }
  })
})
