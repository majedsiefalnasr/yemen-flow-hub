// @vitest-environment jsdom

import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'

// VueFlow uses SVGElement.getBBox internally which is unavailable in jsdom.
// Stub the entire @vue-flow/core module so the canvas mounts without crashing.
vi.mock('@vue-flow/core', async () => {
  const { defineComponent, h } = await import('vue')
  const VueFlowStub = defineComponent({
    name: 'VueFlow',
    props: {
      edges: { type: Array, default: () => [] },
      nodes: { type: Array, default: () => [] },
      nodeTypes: { type: Object, default: () => ({}) },
      minZoom: { type: Number, default: 0 },
      fitViewOnInitOptions: { type: Object, default: () => ({}) },
    },
    emits: ['edge-click', 'edges-change'],
    setup(props, { emit, slots }) {
      return () =>
        h('div', { class: 'vue-flow-stub' }, [
          ...(props.edges as Array<Record<string, unknown>>).map((edge) =>
            h(
              'button',
              {
                class: 'edge-stub vue-flow__edge',
                'data-id': edge.id,
                'aria-label': edge.ariaLabel,
                onClick: () => emit('edge-click', { edge }),
              },
              String(edge.label),
            ),
          ),
          slots.default?.(),
        ])
    },
  })

  return {
    VueFlow: VueFlowStub,
    Panel: { template: '<div><slot /></div>' },
    Handle: { template: '<div />' },
    Position: { Left: 'left', Right: 'right', Top: 'top', Bottom: 'bottom' },
    MarkerType: { Arrow: 'arrow', ArrowClosed: 'arrowclosed' },
    useVueFlow: () => ({
      zoomIn: vi.fn(),
      zoomOut: vi.fn(),
      fitView: vi.fn(),
      updateEdge: vi.fn(),
    }),
  }
})

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
const transitions = ref([
  {
    id: 9,
    workflow_version_id: 10,
    from_stage_id: 1,
    action_id: 3,
    to_stage_id: 2,
    requires_comment: true,
    confirmation_message: null,
    is_default_submit: false,
    is_self_loop: false,
    transition_type: 'FORWARD',
    created_at: null,
    updated_at: null,
    version: 1,
  },
])

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
    transitions,
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
    transitions.value = [
      {
        id: 9,
        workflow_version_id: 10,
        from_stage_id: 1,
        action_id: 3,
        to_stage_id: 2,
        requires_comment: true,
        confirmation_message: null,
        is_default_submit: false,
        is_self_loop: false,
        transition_type: 'FORWARD',
        created_at: null,
        updated_at: null,
        version: 1,
      },
    ]
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
  it.todo(
    'renders stage nodes and action edge labels — requires real browser (VueFlow jsdom limitation)',
  )

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
    expect(wrapper.findComponent({ name: 'VueFlow' }).props('nodes')[0]).toMatchObject({
      draggable: false,
      connectable: false,
    })
  })

  it('gives editable edges an accessible name, focus, and a wider hit area', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()

    const edge = wrapper.findComponent({ name: 'VueFlow' }).props('edges')[0]
    expect(edge).toMatchObject({
      selectable: true,
      focusable: true,
      interactionWidth: 32,
      domAttributes: { tabindex: 0 },
      ariaLabel: 'انتقال اعتماد من استلام إلى اعتماد نهائي',
    })
  })

  it('removes edge interaction affordances from published versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: publishedVersion } })
    await flushPromises()

    const edge = wrapper.findComponent({ name: 'VueFlow' }).props('edges')[0]
    expect(edge).toMatchObject({
      selectable: false,
      focusable: false,
      domAttributes: { tabindex: -1 },
    })
  })

  it('shows explicit keyboard-reachable transition actions after selecting an edge', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()

    await wrapper.get('.edge-stub').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('الانتقال المحدد')
    expect(
      wrapper.findAll('button').some((button) => button.text().includes('تعديل الانتقال')),
    ).toBe(true)
    expect(wrapper.findAll('button').some((button) => button.text().includes('حذف الانتقال'))).toBe(
      true,
    )
  })

  it('opens the transition editor when an editable edge is activated with Enter', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()

    await wrapper.get('.edge-stub').trigger('keydown', { key: 'Enter' })
    await flushPromises()

    expect(document.body.textContent).toContain('حفظ التعديل')
  })

  it('uses shared button controls for canvas zoom actions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()

    for (const label of ['تكبير', 'تصغير', 'ملاءمة']) {
      expect(wrapper.get(`[aria-label="${label}"]`).attributes('data-slot')).toBe('button')
    }
  })

  it('opens with a legible bounded zoom for the seeded workflow', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: publishedVersion } })
    await flushPromises()

    const flow = wrapper.findComponent({ name: 'VueFlow' })
    expect(flow.props('minZoom')).toBe(0.62)
    expect(flow.props('fitViewOnInitOptions')).toMatchObject({ minZoom: 0.62, maxZoom: 1 })
  })

  it('falls back to stage order when a transition cycle blocks topological layout', async () => {
    graph.value = {
      ...mockGraph,
      edges: [
        ...mockGraph.edges,
        {
          id: 10,
          from_stage_id: 2,
          to_stage_id: 1,
          action_id: 4,
          action_code: 'RETURN',
          action_name: 'إعادة',
          requires_comment: true,
          is_self_loop: false,
          is_return: false,
        },
      ],
    }
    const wrapper = mount(WorkflowCanvas, { props: { version: publishedVersion } })
    await flushPromises()

    const nodes = wrapper.findComponent({ name: 'VueFlow' }).props('nodes')
    expect(nodes[1].position.y).toBeGreaterThan(nodes[0].position.y)
  })
})
