import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowTransition } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useWorkflowTransitions } = await import('../../../composables/useWorkflowTransitions')

function makeTransition(overrides: Partial<WorkflowTransition> = {}): WorkflowTransition {
  return {
    id: 1,
    workflow_version_id: 7,
    from_stage_id: 10,
    action_id: 20,
    to_stage_id: 11,
    requires_comment: false,
    confirmation_message: null,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

describe('useWorkflowTransitions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches transitions for a version', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeTransition()] })
    const { transitions, fetchTransitions } = useWorkflowTransitions()
    await fetchTransitions(7)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-versions/7/transitions')
    expect(transitions.value).toHaveLength(1)
  })

  it('appends a created transition', async () => {
    mockGet.mockResolvedValueOnce({ data: [] })
    mockPost.mockResolvedValueOnce({ data: makeTransition({ id: 2 }) })

    const { transitions, fetchTransitions, createTransition } = useWorkflowTransitions()
    await fetchTransitions(7)
    await createTransition(7, { from_stage_id: 10, action_id: 20, to_stage_id: 11 })

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/workflow-versions/7/transitions',
      expect.objectContaining({ from_stage_id: 10, action_id: 20, to_stage_id: 11 }),
    )
    expect(transitions.value).toHaveLength(1)
  })

  it('removes a deleted transition', async () => {
    const transition = makeTransition()
    mockGet.mockResolvedValueOnce({ data: [transition] })
    mockDelete.mockResolvedValueOnce(undefined)

    const { transitions, fetchTransitions, deleteTransition } = useWorkflowTransitions()
    await fetchTransitions(7)
    await deleteTransition(transition)

    expect(mockDelete).toHaveBeenCalledWith('/api/v1/workflow-versions/7/transitions/1')
    expect(transitions.value).toHaveLength(0)
  })

  it('records an error on fetch failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { error, transitions, fetchTransitions } = useWorkflowTransitions()
    await fetchTransitions(7)

    expect(error.value).toBeTruthy()
    expect(transitions.value).toHaveLength(0)
  })
})
