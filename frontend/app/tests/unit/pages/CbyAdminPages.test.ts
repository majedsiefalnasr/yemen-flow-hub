// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { UserRole } from '../../../types/enums'

vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('navigateTo', vi.fn())
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost', demoMode: false } }))

// ─── Shared mocks ───────────────────────────────────────────────────────────

const fetchUsersMock = vi.hoisted(() => vi.fn())
const createUserMock = vi.hoisted(() => vi.fn())
const updateUserMock = vi.hoisted(() => vi.fn())

vi.mock('../../../composables/useUsers', () => ({
  useUsers: () => ({
    fetchUsers: fetchUsersMock,
    createUser: createUserMock,
    updateUser: updateUserMock,
    getUser: vi.fn(),
  }),
}))

const fetchBanksMock = vi.hoisted(() => vi.fn())
const createBankMock = vi.hoisted(() => vi.fn())
const updateBankMock = vi.hoisted(() => vi.fn())

vi.mock('../../../composables/useBanks', () => ({
  useBanks: () => ({
    fetchBanks: fetchBanksMock,
    createBank: createBankMock,
    updateBank: updateBankMock,
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 1, bank_id: null, role: UserRole.CBY_ADMIN, name: 'مدير النظام', email: 'admin@cby.gov.ye' },
    isCbyAdmin: true,
    setUserPreferences: vi.fn(),
  }),
}))

vi.mock('../../../composables/useSettings', () => ({
  useSettings: () => ({
    preferences: ref({ language: 'ar', dashboard_view: 'normal', table_density: 'normal', page_size: 25, notification_preferences: {} }),
    loading: ref(false),
    error: ref(null),
    fetchSettings: vi.fn(),
    updateSettings: vi.fn().mockResolvedValue(true),
    resetSettings: vi.fn().mockResolvedValue(true),
  }),
}))

vi.mock('../../../composables/useProfile', () => ({
  useProfile: () => ({
    profile: ref({ name: 'مدير النظام', email: 'admin@cby.gov.ye', role: UserRole.CBY_ADMIN, bank_name_ar: null }),
    loading: ref(false),
    error: ref(null),
    fetchProfile: vi.fn(),
    changePassword: vi.fn().mockResolvedValue(true),
  }),
}))

// ─── Fixtures ────────────────────────────────────────────────────────────────

function makeUser(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    name: 'أحمد السالمي',
    email: 'ahmed@cby.gov.ye',
    role: UserRole.SUPPORT_COMMITTEE,
    bank_id: null,
    bank_name: null,
    bank_name_ar: null,
    bank_name_en: null,
    is_active: true,
    last_login_at: '2026-05-10T11:45:00Z',
    created_at: '2026-05-01T10:00:00Z',
    ...overrides,
  }
}

function makeBank(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    name_ar: 'البنك التجاري اليمني',
    name_en: 'Yemen Commercial Bank',
    code: 'YCB',
    license_number: 'LIC-001',
    entity_type: 'تجاري',
    user_count: 0,
    is_active: true,
    ...overrides,
  }
}

// Helper: trigger a select change with a value
async function setSelectValue(wrapper: ReturnType<typeof mount>, selector: string, value: string) {
  const sel = wrapper.find(selector)
  const el = sel.element as HTMLSelectElement
  el.value = value
  await sel.trigger('change')
  await sel.trigger('input')
  await flushPromises()
}

// ═══════════════════════════════════════════════════════════════════════════════
// /admin/cby-staff
// ═══════════════════════════════════════════════════════════════════════════════

