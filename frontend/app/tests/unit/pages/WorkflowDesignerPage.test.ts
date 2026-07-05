// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    del: vi.fn(),
  }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const WorkflowDesignerPage = (await import('../../../pages/admin/workflows.vue')).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeDefinition(overrides = {}) {
  return {
    id: 1,
    code: 'import_financing',
    name: 'تمويل الاستيراد',
    description: null,
    is_active: true,
    created_at: null,
    updated_at: null,
    version: 1,
    versions: [
      {
        id: 10,
        workflow_definition_id: 1,
        version_number: 1,
        state: 'PUBLISHED',
        is_editable: false,
        published_at: null,
        created_at: null,
        updated_at: null,
        version: 2,
      },
    ],
    ...overrides,
  }
}

async function mountPage(
  capabilities: Array<'VIEW' | 'MANAGE'>,
  definitions = [makeDefinition()],
  extraStubs: Record<string, unknown> = {},
) {
  // The page mounts both useWorkflows (definitions) and WorkflowActionsCatalog
  // (actions). Child onMounted runs before parent, so route by URL, not call order.
  mockGet.mockImplementation((url: string) => {
    if (url.includes('workflow-actions')) {
      return Promise.resolve({ data: [], meta: META })
    }
    return Promise.resolve({ data: definitions, meta: META })
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowDesignerPage, {
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
        ...extraStubs,
      },
    },
  })
  await flushPromises()

  return wrapper
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((button) => button.text().trim().includes(text))
}

describe('workflow designer page', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('does not mount the page without VIEW permission', async () => {
    const wrapper = await mountPage([])

    expect(wrapper.text()).not.toContain('مصمم مسارات العمل')
  })

  it('renders definitions and version state badges for VIEW users', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(wrapper.text()).toContain('تمويل الاستيراد')
    expect(wrapper.text()).toContain('import_financing')
    expect(wrapper.text()).toContain('منشورة')
  })

  it('hides create and clone controls for VIEW-only users', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(buttonByText(wrapper, 'إنشاء مسار عمل')).toBeUndefined()
    expect(buttonByText(wrapper, 'استنساخ')).toBeUndefined()
  })

  it('shows the clone action on published versions for MANAGE users', async () => {
    const wrapper = await mountPage(['VIEW', 'MANAGE'])

    expect(buttonByText(wrapper, 'استنساخ')).toBeDefined()
  })

  it('disables clone for draft versions (locked, not cloneable)', async () => {
    const draftDefinition = makeDefinition({
      versions: [
        {
          id: 11,
          workflow_definition_id: 1,
          version_number: 2,
          state: 'DRAFT',
          is_editable: true,
          published_at: null,
          created_at: null,
          updated_at: null,
          version: 1,
        },
      ],
    })
    const wrapper = await mountPage(['VIEW', 'MANAGE'], [draftDefinition])

    expect(wrapper.text()).toContain('مسودة')
    expect(buttonByText(wrapper, 'استنساخ')?.attributes('disabled')).toBeDefined()
  })

  it('shows an empty state when there are no definitions', async () => {
    const wrapper = await mountPage(['VIEW'], [])

    expect(wrapper.text()).toContain('لا توجد مسارات عمل')
  })

  const canvasStubs = {
    WorkflowCanvas: { template: '<section>لوحة مسار العمل</section>' },
  }

  it('shows normal and canvas view switches for selected versions', async () => {
    const wrapper = await mountPage(['VIEW'], [makeDefinition()], canvasStubs)

    expect(wrapper.text()).toContain('تفصيلي')
    expect(wrapper.text()).toContain('لوحة')
  })

  it('renders read-only copy for published versions', async () => {
    const wrapper = await mountPage(['VIEW'], [makeDefinition()], canvasStubs)

    expect(wrapper.text()).toContain('هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط')
  })

  it('can switch to the canvas view', async () => {
    const wrapper = await mountPage(['VIEW'], [makeDefinition()], canvasStubs)
    const canvasButton = wrapper.findAll('button').find((button) => button.text().includes('لوحة'))

    expect(canvasButton).toBeDefined()
    await canvasButton!.trigger('click')

    expect(wrapper.text()).toContain('لوحة مسار العمل')
  })

  it('does not show the read-only notice for editable draft versions', async () => {
    const draftDefinition = makeDefinition({
      versions: [
        {
          id: 11,
          workflow_definition_id: 1,
          version_number: 2,
          state: 'DRAFT',
          is_editable: true,
          published_at: null,
          created_at: null,
          updated_at: null,
          version: 1,
        },
      ],
    })
    const wrapper = await mountPage(['VIEW'], [draftDefinition], canvasStubs)

    expect(wrapper.text()).not.toContain('هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط')
  })

  it('renders the designer summary card heading with definition name and code', async () => {
    const wrapper = await mountPage(['VIEW'])

    const heading = wrapper.find('#designer-summary-heading')
    expect(heading.exists()).toBe(true)
    expect(heading.text()).toContain('تمويل الاستيراد')
    expect(heading.text()).toContain('import_financing')
  })

  it('shows stage/transition/field counts from the version resource', async () => {
    const wrapper = await mountPage(
      ['VIEW'],
      [
        makeDefinition({
          versions: [
            {
              id: 10,
              workflow_definition_id: 1,
              version_number: 1,
              state: 'PUBLISHED',
              is_editable: false,
              published_at: null,
              created_at: null,
              updated_at: null,
              version: 2,
              stages_count: 4,
              transitions_count: 6,
              fields_count: 12,
            },
          ],
        }),
      ],
    )

    expect(wrapper.text()).toContain('4 مراحل')
    expect(wrapper.text()).toContain('6 انتقالات')
    expect(wrapper.text()).toContain('12 حقول')
  })

  it('falls back to zero counts when the version resource omits them', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(wrapper.text()).toContain('0 مراحل')
    expect(wrapper.text()).toContain('0 انتقالات')
    expect(wrapper.text()).toContain('0 حقول')
  })

  it('shows the delete-version action and additional-actions menu trigger for MANAGE users', async () => {
    const wrapper = await mountPage(['VIEW', 'MANAGE'])

    expect(buttonByText(wrapper, 'حذف النسخة')).toBeDefined()
    expect(wrapper.find('[aria-label="إجراءات إضافية"]').exists()).toBe(true)
  })

  it('hides the delete-version action and additional-actions menu for VIEW-only users', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(buttonByText(wrapper, 'حذف النسخة')).toBeUndefined()
    expect(wrapper.find('[aria-label="إجراءات إضافية"]').exists()).toBe(false)
  })
})
