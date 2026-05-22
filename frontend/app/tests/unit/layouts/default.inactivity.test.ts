// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DefaultLayout from '../../../layouts/default.vue'

const useInactivityTimerMock = vi.hoisted(() => vi.fn())
const sidebarState = vi.hoisted(() => ({ collapsed: false }))

vi.mock('../../../composables/useInactivityTimer', () => ({
  useInactivityTimer: useInactivityTimerMock,
}))

vi.mock('../../../composables/useSidebar', () => ({
  useSidebar: () => ({
    isCollapsed: computed(() => sidebarState.collapsed),
  }),
}))

vi.mock('../../../components/layout/AppSidebar.vue', () => ({
  default: {
    props: ['mobileOpen'],
    emits: ['close-mobile'],
    template: '<aside class="sidebar-stub" />',
  },
}))

vi.mock('../../../components/layout/AppHeader.vue', () => ({
  default: {
    emits: ['toggle-mobile-menu'],
    template: '<header class="header-stub" />',
  },
}))

describe('default layout inactivity integration', () => {
  beforeEach(() => {
    sidebarState.collapsed = false
    useInactivityTimerMock.mockReset()
    useInactivityTimerMock.mockReturnValue({
      isWarning: ref(true),
      extend: vi.fn(),
      stop: vi.fn(),
    })
  })

  it('instantiates the inactivity timer only once for the authenticated layout', () => {
    mount(DefaultLayout, {
      slots: {
        default: '<div class="page-stub">content</div>',
      },
    })

    expect(useInactivityTimerMock).toHaveBeenCalledTimes(1)
  })

  it('wires the shared timer state into the banner', () => {
    const extend = vi.fn()
    useInactivityTimerMock.mockReturnValue({
      isWarning: ref(true),
      extend,
      stop: vi.fn(),
    })

    const wrapper = mount(DefaultLayout, {
      slots: {
        default: '<div class="page-stub">content</div>',
      },
    })

    expect(wrapper.text()).toContain('أنت على وشك الخروج بسبب عدم النشاط')
  })
})
