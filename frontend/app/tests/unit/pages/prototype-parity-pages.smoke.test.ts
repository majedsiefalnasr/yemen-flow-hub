import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createSSRApp } from 'vue'
import { createPinia } from 'pinia'
import { renderToString } from 'vue/server-renderer'

vi.stubGlobal('definePageMeta', vi.fn())
const navigateToMock = vi.fn()
vi.stubGlobal('navigateTo', navigateToMock)

vi.mock('../../../composables/useMerchants', () => ({
  useMerchants: () => ({
    fetchMerchants: vi.fn().mockResolvedValue([]),
    createMerchant: vi.fn(),
    updateMerchant: vi.fn(),
    suspendMerchant: vi.fn(),
  }),
}))

vi.mock('../../../composables/useUsers', () => ({
  useUsers: () => ({
    fetchUsers: vi.fn().mockResolvedValue([]),
    createUser: vi.fn(),
    updateUser: vi.fn(),
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 1, bank_id: null, role: 'CBY_ADMIN' },
    currentRole: 'CBY_ADMIN',
    isCbyAdmin: true,
    isBankUser: false,
  }),
}))

vi.mock('../../../composables/useBanks', () => ({
  useBanks: () => ({
    fetchBanks: vi.fn().mockResolvedValue([]),
  }),
}))

vi.mock('../../../composables/useAudit', () => ({
  useAudit: () => ({
    fetchAuditLogs: vi.fn().mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 },
    }),
  }),
}))

vi.mock('../../../composables/useDocumentTypes', () => ({
  useDocumentTypes: () => ({
    fetchDocumentTypes: vi.fn().mockResolvedValue([]),
    createDocumentType: vi.fn(),
    updateDocumentType: vi.fn(),
  }),
}))

async function renderPage(component: unknown): Promise<string> {
  const app = createSSRApp(component as any)
  app.use(createPinia())
  return renderToString(app)
}

describe('Story 5.7 page smoke tests', () => {
  beforeEach(() => {
    vi.resetModules()
  })
  it('renders /merchants page shell', async () => {
    const page = await import('../../../pages/merchants.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('المستوردون')
  })

  it('renders /audit page shell', async () => {
    const page = await import('../../../pages/audit.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('التدقيق والامتثال')
  })

  it('renders /admin/workflow-docs page shell', async () => {
    const page = await import('../../../pages/admin/workflow-docs.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('أنواع المستندات')
  })

  it('renders /staff page shell (BANK_ADMIN staff management)', async () => {
    const page = await import('../../../pages/staff.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('موظفو الجهة')
  })

  it('renders Story 6.5 admin pages with real content (not redirects)', async () => {
    const cbystaffHtml = await renderPage(
      (await import('../../../pages/admin/cby-staff.vue')).default,
    )
    expect(cbystaffHtml).toContain('مستخدمي النظام')

    const banksHtml = await renderPage((await import('../../../pages/admin/banks.vue')).default)
    expect(banksHtml).toContain('إدارة البنوك التجارية')

    const rolesHtml = await renderPage((await import('../../../pages/admin/roles.vue')).default)
    expect(rolesHtml).toContain('مصفوفة الأدوار والصلاحيات')
  })
})
