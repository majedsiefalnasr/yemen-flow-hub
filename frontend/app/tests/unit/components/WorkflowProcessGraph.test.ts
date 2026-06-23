// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowGraph, WorkflowVersion } from '../../../types/models'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const WorkflowProcessGraph = (await import('../../../components/workflow/WorkflowProcessGraph.vue'))
  .default

function makeVersion(): WorkflowVersion {
  return {
    id: 7,
    workflow_definition_id: 1,
    version_number: 1,
    state: 'DRAFT',
    is_editable: true,
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 1,
  }
}

const FULL_GRAPH: WorkflowGraph = {
  nodes: [
    {
      id: 1,
      code: 'intake',
      name: 'الاستلام',
      display_label: null,
      is_initial: true,
      is_final: false,
      sort_order: 0,
    },
    {
      id: 2,
      code: 'review',
      name: 'المراجعة',
      display_label: null,
      is_initial: false,
      is_final: true,
      sort_order: 1,
    },
  ],
  edges: [
    {
      id: 10,
      from_stage_id: 1,
      to_stage_id: 2,
      action_id: 20,
      action_code: 'APPROVE',
      action_name: 'اعتماد',
      requires_comment: false,
      is_self_loop: false,
      is_return: false,
    },
    {
      id: 11,
      from_stage_id: 2,
      to_stage_id: 1,
      action_id: 21,
      action_code: 'RETURN',
      action_name: 'إعادة',
      requires_comment: true,
      is_self_loop: false,
      is_return: true,
    },
  ],
}

async function mountGraph(graph: WorkflowGraph | null) {
  mockGet.mockResolvedValueOnce(
    graph === null ? { data: { nodes: [], edges: [] } } : { data: graph },
  )
  const wrapper = mount(WorkflowProcessGraph, {
    props: { version: makeVersion() },
    global: { stubs: { Teleport: true, NuxtLink: true } },
  })
  await flushPromises()
  return wrapper
}

describe('WorkflowProcessGraph', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders nodes with initial/final badges and edges with action labels', async () => {
    const wrapper = await mountGraph(FULL_GRAPH)

    expect(wrapper.text()).toContain('الاستلام')
    expect(wrapper.text()).toContain('المراجعة')
    expect(wrapper.text()).toContain('بداية')
    expect(wrapper.text()).toContain('نهاية')
    expect(wrapper.text()).toContain('اعتماد')
    expect(wrapper.text()).toContain('إعادة')
  })

  it('shows an empty state when there are no nodes', async () => {
    const wrapper = await mountGraph(null)

    expect(wrapper.text()).toContain('لا يوجد مخطط')
  })
})
