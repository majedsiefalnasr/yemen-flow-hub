import { expect, test } from '@playwright/test'

const genericMessage = 'إذا كان البريد موجوداً، فسيتم إرسال رمز الاستعادة.'

function apiResponse<T>(data: T, message = 'OK') {
  return { success: true, message, data }
}

const temporaryUser = {
  id: 9001,
  name: 'مدير بنك مؤقت',
  email: 'bank-admin@example.gov.ye',
  role: 'BANK_ADMIN',
  bank_id: 11,
  bank_name_ar: 'البنك التجاري اليمني',
  bank_name_en: 'Yemen Commercial Bank',
  is_active: true,
  must_change_password: true,
}

const cbyAdmin = {
  ...temporaryUser,
  id: 8001,
  name: 'مدير البنك المركزي',
  email: 'admin@cby.gov.ye',
  role: 'CBY_ADMIN',
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  must_change_password: false,
}

const cbyStaff = {
  ...temporaryUser,
  id: 8002,
  name: 'موظف الدعم المركزي',
  email: 'support@cby.gov.ye',
  role: 'SUPPORT_COMMITTEE',
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  must_change_password: false,
}

const bankAdmin = {
  ...temporaryUser,
  id: 7001,
  name: 'مدير البنك التجاري',
  email: 'admin@bank.gov.ye',
  role: 'BANK_ADMIN',
  must_change_password: false,
}

const bankStaff = {
  ...temporaryUser,
  id: 7002,
  name: 'موظف إدخال البنك',
  email: 'entry@bank.gov.ye',
  role: 'DATA_ENTRY',
  must_change_password: false,
}

type MockApiOptions = {
  currentUser?: typeof temporaryUser | typeof cbyAdmin | null
  users?: (typeof temporaryUser | typeof cbyAdmin)[]
}

async function mockApi(page: Parameters<typeof test>[0]['page'], options: MockApiOptions = {}) {
  let authenticatedUser = options.currentUser ?? null
  const users = options.users ?? []

  if (authenticatedUser) {
    await page.addInitScript(() => localStorage.setItem('yfh-authenticated', '1'))
  }

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

    if (path === '/api/auth/password/forgot') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(
          apiResponse({}, 'If this email exists, a recovery code has been sent.'),
        ),
      })
      return
    }

    if (path === '/api/auth/password/verify') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse({ valid: true })),
      })
      return
    }

    if (path === '/api/auth/password/reset') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse({})),
      })
      return
    }

    if (path === '/api/auth/login') {
      authenticatedUser = temporaryUser
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(
          apiResponse({
            user: temporaryUser,
            token: 'temporary-token',
            token_type: 'Bearer',
            mode: 'token',
            requires_mfa: false,
          }),
        ),
      })
      return
    }

    if (path === '/api/auth/me') {
      if (!authenticatedUser) {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, message: 'Unauthenticated.' }),
        })
        return
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse(authenticatedUser)),
      })
      return
    }

    if (path === '/api/profile/change-temporary-password') {
      authenticatedUser = {
        ...temporaryUser,
        must_change_password: false,
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(
          apiResponse({
            ...temporaryUser,
            must_change_password: false,
          }),
        ),
      })
      return
    }

    if (path === '/api/users' && route.request().method() === 'GET') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse(users)),
      })
      return
    }

    const resetMatch = path.match(/^\/api\/users\/(\d+)\/reset-(password|mfa|pin)$/)
    if (resetMatch) {
      const updated = users.find((user) => user.id === Number(resetMatch[1])) ?? users[0]
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse(updated)),
      })
      return
    }

    if (path === '/api/banks') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(apiResponse([])),
      })
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(apiResponse({})),
    })
  })
}

test.describe('account recovery', () => {
  test('shows generic forgot-password flow and reaches new password screen', async ({ page }) => {
    await mockApi(page)
    await page.goto('/reset-password')

    await page.getByLabel('البريد الإلكتروني المؤسسي').fill('unknown@example.gov.ye')
    await page.getByRole('button', { name: 'إرسال رمز الاستعادة' }).click()
    await expect(page.getByText(genericMessage)).toBeVisible()

    await page.getByLabel('رمز الاستعادة').fill('123456')
    await page.getByRole('button', { name: 'تحقق من الرمز' }).click()
    await expect(page.getByLabel('كلمة المرور الجديدة')).toBeVisible()

    await page.getByLabel('كلمة المرور الجديدة').fill('NewPass123')
    await page.getByLabel('إعادة إدخال كلمة المرور').fill('NewPass123')
    await page.getByRole('button', { name: 'تحديث كلمة المرور' }).click()
    await expect(page).toHaveURL(/\/login/)
  })

  test('forces temporary password change after admin reset login', async ({ page }) => {
    await mockApi(page)
    await page.goto('/login')

    await page.getByLabel('البريد الإلكتروني المؤسسي').fill(temporaryUser.email)
    await page.getByRole('button', { name: 'متابعة' }).click()
    await page.getByRole('textbox', { name: 'كلمة المرور', exact: true }).fill('TempPass123')
    await page.getByRole('button', { name: 'تسجيل الدخول' }).click()

    await expect(page).toHaveURL(/\/change-temporary-password/)
    await page.getByLabel('كلمة المرور الجديدة').fill('NewPass123')
    await page.getByLabel('إعادة إدخال كلمة المرور').fill('NewPass123')
    await page.getByRole('button', { name: 'حفظ كلمة المرور الجديدة' }).click()
    await expect(page).toHaveURL(/\/dashboard/)
  })

  test('CBY Admin can run separate recovery actions for CBY staff', async ({ page }) => {
    await mockApi(page, { currentUser: cbyAdmin, users: [cbyStaff] })
    await page.goto('/admin/cby-staff')

    await expect(page.getByText(cbyStaff.name)).toBeVisible()
    await page.getByRole('button', { name: 'فتح القائمة' }).click()
    await page.getByRole('menuitem', { name: 'استعادة الوصول للحساب' }).click()

    await expect(page.getByRole('dialog', { name: 'استعادة الوصول للحساب' })).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(1)
    await page.getByRole('button', { name: 'إعادة ضبط' }).first().click()
    await expect(page.getByRole('dialog', { name: 'إعادة ضبط تطبيق المصادقة' })).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(1)
    await page.getByRole('button', { name: 'تأكيد إعادة الضبط' }).click()

    await expect(page.getByText('تمت إعادة ضبط تطبيق المصادقة بنجاح.')).toBeVisible()
    await expect(page.getByRole('dialog', { name: 'استعادة الوصول للحساب' })).toBeVisible()
  })

  test('Bank Admin can reset only the own-bank staff returned by the scoped API', async ({
    page,
  }) => {
    await mockApi(page, { currentUser: bankAdmin, users: [bankStaff] })
    await page.goto('/staff')

    await expect(page.getByText(bankStaff.name)).toBeVisible()
    await page.getByRole('button', { name: 'فتح القائمة' }).click()
    await page.getByRole('menuitem', { name: 'استعادة الوصول للحساب' }).click()
    await page.getByRole('button', { name: 'إعادة ضبط' }).nth(1).click()
    await expect(page.getByRole('dialog', { name: 'إعادة ضبط رمز PIN' })).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(1)
    await page.getByRole('button', { name: 'تأكيد إعادة الضبط' }).click()

    await expect(page.getByText('تمت إعادة ضبط رمز PIN بنجاح.')).toBeVisible()
  })
})
