// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { UserRole } from '../../../types/enums'
import profilePage from '../../../pages/profile.vue'

vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('useHead', vi.fn())

const fetchProfileMock = vi.hoisted(() => vi.fn())
const updateProfileMock = vi.hoisted(() => vi.fn())
const toggleMfaMock = vi.hoisted(() => vi.fn())
const changePasswordMock = vi.hoisted(() => vi.fn())

// Use real Vue refs so template reactivity (v-if="profile", v-if="profile.stats") works correctly
let profileRef = ref<any>(null)
let loadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('../../../composables/useProfile', () => ({
  useProfile: () => ({
    get profile() { return profileRef },
    get loading() { return loadingRef },
    get error() { return errorRef },
    fetchProfile: fetchProfileMock,
    updateProfile: updateProfileMock,
    toggleMfa: toggleMfaMock,
    changePassword: changePasswordMock,
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 1, role: UserRole.CBY_ADMIN },
  }),
}))

const SAMPLE_PROFILE = {
  id: 1,
  name: 'أحمد محمد',
  email: 'ahmed@cby.gov.ye',
  phone: '+967111222333',
  role: UserRole.CBY_ADMIN,
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  is_active: true,
  mfa_enabled: true,
  mfa_required: false,
  stats: { total: 15, in_progress: 4, completed: 11 },
  recent_activity: [
    { id: 1, action: 'تسجيل دخول', ref: null, ts: '2026-05-21T10:00:00Z' },
    { id: 2, action: 'تحديث الملف', ref: null, ts: '2026-05-20T09:00:00Z' },
  ],
}

describe('profile.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    profileRef = ref(null)
    loadingRef = ref(false)
    errorRef = ref(null)
    fetchProfileMock.mockResolvedValue(undefined)
  })

  it('renders stats strip when profile is loaded', async () => {
    profileRef.value = SAMPLE_PROFILE
    const wrapper = mount(profilePage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    const strip = wrapper.find('[data-testid="stats-strip"]')
    expect(strip.exists()).toBe(true)
    expect(wrapper.find('[data-testid="stats-total"]').text()).toContain('15')
    expect(wrapper.find('[data-testid="stats-in-progress"]').text()).toContain('4')
    expect(wrapper.find('[data-testid="stats-completed"]').text()).toContain('11')
  })

  it('renders recent activity list items', async () => {
    profileRef.value = SAMPLE_PROFILE
    const wrapper = mount(profilePage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    const list = wrapper.find('[data-testid="recent-activity-list"]')
    expect(list.exists()).toBe(true)
    const items = list.findAll('li')
    expect(items.length).toBe(2)
  })

  it('shows empty state when recent_activity is empty', async () => {
    profileRef.value = { ...SAMPLE_PROFILE, recent_activity: [] }
    const wrapper = mount(profilePage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    expect(wrapper.find('[data-testid="activity-empty"]').exists()).toBe(true)
  })

  it('calls updateProfile on save button click', async () => {
    updateProfileMock.mockResolvedValue(true)
    profileRef.value = SAMPLE_PROFILE
    const wrapper = mount(profilePage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    // type="submit" button — trigger form submit rather than button click
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(updateProfileMock).toHaveBeenCalled()
  })

  it('calls toggleMfa when MFA button is clicked', async () => {
    toggleMfaMock.mockResolvedValue(true)
    profileRef.value = SAMPLE_PROFILE
    const wrapper = mount(profilePage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('[data-testid="mfa-toggle-btn"]').trigger('click')
    await flushPromises()

    expect(toggleMfaMock).toHaveBeenCalled()
  })
})
