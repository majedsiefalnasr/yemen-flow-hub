// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { UserRole } from '../../../types/enums'
import settingsPage from '../../../pages/settings.vue'

vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('useHead', vi.fn())
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost', demoMode: false } }))

const fetchSettingsMock = vi.hoisted(() => vi.fn())
const updateSettingsMock = vi.hoisted(() => vi.fn())
const resetSettingsMock = vi.hoisted(() => vi.fn())

// Use real Vue refs so template reactivity (v-if="preferences") works correctly
let prefsRef = ref<any>(null)
let settingsLoadingRef = ref(false)
let settingsErrorRef = ref<string | null>(null)

vi.mock('../../../composables/useSettings', () => ({
  useSettings: () => ({
    get preferences() { return prefsRef },
    get loading() { return settingsLoadingRef },
    get error() { return settingsErrorRef },
    fetchSettings: fetchSettingsMock,
    updateSettings: updateSettingsMock,
    resetSettings: resetSettingsMock,
  }),
}))

const fetchAdminSettingsMock = vi.hoisted(() => vi.fn())
const fetchSmtpMock = vi.hoisted(() => vi.fn())
const updateSmtpMock = vi.hoisted(() => vi.fn())
const fetchSecurityMock = vi.hoisted(() => vi.fn())
const updateSecurityPolicyMock = vi.hoisted(() => vi.fn())
const updateAdminSettingMock = vi.hoisted(() => vi.fn())

// Use real Vue refs so template reactivity works correctly
let adminSettingsRef = ref<any>(null)
let smtpRef = ref<any>(null)
let securityRef = ref<any>(null)
let pendingKeysRef = ref<Set<string>>(new Set())

vi.mock('../../../composables/useAdminSettings', () => ({
  useAdminSettings: () => ({
    get settings() { return adminSettingsRef },
    get smtpSettings() { return smtpRef },
    get securityPolicies() { return securityRef },
    get pendingKeys() { return pendingKeysRef },
    fetchSettings: fetchAdminSettingsMock,
    fetchSmtpSettings: fetchSmtpMock,
    updateSmtpSettings: updateSmtpMock,
    fetchSecurityPolicies: fetchSecurityMock,
    updateSecurityPolicy: updateSecurityPolicyMock,
    updateSetting: updateAdminSettingMock,
  }),
}))

let mockRole: UserRole = UserRole.CBY_ADMIN

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get user() { return { id: 1, role: mockRole } },
  }),
}))

const SAMPLE_PREFS = {
  language: 'ar',
  dashboard_view: 'normal',
  table_density: 'normal',
  page_size: 25,
  notification_preferences: {},
}

const SAMPLE_ADMIN_SETTINGS = {
  support_claim_ttl: 15,
  voting_session_timeout: 8,
  pdf_upload_size_limit: 10,
  login_lockout_duration: 15,
  notifications_phase_1_enabled: true,
  search_phase_1_enabled: false,
  customs_print_preview_enabled: false,
  support_committee_size: 3,
  executive_committee_size: 5,
  minimum_quorum: 3,
  review_timeout_hours: 48,
  secret_voting: false,
  director_tiebreak: true,
}

describe('settings.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Reset real Vue refs by reassigning .value
    prefsRef.value = null
    adminSettingsRef.value = null
    smtpRef.value = null
    securityRef.value = null
    pendingKeysRef.value = new Set()
    settingsLoadingRef.value = false
    settingsErrorRef.value = null
    mockRole = UserRole.CBY_ADMIN
    fetchSettingsMock.mockResolvedValue(undefined)
    fetchAdminSettingsMock.mockResolvedValue(undefined)
    fetchSmtpMock.mockResolvedValue(undefined)
    fetchSecurityMock.mockResolvedValue(undefined)
  })

  it('renders all tab buttons including workflow, email, notifications, security, general', async () => {
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    expect(wrapper.find('[data-testid="tab-workflow"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tab-email"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tab-notif"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tab-security"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tab-general"]').exists()).toBe(true)
  })

  it('shows workflow readonly note for non-CBY_ADMIN users', async () => {
    mockRole = UserRole.BANK_REVIEWER
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    // Switch to workflow tab
    await wrapper.find('[data-testid="tab-workflow"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="workflow-readonly-note"]').exists()).toBe(true)
  })

  it('shows CBY_ADMIN workflow editable inputs (no readonly note) when admin', async () => {
    mockRole = UserRole.CBY_ADMIN
    adminSettingsRef.value = SAMPLE_ADMIN_SETTINGS
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('[data-testid="tab-workflow"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="workflow-readonly-note"]').exists()).toBe(false)
  })

  it('renders security MFA switch row with correct data-testid', async () => {
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('[data-testid="tab-security"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="security-switch-mfa"]').exists()).toBe(true)
  })

  it('security switches are disabled for non-CBY_ADMIN', async () => {
    mockRole = UserRole.BANK_REVIEWER
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('[data-testid="tab-security"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="security-switches-disabled"]').exists()).toBe(true)
  })

  it('calls updateSettings on general form save', async () => {
    updateSettingsMock.mockResolvedValue(true)
    prefsRef.value = SAMPLE_PREFS
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('[data-testid="tab-general"]').trigger('click')
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(updateSettingsMock).toHaveBeenCalled()
  })

  it('calls updateSmtpSettings when CBY_ADMIN saves SMTP form', async () => {
    updateSmtpMock.mockResolvedValue(true)
    const wrapper = mount(settingsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('[data-testid="tab-email"]').trigger('click')
    await flushPromises()

    await wrapper.find('.btn-primary').trigger('click')
    await flushPromises()

    expect(updateSmtpMock).toHaveBeenCalled()
  })
})
