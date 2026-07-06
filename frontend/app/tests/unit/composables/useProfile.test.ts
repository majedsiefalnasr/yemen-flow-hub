import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockFetch = vi.fn()
const mockAuth = vi.hoisted(() => ({
  user: null as any,
}))
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

// useAuthStore is imported by useProfile — stub it
vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => mockAuth,
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
  beforeEach(() => {
    vi.resetAllMocks()
    mockAuth.user = null
  })

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
  beforeEach(() => {
    vi.resetAllMocks()
    mockAuth.user = null
  })

  it('sends PUT /api/profile with name and phone only', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { ...SAMPLE_PROFILE, name: 'علي سالم' },
    })
    const { updateProfile } = useProfile()
    const result = await updateProfile({
      name: 'علي سالم',
      phone: '+967111000111',
    })
    expect(result).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/profile',
      expect.objectContaining({
        method: 'PUT',
        body: { name: 'علي سالم', phone: '+967111000111' },
      }),
    )
  })

  it('normalizes phone before saving', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: { ...SAMPLE_PROFILE, phone: null } })
    const { updateProfile } = useProfile()
    const result = await updateProfile({
      name: ' علي سالم ',
      phone: ' +967 77 000 111 ',
    })
    expect(result).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/profile',
      expect.objectContaining({
        method: 'PUT',
        body: { name: 'علي سالم', phone: '+96777000111' },
      }),
    )
  })

  it('normalizes blank phone to null before saving', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: { ...SAMPLE_PROFILE, phone: null } })
    const { updateProfile } = useProfile()
    const result = await updateProfile({ name: 'علي سالم', phone: '   ' })
    expect(result).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/profile',
      expect.objectContaining({
        body: expect.objectContaining({ phone: null }),
      }),
    )
  })

  it('refreshes the shared auth user after saving', async () => {
    mockAuth.user = { ...SAMPLE_PROFILE }
    const updated = { ...SAMPLE_PROFILE, name: 'علي سالم', avatar_variant: 'ring' }
    mockFetch.mockResolvedValueOnce({ success: true, data: updated })
    const { updateProfile } = useProfile()
    const result = await updateProfile({
      name: 'علي سالم',
      avatar_variant: 'ring',
    })
    expect(result).toBe(true)
    expect(mockAuth.user).toEqual(updated)
  })

  it('returns false and sets error on API failure', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'Validation failed' } })
    const { error, updateProfile } = useProfile()
    const result = await updateProfile({ name: 'Test' })
    expect(result).toBe(false)
    expect(error.value).toBe('Validation failed')
  })
})

describe('useProfile — toggleMfa()', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockAuth.user = null
  })

  it('sends POST /api/profile/mfa/toggle and updates profile', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { ...SAMPLE_PROFILE, mfa_enabled: false },
    })
    const { profile, toggleMfa } = useProfile()
    const result = await toggleMfa()
    expect(result).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/profile/mfa/toggle',
      expect.objectContaining({ method: 'POST' }),
    )
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

describe('useProfile — TOTP setup()', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockAuth.user = null
  })

  it('returns one-time backup codes after verifying TOTP setup', async () => {
    const data = {
      ...SAMPLE_PROFILE,
      totp_enabled: true,
      recovery_codes: ['ABCD-EFGH', 'JKLM-NPQR'],
    }
    mockFetch.mockResolvedValueOnce({ success: true, data })

    const { verifyTotpSetup } = useProfile()
    const result = await verifyTotpSetup('123456')

    expect(result?.recovery_codes).toEqual(['ABCD-EFGH', 'JKLM-NPQR'])
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/profile/mfa/setup/verify',
      expect.objectContaining({
        method: 'POST',
        body: { code: '123456' },
      }),
    )
  })
})
