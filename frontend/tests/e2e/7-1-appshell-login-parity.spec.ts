import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

const AUTH_USER = {
  id: 9001,
  name: 'Majed Alnasr',
  email: 'majed@cby.ye',
  role: UserRole.CBY_ADMIN,
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  is_active: true,
}

const NOTIFICATIONS = [
  {
    id: 'notif-1',
    type: 'request_submitted',
    data: {
      type: 'request_submitted',
      message: 'تم تقديم طلب جديد للمراجعة',
      request_id: 101,
      reference_number: 'YFH-2026-000101',
    },
    read_at: null,
    created_at: '2026-05-19T08:00:00.000Z',
  },
  {
    id: 'notif-2',
    type: 'swift_upload_requested',
    data: {
      type: 'swift_upload_requested',
      message: 'تم طلب رفع مستند SWIFT',
      request_id: 102,
      reference_number: 'YFH-2026-000102',
    },
    read_at: '2026-05-19T07:30:00.000Z',
    created_at: '2026-05-19T07:00:00.000Z',
  },
]

const CBY_ADMIN_STATS = {
  total: 124,
  approved: 51,
  in_process: 38,
  rejected: 11,
  compliance_alerts: {
    duplicate_suppliers: [
      { supplier_name: 'شركة البحر الأحمر', count: 2 },
    ],
    high_amount_requests: [
      {
        id: 77,
        reference_number: 'YFH-2026-000077',
        amount: 1450000,
        currency: 'USD',
        bank_name: 'بنك اليمن الدولي',
      },
    ],
    stale_pending_requests: [
      {
        id: 78,
        reference_number: 'YFH-2026-000078',
        bank_name: 'بنك عدن',
        updated_at: '2026-05-01T10:00:00.000Z',
      },
    ],
  },
  most_active_banks: [
    { bank_id: 1, bank_name: 'بنك اليمن الدولي', request_count: 34 },
    { bank_id: 2, bank_name: 'بنك عدن', request_count: 19 },
  ],
}

async function mockApi(page: Page) {
  await page.route('**/*', async (route) => {
    const url = new URL(route.request().url())

    if (url.pathname === '/sanctum/csrf-cookie') {
      await route.fulfill({ status: 204, body: '' })
      return
    }

    if (url.pathname === '/api/auth/login') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OTP sent',
          data: {
            requires_mfa: true,
            email: AUTH_USER.email,
            challenge_id: 'challenge-7-1',
            token: null,
            token_type: null,
            mode: 'cookie',
          },
        }),
      })
      return
    }

    if (url.pathname === '/api/auth/verify-otp') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Authenticated',
          data: {
            user: AUTH_USER,
            token: null,
            token_type: null,
            mode: 'cookie',
            requires_mfa: false,
          },
        }),
      })
      return
    }

    if (url.pathname === '/api/auth/me') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: AUTH_USER,
        }),
      })
      return
    }

    if (url.pathname === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: CBY_ADMIN_STATS,
        }),
      })
      return
    }

    if (url.pathname === '/api/notifications/unread-count') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: { count: 1 },
        }),
      })
      return
    }

    if (url.pathname === '/api/notifications' && url.searchParams.get('page') === '1') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            data: NOTIFICATIONS,
            links: {
              first: '/api/notifications?page=1',
              last: '/api/notifications?page=1',
              prev: null,
              next: null,
            },
            meta: {
              current_page: 1,
              from: 1,
              last_page: 1,
              path: '/api/notifications',
              per_page: 20,
              to: 2,
              total: 2,
            },
          },
        }),
      })
      return
    }

    if (url.pathname === '/api/notifications/read-all') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: null,
        }),
      })
      return
    }

    await route.continue()
  })
}

async function waitForNuxtHydration(page: Page) {
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt') as Record<string, unknown> | null
    return Boolean(root && '__vue_app__' in root)
  })
}

async function openAuthenticatedDashboard(page: Page, collapsed = false) {
  await page.addInitScript(({ collapsedState }) => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', collapsedState ? 'true' : 'false')
  }, { collapsedState: collapsed })

  await page.goto('/dashboard')
  await page.waitForURL('**/dashboard')
  await expect(page.getByRole('heading', { name: 'لوحة إدارة النظام' })).toBeVisible()
  await expect(page.getByText('منصة الواردات')).toBeVisible()
}

test.beforeEach(async ({ page }) => {
  await mockApi(page)
})

test('7.1 login screenshot desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/login')
  await waitForNuxtHydration(page)
  await expect(page.getByRole('heading', { name: 'تسجيل الدخول' })).toBeVisible()
  await expect(page).toHaveScreenshot(['7-1', 'login-desktop.png'], {
    animations: 'disabled',
  })
})

test('7.1 otp screenshot desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/login')
  await waitForNuxtHydration(page)
  await page.getByLabel('البريد الإلكتروني').fill(AUTH_USER.email)
  await page.getByLabel('كلمة المرور').fill('password123')
  await page.getByRole('button', { name: 'متابعة' }).click()

  await expect(page.getByRole('heading', { name: 'رمز التحقق (OTP)' })).toBeVisible()
  await expect(page).toHaveScreenshot(['7-1', 'login-otp-desktop.png'], {
    animations: 'disabled',
  })
})

test('7.1 dashboard screenshots desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await openAuthenticatedDashboard(page)

  await expect(page).toHaveScreenshot(['7-1', 'dashboard-expanded-desktop.png'], {
    animations: 'disabled',
  })

  await page.getByRole('button', { name: '‹ طي الشريط الجانبي' }).click()
  await expect(page.getByRole('button', { name: 'توسيع ›' })).toBeVisible()
  await expect(page).toHaveScreenshot(['7-1', 'dashboard-collapsed-desktop.png'], {
    animations: 'disabled',
  })
})

test('7.1 login screenshot mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/login')
  await waitForNuxtHydration(page)
  await expect(page.getByRole('heading', { name: 'تسجيل الدخول' })).toBeVisible()
  await expect(page).toHaveScreenshot(['7-1', 'login-mobile.png'], {
    animations: 'disabled',
  })
})
