import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

// ── Fixtures ──────────────────────────────────────────────────────────────────

const BANK_ID = 1
const BANK_NAME_AR = 'بنك اليمن الدولي'
const BANK_NAME_EN = 'Yemen International Bank'

const BANKS = [
  { id: 1, name_ar: BANK_NAME_AR, name_en: BANK_NAME_EN, code: 'BNK-001', license_number: 'LIC-2026-001', is_active: true },
  { id: 2, name_ar: 'بنك التضامن الإسلامي', name_en: 'Al Tadhamun Islamic Bank', code: 'BNK-002', license_number: 'LIC-2026-002', is_active: true },
  { id: 3, name_ar: 'بنك سبأ الإسلامي', name_en: 'Saba Islamic Bank', code: 'BNK-003', license_number: null, is_active: false },
]

const BANK_STAFF = [
  {
    id: 10,
    name: 'محمد أحمد علي',
    email: 'data@bank.ye',
    role: UserRole.DATA_ENTRY,
    bank_id: BANK_ID,
    bank_name_ar: BANK_NAME_AR,
    bank_name_en: BANK_NAME_EN,
    is_active: true,
    last_login_at: '2026-05-19T10:00:00.000Z',
    last_seen_at: '2026-05-19T10:00:00.000Z',
    created_at: '2026-01-01T00:00:00.000Z',
  },
  {
    id: 11,
    name: 'سارة محمد الزبيري',
    email: 'reviewer@bank.ye',
    role: UserRole.BANK_REVIEWER,
    bank_id: BANK_ID,
    bank_name_ar: BANK_NAME_AR,
    bank_name_en: BANK_NAME_EN,
    is_active: true,
    last_login_at: '2026-05-18T09:00:00.000Z',
    last_seen_at: '2026-05-18T09:00:00.000Z',
    created_at: '2026-02-15T00:00:00.000Z',
  },
  {
    id: 12,
    name: 'فاطمة حسن مقبل',
    email: 'data2@bank.ye',
    role: UserRole.DATA_ENTRY,
    bank_id: BANK_ID,
    bank_name_ar: BANK_NAME_AR,
    bank_name_en: BANK_NAME_EN,
    is_active: false,
    last_login_at: null,
    last_seen_at: null,
    created_at: '2026-03-10T00:00:00.000Z',
  },
]

const CBY_USERS = [
  {
    id: 20,
    name: 'أحمد ناصر القحطاني',
    email: 'support@cby.gov.ye',
    role: UserRole.SUPPORT_COMMITTEE,
    bank_id: null,
    bank_name_ar: null,
    bank_name_en: null,
    is_active: true,
    last_login_at: '2026-05-20T08:00:00.000Z',
    last_seen_at: '2026-05-20T08:00:00.000Z',
    created_at: '2026-01-01T00:00:00.000Z',
  },
  {
    id: 21,
    name: 'منى عبدالله الشامي',
    email: 'exec@cby.gov.ye',
    role: UserRole.EXECUTIVE_MEMBER,
    bank_id: null,
    bank_name_ar: null,
    bank_name_en: null,
    is_active: true,
    last_login_at: '2026-05-19T15:00:00.000Z',
    last_seen_at: '2026-05-19T15:00:00.000Z',
    created_at: '2026-01-10T00:00:00.000Z',
  },
  {
    id: 22,
    name: 'عبدالرحمن محمد الحمدي',
    email: 'director@cby.gov.ye',
    role: UserRole.COMMITTEE_DIRECTOR,
    bank_id: null,
    bank_name_ar: null,
    bank_name_en: null,
    is_active: false,
    last_login_at: null,
    last_seen_at: null,
    created_at: '2026-02-01T00:00:00.000Z',
  },
]

const NOTIFICATIONS_STUB = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: null, last_page: 1, path: '/api/notifications', per_page: 20, to: null, total: 0 },
}

const DASHBOARD_STUB = { total: 0, pending: 0, approved: 0, rejected: 0 }

function makeAuthUser(role: UserRole) {
  const isBankRole = [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.SWIFT_OFFICER].includes(role)
  return {
    id: 9000 + Object.values(UserRole).indexOf(role),
    name: role === UserRole.BANK_ADMIN ? 'مسؤول البنك الأول' : 'مدير النظام',
    email: role === UserRole.BANK_ADMIN ? 'admin@bank.ye' : 'admin@cby.gov.ye',
    role,
    bank_id: isBankRole ? BANK_ID : null,
    bank_name_ar: isBankRole ? BANK_NAME_AR : null,
    bank_name_en: isBankRole ? BANK_NAME_EN : null,
    is_active: true,
  }
}

