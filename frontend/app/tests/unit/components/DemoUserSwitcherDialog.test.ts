// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { UserRole } from '../../../types/enums'
import type { DemoUser } from '../../../types/models'

const mockFetchDemoUsers = vi.fn()
const mockUsers = ref<DemoUser[]>([])
const mockLoading = ref(false)
const mockError = ref<string | null>(null)

vi.mock('../../../composables/useDemoUsers', () => ({
  useDemoUsers: () => ({
    users: mockUsers,
    loading: mockLoading,
    error: mockError,
    fetchDemoUsers: mockFetchDemoUsers,
  }),
}))

const mockSwitchDemoUser = vi.fn()
vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    switchDemoUser: mockSwitchDemoUser,
  }),
}))

vi.stubGlobal('navigateTo', vi.fn())

// shadcn Dialog's DialogContent renders inside a reka-ui DialogPortal, which
// teleports its content to document.body — @vue/test-utils' mount() wrapper
// cannot introspect Teleport targets. Per AGENTS.md, Dialog must not be
// downgraded to raw HTML in the SOURCE component to make tests pass; instead
// (same technique as CommandPalette.test.ts's `@/components/ui/command`
// mock) the TEST replaces the shadcn Dialog module with simple passthrough
// stubs that render their default slots directly into the DOM, no Teleport
// involved. DemoUserSwitcherDialog.vue itself is untouched and keeps using
// the real `<Dialog>`/`<DialogContent>` API surface.
function passthrough(name: string) {
  return defineComponent({
    name,
    setup(_, { slots, attrs }) {
      return () => h('div', attrs, slots.default?.())
    },
  })
}

vi.mock('../../../components/ui/dialog', () => ({
  Dialog: passthrough('Dialog'),
  DialogContent: passthrough('DialogContent'),
  DialogHeader: passthrough('DialogHeader'),
  DialogTitle: passthrough('DialogTitle'),
  DialogDescription: passthrough('DialogDescription'),
}))

const SAMPLE_USERS: DemoUser[] = [
  {
    id: 1,
    name: 'Fatima Al-Maqtari',
    email: 'admin@ybrd.com.ye',
    role: UserRole.BANK_ADMIN,
    role_label: 'مسؤول البنك / Bank Admin',
    organization: { id: 1, code: 'commercial_banks', name: 'Commercial Banks' },
    team: { id: 1, organization_id: 1, code: 'bank_admin', name: 'Bank Admin' },
    bank: { id: 1, code: 'ybrd', name: 'YBRD' },
  },
  {
    id: 2,
    name: 'Nada Al-Kibsi',
    email: 'exec2@cby.gov.ye',
    role: UserRole.EXECUTIVE_MEMBER,
    role_label: 'عضو تنفيذي / Executive Committee Member',
    organization: { id: 2, code: 'national_committee', name: 'National Committee' },
    team: { id: 2, organization_id: 2, code: 'executive', name: 'Executive' },
    bank: null,
  },
]

const { default: DemoUserSwitcherDialog } =
  await import('../../../components/auth/DemoUserSwitcherDialog.vue')

describe('DemoUserSwitcherDialog', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchDemoUsers.mockReset()
    mockSwitchDemoUser.mockReset()
    mockUsers.value = [...SAMPLE_USERS]
    mockLoading.value = false
    mockError.value = null
  })

  it('filters the visible users by the search query', async () => {
    const wrapper = mount(DemoUserSwitcherDialog, {
      props: { open: true, 'onUpdate:open': () => {} },
    })

    expect(wrapper.text()).toContain('Fatima Al-Maqtari')
    expect(wrapper.text()).toContain('Nada Al-Kibsi')

    const searchInput = wrapper.find('input[type="text"], input:not([type])')
    await searchInput.setValue('Nada')

    expect(wrapper.text()).not.toContain('Fatima Al-Maqtari')
    expect(wrapper.text()).toContain('Nada Al-Kibsi')
  })

  it('calls switchDemoUser with the clicked user id', async () => {
    mockSwitchDemoUser.mockResolvedValueOnce(undefined)

    const wrapper = mount(DemoUserSwitcherDialog, {
      props: { open: true, 'onUpdate:open': () => {} },
    })

    const card = wrapper.findAll('[role="button"]').find((el) => el.text().includes('Nada'))
    await card?.trigger('click')

    expect(mockSwitchDemoUser).toHaveBeenCalledWith(2)
  })
})
