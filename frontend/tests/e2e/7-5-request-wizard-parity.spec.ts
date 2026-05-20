import { expect, test, type Page, type Route } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

type MerchantFixture = {
  id: number
  name: string
  commercial_register: string
}

type MockWizardOptions = {
  role: UserRole
  merchants?: MerchantFixture[]
  onUpload?: (route: Route) => Promise<void>
  onSubmit?: (route: Route) => Promise<void>
}

const BANK_ADMIN_MERCHANTS: MerchantFixture[] = [
  { id: 1, name: 'مؤسسة النور التجارية', commercial_register: 'CR-001' },
  { id: 2, name: 'شركة الأمل للتجارة', commercial_register: 'CR-002' },
]

const DATA_ENTRY_MERCHANTS: MerchantFixture[] = [
  { id: 7, name: 'شركة هائل سعيد أنعم — مواد غذائية', commercial_register: 'CR-007' },
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
    bank_id: isBankRole ? 1 : null,
    bank_name_ar: isBankRole ? 'بنك اليمن الدولي' : null,
    bank_name_en: isBankRole ? 'Yemen International Bank' : null,
    is_active: true,
  }
}

function makeDraftRequest(merchant: MerchantFixture) {
  return {
    id: 99,
    reference_number: 'YFH-2026-000099',
    bank_id: 1,
    bank_name: 'بنك اليمن الدولي',
    merchant: {
      id: merchant.id,
      name: merchant.name,
      commercial_register: merchant.commercial_register,
    },
    merchant_id: merchant.id,
    currency: 'USD',
    amount: 50000,
    supplier_name: 'Cargill Trading Inc.',
    goods_type: 'مواد غذائية',
    payment_terms: 'LC',
    due_date: '2026-12-31',
    notes: 'شحنة غذائية موسمية',
    current_status: 'DRAFT',
    created_at: '2026-01-01T00:00:00.000Z',
    updated_at: '2026-01-01T00:00:00.000Z',
    documents: [],
  }
}

async function mockWizardApi(page: Page, options: MockWizardOptions) {
  const merchants = options.merchants ?? BANK_ADMIN_MERCHANTS
  const user = makeUser(options.role)
  const draftRequest = makeDraftRequest(merchants[0] ?? BANK_ADMIN_MERCHANTS[0]!)

  await page.route('**/*', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname
    const method = route.request().method()

    if (path === '/sanctum/csrf-cookie') {
      await route.fulfill({ status: 204, body: '' })
      return
    }

    if (path === '/api/auth/me') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: user }),
      })
      return
    }

    if (path === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: DASHBOARD_STUB }),
      })
      return
    }

    if (path === '/api/settings') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: null }),
      })
      return
    }

    if (path === '/api/notifications' || path.startsWith('/api/notifications')) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: NOTIFICATIONS_STUB }),
      })
      return
    }

    if (path === '/api/merchants') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            data: merchants,
            meta: {
              current_page: 1,
              last_page: 1,
              per_page: merchants.length,
              total: merchants.length,
            },
          },
        }),
      })
      return
    }

    if (path === '/api/requests' && method === 'POST') {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'Created', data: draftRequest }),
      })
      return
    }

    if (path === '/api/documents/upload' && method === 'POST') {
      if (options.onUpload) {
        await options.onUpload(route)
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'Uploaded', data: { id: 1 } }),
      })
      return
    }

    if (path === `/api/workflow/${draftRequest.id}/submit` && method === 'POST') {
      if (options.onSubmit) {
        await options.onSubmit(route)
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'Submitted', data: { ...draftRequest, current_status: 'SUBMITTED' } }),
      })
      return
    }

    await route.continue()
  })
}

async function waitForNuxtHydration(page: Page) {
  await page.waitForSelector('#__nuxt')
  await page.waitForFunction(() => (document.querySelector('#__nuxt')?.children.length ?? 0) > 0)
}

