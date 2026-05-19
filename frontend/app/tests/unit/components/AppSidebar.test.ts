// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { computed } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppSidebar from '../../../components/layout/AppSidebar.vue'

const mockPush = vi.hoisted(() => vi.fn())
const mockLogout = vi.hoisted(() => vi.fn())
const collapsed = vi.hoisted(() => ({ value: false }))
const toggleSidebar = vi.hoisted(() => vi.fn(() => {
  collapsed.value = !collapsed.value
}))
const mockPath = vi.hoisted(() => ({ value: '/dashboard' }))
const mockUser = vi.hoisted(() => ({ value: {
  name: 'Test User',
  role: 'CBY_ADMIN',
} }))

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: mockPath.value }),
  useRouter: () => ({ push: mockPush }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: mockUser.value,
    logout: mockLogout,
  }),
}))

vi.mock('../../../composables/useSidebar', () => ({
  useSidebar: () => ({
    isCollapsed: computed(() => collapsed.value),
    toggle: toggleSidebar,
    collapse: vi.fn(),
    expand: vi.fn(),
  }),
}))

vi.mock('../../../constants/workflow', () => ({
  ROLE_LABELS: {
    CBY_ADMIN: 'مدير النظام',
    DATA_ENTRY: 'إدخال البيانات',
  },
  NAV_ITEMS: [
    { route: '/dashboard', label: 'لوحة التحكم', icon: 'home', roles: ['CBY_ADMIN', 'DATA_ENTRY'] },
    { route: '/users', label: 'المستخدمون', icon: 'users', roles: ['CBY_ADMIN'] },
    { route: '/requests', label: 'الطلبات', icon: 'file-text', roles: ['DATA_ENTRY'] },
  ],
}))

vi.mock('../../../components/ui/Icon.vue', () => ({
  default: {
    props: ['name'],
    template: '<span class="icon-stub" :data-icon="name" />',
  },
}))

describe('AppSidebar', () => {
  beforeEach(() => {
    collapsed.value = false
    mockPath.value = '/dashboard'
    mockLogout.mockReset()
    mockPush.mockReset()
    toggleSidebar.mockClear()
    mockUser.value = {
      name: 'Test User',
      role: 'CBY_ADMIN',
    }
  })

  function mountSidebar() {
    return mount(AppSidebar, {
      props: { mobileOpen: false },
      global: {
        stubs: {
          NuxtLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
        },
      },
    })
  }

  it('renders expanded sidebar brand, labels, and collapse copy', () => {
    const wrapper = mountSidebar()
    expect(wrapper.find('.brand-logo').text()).toBe('ب م')
    expect(wrapper.find('.brand-name').text()).toBe('منصة الواردات')
    expect(wrapper.find('.brand-subtitle').text()).toBe('البنك المركزي اليمني')
    expect(wrapper.find('.collapse-btn').text()).toContain('‹ طي الشريط الجانبي')
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders only role-authorized nav items', () => {
    const wrapper = mountSidebar()
    const navLinks = wrapper.findAll('.nav-item')
    expect(navLinks).toHaveLength(2)
    expect(navLinks[0]?.text()).toContain('لوحة التحكم')
    expect(navLinks[1]?.text()).toContain('المستخدمون')
    expect(wrapper.html()).not.toContain('الطلبات')
  })

  it('applies collapsed class and keeps collapse toggle text parity', async () => {
    collapsed.value = true
    const wrapper = mountSidebar()
    expect(wrapper.find('.sidebar').classes()).toContain('sidebar--collapsed')
    expect(wrapper.find('.collapse-btn').text()).toContain('توسيع ›')
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('logs out and navigates to login on logout click', async () => {
    mockLogout.mockResolvedValueOnce(undefined)
    const wrapper = mountSidebar()
    await wrapper.get('.logout-btn').trigger('click')
    expect(mockLogout).toHaveBeenCalledTimes(1)
    expect(mockPush).toHaveBeenCalledWith('/login')
  })
})
