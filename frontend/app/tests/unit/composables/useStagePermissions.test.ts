import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { StagePermission } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useStagePermissions } = await import('../../../composables/useStagePermissions')

function makePermission(overrides: Partial<StagePermission> = {}): StagePermission {
  return {
    id: 1,
    stage_id: 5,
    organization_id: 1,
    team_id: null,
    role_id: 2,
    user_id: null,
    access_level: 'EXECUTE',
    display_label: 'Reviewers',
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

describe('useStagePermissions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches permissions for a stage', async () => {
    mockGet.mockResolvedValueOnce({ data: [makePermission()] })
    const { permissions, fetchPermissions } = useStagePermissions()
    await fetchPermissions(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-stages/5/permissions')
    expect(permissions.value).toHaveLength(1)
  })

  it('appends a created permission', async () => {
    mockGet.mockResolvedValueOnce({ data: [] })
    mockPost.mockResolvedValueOnce({ data: makePermission({ id: 2 }) })

    const { permissions, fetchPermissions, createPermission } = useStagePermissions()
    await fetchPermissions(5)
    await createPermission(5, { role_id: 2, access_level: 'EXECUTE', display_label: 'Reviewers' })

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/workflow-stages/5/permissions',
      expect.objectContaining({ role_id: 2, access_level: 'EXECUTE' }),
    )
    expect(permissions.value).toHaveLength(1)
  })

  it('removes a deleted permission', async () => {
    const permission = makePermission()
    mockGet.mockResolvedValueOnce({ data: [permission] })
    mockDelete.mockResolvedValueOnce(undefined)

    const { permissions, fetchPermissions, deletePermission } = useStagePermissions()
    await fetchPermissions(5)
    await deletePermission(permission)

    expect(mockDelete).toHaveBeenCalledWith('/api/v1/workflow-stages/5/permissions/1')
    expect(permissions.value).toHaveLength(0)
  })

  it('records an error on fetch failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { error, permissions, fetchPermissions } = useStagePermissions()
    await fetchPermissions(5)

    expect(error.value).toBeTruthy()
    expect(permissions.value).toHaveLength(0)
  })
})
