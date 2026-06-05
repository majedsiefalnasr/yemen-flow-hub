import { expect, test, type APIRequestContext } from '@playwright/test'
import { execFileSync } from 'node:child_process'

const backendUrl = process.env['ACCOUNT_RECOVERY_BACKEND_URL'] ?? 'http://localhost:8000'
const mailpitUrl = process.env['MAILPIT_API_URL'] ?? 'http://localhost:8025'
const recoveryEmail =
  process.env['ACCOUNT_RECOVERY_TEST_EMAIL'] ?? `account-recovery-e2e-${Date.now()}@example.gov.ye`
const newPassword = 'MailpitRecovery123'
const genericMessage = 'إذا كان البريد موجوداً، فسيتم إرسال رمز الاستعادة.'

type MailpitMessage = {
  ID: string
  Created: string
  To: { Address: string }[]
  Subject: string
  Snippet: string
}

type MailpitMessagesResponse = {
  messages: MailpitMessage[]
}

function runBackendTinker(code: string) {
  execFileSync('docker', ['exec', 'yfh-backend', 'php', 'artisan', 'tinker', `--execute=${code}`])
}

async function latestOtpFor(
  request: APIRequestContext,
  email: string,
  requestedAfter: number,
): Promise<string> {
  await expect
    .poll(
      async () => {
        const response = await request.get(`${mailpitUrl}/api/v1/messages?limit=50`)
        const payload = (await response.json()) as MailpitMessagesResponse
        return payload.messages.find(
          (message) =>
            Date.parse(message.Created) >= requestedAfter &&
            message.Subject === 'Yemen Flow Hub password recovery code' &&
            message.To.some((recipient) => recipient.Address === email),
        )?.Snippet
      },
      { timeout: 10_000 },
    )
    .toMatch(/رمز الاستعادة:\s*\d{6}/)

  const response = await request.get(`${mailpitUrl}/api/v1/messages?limit=50`)
  const payload = (await response.json()) as MailpitMessagesResponse
  const message = payload.messages.find(
    (item) =>
      Date.parse(item.Created) >= requestedAfter &&
      item.Subject === 'Yemen Flow Hub password recovery code' &&
      item.To.some((recipient) => recipient.Address === email),
  )
  const otp = message?.Snippet.match(/رمز الاستعادة:\s*(\d{6})/)?.[1]
  if (!otp) throw new Error(`No Mailpit recovery OTP found for ${email}`)
  return otp
}

test.describe('Mailpit account recovery integration', () => {
  test.skip(
    process.env['RUN_MAILPIT_E2E'] !== '1',
    'Set RUN_MAILPIT_E2E=1 when local backend and Mailpit are running.',
  )
  test.describe.configure({ mode: 'serial' })

  test.beforeAll(() => {
    runBackendTinker(
      `App\\Models\\User::query()->where('email', '${recoveryEmail}')->delete(); ` +
        `App\\Models\\User::query()->create(['name' => 'Mailpit Recovery E2E', ` +
        `'email' => '${recoveryEmail}', 'password' => 'InitialRecovery123', ` +
        `'role' => App\\Enums\\UserRole::CBY_ADMIN, 'bank_id' => null, ` +
        `'is_active' => true, 'mfa_enabled' => false, 'pin_enabled' => false]);`,
    )
  })

  test.afterAll(() => {
    runBackendTinker(`App\\Models\\User::query()->where('email', '${recoveryEmail}')->delete();`)
  })

  test('uses a real emailed OTP once and redirects to login after resetting the password', async ({
    page,
    request,
  }) => {
    const requestedAfter = Date.now() - 30_000
    await page.goto('/reset-password')
    await page.getByLabel('البريد الإلكتروني المؤسسي').fill(recoveryEmail)
    const recoveryResponsePromise = page.waitForResponse((response) => {
      const url = new URL(response.url())
      return url.pathname === '/api/auth/password/forgot' && response.request().method() === 'POST'
    })
    await page.getByRole('button', { name: 'إرسال رمز الاستعادة' }).click()
    const recoveryResponse = await recoveryResponsePromise
    expect(
      recoveryResponse.status(),
      `${recoveryResponse.url()}: ${await recoveryResponse.text()}`,
    ).toBe(200)
    await expect(page.getByText(genericMessage)).toBeVisible()

    const otp = await latestOtpFor(request, recoveryEmail, requestedAfter)
    await page.getByLabel('رمز الاستعادة').fill(otp)
    await page.getByRole('button', { name: 'تحقق من الرمز' }).click()
    await page.getByLabel('كلمة المرور الجديدة').fill(newPassword)
    await page.getByLabel('إعادة إدخال كلمة المرور').fill(newPassword)
    await page.getByRole('button', { name: 'تحديث كلمة المرور' }).click()

    await expect(page).toHaveURL(/\/login/)

    const reuseResponse = await request.post(`${backendUrl}/api/auth/password/reset`, {
      data: {
        email: recoveryEmail,
        otp,
        password: 'ShouldNotApply123',
        password_confirmation: 'ShouldNotApply123',
      },
      headers: { Accept: 'application/json' },
    })
    expect(reuseResponse.status()).toBe(422)
  })
})
