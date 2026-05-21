import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

// useAuthStore is imported by useProfile — stub it
vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({ user: null }),
}))

const { useProfile } = await import('../../../composables/useProfile')

const SAMPLE_PROFILE = {
  id: 1,
  name: 'أحمد محمد',
  email: 'ahmed@cby.gov.ye',
  phone: '+967111222333',
  role: 'CBY_ADMIN',
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  is_active: true,
  mfa_enabled: true,
  mfa_required: false,
  stats: { total: 10, in_progress: 3, completed: 7 },
  recent_activity: [],
}

describe('useProfile — fetchProfile()', () => {
  beforeEach(() => vi.resetAllMocks())

  it('sets profile on success', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: SAMPLE_PROFILE })
    const { profile, fetchProfile } = useProfile()
    await fetchProfile()
    expect(profile.value).toEqual(SAMPLE_PROFILE)
  })

  it('sets error on failure', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'Unauthorized' } })
    const { profile, error, fetchProfile } = useProfile()
    await fetchProfile()
    expect(profile.value).toBeNull()
    expect(error.value).toBe('Unauthorized')
  })
})

describe('useProfile — updateProfile()', () => {
  beforeEach(() => vi.resetAllMocks())

  it('sends PUT /api/profile with name, email, phone', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: { ...SAMPLE_PROFILE, name: 'علي سالم' } })
    const { updateProfile } = useProfile()
    const result = await updateProfile({ name: 'علي سالم', email: 'ali@cby.gov.ye', phone: '+967111000111' })
    expect(result).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith('/api/profile', expect.objectContaining({
      method: 'PUT',
      body: { name: 'علي سالم', email: 'ali@cby.gov.ye', phone: '+967111000111' },
    }))
  })

  it('returns false and sets error on API failure', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'Validation failed' } })
    const { error, updateProfile } = useProfile()
    const result = await updateProfile({ name: 'Test', email: 'test@test.com' })
    expect(result).toBe(false)
    expect(error.value).toBe('Validation failed')
  })
})

describe('useProfile — toggleMfa()', () => {
  beforeEach(() => vi.resetAllMocks())

  it('sends POST /api/profile/mfa/toggle and updates profile', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: { ...SAMPLE_PROFILE, mfa_enabled: false } })
    const { profile, toggleMfa } = useProfile()
    const result = await toggleMfa()
    expect(result).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith('/api/profile/mfa/toggle', expect.objectContaining({ method: 'POST' }))
    expect(profile.value?.mfa_enabled).toBe(false)
  })

  it('returns false when server returns error', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'MFA is required by policy' } })
    const { error, toggleMfa } = useProfile()
    const result = await toggleMfa()
    expect(result).toBe(false)
    expect(error.value).toBe('MFA is required by policy')
  })
})
