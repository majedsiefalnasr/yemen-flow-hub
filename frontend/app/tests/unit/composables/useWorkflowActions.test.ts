import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowAction } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useWorkflowActions } = await import('../../../composables/useWorkflowActions')

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeAction(overrides: Partial<WorkflowAction> = {}): WorkflowAction {
  return {
    id: 1,
    code: 'APPROVE',
    name: 'Approve',
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

describe('useWorkflowActions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches actions', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeAction()], meta: META })
    const { actions, fetchActions } = useWorkflowActions()
    await fetchActions()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-actions', expect.anything())
    expect(actions.value).toHaveLength(1)
  })

  it('prepends a created action', async () => {
    mockGet.mockResolvedValueOnce({ data: [], meta: META })
    mockPost.mockResolvedValueOnce({
      data: makeAction({ id: 2, code: 'ESCALATE', kind: 'CUSTOM', is_system: false }),
    })

    const { actions, fetchActions, createAction } = useWorkflowActions()
    await fetchActions()
    await createAction({ code: 'ESCALATE', name: 'Escalate', kind: 'CUSTOM' })

    expect(actions.value[0]?.code).toBe('ESCALATE')
  })

  it('sends optimistic version on update and replaces in place', async () => {
    const action = makeAction({ version: 2 })
    mockGet.mockResolvedValueOnce({ data: [action], meta: META })
    mockPut.mockResolvedValueOnce({ data: makeAction({ name: 'Approved', version: 3 }) })

    const { actions, fetchActions, updateAction } = useWorkflowActions()
    await fetchActions()
    await updateAction(action, { name: 'Approved' })

    expect(mockPut).toHaveBeenCalledWith(
      '/api/v1/workflow-actions/1',
      expect.objectContaining({ version: 2 }),
    )
    expect(actions.value[0]?.name).toBe('Approved')
  })

  it('toggles active state via activate/deactivate endpoints', async () => {
    const action = makeAction({ is_active: true, is_system: false, version: 1 })
    mockGet.mockResolvedValueOnce({ data: [action], meta: META })
    mockPost.mockResolvedValueOnce({ data: makeAction({ is_active: false, version: 2 }) })

    const { actions, fetchActions, setActionActive } = useWorkflowActions()
    await fetchActions()
    await setActionActive(action, false)

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/workflow-actions/1/deactivate',
      expect.objectContaining({ version: 1 }),
    )
    expect(actions.value[0]?.is_active).toBe(false)
  })

  it('removes a deleted action', async () => {
    const action = makeAction({ is_system: false })
    mockGet.mockResolvedValueOnce({ data: [action], meta: META })
    mockDelete.mockResolvedValueOnce(undefined)

    const { actions, fetchActions, deleteAction } = useWorkflowActions()
    await fetchActions()
    await deleteAction(action)

    expect(actions.value).toHaveLength(0)
  })

  it('records an error on fetch failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { error, actions, fetchActions } = useWorkflowActions()
    await fetchActions()

    expect(error.value).toBeTruthy()
    expect(actions.value).toHaveLength(0)
  })
})
