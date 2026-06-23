import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowStage } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useWorkflowStages } = await import('../../../composables/useWorkflowStages')

function makeStage(overrides: Partial<WorkflowStage> = {}): WorkflowStage {
  return {
    id: 1,
    workflow_version_id: 7,
    code: 'intake',
    name: 'Intake',
    description: null,
    sort_order: 1,
    is_initial: true,
    is_final: false,
    sla_duration_minutes: null,
    status: 'ACTIVE',
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

describe('useWorkflowStages', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches stages for a version', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeStage()] })
    const { stages, fetchStages } = useWorkflowStages()
    await fetchStages(7)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-versions/7/stages')
    expect(stages.value).toHaveLength(1)
  })

  it('inserts a created stage in sort order', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeStage({ id: 1, sort_order: 2 })] })
    mockPost.mockResolvedValueOnce({ data: makeStage({ id: 2, code: 'review', sort_order: 1 }) })

    const { stages, fetchStages, createStage } = useWorkflowStages()
    await fetchStages(7)
    await createStage(7, { code: 'review', name: 'Review', sort_order: 1 })

    expect(stages.value[0]?.code).toBe('review')
    expect(stages.value).toHaveLength(2)
  })

  it('sends the optimistic version on update', async () => {
    const stage = makeStage({ version: 3 })
    mockGet.mockResolvedValueOnce({ data: [stage] })
    mockPut.mockResolvedValueOnce({ data: makeStage({ name: 'Bank Intake', version: 4 }) })

    const { stages, fetchStages, updateStage } = useWorkflowStages()
    await fetchStages(7)
    await updateStage(stage, { name: 'Bank Intake' })

    expect(mockPut).toHaveBeenCalledWith(
      '/api/v1/workflow-versions/7/stages/1',
      expect.objectContaining({ version: 3 }),
    )
    expect(stages.value[0]?.name).toBe('Bank Intake')
  })

  it('removes a deleted stage', async () => {
    const stage = makeStage()
    mockGet.mockResolvedValueOnce({ data: [stage] })
    mockDelete.mockResolvedValueOnce(undefined)

    const { stages, fetchStages, deleteStage } = useWorkflowStages()
    await fetchStages(7)
    await deleteStage(stage)

    expect(mockDelete).toHaveBeenCalledWith('/api/v1/workflow-versions/7/stages/1')
    expect(stages.value).toHaveLength(0)
  })

  it('records an error on fetch failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { error, stages, fetchStages } = useWorkflowStages()
    await fetchStages(7)

    expect(error.value).toBeTruthy()
    expect(stages.value).toHaveLength(0)
  })
})
