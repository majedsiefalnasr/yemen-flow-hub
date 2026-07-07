import { expect, test } from '@playwright/test'

function apiResponse<T>(data: T, message = 'OK') {
  return { success: true, message, data }
}

const cbyAdmin = {
  id: 8001,
  name: 'مدير البنك المركزي',
  email: 'admin@cby.gov.ye',
  role: 'CBY_ADMIN',
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  is_active: true,
}

const bankReviewer = {
  ...cbyAdmin,
  id: 7001,
  name: 'مراجع البنك',
  email: 'reviewer@bank.gov.ye',
  role: 'BANK_REVIEWER',
  bank_id: 11,
}

const template = {
  type: 'REQUEST_APPROVED',
  admin_editable: true,
  is_active: true,
  allowed_variables: ['reference_number', 'bank_name', 'amount', 'currency', 'status', 'user_name'],
  active: {
    id: 1,
    subject: 'موافقة {{reference_number}}',
    body: 'مرحبا {{user_name}}',
    changed_by: null,
    changed_by_name: null,
    changed_at: '2026-06-06T12:00:00.000Z',
  },
  versions: [
    {
      id: 1,
      changed_by: null,
      changed_by_name: null,
      changed_at: '2026-06-06T12:00:00.000Z',
      is_active_version: true,
    },
  ],
}

async function mockApi(page: Parameters<typeof test>[0]['page'], user = cbyAdmin) {
  await page.addInitScript(() => localStorage.setItem('yfh-authenticated', '1'))

  await page.route(
    /^https?:\/\/(?:localhost|127\.0\.0\.1):8000\/sanctum\/csrf-cookie$/,
    async (route) => {
      await route.fulfill({
        status: 204,
        headers: {
          'access-control-allow-origin': 'http://localhost:3000',
          'access-control-allow-credentials': 'true',
          'set-cookie': 'XSRF-TOKEN=test-xsrf; Path=/',
        },
        body: '',
      })
    },
  )

  await page.route(/^https?:\/\/(?:localhost|127\.0\.0\.1):8000\/api\//, async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname

    if (path === '/api/auth/me') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse(user)),
      })
      return
    }

    if (path === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse({})),
      })
      return
    }

    if (path === '/api/v1/notifications/unread-count') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse({ count: 0 })),
      })
      return
    }

    if (path === '/api/v1/notifications') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse({ data: [], meta: { total: 0 } })),
      })
      return
    }

    if (path === '/api/admin/notification-templates' && route.request().method() === 'GET') {
      if (user.role !== 'CBY_ADMIN') {
        await route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, message: 'Forbidden' }),
        })
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse([template])),
      })
      return
    }

    if (path === '/api/admin/notification-templates/REQUEST_APPROVED') {
      if (user.role !== 'CBY_ADMIN') {
        await route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, message: 'Forbidden' }),
        })
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse(template)),
      })
      return
    }

    if (path === '/api/admin/notification-templates/REQUEST_APPROVED/preview') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(
          apiResponse({
            source: { subject: 'موافقة {{reference_number}}', body: 'مرحبا {{reference_number}}' },
            rendered: {
              subject: 'موافقة YFH-2026-000123',
              html: '<html dir="rtl"><body>YFH-2026-000123 Yemen International Bank</body></html>',
              text: 'YFH-2026-000123 Yemen International Bank',
              source: 'preview',
              template_version_id: null,
              locale: 'ar',
            },
          }),
        ),
      })
      return
    }

    await route.fulfill({
      status: 404,
      contentType: 'application/json',
      body: JSON.stringify({ success: false, message: `Unhandled ${path}` }),
    })
  })
}

test('CBY Admin manages email templates with preview and save path', async ({ page }) => {
  await mockApi(page)

  await page.goto('/admin/email-templates')
  await expect(page.getByText('إشعار موافقة الطلب')).toBeVisible()

  await page.getByRole('button', { name: /تحرير/ }).click()
  await expect(page).toHaveURL(/\/admin\/email-templates\/REQUEST_APPROVED/)
  await expect(page.getByText('سجل الإصدارات')).toBeVisible()

  const body = page.getByPlaceholder('أدخل نص القالب بصيغة Markdown')
  await body.click()
  await body.fill('مرحبا ')
  await page.getByRole('button', { name: '{{reference_number}}' }).click()
  await expect(body).toHaveValue(/{{reference_number}}/)

  await page.getByRole('button', { name: /معاينة/ }).click()
  await page.getByRole('tab', { name: 'النص البديل' }).click()
  await expect(page.getByText('YFH-2026-000123')).toBeVisible()

  const saveRequest = page.waitForRequest(
    (request) =>
      request.method() === 'PUT' &&
      request.url().includes('/api/admin/notification-templates/REQUEST_APPROVED'),
  )
  await page.getByRole('button', { name: /حفظ إصدار جديد/ }).click()
  await saveRequest
})

test('non-CBY Admin is blocked from the template page and API', async ({ page }) => {
  await mockApi(page, bankReviewer)
  await page.goto('/admin/email-templates')
  await expect(page).toHaveURL(/\/forbidden/)

  const status = await page.evaluate(async () => {
    const response = await fetch('http://localhost:8000/api/admin/notification-templates', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    return response.status
  })
  expect(status).toBe(403)
})
