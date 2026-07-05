// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

// shadcn Dialog's DialogContent renders inside a reka-ui DialogPortal, which
// teleports its content to document.body — @vue/test-utils' mount() wrapper
// cannot introspect Teleport targets. Per AGENTS.md, Dialog must not be
// downgraded to raw HTML in the SOURCE component to make tests pass; instead
// (same technique as WorkflowFieldDesigner.test.ts) the TEST replaces the
// shadcn Dialog module with simple passthrough stubs that render their
// default slots directly into the DOM, no Teleport involved.
// WorkflowActionsCatalog.vue itself is untouched and keeps using the real
// `<Dialog>`/`<DialogContent>` API surface.
function passthrough(name: string) {
  return defineComponent({
    name,
    inheritAttrs: false,
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
  DialogFooter: passthrough('DialogFooter'),
}))

const WorkflowActionsCatalog = (
  await import('../../../components/workflow/WorkflowActionsCatalog.vue')
).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeAction(overrides = {}) {
  return {
    id: 1,
    code: 'APPROVE',
    name: 'اعتماد',
    kind: 'APPROVE',
    is_active: true,
    is_system: true,
    is_in_use: false,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

async function mountCatalog(capabilities: Array<'VIEW' | 'MANAGE'>, actions = [makeAction()]) {
  mockGet.mockResolvedValueOnce({ data: actions, meta: META })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowActionsCatalog, {
    global: {
      plugins: [pinia],
      stubs: {
        Teleport: true,
        NuxtLink: true,
        // Tooltip needs a TooltipProvider ancestor (supplied at app root); render
        // the trigger slot transparently in isolated mounts.
        Tooltip: { template: '<div><slot /></div>' },
        TooltipTrigger: { template: '<div><slot /></div>' },
        TooltipContent: { template: '<div><slot /></div>' },
      },
    },
  })
  await flushPromises()

  return wrapper
}

function buttonByLabel(wrapper: VueWrapper, label: string) {
  return wrapper.findAll('button').find((b) => b.attributes('aria-label') === label)
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((b) => b.text().trim().includes(text))
}

describe('WorkflowActionsCatalog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders actions with kind and system badges', async () => {
    const wrapper = await mountCatalog(['VIEW'])

    expect(wrapper.text()).toContain('APPROVE')
    expect(wrapper.text()).toContain('اعتماد')
    expect(wrapper.text()).toContain('نظامي')
  })

  it('hides create/edit/delete affordances for VIEW-only users', async () => {
    const wrapper = await mountCatalog(['VIEW'])

    expect(buttonByText(wrapper, 'إضافة إجراء')).toBeUndefined()
    expect(buttonByLabel(wrapper, 'تعديل الإجراء')).toBeUndefined()
  })

  it('shows create and edit for permitted users', async () => {
    const wrapper = await mountCatalog(['VIEW', 'MANAGE'])

    expect(buttonByText(wrapper, 'إضافة إجراء')).toBeDefined()
    expect(buttonByLabel(wrapper, 'تعديل الإجراء')).toBeDefined()
  })

  it('hides delete for system actions even with MANAGE permission', async () => {
    const wrapper = await mountCatalog(['VIEW', 'MANAGE'], [makeAction({ is_system: true })])

    expect(buttonByLabel(wrapper, 'حذف الإجراء')).toBeUndefined()
  })

  it('shows delete for a custom unused action with MANAGE permission', async () => {
    const wrapper = await mountCatalog(
      ['VIEW', 'MANAGE'],
      [makeAction({ id: 2, code: 'ESCALATE', is_system: false, is_in_use: false })],
    )

    expect(buttonByLabel(wrapper, 'حذف الإجراء')).toBeDefined()
  })

  it('does not render an active-status column', async () => {
    const wrapper = await mountCatalog(['VIEW'])

    expect(wrapper.text()).not.toContain('نشط')
  })

  it('shows inactive badge when action is not active', async () => {
    const wrapper = await mountCatalog(['VIEW'], [makeAction({ is_active: false })])

    expect(wrapper.text()).toContain('غير نشط')
  })

  it('does not show inactive badge when action is active', async () => {
    const wrapper = await mountCatalog(['VIEW'], [makeAction({ is_active: true })])

    expect(wrapper.text()).not.toContain('غير نشط')
  })

  it('toggles is_active via setActionActive when the edit-dialog switch flips', async () => {
    const action = makeAction({ is_active: true, version: 1 })
    mockPost.mockResolvedValueOnce({ data: makeAction({ is_active: false, version: 2 }) })

    const wrapper = await mountCatalog(['VIEW', 'MANAGE'], [action])
    await buttonByLabel(wrapper, 'تعديل الإجراء')?.trigger('click')
    await flushPromises()

    const toggle = wrapper.find('#action-is-active')
    expect(toggle.exists()).toBe(true)

    await toggle.trigger('click')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/workflow-actions/1/deactivate',
      expect.objectContaining({ version: 1 }),
    )
  })
})