async function openWizardForRole(page: Page) {
  await page.addInitScript(() => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
  })

  await page.goto('/dashboard')
  await waitForNuxtHydration(page)
  await page.waitForURL('**/dashboard', { timeout: 12000 })
  await page.evaluate(() => {
    const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
    nuxtApp?.$router.push('/requests/new')
  })
  await page.waitForURL('**/requests/new', { timeout: 12000 })
  await page.waitForSelector('.new-request-page', { timeout: 12000 })
  await page.waitForSelector('.wizard-stepper', { timeout: 12000 })
}

async function fillStep1(page: Page, role: UserRole) {
  await page.locator('#goods-type').selectOption({ index: 1 })

  if (role === UserRole.BANK_ADMIN) {
    await page.locator('#merchant').selectOption(String(BANK_ADMIN_MERCHANTS[0]!.id))
  }
  else {
    await expect(page.locator('.merchant-readonly')).toContainText(DATA_ENTRY_MERCHANTS[0]!.name)
  }

  await page.locator('#amount').fill('50000')
  await page.locator('#currency').selectOption('USD')
  await page.locator('#payment-terms').selectOption('LC')
  await page.locator('#due-date').fill('2026-12-31')
  await page.locator('#notes').fill('شحنة غذائية موسمية')
}

async function fillStep2(page: Page) {
  await page.locator('#supplier-name').fill('Cargill Trading Inc.')
  await page.locator('#origin-country').selectOption('الولايات المتحدة')
  await page.locator('#invoice-number').fill('INV-2026-0001')
  await page.locator('#invoice-date').fill('2026-05-10')
  await page.locator('#arrival-port').selectOption('ميناء عدن')
  await page.locator('#shipping-port').fill('Port of Houston, USA')
  await page.locator('#bl-number').fill('BL-2026-0001')
}

async function attachRequiredDocuments(page: Page) {
  const pdfPayload = {
    name: 'required-document.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4 wizard fixture'),
  }

  const fileInputs = page.locator('input[type="file"]')
  await expect(fileInputs).toHaveCount(4)

  await fileInputs.first().setInputFiles(pdfPayload)
  await fileInputs.first().setInputFiles({ ...pdfPayload, name: 'commercial-register.pdf' })
  await fileInputs.first().setInputFiles({ ...pdfPayload, name: 'tax-card.pdf' })
}

async function goToStep2(page: Page, role: UserRole) {
  await fillStep1(page, role)
  await page.locator('.btn-next').click()
  await expect(page.locator('#supplier-name')).toBeVisible()
}

async function goToStep3(page: Page, role: UserRole) {
  await goToStep2(page, role)
  await fillStep2(page)
  await page.locator('.btn-next').click()
  await expect(page.locator('.upload-grid')).toBeVisible()
}

async function goToStep4(page: Page, role: UserRole) {
  await goToStep3(page, role)
  await attachRequiredDocuments(page)
  await page.locator('.btn-next').click()
  await expect(page.locator('.summary-card')).toBeVisible()
}

const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}

