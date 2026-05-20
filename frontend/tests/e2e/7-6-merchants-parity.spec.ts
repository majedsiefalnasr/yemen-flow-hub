import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

// ── Fixtures ──────────────────────────────────────────────────────────────────

const BANK_ID = 1
const BANK_NAME = 'بنك اليمن الدولي'

const MERCHANTS = [
  {
    id: 1,
    bank_id: BANK_ID,
    bank_name: BANK_NAME,
    name: 'شركة الأمل للاستيراد',
    commercial_register: 'CR-00001',
    tax_number: 'TX-11111',
    national_id: null,
    owner_name: null,
    phone: '+967700000001',
    email: null,
    address: 'صنعاء، شارع التحرير',
    business_type: 'import',
    is_active: true,
    transaction_count: 7,
    created_by: 1,
    created_at: '2026-01-01T00:00:00.000Z',
  },
  {
    id: 2,
    bank_id: BANK_ID,
    bank_name: BANK_NAME,
    name: 'مؤسسة النور التجارية',
    commercial_register: 'CR-00002',
    tax_number: 'TX-22222',
    national_id: null,
    owner_name: null,
    phone: '+967700000002',
    email: null,
    address: 'عدن، كريتر',
    business_type: 'retail',
    is_active: false,
    transaction_count: 3,
    created_by: 1,
    created_at: '2026-01-15T00:00:00.000Z',
  },
]

const BANKS = [
  { id: 1, name_ar: 'بنك اليمن الدولي', name_en: 'Yemen International Bank', code: 'YIB', is_active: true },
  { id: 2, name_ar: 'بنك التضامن', name_en: 'Al Tadhamun Bank', code: 'ATB', is_active: true },
]

const NOTIFICATIONS_STUB = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: null, last_page: 1, path: '/api/notifications', per_page: 20, to: null, total: 0 },
}

const DASHBOARD_STUB = { total: 0, pending: 0, approved: 0, rejected: 0 }

function makeUser(role: UserRole) {
  const isBankRole = [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.SWIFT_OFFICER].includes(role)
  return {
    id: 9000 + Object.values(UserRole).indexOf(role),
    name: `مستخدم ${role}`,
    email: `${role.toLowerCase()}@test.ye`,
    role,
    bank_id: isBankRole ? BANK_ID : null,
    bank_name_ar: isBankRole ? BANK_NAME : null,
    bank_name_en: isBankRole ? 'Yemen International Bank' : null,
    is_active: true,
  }
}

async function mockMerchantsApi(page: Page, role: UserRole) {
  const user = makeUser(role)

  await page.route('**/*', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname

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
        status: 200, contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'ok', data: { data: BANKS, meta: { current_page: 1, last_page: 1, per_page: 100, total: BANKS.length } } }),
      })
      return
    }

    const merchantUpdateMatch = path.match(/^\/api\/merchants\/(\d+)$/)
    if (merchantUpdateMatch && route.request().method() === 'PUT') {
      const body = await route.request().postDataJSON()
      const id = Number(merchantUpdateMatch[1])
      const existing = MERCHANTS.find(m => m.id === id) ?? MERCHANTS[0]!
      await route.fulfill({
        status: 200, contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'updated', data: { ...existing, ...body } }),
      })
      return
    }

    if (path === '/api/merchants' && route.request().method() === 'POST') {
      const body = await route.request().postDataJSON()
      await route.fulfill({
        status: 201, contentType: 'application/json',
        body: JSON.stringify({
          success: true, message: 'created',
          data: { id: 99, bank_id: BANK_ID, bank_name: BANK_NAME, ...body, transaction_count: 0, created_by: user.id, created_at: '2026-05-20T00:00:00.000Z' },
        }),
      })
      return
    }

    if (path === '/api/merchants' || path.startsWith('/api/merchants')) {
      await route.fulfill({
        status: 200, contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'ok', data: { data: MERCHANTS, meta: { current_page: 1, last_page: 1, per_page: 100, total: MERCHANTS.length } } }),
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

async function openMerchantsForRole(page: Page, role: UserRole) {
  await page.addInitScript(() => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
  })
  await mockMerchantsApi(page, role)
  await page.goto('/dashboard')
  await waitForNuxtHydration(page)
  await page.waitForURL('**/dashboard', { timeout: 12000 })
  await page.evaluate(() => {
    const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
    nuxtApp?.$router.push('/merchants')
  })
  await page.waitForURL('**/merchants', { timeout: 12000 })
}

