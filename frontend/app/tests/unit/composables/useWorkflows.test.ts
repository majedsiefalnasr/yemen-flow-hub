import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowVersion } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDel = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDel }),
}))

// extractApiErrorMessage is a Nuxt auto-import; stub it for the composable under test.
vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useWorkflows } = await import('../../../composables/useWorkflows')

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeVersion(overrides: Partial<WorkflowVersion> = {}): WorkflowVersion {
  return {
    id: 10,
    workflow_definition_id: 1,
    version_number: 1,
    state: 'PUBLISHED',
    is_editable: false,
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 2,
    ...overrides,
  }
}

describe('useWorkflows', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches and stores definitions', async () => {
    const definition = {
      id: 1,
      code: 'import_financing',
      name: 'Import Financing',
      description: null,
      is_active: true,
      versions: [makeVersion()],
      created_at: null,
      updated_at: null,
      version: 1,
    }
    mockGet.mockResolvedValueOnce({ data: [definition], meta: META })

    const { definitions, fetchDefinitions, loading } = useWorkflows()
    await fetchDefinitions()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-definitions', expect.anything())
    expect(definitions.value).toHaveLength(1)
    expect(loading.value).toBe(false)
  })

  it('prepends a created definition', async () => {
    mockGet.mockResolvedValueOnce({ data: [], meta: META })
    const created = {
      id: 2,
      code: 'new_flow',
      name: 'New Flow',
      description: null,
      is_active: true,
      versions: [makeVersion({ id: 20, workflow_definition_id: 2, state: 'DRAFT' })],
      created_at: null,
      updated_at: null,
      version: 1,
    }
    mockPost.mockResolvedValueOnce({ data: created })

    const { definitions, fetchDefinitions, createDefinition } = useWorkflows()
    await fetchDefinitions()
    await createDefinition({ code: 'new_flow', name: 'New Flow' })

    expect(definitions.value[0]?.code).toBe('new_flow')
  })

  it('adds a cloned draft version to the parent definition', async () => {
    const source = makeVersion()
    const definition = {
      id: 1,
      code: 'import_financing',
      name: 'Import Financing',
      description: null,
      is_active: true,
      versions: [source],
      created_at: null,
      updated_at: null,
      version: 1,
    }
    mockGet.mockResolvedValueOnce({ data: [definition], meta: META })
    const clone = makeVersion({ id: 11, version_number: 2, state: 'DRAFT', is_editable: true })
    mockPost.mockResolvedValueOnce({ data: clone })

    const { definitions, fetchDefinitions, cloneVersion } = useWorkflows()
    await fetchDefinitions()
    const result = await cloneVersion(source)

    expect(result.version_number).toBe(2)
    expect(definitions.value[0]?.versions[0]?.state).toBe('DRAFT')
    expect(definitions.value[0]?.versions).toHaveLength(2)
  })

  it('records an error message when the fetch fails', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))

    const { error, definitions, fetchDefinitions } = useWorkflows()
    await fetchDefinitions()

    expect(error.value).toBeTruthy()
    expect(definitions.value).toHaveLength(0)
  })

  describe('deleteVersion', () => {
    it('calls DELETE on the version endpoint', async () => {
      mockDel.mockResolvedValueOnce(undefined)
      const { deleteVersion } = useWorkflows()

      await deleteVersion({ id: 42 })

      expect(mockDel).toHaveBeenCalledWith('/api/v1/workflow-versions/42')
    })
  })

  describe('deleteDefinition', () => {
    it('calls DELETE on the definition endpoint', async () => {
      mockDel.mockResolvedValueOnce(undefined)
      const { deleteDefinition } = useWorkflows()

      await deleteDefinition({ id: 7 })

      expect(mockDel).toHaveBeenCalledWith('/api/v1/workflow-definitions/7')
    })
  })
})