for (const [label, viewport] of Object.entries(VIEWPORTS)) {
  test(`7.5 BANK_ADMIN step 1 ${label} parity`, async ({ page }) => {
    await mockWizardApi(page, { role: UserRole.BANK_ADMIN, merchants: BANK_ADMIN_MERCHANTS })
    await page.setViewportSize(viewport)
    await openWizardForRole(page)
    await expect(page.locator('.page-title')).toContainText('تقديم طلب تمويل واردات جديد')
    await expect(page).toHaveScreenshot(['7-5', `bank-admin-new-request-step1-${label}.png`], {
      animations: 'disabled',
      fullPage: false,
      maxDiffPixelRatio: 0.02,
    })
  })

  test(`7.5 BANK_ADMIN step 2 ${label} parity`, async ({ page }) => {
    await mockWizardApi(page, { role: UserRole.BANK_ADMIN, merchants: BANK_ADMIN_MERCHANTS })
    await page.setViewportSize(viewport)
    await openWizardForRole(page)
    await goToStep2(page, UserRole.BANK_ADMIN)
    await expect(page.locator('#supplier-name')).toBeVisible()
    await expect(page).toHaveScreenshot(['7-5', `bank-admin-new-request-step2-${label}.png`], {
      animations: 'disabled',
      fullPage: false,
      maxDiffPixelRatio: 0.02,
    })
  })

  test(`7.5 BANK_ADMIN step 3 ${label} parity`, async ({ page }) => {
    await mockWizardApi(page, { role: UserRole.BANK_ADMIN, merchants: BANK_ADMIN_MERCHANTS })
    await page.setViewportSize(viewport)
    await openWizardForRole(page)
    await goToStep3(page, UserRole.BANK_ADMIN)
    await expect(page.locator('.upload-grid')).toBeVisible()
    await expect(page).toHaveScreenshot(['7-5', `bank-admin-new-request-step3-${label}.png`], {
      animations: 'disabled',
      fullPage: false,
      maxDiffPixelRatio: 0.02,
    })
  })

  test(`7.5 BANK_ADMIN step 4 ${label} parity`, async ({ page }) => {
    await mockWizardApi(page, { role: UserRole.BANK_ADMIN, merchants: BANK_ADMIN_MERCHANTS })
    await page.setViewportSize(viewport)
    await openWizardForRole(page)
    await goToStep4(page, UserRole.BANK_ADMIN)
    await expect(page.locator('.ack-container')).toBeVisible()
    await expect(page).toHaveScreenshot(['7-5', `bank-admin-new-request-step4-${label}.png`], {
      animations: 'disabled',
      fullPage: false,
      maxDiffPixelRatio: 0.02,
    })
  })
}

test('7.5 DATA_ENTRY step 1 shows read-only merchant state', async ({ page }) => {
  await mockWizardApi(page, { role: UserRole.DATA_ENTRY, merchants: DATA_ENTRY_MERCHANTS })
  await page.setViewportSize(VIEWPORTS.desktop)
  await openWizardForRole(page)
  await expect(page.locator('.merchant-readonly')).toContainText(DATA_ENTRY_MERCHANTS[0]!.name)
  await expect(page).toHaveScreenshot(['7-5', 'data-entry-new-request-step1-readonly-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.5 mobile layout avoids horizontal overflow on step 1', async ({ page }) => {
  await mockWizardApi(page, { role: UserRole.BANK_ADMIN, merchants: BANK_ADMIN_MERCHANTS })
  await page.setViewportSize(VIEWPORTS.mobile)
  await openWizardForRole(page)

  const hasHorizontalOverflow = await page.evaluate(() =>
    document.documentElement.scrollWidth > window.innerWidth,
  )

  expect(hasHorizontalOverflow).toBe(false)
})

test('7.5 failed required uploads keep the request out of workflow submit', async ({ page }) => {
  let uploadAttempts = 0
  let submitAttempts = 0

  await mockWizardApi(page, {
    role: UserRole.BANK_ADMIN,
    merchants: BANK_ADMIN_MERCHANTS,
    onUpload: async (route) => {
      uploadAttempts++
      if (uploadAttempts === 1) {
        await route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, message: 'Upload failed' }),
        })
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'Uploaded', data: { id: uploadAttempts } }),
      })
    },
    onSubmit: async (route) => {
      submitAttempts++
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'Submitted' }),
      })
    },
  })

  await page.setViewportSize(VIEWPORTS.desktop)
  await openWizardForRole(page)
  await goToStep4(page, UserRole.BANK_ADMIN)

  await page.locator('.ack-checkbox').check()
  await page.locator('.btn-submit').click()

  await expect(page.locator('.section-title')).toContainText('رفع الوثائق المطلوبة')
  await expect(page.locator('.zone-file-error').first()).toBeVisible()
  await expect(page.locator('.toast--error')).toContainText('تعذّر رفع بعض الوثائق')
  expect(uploadAttempts).toBeGreaterThan(0)
  expect(submitAttempts).toBe(0)
})
