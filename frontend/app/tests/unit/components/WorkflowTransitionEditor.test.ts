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
// WorkflowTransitionEditor.vue itself is untouched and keeps using the real
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

const WorkflowTransitionEditor = (
  await import('../../../components/workflow/WorkflowTransitionEditor.vue')
).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

const STAGES = [
  {
    id: 10,
    workflow_version_id: 7,
    code: 'a',
    name: 'مرحلة أ',
    description: null,
    sort_order: 1,
    is_initial: true,
    is_final: false,
    sla_duration_minutes: null,
    status: 'ACTIVE',
    created_at: null,
    updated_at: null,
    version: 1,
  },
  {
    id: 11,
    workflow_version_id: 7,
    code: 'b',
    name: 'مرحلة ب',
    description: null,
    sort_order: 2,
    is_initial: false,
    is_final: true,
    sla_duration_minutes: null,
    status: 'ACTIVE',
    created_at: null,
    updated_at: null,
    version: 1,
  },
]
const ACTIONS = [
  {
    id: 20,
    code: 'APPROVE',
    name: 'اعتماد',
    kind: 'APPROVE',
    is_active: true,
    is_system: true,
    is_in_use: false,
    created_at: null,
    updated_at: null,
    version: 1,
  },
]

function makeTransition(overrides = {}) {
  return {
    id: 1,
    workflow_version_id: 7,
    from_stage_id: 10,
    action_id: 20,
    to_stage_id: 11,
    requires_comment: true,
    confirmation_message: null,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

function makeVersion(state: 'DRAFT' | 'PUBLISHED' = 'DRAFT') {
  return {
    id: 7,
    workflow_definition_id: 1,
    version_number: 1,
    state,
    is_editable: state === 'DRAFT',
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 1,
  }
}

async function mountEditor(
  capabilities: Array<'VIEW' | 'MANAGE'>,
  state: 'DRAFT' | 'PUBLISHED' = 'DRAFT',
  transitions = [makeTransition()],
) {
  mockGet.mockImplementation((url: string) => {
    if (url.includes('/transitions')) return Promise.resolve({ data: transitions })
    if (url.includes('/stages')) return Promise.resolve({ data: STAGES })
    if (url.includes('workflow-actions')) return Promise.resolve({ data: ACTIONS, meta: META })
    return Promise.resolve({ data: [] })
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowTransitionEditor, {
    props: { version: makeVersion(state) },
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

describe('WorkflowTransitionEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders transitions resolving stage and action names', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).toContain('مرحلة أ')
    expect(wrapper.text()).toContain('اعتماد')
    expect(wrapper.text()).toContain('مرحلة ب')
  })

  it('shows add affordance on a DRAFT version for MANAGE users', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByText(wrapper, 'إضافة انتقال')).toBeDefined()
  })

  it('hides mutation affordances on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByText(wrapper, 'إضافة انتقال')).toBeUndefined()
    expect(buttonByLabel(wrapper, 'حذف الانتقال')).toBeUndefined()
    expect(wrapper.text()).toContain('مقفلة')
  })

  it('hides add for VIEW-only users', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(buttonByText(wrapper, 'إضافة انتقال')).toBeUndefined()
  })

  it('shows an empty state when there are no transitions', async () => {
    const wrapper = await mountEditor(['VIEW'], 'DRAFT', [])

    expect(wrapper.text()).toContain('لا توجد انتقالات')
  })

  it('shows an edit affordance for MANAGE users on a DRAFT version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByLabel(wrapper, 'تعديل الانتقال')).toBeDefined()
  })

  it('hides edit affordance on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByLabel(wrapper, 'تعديل الانتقال')).toBeUndefined()
  })

  it('sends a PUT with a locked from_stage_id/action_id when saving an edited transition', async () => {
    const transition = makeTransition({ version: 1 })
    mockPut.mockResolvedValueOnce({ data: makeTransition({ version: 2 }) })

    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'DRAFT', [transition])
    await buttonByLabel(wrapper, 'تعديل الانتقال')?.trigger('click')
    await flushPromises()

    await buttonByText(wrapper, 'حفظ')?.trigger('click')
    await flushPromises()

    expect(mockPut).toHaveBeenCalledTimes(1)
    const [url, body] = mockPut.mock.calls[0] as [string, Record<string, unknown>]
    expect(url).toBe('/api/v1/workflow-versions/7/transitions/1')
    expect(body).not.toHaveProperty('from_stage_id')
    expect(body).not.toHaveProperty('action_id')
  })
})