describe('/admin/cby-staff', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchUsersMock.mockResolvedValue([])
    fetchBanksMock.mockResolvedValue([])
    createUserMock.mockResolvedValue(makeUser({ id: 2 }))
    updateUserMock.mockResolvedValue(makeUser())
  })

  async function mountPage() {
    const page = (await import('../../../pages/admin/cby-staff.vue')).default
    const wrapper = mount(page, { global: { stubs: { Teleport: true } } })
    await flushPromises()
    return wrapper
  }

  it('renders empty state when no users', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-empty-state-variant="cby-staff"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('لا يوجد مستخدمون')
  })

  it('renders user table with all required columns', async () => {
    fetchUsersMock.mockResolvedValue([makeUser()])
    const wrapper = await mountPage()
    const headers = wrapper.findAll('thead th').map(th => th.text())
    expect(headers).toContain('الاسم')
    expect(headers).toContain('البريد الإلكتروني')
    expect(headers).toContain('الدور')
    expect(headers).toContain('الجهة')
    expect(headers).toContain('الحالة')
    expect(headers).toContain('آخر ظهور')
    expect(headers).toContain('إجراءات')
  })

  it('renders user row with name and status badge', async () => {
    fetchUsersMock.mockResolvedValue([makeUser()])
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('أحمد السالمي')
    expect(wrapper.text()).toContain('نشط')
  })

  it('renders last-seen value in table row', async () => {
    fetchUsersMock.mockResolvedValue([makeUser({ last_login_at: '2026-05-12T08:30:00Z' })])
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('آخر ظهور')
    expect(wrapper.text()).not.toContain('—')
  })

  it('renders role, bank and status filter dropdowns', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="filter-role"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="filter-bank"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="filter-status"]').exists()).toBe(true)
  })

  it('status filter hides inactive users when set to active-only', async () => {
    fetchUsersMock.mockResolvedValue([
      makeUser({ id: 1, name: 'مستخدم نشط', is_active: true }),
      makeUser({ id: 2, name: 'مستخدم موقوف', is_active: false }),
    ])
    const wrapper = await mountPage()

    // Before filter: both visible
    expect(wrapper.text()).toContain('مستخدم نشط')
    expect(wrapper.text()).toContain('مستخدم موقوف')

    // Manually set filterStatus to 'active' on the component instance
    const vm = wrapper.vm as unknown as { filterStatus: ReturnType<typeof ref> }
    ;(vm as any).filterStatus = 'active'
    await flushPromises()

    expect(wrapper.text()).toContain('مستخدم نشط')
    expect(wrapper.text()).not.toContain('مستخدم موقوف')
  })

  it('role filter hides non-matching users', async () => {
    fetchUsersMock.mockResolvedValue([
      makeUser({ id: 1, name: 'مسؤول النظام', role: UserRole.CBY_ADMIN }),
      makeUser({ id: 2, name: 'مراجع البنك الوحيد', role: UserRole.BANK_REVIEWER }),
    ])
    const wrapper = await mountPage()

    const vm = wrapper.vm as any
    // refs in script setup are exposed as raw refs on vm
    if (vm.filterRole && typeof vm.filterRole === 'object' && 'value' in vm.filterRole) {
      vm.filterRole.value = UserRole.CBY_ADMIN
    }
    else {
      vm.filterRole = UserRole.CBY_ADMIN
    }
    await flushPromises()

    expect(wrapper.text()).toContain('مسؤول النظام')
    expect(wrapper.text()).not.toContain('مراجع البنك الوحيد')
  })

  it('resolves bank label from banks list when user bank name is missing', async () => {
    fetchBanksMock.mockResolvedValue([makeBank({ id: 7, name_ar: 'بنك عدن' })])
    fetchUsersMock.mockResolvedValue([
      makeUser({
        name: 'مشرف فرع',
        bank_id: 7,
        bank_name: null,
        bank_name_ar: null,
        bank_name_en: null,
      }),
    ])
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('بنك عدن')
    expect(wrapper.text()).not.toContain('البنك المركزي اليمني')
  })

  it('opens modal when clicking Add button', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')
    expect(wrapper.find('.modal').exists()).toBe(true)
    expect(wrapper.text()).toContain('إضافة مستخدم نظام')
  })

  it('modal role dropdown includes all canonical roles', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')
    const options = wrapper.find('.modal select').findAll('option').map(o => o.element.value)
    expect(options).toContain(UserRole.CBY_ADMIN)
    expect(options).toContain(UserRole.BANK_ADMIN)
    expect(options).toContain(UserRole.DATA_ENTRY)
    expect(options).toContain(UserRole.EXECUTIVE_MEMBER)
    expect(options).toContain(UserRole.COMMITTEE_DIRECTOR)
  })

  it('bank_id field hidden for CBY roles and visible for bank roles', async () => {
    fetchBanksMock.mockResolvedValue([makeBank()])
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')

    const vm = wrapper.vm as any
    // CBY role → no bank field
    vm.form.role = UserRole.CBY_ADMIN
    await flushPromises()
    expect(wrapper.find('.modal').text()).not.toContain('البنك *')

    // Bank role → bank field appears
    vm.form.role = UserRole.DATA_ENTRY
    await flushPromises()
    expect(wrapper.find('.modal').text()).toContain('البنك')
  })

  it('validates required fields on save', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')

    const saveBtn = wrapper.findAll('.modal-actions button')[1]!
    await saveBtn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('الاسم مطلوب')
    expect(createUserMock).not.toHaveBeenCalled()
  })

  it('shows no validation errors when form is valid', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')

    const vm = wrapper.vm as any
    vm.form.name = 'مستخدم جديد'
    vm.form.email = 'new@cby.gov.ye'
    vm.form.password = 'password123'
    vm.form.role = UserRole.SWIFT_OFFICER
    await flushPromises()

    const saveBtn = wrapper.findAll('.modal-actions button')[1]!
    await saveBtn.trigger('click')
    await flushPromises()

    // No validation error shown → form was valid
    expect(wrapper.text()).not.toContain('الاسم مطلوب')
    expect(wrapper.text()).not.toContain('البريد الإلكتروني مطلوب')
    expect(wrapper.text()).not.toContain('كلمة المرور مطلوبة')
    expect(wrapper.text()).not.toContain('الدور الوظيفي مطلوب')
  })

  it('opens edit modal with pre-filled user data', async () => {
    fetchUsersMock.mockResolvedValue([makeUser()])
    const wrapper = await mountPage()

    await wrapper.get('.btn-edit').trigger('click')
    expect(wrapper.find('.modal').exists()).toBe(true)
    expect(wrapper.text()).toContain('تعديل بيانات المستخدم')
  })

  it('updates a user via edit form', async () => {
    fetchUsersMock.mockResolvedValue([makeUser({ id: 5 })])
    updateUserMock.mockResolvedValue(makeUser({ id: 5, is_active: false }))
    const wrapper = await mountPage()

    await wrapper.get('.btn-edit').trigger('click')
    const vm = wrapper.vm as any
    vm.form.is_active = false
    await flushPromises()

    const saveBtn = wrapper.findAll('.modal-actions button')[1]!
    await saveBtn.trigger('click')
    await flushPromises()

    expect(updateUserMock).toHaveBeenCalledWith(5, expect.objectContaining({ is_active: false }))
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// /admin/entities
// ═══════════════════════════════════════════════════════════════════════════════

describe('/admin/entities', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchUsersMock.mockResolvedValue([])
    fetchBanksMock.mockResolvedValue([])
    createBankMock.mockResolvedValue(makeBank({ id: 2 }))
    updateBankMock.mockResolvedValue(makeBank())
  })

  async function mountPage() {
    const page = (await import('../../../pages/admin/entities.vue')).default
    const wrapper = mount(page, { global: { stubs: { Teleport: true } } })
    await flushPromises()
    return wrapper
  }

  it('renders page title as "إدارة الجهات"', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('.page-title').text()).toBe('إدارة الجهات')
  })

  it('renders empty state when no banks', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-empty-state-variant="entities"]').exists()).toBe(true)
  })

  it('renders entity rows with name, code, status', async () => {
    fetchBanksMock.mockResolvedValue([makeBank()])
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('البنك التجاري اليمني')
    expect(wrapper.text()).toContain('YCB')
    expect(wrapper.text()).toContain('نشط')
  })

  it('renders entity English name', async () => {
    fetchBanksMock.mockResolvedValue([makeBank()])
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Yemen Commercial Bank')
  })

  it('renders entity type column header', async () => {
    const wrapper = await mountPage()
    const headers = wrapper.findAll('thead th').map(th => th.text())
    expect(headers).toContain('نوع الجهة')
    expect(headers).toContain('رقم الترخيص')
    expect(headers).toContain('عدد المستخدمين')
  })

  it('renders entity type, license number, and user count from data', async () => {
    fetchUsersMock.mockResolvedValue([
      makeUser({ id: 10, role: UserRole.BANK_ADMIN, bank_id: 1 }),
      makeUser({ id: 11, role: UserRole.DATA_ENTRY, bank_id: 1 }),
      makeUser({ id: 12, role: UserRole.CBY_ADMIN, bank_id: null }),
    ])
    fetchBanksMock.mockResolvedValue([
      makeBank({ id: 1, entity_type: 'إسلامي', license_number: 'LIC-77' }),
    ])
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('إسلامي')
    expect(wrapper.text()).toContain('LIC-77')
    expect(wrapper.text()).toContain('2')
  })

  it('opens create modal with required form fields', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')
    expect(wrapper.find('.modal').exists()).toBe(true)
    expect(wrapper.text()).toContain('إضافة جهة جديدة')
    const text = wrapper.find('.modal').text()
    expect(text).toContain('الاسم بالعربية')
    expect(text).toContain('الاسم بالإنجليزية')
    expect(text).toContain('الرمز')
  })

  it('validates required fields before saving', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')

    const saveBtn = wrapper.findAll('.modal-actions button')[1]!
    await saveBtn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('الاسم بالعربية مطلوب')
    expect(createBankMock).not.toHaveBeenCalled()
  })

  it('creates an entity and dismisses modal on success', async () => {
    createBankMock.mockResolvedValue(makeBank({ id: 99, name_ar: 'بنك جديد' }))
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')

    const vm = wrapper.vm as any
    vm.form.name_ar = 'بنك جديد'
    vm.form.name_en = 'New Bank'
    vm.form.code = 'NB'
    await flushPromises()

    const saveBtn = wrapper.findAll('.modal-actions button')[1]!
    await saveBtn.trigger('click')
    await flushPromises()

    expect(createBankMock).toHaveBeenCalled()
    expect(wrapper.find('.modal').exists()).toBe(false)
  })

  it('opens edit modal with title "تعديل بيانات الجهة"', async () => {
    fetchBanksMock.mockResolvedValue([makeBank()])
    const wrapper = await mountPage()
    await wrapper.get('.btn-edit').trigger('click')
    expect(wrapper.text()).toContain('تعديل بيانات الجهة')
  })

  it('entity type dropdown has multiple options', async () => {
    const wrapper = await mountPage()
    await wrapper.get('.btn-primary').trigger('click')
    const selects = wrapper.findAll('.modal select')
    const typeSelect = selects[selects.length - 1]!
    const options = typeSelect.findAll('option').map(o => o.text())
    expect(options).toContain('تجاري')
    expect(options).toContain('إسلامي')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// /admin/roles
// ═══════════════════════════════════════════════════════════════════════════════

describe('/admin/roles', () => {
  beforeEach(() => {
    fetchUsersMock.mockResolvedValue([
      makeUser({ id: 1, role: UserRole.CBY_ADMIN }),
      makeUser({ id: 2, role: UserRole.BANK_ADMIN, bank_id: 1 }),
      makeUser({ id: 3, role: UserRole.BANK_REVIEWER, bank_id: 1 }),
      makeUser({ id: 4, role: UserRole.BANK_REVIEWER, bank_id: 1 }),
    ])
  })

  async function mountPage() {
    const page = (await import('../../../pages/admin/roles.vue')).default
    const wrapper = mount(page, { global: { stubs: { Teleport: true } } })
    await flushPromises()
    return wrapper
  }

  it('renders page title "الأدوار والصلاحيات"', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('.page-title').text()).toBe('الأدوار والصلاحيات')
  })

  it('renders "قراءة فقط" badge', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('.read-only-badge').text()).toContain('قراءة فقط')
  })

  it('renders all 8 canonical role rows', async () => {
    const wrapper = await mountPage()
    const allRoles = Object.values(UserRole)
    const rows = wrapper.findAll('tbody tr')
    expect(rows.length).toBe(allRoles.length)
  })

  it('renders DATA_ENTRY row with label and permissions', async () => {
    const wrapper = await mountPage()
    const row = wrapper.find('[data-role="DATA_ENTRY"]')
    expect(row.exists()).toBe(true)
    expect(row.text()).toContain('إدخال البيانات')
    expect(row.text()).toContain('إنشاء طلب')
  })

  it('renders CBY_ADMIN row', async () => {
    const wrapper = await mountPage()
    const row = wrapper.find('[data-role="CBY_ADMIN"]')
    expect(row.exists()).toBe(true)
    expect(row.text()).toContain('مدير النظام')
  })

  it('renders COMMITTEE_DIRECTOR with director-specific permissions', async () => {
    const wrapper = await mountPage()
    const row = wrapper.find('[data-role="COMMITTEE_DIRECTOR"]')
    expect(row.text()).toContain('فتح جلسة التصويت')
  })

  it('has no create/edit buttons (read-only)', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('.btn-primary').exists()).toBe(false)
    expect(wrapper.find('.btn-edit').exists()).toBe(false)
  })

  it('renders 4 header columns: الدور, الوصف, الصلاحيات, عدد المستخدمين', async () => {
    const wrapper = await mountPage()
    const headers = wrapper.findAll('thead th').map(th => th.text())
    expect(headers).toContain('الدور')
    expect(headers).toContain('الوصف')
    expect(headers).toContain('الصلاحيات')
    expect(headers).toContain('عدد المستخدمين')
  })

  it('each row has a role enum code displayed', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('DATA_ENTRY')
    expect(wrapper.text()).toContain('CBY_ADMIN')
  })

  it('renders per-role user counts', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-role="BANK_REVIEWER"]').text()).toContain('2')
    expect(wrapper.find('[data-role="BANK_ADMIN"]').text()).toContain('1')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// /settings — 6-tab layout
