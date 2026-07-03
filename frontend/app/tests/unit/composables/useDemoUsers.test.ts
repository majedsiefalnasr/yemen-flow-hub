import { describe, it, expect, vi, beforeEach } from 'vitest'
import { UserRole } from '../../../types/enums'
import type { DemoUser } from '../../../types/models'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost:8000' },
}))

const { useDemoUsers } = await import('../../../composables/useDemoUsers')

const SAMPLE_USERS: DemoUser[] = [
  {
    id: 1,
    name: 'Fatima Al-Maqtari',
    email: 'admin@ybrd.com.ye',
    role: UserRole.BANK_ADMIN,
    role_label: 'مسؤول البنك / Bank Admin',
    organization: { id: 1, code: 'commercial_banks', name: 'Commercial Banks' },
    team: { id: 1, organization_id: 1, code: 'bank_admin', name: 'Bank Admin' },
    bank: { id: 1, code: 'ybrd', name: 'YBRD' },
  },
]

describe('useDemoUsers', () => {
  beforeEach(() => {
    mockFetch.mockReset()
  })

  it('starts with an empty list and not loading', () => {
    const { users, loading, error } = useDemoUsers()
    expect(users.value).toEqual([])
    expect(loading.value).toBe(false)
    expect(error.value).toBeNull()
  })

  it('fetches and stores the demo user list', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { users: SAMPLE_USERS },
    })

    const { users, loading, fetchDemoUsers } = useDemoUsers()
    const promise = fetchDemoUsers()
    expect(loading.value).toBe(true)
    await promise

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/auth/demo-users',
      expect.objectContaining({ baseURL: 'http://localhost:8000' }),
    )
    expect(users.value).toEqual(SAMPLE_USERS)
    expect(loading.value).toBe(false)
  })

  it('sets an error message when the fetch fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network error'))

    const { error, fetchDemoUsers } = useDemoUsers()
    await fetchDemoUsers()

    expect(error.value).not.toBeNull()
  })
})
