// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { vi, describe, it, expect, beforeEach } from 'vitest'
import AppHeader from '../../../components/layout/AppHeader.vue'

const mockPush = vi.hoisted(() => vi.fn())
const mockRefreshUnreadCount = vi.hoisted(() => vi.fn())
const mockFetchRecent = vi.hoisted(() => vi.fn())
const mockMarkAllRead = vi.hoisted(() => vi.fn())

vi.stubGlobal('useRouter', () => ({ push: mockPush }))
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost', demoMode: false } }))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({ user: { name: 'Test User', role: 'DATA_ENTRY' } }),
}))

vi.mock('../../../stores/notifications.store', () => ({
  useNotificationsStore: () => ({
    unreadCount: 2,
    items: [],
    refreshUnreadCount: mockRefreshUnreadCount,
    fetchRecent: mockFetchRecent,
    markAllRead: mockMarkAllRead,
  }),
}))

vi.mock('../../../constants/workflow', () => ({
  ROLE_LABELS: { DATA_ENTRY: 'إدخال البيانات' },
}))

vi.mock('../../../components/ui/Icon.vue', () => ({
  default: {
    props: ['name'],
    template: '<span class="icon-stub" :data-icon="name" />',
  },
}))

vi.mock('../../../composables/useColorScheme', () => ({
  useColorScheme: () => ({ isDark: { value: false }, toggle: vi.fn(), hydrate: vi.fn() }),
}))

vi.mock('../../../components/layout/RoleSwitcher.vue', () => ({
  default: { template: '<div class="role-switcher-stub" />' },
}))

vi.mock('../../../components/layout/GlobalSearch.vue', () => ({
  default: {
    props: {
      mobile: {
        type: Boolean,
        default: false,
      },
    },
    template: '<div class="global-search-stub" :data-mobile="mobile ? `true` : `false`" />',
  },
}))

vi.mock('../../../components/ui/Popover.vue', () => ({
  default: { template: '<div><slot name="trigger" /><slot /></div>' },
}))

vi.mock('../../../components/ui/DropdownMenu.vue', () => ({
  default: { template: '<div><slot name="trigger" /><slot /></div>' },
}))

vi.mock('../../../components/ui/EmptyState.vue', () => ({
  default: { template: '<div class="empty-state-stub" />' },
}))

describe('AppHeader — GlobalSearch integration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders desktop GlobalSearch in the center header region', () => {
    const wrapper = mount(AppHeader)

    expect(wrapper.find('.header-center .global-search-stub').exists()).toBe(true)
    expect(wrapper.find('.header-center .global-search-stub').attributes('data-mobile')).toBe('false')
  })

  it('opens and closes the mobile search overlay from header controls', async () => {
    const wrapper = mount(AppHeader)

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(false)

    await wrapper.get('button.mobile-search-btn').trigger('click')

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(true)
    expect(wrapper.find('.mobile-search-overlay .global-search-stub').attributes('data-mobile')).toBe('true')

    await wrapper.get('.mobile-search-overlay button.icon-btn').trigger('click')

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(false)
  })

  it('emits toggleMobileMenu from the mobile menu button', async () => {
    const wrapper = mount(AppHeader)

    await wrapper.get('button.mobile-menu-btn').trigger('click')

    expect(wrapper.emitted('toggleMobileMenu')).toHaveLength(1)
  })

  it('refreshes unread notification count on mount and loads recent notifications on bell click', async () => {
    const wrapper = mount(AppHeader)

    expect(mockRefreshUnreadCount).toHaveBeenCalledTimes(1)

    await wrapper.get('button[aria-label="الإشعارات"]').trigger('click')

    expect(mockFetchRecent).toHaveBeenCalledTimes(1)
  })
})