async function mockStaffApi(page: Page, role: UserRole, usersFixture: typeof BANK_STAFF | typeof CBY_USERS) {
  const user = makeAuthUser(role)

  await page.route('**/*', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname
    const method = route.request().method()

    if (path === '/sanctum/csrf-cookie') {
      await route.fulfill({ status: 204, body: '' })
      return
    }

    if (path === '/api/auth/me') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'ok', data: user }) })
      return
    }

    if (path === '/api/notifications/unread-count') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'ok', data: { count: 0 } }) })
      return
    }

    if (path === '/api/notifications') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'ok', data: NOTIFICATIONS_STUB }) })
      return
    }

    if (path === '/api/dashboard/stats' || path.startsWith('/api/dashboard')) {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'ok', data: DASHBOARD_STUB }) })
      return
    }

    if (path === '/api/settings') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'ok', data: {} }) })
      return
    }

    if (path === '/api/banks') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'ok', data: { data: BANKS, meta: { current_page: 1, last_page: 1, per_page: 100, total: BANKS.length } } }),
      })
      return
    }

    const bankUpdateMatch = path.match(/^\/api\/banks\/(\d+)$/)
    if (bankUpdateMatch && method === 'PUT') {
      const body = await route.request().postDataJSON()
      const id = Number(bankUpdateMatch[1])
      const existing = BANKS.find(b => b.id === id) ?? BANKS[0]!
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'updated', data: { ...existing, ...body } }) })
      return
    }

    if (path === '/api/banks' && method === 'POST') {
      const body = await route.request().postDataJSON()
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'created', data: { id: 99, ...body } }),
      })
      return
    }

    const userGetMatch = path.match(/^\/api\/users\/(\d+)$/)
    if (userGetMatch && method === 'GET') {
      const id = Number(userGetMatch[1])
      const found = [...BANK_STAFF, ...CBY_USERS].find(u => u.id === id) ?? usersFixture[0]!
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'ok', data: found }) })
      return
    }

    const userUpdateMatch = path.match(/^\/api\/users\/(\d+)$/)
    if (userUpdateMatch && method === 'PUT') {
      const body = await route.request().postDataJSON()
      const id = Number(userUpdateMatch[1])
      const existing = [...BANK_STAFF, ...CBY_USERS].find(u => u.id === id) ?? usersFixture[0]!
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, message: 'updated', data: { ...existing, ...body } }) })
      return
    }

    if (path === '/api/users' && method === 'POST') {
      const body = await route.request().postDataJSON()
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'created', data: { id: 99, ...body, is_active: true, last_login_at: null, last_seen_at: null, created_at: '2026-05-20T00:00:00.000Z' } }),
      })
      return
    }

    if (path === '/api/users' || path.startsWith('/api/users')) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'ok', data: usersFixture }),
      })
      return
    }

    await route.continue()
  })
}

// ── Navigation helpers ────────────────────────────────────────────────────────

async function waitForNuxtHydration(page: Page) {
  await page.waitForSelector('#__nuxt')
  await page.waitForFunction(() => (document.querySelector('#__nuxt')?.children.length ?? 0) > 0)
}

async function disableAnimations(page: Page) {
  await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' })
}

async function navigateToPage(page: Page, path: string) {
  await page.evaluate((p) => {
    const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
    nuxtApp?.$router.push(p)
  }, path)
  await page.waitForURL(`**${path}`, { timeout: 12000 })
}

// ── BANK_ADMIN staff tests ────────────────────────────────────────────────────

test.describe('BANK_ADMIN staff parity', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('yfh-authenticated', '1')
      localStorage.setItem('sidebar_collapsed', 'false')
    })
    await mockStaffApi(page, UserRole.BANK_ADMIN, BANK_STAFF)
    await page.goto('/dashboard')
    await waitForNuxtHydration(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateToPage(page, '/staff')
    await disableAnimations(page)
  })

  test('staff list desktop — page header, stats, filter, table', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await page.waitForSelector('.stat-cards', { timeout: 5000 })
    await page.waitForSelector('.data-table', { timeout: 5000 })
    await expect(page.locator('.page-title')).toContainText('موظفو الجهة')
    await expect(page.locator('.stat-cards')).toBeVisible()
    await expect(page.locator('.data-table')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-bank-admin-staff-list-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('staff list mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page).toHaveScreenshot('7-7-bank-admin-staff-list-mobile.png', { maxDiffPixelRatio: 0.07 })
  })

  test('staff add modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.btn-primary', { timeout: 10000 })
    await page.locator('.btn-primary').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-bank-admin-staff-add-modal-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('staff edit modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.data-table', { timeout: 10000 })
    await page.locator('button.btn-edit').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-bank-admin-staff-edit-modal-desktop.png', { maxDiffPixelRatio: 0.05 })
  })
})

