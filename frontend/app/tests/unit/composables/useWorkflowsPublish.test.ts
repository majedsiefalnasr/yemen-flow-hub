import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowVersion } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost }),
}))

const { useWorkflows } = await import('../../../composables/useWorkflows')

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeVersion(overrides: Partial<WorkflowVersion> = {}): WorkflowVersion {
  return {
    id: 10,
    workflow_definition_id: 1,
    version_number: 1,
    state: 'DRAFT',
    is_editable: true,
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 3,
    ...overrides,
  }
}

describe('useWorkflows validate/publish', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns validation errors from the validate endpoint', async () => {
    mockPost.mockResolvedValueOnce({
      data: { errors: [{ code: 'NO_FINAL_STAGE', target: 'stages', message: 'No final stage.' }] },
    })

    const { validateVersion } = useWorkflows()
    const errors = await validateVersion(10)

    expect(mockPost).toHaveBeenCalledWith('/api/v1/workflow-versions/10/validate', {})
    expect(errors).toHaveLength(1)
    expect(errors[0]?.code).toBe('NO_FINAL_STAGE')
  })

  it('publishes a version with its optimistic version and archives the prior published', async () => {
    const draft = makeVersion({ id: 10, state: 'DRAFT', version: 3 })
    const priorPublished = makeVersion({ id: 9, state: 'PUBLISHED', version: 5 })
    const definition = {
      id: 1,
      code: 'flow',
      name: 'Flow',
      description: null,
      is_active: true,
      versions: [draft, priorPublished],
      created_at: null,
      updated_at: null,
      version: 1,
    }
    mockGet.mockResolvedValueOnce({ data: [definition], meta: META })
    mockPost.mockResolvedValueOnce({ data: { ...draft, state: 'PUBLISHED', version: 4 } })

    const { definitions, fetchDefinitions, publishVersion } = useWorkflows()
    await fetchDefinitions()
    await publishVersion(draft)

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/workflow-versions/10/publish',
      expect.objectContaining({ version: 3 }),
    )

    const versions = definitions.value[0]?.versions ?? []
    expect(versions.find((v) => v.id === 10)?.state).toBe('PUBLISHED')
    expect(versions.find((v) => v.id === 9)?.state).toBe('ARCHIVED')
  })
})