// ── Helper ────────────────────────────────────────────────────────────────────

async function disableAnimations(page: Page) {
  await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' })
}

// ── BANK_ADMIN tests ──────────────────────────────────────────────────────────

test.describe('BANK_ADMIN merchants parity', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('yfh-authenticated', '1')
      localStorage.setItem('sidebar_collapsed', 'false')
    })
    await mockMerchantsApi(page, UserRole.BANK_ADMIN)
    await page.goto('/dashboard')
    await waitForNuxtHydration(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await page.evaluate(() => {
      const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
      nuxtApp?.$router.push('/merchants')
    })
    await page.waitForURL('**/merchants', { timeout: 12000 })
    await page.waitForSelector('.merchant-card', { timeout: 12000 })
  })

  test('desktop: merchant card list', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await disableAnimations(page)
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-list-desktop.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('desktop: suspended filter state', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await disableAnimations(page)
    await page.selectOption('[aria-label="تصفية بالحالة"]', 'suspended')
    await page.waitForTimeout(100)
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-suspended-desktop.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('desktop: add merchant modal', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await disableAnimations(page)
    await page.click('button:has-text("تاجر جديد")')
    await page.waitForSelector('.modal-title', { timeout: 8000 })
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-add-modal-desktop.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('desktop: edit merchant modal', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await disableAnimations(page)
    await page.locator('.merchant-card').first().locator('[aria-label="تعديل التاجر"]').click()
    await page.waitForSelector('.modal-title', { timeout: 8000 })
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-edit-modal-desktop.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('mobile: merchant card list', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await disableAnimations(page)
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-list-mobile.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('mobile: add merchant modal', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await disableAnimations(page)
    await page.click('button:has-text("تاجر جديد")')
    await page.waitForSelector('.modal-title', { timeout: 8000 })
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-add-modal-mobile.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('mobile: edit merchant modal', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await disableAnimations(page)
    await page.locator('.merchant-card').first().locator('[aria-label="تعديل التاجر"]').click()
    await page.waitForSelector('.modal-title', { timeout: 8000 })
    await expect(page).toHaveScreenshot('7-6/bank-admin-merchants-edit-modal-mobile.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('stat cards show correct counts', async ({ page }) => {
    const statValues = await page.locator('.stat-value').allTextContents()
    expect(statValues[0]).toBe('2')
    expect(statValues[1]).toBe('1')
    expect(statValues[2]).toBe('1')
  })

  test('search filters cards by name', async ({ page }) => {
    await page.fill('[aria-label="بحث عن تاجر"]', 'الأمل')
    await page.waitForTimeout(100)
    const cards = page.locator('.merchant-card')
    await expect(cards).toHaveCount(1)
  })

  test('filtered-empty shows "لا توجد نتائج مطابقة."', async ({ page }) => {
    await page.fill('[aria-label="بحث عن تاجر"]', 'xxxnonexistent')
    await page.waitForTimeout(100)
    await expect(page.locator('.empty-heading')).toContainText('لا توجد نتائج مطابقة')
  })

  test('add modal title is "تسجيل تاجر جديد"', async ({ page }) => {
    await page.click('button:has-text("تاجر جديد")')
    await page.waitForSelector('.modal-title', { timeout: 8000 })
    await expect(page.locator('.modal-title')).toHaveText('تسجيل تاجر جديد')
  })

  test('edit modal title is "تعديل بيانات التاجر"', async ({ page }) => {
    await page.locator('.merchant-card').first().locator('[aria-label="تعديل التاجر"]').click()
    await page.waitForSelector('.modal-title', { timeout: 8000 })
    await expect(page.locator('.modal-title')).toHaveText('تعديل بيانات التاجر')
  })

  test('page title and breadcrumbs are present', async ({ page }) => {
    await expect(page.locator('.page-title')).toHaveText('إدارة التجار')
    await expect(page.locator('.breadcrumbs')).toContainText('الرئيسية')
    await expect(page.locator('.breadcrumbs')).toContainText('التجار')
  })

  test('no bank filter shown for BANK_ADMIN', async ({ page }) => {
    await expect(page.locator('[aria-label="تصفية بالبنك"]')).toHaveCount(0)
  })
})

// ── CBY_ADMIN tests ───────────────────────────────────────────────────────────

test.describe('CBY_ADMIN merchants parity', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('yfh-authenticated', '1')
      localStorage.setItem('sidebar_collapsed', 'false')
    })
    await mockMerchantsApi(page, UserRole.CBY_ADMIN)
    await page.goto('/dashboard')
    await waitForNuxtHydration(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await page.evaluate(() => {
      const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
      nuxtApp?.$router.push('/merchants')
    })
    await page.waitForURL('**/merchants', { timeout: 12000 })
    await page.waitForSelector('.merchants-table', { timeout: 12000 })
  })

  test('desktop: merchant table', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await disableAnimations(page)
    await expect(page).toHaveScreenshot('7-6/cby-admin-merchants-table-desktop.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('desktop: view merchant modal', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await disableAnimations(page)
    await page.locator('.icon-btn-view').first().click()
    await page.waitForSelector('.view-modal-title', { timeout: 8000 })
    await expect(page).toHaveScreenshot('7-6/cby-admin-merchants-view-modal-desktop.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('mobile: merchant table', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await disableAnimations(page)
    await expect(page).toHaveScreenshot('7-6/cby-admin-merchants-table-mobile.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('mobile: view merchant modal', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await disableAnimations(page)
    await page.locator('.icon-btn-view').first().click()
    await page.waitForSelector('.view-modal-title', { timeout: 8000 })
    await expect(page).toHaveScreenshot('7-6/cby-admin-merchants-view-modal-mobile.png', { fullPage: false, maxDiffPixelRatio: 0.02 })
  })

  test('renders table with correct columns', async ({ page }) => {
    const headers = await page.locator('thead th').allTextContents()
    expect(headers).toContain('التاجر')
    expect(headers).toContain('السجل التجاري')
    expect(headers).toContain('الرقم الضريبي')
    expect(headers).toContain('القطاع')
    expect(headers).toContain('البنك التابع له')
    expect(headers).toContain('الحالة')
    expect(headers).toContain('المعاملات')
  })

  test('no create button for CBY_ADMIN', async ({ page }) => {
    await expect(page.locator('button:has-text("تاجر جديد")')).toHaveCount(0)
  })

  test('bank filter is present for CBY_ADMIN', async ({ page }) => {
    await expect(page.locator('[aria-label="تصفية بالبنك"]')).toHaveCount(1)
  })

  test('view modal shows merchant details as read-only', async ({ page }) => {
    await page.locator('.icon-btn-view').first().click()
    await page.waitForSelector('.view-modal-title', { timeout: 8000 })
    await expect(page.locator('.view-modal-title')).toHaveText('تفاصيل التاجر')
    await expect(page.locator('.view-modal')).toContainText('شركة الأمل للاستيراد')
    await expect(page.locator('.view-modal')).toContainText('CR-00001')
  })

  test('view modal has no edit/suspend/delete buttons', async ({ page }) => {
    await page.locator('.icon-btn-view').first().click()
    await page.waitForSelector('.view-modal-title', { timeout: 8000 })
    await expect(page.locator('.view-modal button:has-text("تعديل")')).toHaveCount(0)
    await expect(page.locator('.view-modal button:has-text("تعليق")')).toHaveCount(0)
    await expect(page.locator('.view-modal button:has-text("حذف")')).toHaveCount(0)
  })

  test('table shows transaction count', async ({ page }) => {
    const counts = await page.locator('.cell-count').allTextContents()
    expect(counts.some(c => Number(c) >= 0)).toBe(true)
  })

  test('page subtitle is CBY-specific', async ({ page }) => {
    await expect(page.locator('.page-subtitle')).toContainText('عرض جميع التجار المسجّلين')
  })
})
