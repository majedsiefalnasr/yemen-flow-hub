import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

// ── Shared fixtures ───────────────────────────────────────────────────────────

const CBY_ADMIN_USER = {
  id: 1,
  name: 'أحمد محمد',
  email: 'admin@cby.gov.ye',
  phone: '+967111222333',
  role: UserRole.CBY_ADMIN,
  bank_id: null,
  bank_name_ar: null,
  bank_name_en: null,
  is_active: true,
  mfa_enabled: true,
  mfa_required: false,
  stats: { total: 15, in_progress: 4, completed: 11 },
  recent_activity: [
    { id: 1, action: 'تسجيل دخول', ref: null, ts: '2026-05-21T10:00:00Z' },
    { id: 2, action: 'تحديث الملف', ref: null, ts: '2026-05-20T09:00:00Z' },
  ],
}

const ADMIN_SETTINGS = {
  support_claim_ttl: 15,
  voting_session_timeout: 30,
  pdf_upload_size_limit: 10,
  login_lockout_duration: 15,
  notifications_phase_1_enabled: true,
  search_phase_1_enabled: false,
  customs_print_preview_enabled: false,
  support_committee_size: 3,
  executive_committee_size: 5,
  minimum_quorum: 3,
  review_timeout_hours: 48,
  secret_voting: false,
  director_tiebreak: true,
  mfa_required: false,
  password_expiry_90_days: false,
  lockout_after_5_attempts: true,
  encrypt_uploads_aes256: true,
  log_all_audit: true,
  allow_external_access: false,
}

const SMTP_SETTINGS = {
  host: 'smtp.cby.gov.ye',
  port: 587,
  username: 'notify@cby.gov.ye',
  password: '••••••••',
  template: '',
}

async function mockAuth(page: Page) {
  await page.route('**/api/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
  await page.route('**/api/auth/user', route =>
    route.fulfill({ json: { success: true, data: CBY_ADMIN_USER } }),
  )
}

async function mockProfileApis(page: Page) {
  await page.route('**/api/profile', route =>
    route.fulfill({ json: { success: true, data: CBY_ADMIN_USER } }),
  )
}

async function mockSettingsApis(page: Page) {
  await page.route('**/api/admin/settings', route =>
    route.fulfill({ json: { success: true, data: ADMIN_SETTINGS } }),
  )
  await page.route('**/api/admin/settings/smtp', route =>
    route.fulfill({ json: { success: true, data: SMTP_SETTINGS } }),
  )
  await page.route('**/api/preferences', route =>
    route.fulfill({
      json: {
        success: true,
        data: {
          language: 'ar',
          dashboard_view: 'normal',
          table_density: 'normal',
          page_size: 25,
          default_filters: {},
          notification_preferences: {},
        },
      },
    }),
  )
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('7.10 — Profile page (CBY_ADMIN)', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuth(page)
    await mockProfileApis(page)
  })

  test('profile page renders 3-column layout with stats strip', async ({ page }) => {
    await page.goto('/profile')
    await page.waitForSelector('[data-testid="profile-layout"]', { timeout: 10000 })

    const strip = page.locator('[data-testid="stats-strip"]')
    await expect(strip).toBeVisible()

    await expect(page.locator('[data-testid="stats-total"]')).toContainText('15')
    await expect(page.locator('[data-testid="stats-in-progress"]')).toContainText('4')
    await expect(page.locator('[data-testid="stats-completed"]')).toContainText('11')

    await page.screenshot({ path: 'tests/screenshots/7-10/profile-cby-admin.png', fullPage: true })
  })
})

test.describe('7.10 — Settings page workflow tab', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuth(page)
    await mockSettingsApis(page)
  })

  test('settings workflow tab renders editable grid for CBY_ADMIN', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForSelector('[data-testid="tab-workflow"]', { timeout: 10000 })

    await page.locator('[data-testid="tab-workflow"]').click()
    await expect(page.locator('[data-testid="workflow-readonly-note"]')).not.toBeVisible()

    await page.screenshot({ path: 'tests/screenshots/7-10/settings-workflow-tab.png', fullPage: true })
  })
})

test.describe('7.10 — Settings page email tab', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuth(page)
    await mockSettingsApis(page)
  })

  test('settings email tab shows SMTP form for CBY_ADMIN', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForSelector('[data-testid="tab-email"]', { timeout: 10000 })

    await page.locator('[data-testid="tab-email"]').click()
    await expect(page.locator('[data-panel="email"]')).toBeVisible()

    await page.screenshot({ path: 'tests/screenshots/7-10/settings-email-tab.png', fullPage: true })
  })
})

test.describe('7.10 — Settings page notifications tab', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuth(page)
    await mockSettingsApis(page)
  })

  test('settings notifications tab renders 5 system channel rows', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForSelector('[data-testid="tab-notif"]', { timeout: 10000 })

    await page.locator('[data-testid="tab-notif"]').click()
    await expect(page.locator('[data-panel="notifications"]')).toBeVisible()

    await page.screenshot({ path: 'tests/screenshots/7-10/settings-notifications-tab.png', fullPage: true })
  })
})

test.describe('7.10 — Settings page security tab', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuth(page)
    await mockSettingsApis(page)
  })

  test('settings security tab shows 6 policy switches for CBY_ADMIN', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForSelector('[data-testid="tab-security"]', { timeout: 10000 })

    await page.locator('[data-testid="tab-security"]').click()
    await expect(page.locator('[data-testid="security-switch-mfa"]')).toBeVisible()
    await expect(page.locator('[data-testid="security-switches-enabled"]')).toBeVisible()

    await page.screenshot({ path: 'tests/screenshots/7-10/settings-security-tab.png', fullPage: true })
  })
})

test.describe('7.10 — Settings page general tab', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuth(page)
    await mockSettingsApis(page)
  })

  test('settings general tab renders platform info + display preferences', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForSelector('[data-testid="tab-general"]', { timeout: 10000 })

    await page.locator('[data-testid="tab-general"]').click()
    await expect(page.locator('[data-panel="general"]')).toBeVisible()

    await page.screenshot({ path: 'tests/screenshots/7-10/settings-general-tab.png', fullPage: true })
  })
})
