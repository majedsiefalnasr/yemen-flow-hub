// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { nextTick, reactive } from 'vue'
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest'
import AppHeader from '../../../components/layout/AppHeader.vue'

const mockPush = vi.hoisted(() => vi.fn())
const mockRefreshUnreadCount = vi.hoisted(() => vi.fn())
const mockFetchRecent = vi.hoisted(() => vi.fn())
const mockMarkAllRead = vi.hoisted(() => vi.fn())
const mockRoute = reactive({ fullPath: '/dashboard', path: '/dashboard' })

vi.stubGlobal('useRouter', () => ({ push: mockPush }))
vi.stubGlobal('useRoute', () => mockRoute)
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

vi.mock('../../../components/ui/EmptyState.vue', () => ({
  default: { template: '<div class="empty-state-stub" />' },
}))

describe('AppHeader — GlobalSearch integration', () => {
  const mountedWrappers: Array<{ unmount: () => void }> = []

  function mountHeader() {
    const wrapper = mount(AppHeader, {
      attachTo: document.body,
      global: {
        stubs: {
          NuxtLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
        },
      },
    })

    mountedWrappers.push(wrapper)

    return wrapper
  }

  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.fullPath = '/dashboard'
    mockRoute.path = '/dashboard'
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 1024 })
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: 768 })
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(() => ({
      x: 900,
      y: 24,
      width: 40,
      height: 40,
      top: 24,
      right: 940,
      bottom: 64,
      left: 900,
      toJSON: () => '',
    }))
  })

  afterEach(() => {
    while (mountedWrappers.length) mountedWrappers.pop()?.unmount()
    vi.restoreAllMocks()
    document.body.innerHTML = ''
  })

  it('renders desktop GlobalSearch in the center header region', () => {
    const wrapper = mountHeader()

    expect(wrapper.find('.header-center .global-search-stub').exists()).toBe(true)
    expect(wrapper.find('.header-center .global-search-stub').attributes('data-mobile')).toBe('false')
  })

  it('opens and closes the mobile search overlay from header controls', async () => {
    const wrapper = mountHeader()

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(false)

    await wrapper.get('button.mobile-search-btn').trigger('click')

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(true)
    expect(wrapper.find('.mobile-search-overlay .global-search-stub').attributes('data-mobile')).toBe('true')

    await wrapper.get('.mobile-search-overlay button.icon-btn').trigger('click')

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(false)
  })

  it('emits toggleMobileMenu from the mobile menu button', async () => {
    const wrapper = mountHeader()

    await wrapper.get('button.mobile-menu-btn').trigger('click')

    expect(wrapper.emitted('toggleMobileMenu')).toHaveLength(1)
  })

  it('refreshes unread notification count on mount and loads recent notifications on bell click', async () => {
    const wrapper = mountHeader()

    expect(mockRefreshUnreadCount).toHaveBeenCalledTimes(1)

    await wrapper.get('button[aria-label="الإشعارات"]').trigger('click')
    await nextTick()

    expect(mockFetchRecent).toHaveBeenCalledTimes(1)
    expect(document.body.querySelector('.popover-content')).not.toBeNull()
  })

  it('closes mobile search and teleported menus when the route changes', async () => {
    const wrapper = mountHeader()

    await wrapper.get('button.mobile-search-btn').trigger('click')
    await wrapper.get('button[aria-label="الإشعارات"]').trigger('click')
    await nextTick()

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(true)
    expect(document.body.querySelector('.popover-content')).not.toBeNull()

    mockRoute.fullPath = '/profile'
    mockRoute.path = '/profile'
    await nextTick()

    expect(wrapper.find('.mobile-search-overlay').exists()).toBe(false)
    expect(document.body.querySelector('.popover-content')).toBeNull()

    await wrapper.get('button.user-trigger').trigger('click')
    await nextTick()

    expect(document.body.querySelector('.dropdown-content')).not.toBeNull()

    mockRoute.fullPath = '/settings'
    mockRoute.path = '/settings'
    await nextTick()

    expect(document.body.querySelector('.dropdown-content')).toBeNull()
  })

  it('applies real width clamping to teleported popovers and dropdowns on narrow viewports', async () => {
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 180 })

    const wrapper = mountHeader()

    await wrapper.get('button[aria-label="الإشعارات"]').trigger('click')
    await nextTick()

    const popover = document.body.querySelector('.popover-content') as HTMLElement | null

    expect(popover?.style.width).toBe('164px')
    expect(popover?.style.maxWidth).toBe('164px')

    mockRoute.fullPath = '/collapsed-popover'
    mockRoute.path = '/collapsed-popover'
    await nextTick()

    await wrapper.get('button.user-trigger').trigger('click')
    await nextTick()

    const dropdown = document.body.querySelector('.dropdown-content') as HTMLElement | null

    expect(dropdown?.style.width).toBe('164px')
    expect(dropdown?.style.maxWidth).toBe('164px')
  })
})
