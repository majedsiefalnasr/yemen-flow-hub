// @vitest-environment jsdom

import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'

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

  it('renders stage nodes and action edge labels', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    expect(wrapper.get('[data-testid="workflow-canvas-node-1"]').text()).toContain('استلام')
    expect(wrapper.get('[data-testid="workflow-canvas-node-2"]').text()).toContain('اعتماد نهائي')
    expect(wrapper.get('[data-testid="workflow-canvas-edge-9"]').text()).toContain('اعتماد')
    expect(wrapper.get('[data-testid="workflow-canvas-edge-9"]').text()).toContain('تعليق')
  })

  it('shows edit affordances for draft versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    expect(wrapper.text()).toContain('إضافة انتقال')
    expect(wrapper.find('[data-testid="workflow-canvas-readonly"]').exists()).toBe(false)
  })

  it('is inspect-only for published versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: publishedVersion } })
    await flushPromises()
    expect(wrapper.text()).not.toContain('إضافة انتقال')
    expect(wrapper.get('[data-testid="workflow-canvas-readonly"]').text()).toContain('للعرض فقط')
  })
})
