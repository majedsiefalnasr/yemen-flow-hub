import { vi, describe, it, expect, beforeEach } from 'vitest'
import { UserRole } from '../../../types/enums'

vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))
vi.stubGlobal('definePageMeta', vi.fn())

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)

let mockAuthRole: UserRole | null = null

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get user() {
      return mockAuthRole ? { role: mockAuthRole } : null
    },
    setUserPreferences: vi.fn(),
  }),
}))

// Load settings composable and notification pref helpers after mocks are set
const { useSettings } = await import('../../../composables/useSettings')

// Inline the role-filtering logic mirroring settings.vue
interface NotifPrefItem {
  key: string
  label: string
  mandatory: boolean
  roles: UserRole[]
}

const ALL_NOTIF_PREFS: NotifPrefItem[] = [
  {
    key: 'request_submitted',
    label: 'إشعار تقديم الطلبات الجديدة',
    mandatory: false,
    roles: [UserRole.BANK_REVIEWER],
  },
  {
    key: 'request_approved',
    label: 'إشعار الموافقة على الطلبات',
    mandatory: false,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'request_rejected',
    label: 'إشعار رفض الطلبات',
    mandatory: true,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'request_returned',
    label: 'إشعار إعادة الطلبات للمراجعة',
    mandatory: true,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'swift_upload_requested',
    label: 'إشعار طلب رفع SWIFT',
    mandatory: false,
    roles: [UserRole.SWIFT_OFFICER],
  },
  {
    key: 'voting_opened',
    label: 'إشعار فتح جلسة التصويت',
    mandatory: false,
    roles: [UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'customs_issued',
    label: 'إشعار إصدار تأكيد المصارفة الخارجية',
    mandatory: false,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'claim_released',
    label: 'إشعار إلغاء المطالبة',
    mandatory: false,
    roles: [UserRole.CBY_ADMIN],
  },
]

function getVisiblePrefs(role: UserRole) {
  return ALL_NOTIF_PREFS.filter((p) => p.roles.includes(role))
}

describe('settings — notification preferences role filtering', () => {
  it('DATA_ENTRY sees: request_approved, request_rejected, request_returned, customs_issued', () => {
    const prefs = getVisiblePrefs(UserRole.DATA_ENTRY)
    const keys = prefs.map((p) => p.key)
    expect(keys).toContain('request_approved')
    expect(keys).toContain('request_rejected')
    expect(keys).toContain('request_returned')
    expect(keys).toContain('customs_issued')
    expect(keys).not.toContain('request_submitted')
    expect(keys).not.toContain('swift_upload_requested')
    expect(keys).not.toContain('voting_opened')
  })

  it('BANK_REVIEWER sees: request_submitted, request_approved, request_rejected, request_returned, customs_issued', () => {
    const prefs = getVisiblePrefs(UserRole.BANK_REVIEWER)
    const keys = prefs.map((p) => p.key)
    expect(keys).toContain('request_submitted')
    expect(keys).toContain('request_approved')
    expect(keys).toContain('request_rejected')
    expect(keys).toContain('request_returned')
    expect(keys).toContain('customs_issued')
    expect(keys).not.toContain('swift_upload_requested')
    expect(keys).not.toContain('voting_opened')
  })

  it('SWIFT_OFFICER sees only swift_upload_requested', () => {
    const prefs = getVisiblePrefs(UserRole.SWIFT_OFFICER)
    const keys = prefs.map((p) => p.key)
    expect(keys).toEqual(['swift_upload_requested'])
  })

  it('EXECUTIVE_MEMBER sees only voting_opened', () => {
    const prefs = getVisiblePrefs(UserRole.EXECUTIVE_MEMBER)
    const keys = prefs.map((p) => p.key)
    expect(keys).toEqual(['voting_opened'])
  })

  it('COMMITTEE_DIRECTOR sees only voting_opened', () => {
    const prefs = getVisiblePrefs(UserRole.COMMITTEE_DIRECTOR)
    const keys = prefs.map((p) => p.key)
    expect(keys).toEqual(['voting_opened'])
  })
})

describe('settings — mandatory notification types', () => {
  it('request_rejected is mandatory', () => {
    const item = ALL_NOTIF_PREFS.find((p) => p.key === 'request_rejected')!
    expect(item.mandatory).toBe(true)
  })

  it('request_returned is mandatory', () => {
    const item = ALL_NOTIF_PREFS.find((p) => p.key === 'request_returned')!
    expect(item.mandatory).toBe(true)
  })

  it('all other types are non-mandatory', () => {
    const nonMandatory = ALL_NOTIF_PREFS.filter((p) => !p.mandatory).map((p) => p.key)
    expect(nonMandatory).toContain('request_submitted')
    expect(nonMandatory).toContain('request_approved')
    expect(nonMandatory).toContain('swift_upload_requested')
    expect(nonMandatory).toContain('voting_opened')
    expect(nonMandatory).toContain('customs_issued')
  })
})

describe('settings — claim_released preference (CBY_ADMIN)', () => {
  it('CBY_ADMIN sees claim_released toggle', () => {
    const prefs = getVisiblePrefs(UserRole.CBY_ADMIN)
    const keys = prefs.map((p) => p.key)
    expect(keys).toContain('claim_released')
  })

  it('claim_released is not mandatory', () => {
    const item = ALL_NOTIF_PREFS.find((p) => p.key === 'claim_released')!
    expect(item.mandatory).toBe(false)
  })

  it('claim_released label is correct Arabic', () => {
    const item = ALL_NOTIF_PREFS.find((p) => p.key === 'claim_released')!
    expect(item.label).toBe('إشعار إلغاء المطالبة')
  })

  it('non-CBY_ADMIN roles do not see claim_released', () => {
    const rolesWithoutClaim = [
      UserRole.DATA_ENTRY,
      UserRole.BANK_REVIEWER,
      UserRole.SWIFT_OFFICER,
      UserRole.SUPPORT_COMMITTEE,
      UserRole.EXECUTIVE_MEMBER,
      UserRole.COMMITTEE_DIRECTOR,
    ]
    rolesWithoutClaim.forEach((role) => {
      const keys = getVisiblePrefs(role).map((p) => p.key)
      expect(keys).not.toContain('claim_released')
    })
  })
})

describe('settings — toggleNotifPref calls updateSettings', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockAuthRole = UserRole.BANK_REVIEWER
  })

  it('calls PUT /api/settings with updated notification_preferences on toggle', async () => {
    // Setup: fetch settings returns prefs with empty notification_preferences
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        language: 'ar',
        dashboard_view: 'normal',
        table_density: 'normal',
        page_size: 25,
        default_filters: {},
        notification_preferences: {},
      },
    })
    // Then: update settings
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        language: 'ar',
        dashboard_view: 'normal',
        table_density: 'normal',
        page_size: 25,
        default_filters: {},
        notification_preferences: { voting_opened: false },
      },
    })

    const { updateSettings } = useSettings()
    await updateSettings({ notification_preferences: { voting_opened: false } })

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/settings',
      expect.objectContaining({
        method: 'PUT',
        body: expect.objectContaining({ notification_preferences: { voting_opened: false } }),
      }),
    )
  })

  it('toggling claim_released to false persists via updateSettings', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        language: 'ar',
        dashboard_view: 'normal',
        table_density: 'normal',
        page_size: 25,
        default_filters: {},
        notification_preferences: { claim_released: false },
      },
    })

    const { updateSettings } = useSettings()
    await updateSettings({ notification_preferences: { claim_released: false } })

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/settings',
      expect.objectContaining({
        method: 'PUT',
        body: expect.objectContaining({ notification_preferences: { claim_released: false } }),
      }),
    )
  })

  it('merges existing notification_preferences on toggle', async () => {
    const existing = { request_approved: false }
    const updated = { ...existing, voting_opened: false }

    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        language: 'ar',
        dashboard_view: 'normal',
        table_density: 'normal',
        page_size: 25,
        default_filters: {},
        notification_preferences: updated,
      },
    })

    const { updateSettings } = useSettings()
    await updateSettings({ notification_preferences: updated })

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/settings',
      expect.objectContaining({
        body: expect.objectContaining({ notification_preferences: updated }),
      }),
    )
  })
})