// ── CBY_ADMIN system users tests ──────────────────────────────────────────────

test.describe('CBY_ADMIN system users parity', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('yfh-authenticated', '1')
      localStorage.setItem('sidebar_collapsed', 'false')
    })
    await mockStaffApi(page, UserRole.CBY_ADMIN, CBY_USERS)
    await page.goto('/dashboard')
    await waitForNuxtHydration(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateToPage(page, '/admin/cby-staff')
    await disableAnimations(page)
  })

  test('system users list desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toContainText('مستخدمي النظام')
    await expect(page.locator('.stat-cards')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-cby-admin-system-users-list-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('system users list mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page).toHaveScreenshot('7-7-cby-admin-system-users-list-mobile.png', { maxDiffPixelRatio: 0.07 })
  })

  test('add user modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.btn-primary', { timeout: 10000 })
    await page.locator('.btn-primary').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-cby-admin-add-user-modal-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('view user modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.data-table', { timeout: 10000 })
    const viewBtns = page.locator('button.btn-view')
    await viewBtns.first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-cby-admin-view-user-modal-desktop.png', { maxDiffPixelRatio: 0.05 })
  })
})

// ── CBY_ADMIN entities tests ──────────────────────────────────────────────────

test.describe('CBY_ADMIN entities parity', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('yfh-authenticated', '1')
      localStorage.setItem('sidebar_collapsed', 'false')
    })
    await mockStaffApi(page, UserRole.CBY_ADMIN, CBY_USERS)
    await page.goto('/dashboard')
    await waitForNuxtHydration(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateToPage(page, '/admin/entities')
    await disableAnimations(page)
  })

  test('entities list desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toContainText('إدارة البنوك التجارية')
    await expect(page.locator('.stat-cards')).toBeVisible()
    await expect(page.locator('.data-table')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-cby-admin-entities-list-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('entities list mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page).toHaveScreenshot('7-7-cby-admin-entities-list-mobile.png', { maxDiffPixelRatio: 0.07 })
  })

  test('add bank modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.btn-primary', { timeout: 10000 })
    await page.locator('.btn-primary').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-cby-admin-add-bank-modal-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('view bank modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.data-table', { timeout: 10000 })
    await page.locator('button.btn-view').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await expect(page).toHaveScreenshot('7-7-cby-admin-view-bank-modal-desktop.png', { maxDiffPixelRatio: 0.05 })
  })
})

// ── CBY_ADMIN roles matrix tests ──────────────────────────────────────────────

test.describe('CBY_ADMIN roles matrix parity', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('yfh-authenticated', '1')
      localStorage.setItem('sidebar_collapsed', 'false')
    })
    await mockStaffApi(page, UserRole.CBY_ADMIN, CBY_USERS)
    await page.goto('/dashboard')
    await waitForNuxtHydration(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateToPage(page, '/admin/roles')
    await disableAnimations(page)
  })

  test('roles matrix desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toContainText('مصفوفة الأدوار والصلاحيات')
    await expect(page.locator('.matrix-table')).toBeVisible()
    // All checkboxes are disabled
    const checkboxes = page.locator('.perm-checkbox')
    await expect(checkboxes.first()).toBeDisabled()
    await expect(page).toHaveScreenshot('7-7-cby-admin-roles-matrix-desktop.png', { maxDiffPixelRatio: 0.05 })
  })

  test('roles matrix mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page).toHaveScreenshot('7-7-cby-admin-roles-matrix-mobile.png', { maxDiffPixelRatio: 0.07 })
  })

  test('roles matrix has 8 role columns and 14 permission rows', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.matrix-table', { timeout: 10000 })
    const headerCells = page.locator('.matrix-table thead tr th')
    await expect(headerCells).toHaveCount(9) // 1 perm col + 8 role cols
    const bodyRows = page.locator('.matrix-table tbody tr')
    await expect(bodyRows).toHaveCount(14)
  })
})
