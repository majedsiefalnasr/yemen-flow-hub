import { vi, describe, it, expect, beforeEach } from 'vitest'
import { UserRole } from '../../../types/enums'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut }),
}))

const { useUsers } = await import('../../../composables/useUsers')

const USER_FIXTURE = {
  id: 1,
  name: 'أحمد اليماني',
  email: 'ahmed@bank.ye',
  role: UserRole.DATA_ENTRY,
  role_label: 'إدخال البيانات',
  bank_id: 1,
  bank_name_ar: 'بنك عدن',
  bank_name_en: 'Aden Bank',
  is_active: true,
}

describe('useUsers', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockPut.mockReset()
  })

  describe('fetchUsers()', () => {
    it('calls GET /api/users and returns the inner paginated data array', async () => {
      mockGet.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          data: [USER_FIXTURE],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 1,
          },
        },
      })
      const { fetchUsers } = useUsers()
      const result = await fetchUsers()
      expect(mockGet).toHaveBeenCalledWith('/api/users')
      expect(result).toEqual([USER_FIXTURE])
    })

    it('supports legacy non-paginated array responses', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: [USER_FIXTURE] })
      const { fetchUsers } = useUsers()
      await expect(fetchUsers()).resolves.toEqual([USER_FIXTURE])
    })

    it('propagates errors from the API', async () => {
      mockGet.mockRejectedValueOnce(new Error('Network error'))
      const { fetchUsers } = useUsers()
      await expect(fetchUsers()).rejects.toThrow('Network error')
    })

    it('appends supported query params when provided', async () => {
      mockGet.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          data: [USER_FIXTURE],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 100,
            total: 1,
          },
        },
      })
      const { fetchUsers } = useUsers()

      await fetchUsers({
        role: UserRole.BANK_REVIEWER,
        bank_id: 7,
        is_active: true,
        per_page: 100,
      })

      expect(mockGet).toHaveBeenCalledWith('/api/users?role=BANK_REVIEWER&bank_id=7&is_active=true&per_page=100')
    })
  })

  describe('createUser()', () => {
    it('calls POST /api/users with the payload and returns the created user', async () => {
      mockPost.mockResolvedValueOnce({ success: true, message: 'Created', data: USER_FIXTURE })
      const { createUser } = useUsers()
      const payload = {
        name: 'أحمد اليماني',
        email: 'ahmed@bank.ye',
        password: 'password123',
        role: UserRole.DATA_ENTRY,
        bank_id: 1,
        is_active: true,
      }
      const result = await createUser(payload)
      expect(mockPost).toHaveBeenCalledWith('/api/users', payload)
      expect(result).toEqual(USER_FIXTURE)
    })
  })

  describe('updateUser()', () => {
    it('calls PUT /api/users/:id with the payload and returns the updated user', async () => {
      const updated = { ...USER_FIXTURE, name: 'اسم محدث', is_active: false }
      mockPut.mockResolvedValueOnce({ success: true, message: 'Updated', data: updated })
      const { updateUser } = useUsers()
      const payload = {
        name: 'اسم محدث',
        email: 'ahmed@bank.ye',
        role: UserRole.DATA_ENTRY,
        bank_id: 1,
        is_active: false,
      }
      const result = await updateUser(1, payload)
      expect(mockPut).toHaveBeenCalledWith('/api/users/1', payload)
      expect(result).toEqual(updated)
    })

    it('includes password in payload when provided', async () => {
      mockPut.mockResolvedValueOnce({ success: true, message: 'Updated', data: USER_FIXTURE })
      const { updateUser } = useUsers()
      const payload = {
        name: 'أحمد',
        email: 'ahmed@bank.ye',
        password: 'newpass123',
        role: UserRole.DATA_ENTRY,
        bank_id: 1,
        is_active: true,
      }
      await updateUser(1, payload)
      expect(mockPut).toHaveBeenCalledWith('/api/users/1', expect.objectContaining({ password: 'newpass123' }))
    })
  })
})
