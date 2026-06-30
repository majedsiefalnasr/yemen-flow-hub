// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { NAV_ITEMS } from '../../../constants/workflow'
import { UserRole } from '../../../types/enums'
import CommandPalette from '../../../components/CommandPalette.vue'

const authUser = ref({
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  role: UserRole.DATA_ENTRY as UserRole,
})

const push = vi.fn()
let keydownHandler: ((event: KeyboardEvent) => void) | null = null

const QUICK_ACTION_LABELS: Record<UserRole, string[]> = {
  [UserRole.DATA_ENTRY]: ['تقديم طلب جديد'],
  [UserRole.BANK_REVIEWER]: ['طلبات تنتظر المراجعة'],
  [UserRole.BANK_ADMIN]: ['لوحة الإشراف البنكي'],
  [UserRole.SWIFT_OFFICER]: ['رفع مستندات SWIFT'],
  [UserRole.SUPPORT_COMMITTEE]: ['قائمة انتظار لجنة المساندة'],
  [UserRole.EXECUTIVE_MEMBER]: ['جلسات التصويت النشطة'],
  [UserRole.COMMITTEE_DIRECTOR]: ['التحكم في جلسات التصويت', 'تأكيد المصارفة الخارجية'],
  [UserRole.CBY_ADMIN]: ['لوحة الحوكمة والإشراف', 'سجلات التدقيق والامتثال'],
}

function passthrough(name: string) {
  return defineComponent({
    name,
    setup(_, { slots, attrs }) {
      return () => h('div', attrs, slots.default?.())
    },
  })
}

vi.mock('@vueuse/core', () => ({
  useMagicKeys: () => ({ meta_k: ref(false), ctrl_k: ref(false) }),
  whenever: () => {},
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get user() {
      return authUser.value
    },
  }),
}))

vi.mock('../../../components/ui/command', () => ({
  CommandDialog: passthrough('CommandDialog'),
  CommandEmpty: passthrough('CommandEmpty'),
  CommandGroup: passthrough('CommandGroup'),
  CommandInput: passthrough('CommandInput'),
  CommandItem: passthrough('CommandItem'),
  CommandList: passthrough('CommandList'),
  CommandSeparator: passthrough('CommandSeparator'),
  CommandShortcut: passthrough('CommandShortcut'),
}))

vi.stubGlobal('useRouter', () => ({ push }))
vi.stubGlobal('useEventListener', (_event: string, handler: (event: KeyboardEvent) => void) => {
  keydownHandler = handler
})

function mountForRole(role: UserRole) {
  authUser.value = { ...authUser.value, role }
  return mount(CommandPalette, {
    global: {
      stubs: {
        Button: passthrough('Button'),
        Kbd: passthrough('Kbd'),
      },
    },
  })
}

describe('CommandPalette role safety', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    keydownHandler = null
  })

  it('renders only role-allowed navigation labels (forbidden routes not mounted)', () => {
    for (const role of Object.values(UserRole)) {
      const wrapper = mountForRole(role)
      const text = wrapper.text()
      const allowed = NAV_ITEMS.filter((item) => item.roles.includes(role))
      const forbidden = NAV_ITEMS.filter((item) => !item.roles.includes(role))

      for (const item of allowed) {
        expect(text, `Role ${role} should render ${item.route}`).toContain(item.label)
      }
      for (const item of forbidden) {
        expect(text, `Role ${role} should not render ${item.route}`).not.toContain(item.label)
      }

      wrapper.unmount()
    }
  })

  it('renders only role-allowed quick actions (forbidden actions not mounted)', () => {
    const allQuickLabels = Object.values(QUICK_ACTION_LABELS).flat()

    for (const role of Object.values(UserRole)) {
      const wrapper = mountForRole(role)
      const text = wrapper.text()
      const allowedQuick = QUICK_ACTION_LABELS[role]
      const forbiddenQuick = allQuickLabels.filter((label) => !allowedQuick.includes(label))

      for (const label of allowedQuick) {
        expect(text, `Role ${role} should render quick action: ${label}`).toContain(label)
      }
      for (const label of forbiddenQuick) {
        expect(text, `Role ${role} should hide quick action: ${label}`).not.toContain(label)
      }

      wrapper.unmount()
    }
  })

  it('does not register "/" keyboard shortcut (reserved for table search inputs)', () => {
    const wrapper = mountForRole(UserRole.DATA_ENTRY)
    expect(keydownHandler).toBeNull()

    wrapper.unmount()
  })
})
