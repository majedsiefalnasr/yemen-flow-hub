// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { computed, defineComponent, h, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import GlobalTopbar from '../../../../components/layout/GlobalTopbar.vue'
import { UserRole } from '../../../../types/enums'

const authUser = ref({
  id: 1,
  name: 'Topbar User',
  email: 'topbar@example.com',
  role: UserRole.DATA_ENTRY as UserRole,
})

function passthrough(name: string, tag = 'div') {
  return defineComponent({
    name,
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
      return () => h(tag, attrs, slots.default?.())
    },
  })
}

vi.mock('../../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get user() {
      return authUser.value
    },
    logout: vi.fn(async () => {}),
  }),
}))

vi.mock('../../../../stores/theming.store', () => ({
  useThemingStore: () => ({
    isDark: computed(() => false),
    setMode: vi.fn(),
  }),
}))

vi.mock('../../../../components/CommandPalette.vue', () => ({
  default: defineComponent({
    name: 'CommandPaletteStub',
    template: '<button type="button" aria-label="تشغيل لوحة الأوامر">لوحة الأوامر</button>',
  }),
}))

vi.mock('../../../../components/ui/sidebar', () => ({
  SidebarTrigger: defineComponent({
    name: 'SidebarTrigger',
    inheritAttrs: false,
    setup(_, { attrs }) {
      return () => h('button', { type: 'button', ...attrs }, 'toggle')
    },
  }),
}))

vi.mock('../../../../components/ui/dropdown-menu', () => ({
  DropdownMenu: passthrough('DropdownMenu'),
  DropdownMenuContent: passthrough('DropdownMenuContent'),
  DropdownMenuGroup: passthrough('DropdownMenuGroup'),
  DropdownMenuItem: passthrough('DropdownMenuItem', 'button'),
  DropdownMenuLabel: passthrough('DropdownMenuLabel'),
  DropdownMenuSeparator: passthrough('DropdownMenuSeparator'),
  DropdownMenuTrigger: passthrough('DropdownMenuTrigger'),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

describe('GlobalTopbar keyboard checks', () => {
  it('keeps sidebar toggle and command palette trigger keyboard-focusable', () => {
    const wrapper = mount(GlobalTopbar, {
      attachTo: document.body,
      global: {
        stubs: {
          NuxtLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
          Avatar: passthrough('Avatar'),
          AvatarImage: passthrough('AvatarImage'),
          AvatarFallback: passthrough('AvatarFallback'),
          Button: passthrough('Button', 'button'),
          Separator: passthrough('Separator'),
        },
      },
    })

    const sidebarToggle = wrapper.find('[aria-label="تبديل الشريط الجانبي"]')
    const commandTrigger = wrapper.find('[aria-label="تشغيل لوحة الأوامر"]')

    expect(sidebarToggle.exists()).toBe(true)
    expect(commandTrigger.exists()).toBe(true)

    ;(sidebarToggle.element as HTMLElement).focus()
    expect(document.activeElement).toBe(sidebarToggle.element)

    ;(commandTrigger.element as HTMLElement).focus()
    expect(document.activeElement).toBe(commandTrigger.element)

    wrapper.unmount()
  })
})
