import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, put: mockPut, post: vi.fn(), del: vi.fn() }),
}))

const { useScreenPermissionsAdmin } = await import('../../../composables/useScreenPermissionsAdmin')

const SCREEN_FIXTURE = [
  { id: 1, key: 'requests', label: 'الطلبات' },
  { id: 2, key: 'reports', label: 'التقارير' },
]

describe('useScreenPermissionsAdmin', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPut.mockReset()
  })

  describe('fetchScreens()', () => {
    it('loads screens from GET /api/v1/screens', async () => {
      mockGet.mockResolvedValueOnce({ data: SCREEN_FIXTURE })
      const { screens, fetchScreens } = useScreenPermissionsAdmin()
      await fetchScreens()
      expect(mockGet).toHaveBeenCalledWith('/api/v1/screens')
      expect(screens.value).toEqual(SCREEN_FIXTURE)
    })

    it('sets error on failure', async () => {
      mockGet.mockRejectedValueOnce({ data: { message: 'Server error' } })
      const { error, fetchScreens } = useScreenPermissionsAdmin()
      await fetchScreens()
      expect(error.value).toBe('Server error')
    })
  })

  describe('fetchRoleGrants()', () => {
    it('returns grants for a role', async () => {
      const payload = { role_id: 1, role_code: 'intake', grants: { requests: ['VIEW', 'CREATE'] } }
      mockGet.mockResolvedValueOnce({ data: payload })
      const { fetchRoleGrants } = useScreenPermissionsAdmin()
      const result = await fetchRoleGrants(1)
      expect(mockGet).toHaveBeenCalledWith('/api/v1/roles/1/screen-permissions')
      expect(result).toEqual(payload)
    })

    it('returns null on error', async () => {
      mockGet.mockRejectedValueOnce(new Error('fail'))
      const { fetchRoleGrants } = useScreenPermissionsAdmin()
      const result = await fetchRoleGrants(999)
      expect(result).toBeNull()
    })
  })

  describe('saveRoleGrants()', () => {
    it('sends PUT with grants', async () => {
      mockPut.mockResolvedValueOnce({ data: { role_id: 1, grants: {} } })
      const { saveRoleGrants } = useScreenPermissionsAdmin()
      const ok = await saveRoleGrants(1, { requests: ['VIEW'] })
      expect(mockPut).toHaveBeenCalledWith('/api/v1/roles/1/screen-permissions', {
        grants: { requests: ['VIEW'] },
      })
      expect(ok).toBe(true)
    })

    it('sets error on validation failure', async () => {
      mockPut.mockRejectedValueOnce({
        data: { errors: { grants: ['Cannot remove last admin'] } },
      })
      const { error, saveRoleGrants } = useScreenPermissionsAdmin()
      const ok = await saveRoleGrants(1, {})
      expect(ok).toBe(false)
      expect(error.value).toBe('Cannot remove last admin')
    })
  })
})
