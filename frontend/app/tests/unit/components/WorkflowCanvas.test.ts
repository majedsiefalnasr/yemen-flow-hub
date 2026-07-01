// @vitest-environment jsdom

import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'

// VueFlow uses SVGElement.getBBox internally which is unavailable in jsdom.
// Stub the entire @vue-flow/core module so the canvas mounts without crashing.
vi.mock('@vue-flow/core', () => ({
  VueFlow: { template: '<div class="vue-flow-stub"><slot /></div>' },
  Panel: { template: '<div><slot /></div>' },
  Handle: { template: '<div />' },
  Position: { Left: 'left', Right: 'right', Top: 'top', Bottom: 'bottom' },
  MarkerType: { Arrow: 'arrow', ArrowClosed: 'arrowclosed' },
  useVueFlow: () => ({
    zoomIn: vi.fn(),
    zoomOut: vi.fn(),
    fitView: vi.fn(),
  }),
}))

vi.mock('@vue-flow/background', () => ({
  Background: { template: '<div />' },
  BackgroundVariant: { Dots: 'dots', Lines: 'lines' },
}))

const mockGraph = {
  nodes: [
    {
      id: 1,
      code: 'INTAKE',
      name: 'استلام',
      display_label: null,
      is_initial: true,
      is_final: false,
      sort_order: 0,
    },
    {
      id: 2,
      code: 'APPROVED',
      name: 'اعتماد',
      display_label: 'اعتماد نهائي',
      is_initial: false,
      is_final: true,
      sort_order: 1,
    },
  ],
  edges: [
    {
      id: 9,
      from_stage_id: 1,
      to_stage_id: 2,
      action_id: 3,
      action_code: 'APPROVE',
      action_name: 'اعتماد',
      requires_comment: true,
      is_self_loop: false,
      is_return: false,
    },
  ],
}

const fetchGraph = vi.fn()
const graph = ref(mockGraph)
const loading = ref(false)
const error = ref<string | null>(null)

vi.mock('@/composables/useWorkflowGraph', () => ({
  useWorkflowGraph: () => ({ graph, loading, error, fetchGraph }),
}))

vi.mock('@/composables/useWorkflowStages', () => ({
  useWorkflowStages: () => ({
    stages: ref([]),
    loading: ref(false),
    error: ref(null),
    fetchStages: vi.fn(),
    createStage: vi.fn(),
    updateStage: vi.fn(),
  }),
}))

vi.mock('@/composables/useWorkflowTransitions', () => ({
  useWorkflowTransitions: () => ({
    transitions: ref([]),
    createTransition: vi.fn(),
    updateTransition: vi.fn(),
    deleteTransition: vi.fn(),
    fetchTransitions: vi.fn(),
  }),
}))

vi.mock('@/composables/useWorkflowActions', () => ({
  useWorkflowActions: () => ({
    actions: ref([]),
    fetchActions: vi.fn(),
  }),
}))

const draftVersion = {
  id: 10,
  workflow_definition_id: 1,
  version_number: 2,
  state: 'DRAFT',
  is_editable: true,
  published_at: null,
  created_at: null,
  updated_at: null,
  version: 1,
} as const

const publishedVersion = {
  ...draftVersion,
  state: 'PUBLISHED',
  is_editable: false,
} as const

describe('WorkflowCanvas', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    graph.value = mockGraph
    loading.value = false
    error.value = null
  })

  it('loads the workflow graph for the selected version', async () => {
    mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    expect(fetchGraph).toHaveBeenCalledWith(10)
  })

  // Node and edge DOM introspection is not possible in jsdom: VueFlow renders
  // nodes via its own internal renderer inside a stubbed component boundary.
  // The canvas renders correctly in a real browser (verified via playwright-cli).
  it.todo('renders stage nodes and action edge labels — requires real browser (VueFlow jsdom limitation)')

  it('shows edit affordances for draft versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    // Buttons show "مرحلة" (add stage) and "انتقال" (add transition) for draft
    expect(wrapper.text()).toContain('انتقال')
    expect(wrapper.find('[data-testid="workflow-canvas-readonly"]').exists()).toBe(false)
  })

  it('is inspect-only for published versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: publishedVersion } })
    await flushPromises()
    // No edit buttons present for published versions
    expect(wrapper.text()).not.toContain('مرحلة جديدة')
    expect(wrapper.get('[data-testid="workflow-canvas-readonly"]').text()).toContain('للعرض فقط')
  })
})