// ═══════════════════════════════════════════════════════════════════════════════

describe('/settings — 6-tab layout', () => {
  async function mountPage() {
    const page = (await import('../../../pages/settings.vue')).default
    const wrapper = mount(page, {
      attachTo: document.body,
      global: { stubs: { Teleport: true } },
    })
    await flushPromises()
    return wrapper
  }

  it('renders 5 visible tabs (demo tab hidden in non-demo mode)', async () => {
    const wrapper = await mountPage()
    const tabs = wrapper.findAll('[data-tab]')
    expect(tabs.length).toBe(5)
    const tabIds = tabs.map(t => t.attributes('data-tab'))
    expect(tabIds).not.toContain('demo')
  })

  it('renders all expected tab labels', async () => {
    const wrapper = await mountPage()
    const tabText = wrapper.findAll('[data-tab]').map(t => t.text())
    expect(tabText).toContain('سير العمل')
    expect(tabText).toContain('البريد الإلكتروني')
    expect(tabText).toContain('الإشعارات')
    expect(tabText).toContain('الأمن')
    expect(tabText).toContain('عام')
  })

  it('defaults to "عام" tab being active', async () => {
    const wrapper = await mountPage()
    const generalTab = wrapper.find('[data-tab="general"]')
    expect(generalTab.classes()).toContain('active')
  })

  it('الأمن tab becomes active when clicked', async () => {
    const wrapper = await mountPage()
    const securityTab = wrapper.find('[data-tab="security"]')
    await securityTab.trigger('click')
    await flushPromises()
    expect(securityTab.classes()).toContain('active')
  })

  it('الأمن panel contains lockout threshold data', async () => {
    const wrapper = await mountPage()
    await wrapper.find('[data-tab="security"]').trigger('click')
    await flushPromises()

    const threshold = wrapper.find('[data-testid="lockout-threshold"]')
    expect(threshold.exists()).toBe(true)
    expect(threshold.text()).toContain('10')
  })

  it('الأمن panel contains lockout duration data', async () => {
    const wrapper = await mountPage()
    await wrapper.find('[data-tab="security"]').trigger('click')
    await flushPromises()

    const duration = wrapper.find('[data-testid="lockout-duration"]')
    expect(duration.exists()).toBe(true)
    expect(duration.text()).toContain('15')
  })

  it('الأمن panel contains MFA toggle input', async () => {
    const wrapper = await mountPage()
    await wrapper.find('[data-tab="security"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="mfa-toggle"]').exists()).toBe(true)
  })

  it('general panel html includes language select', async () => {
    const wrapper = await mountPage()
    const generalPanel = wrapper.find('[data-panel="general"]')
    expect(generalPanel.html()).toContain('العربية')
  })

  it('notifications tab activates on click', async () => {
    const wrapper = await mountPage()
    const notifTab = wrapper.find('[data-tab="notifications"]')
    await notifTab.trigger('click')
    await flushPromises()
    expect(notifTab.classes()).toContain('active')
  })

  it('workflow tab activates and panel contains workflow content', async () => {
    const wrapper = await mountPage()
    await wrapper.find('[data-tab="workflow"]').trigger('click')
    await flushPromises()

    const workflowPanel = wrapper.find('[data-panel="workflow"]')
    expect(workflowPanel.text()).toContain('إعدادات سير العمل')
  })

  it('email tab activates and shows email panel', async () => {
    const wrapper = await mountPage()
    await wrapper.find('[data-tab="email"]').trigger('click')
    await flushPromises()

    const emailPanel = wrapper.find('[data-panel="email"]')
    expect(emailPanel.text()).toContain('البريد الإلكتروني')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// /profile — full profile page
// ═══════════════════════════════════════════════════════════════════════════════

describe('/profile', () => {
  async function mountPage() {
    const page = (await import('../../../pages/profile.vue')).default
    const wrapper = mount(page, { global: { stubs: { Teleport: true } } })
    await flushPromises()
    return wrapper
  }

  it('renders page title "الملف الشخصي"', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('.page-title').text()).toBe('الملف الشخصي')
  })

  it('renders avatar initials from user name', async () => {
    const wrapper = await mountPage()
    const avatar = wrapper.find('[data-testid="avatar-initials"]')
    expect(avatar.exists()).toBe(true)
    expect(avatar.text().trim().length).toBeGreaterThan(0)
  })

  it('renders user name', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="profile-name"]').text()).toBe('مدير النظام')
  })

  it('renders user email', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="profile-email"]').text()).toBe('admin@cby.gov.ye')
  })

  it('renders role badge', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('.badge-role').exists()).toBe(true)
  })

  it('renders stats section with last login row', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="stat-last-login"]').exists()).toBe(true)
  })

  it('renders total actions stat row', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="stat-total-actions"]').exists()).toBe(true)
  })

  it('renders recent activity section', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="recent-activity"]').exists()).toBe(true)
  })

  it('renders change password form', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('تغيير كلمة المرور')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('renders MFA toggle button', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="mfa-toggle-btn"]').exists()).toBe(true)
  })

  it('MFA toggle button text changes on click', async () => {
    const wrapper = await mountPage()
    const btn = wrapper.find('[data-testid="mfa-toggle-btn"]')
    const initialText = btn.text()
    await btn.trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="mfa-toggle-btn"]').text()).not.toBe(initialText)
  })

  it('shows success banner after password change', async () => {
    const wrapper = await mountPage()
    const vm = wrapper.vm as any
    vm.passwordForm.current_password = 'oldpass123'
    vm.passwordForm.password = 'newpass123'
    vm.passwordForm.password_confirmation = 'newpass123'
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('.success-banner').exists()).toBe(true)
  })
})
