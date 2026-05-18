import { describe, expect, it, vi } from 'vitest'
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

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    currentRole: 'BANK_ADMIN',
    isCbyAdmin: false,
    isBankUser: true,
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
  it('renders /merchants page shell', async () => {
    const page = await import('../../../pages/merchants.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('إدارة التجار')
  })

  it('renders /audit page shell', async () => {
    const page = await import('../../../pages/audit.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('التدقيق والامتثال')
  })

  it('renders /admin/workflow-docs page shell', async () => {
    const page = await import('../../../pages/admin/workflow-docs.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('قواعد المستندات')
  })

  it('resolves Story 6.2 admin and staff route wrappers', async () => {
    navigateToMock.mockClear()

    await renderPage((await import('../../../pages/staff.vue')).default)
    await renderPage((await import('../../../pages/admin/cby-staff.vue')).default)
    await renderPage((await import('../../../pages/admin/entities.vue')).default)
    await renderPage((await import('../../../pages/admin/roles.vue')).default)

    expect(navigateToMock).toHaveBeenCalledWith('/users', { replace: true })
    expect(navigateToMock).toHaveBeenCalledWith('/banks', { replace: true })
  })
})
