import * as fs from 'node:fs'
import * as path from 'node:path'
import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

// ── Shared fixtures ────────────────────────────────────────────────────────────

const BANKS = [
  { id: 1, name_ar: 'بنك اليمن الدولي', name_en: 'Yemen International Bank', code: 'BNK-001', license_number: 'LIC-2026-001', is_active: true },
  { id: 2, name_ar: 'بنك التضامن الإسلامي', name_en: 'Al Tadhamun Islamic Bank', code: 'BNK-002', license_number: 'LIC-2026-002', is_active: true },
  { id: 3, name_ar: 'بنك سبأ الإسلامي', name_en: 'Saba Islamic Bank', code: 'BNK-003', license_number: null, is_active: false },
]

const CBY_USERS = [
  { id: 20, name: 'أحمد ناصر القحطاني', email: 'support@cby.gov.ye', role: UserRole.SUPPORT_COMMITTEE, bank_id: null, is_active: true, last_login_at: '2026-05-20T08:00:00.000Z', created_at: '2026-01-01T00:00:00.000Z' },
  { id: 21, name: 'منى عبدالله الشامي', email: 'exec@cby.gov.ye', role: UserRole.EXECUTIVE_MEMBER, bank_id: null, is_active: true, last_login_at: '2026-05-19T15:00:00.000Z', created_at: '2026-01-10T00:00:00.000Z' },
  { id: 22, name: 'عبدالرحمن محمد الحمدي', email: 'director@cby.gov.ye', role: UserRole.COMMITTEE_DIRECTOR, bank_id: null, is_active: false, last_login_at: null, created_at: '2026-02-01T00:00:00.000Z' },
]

const NOTIFICATIONS_DATA = [
  { id: 'a1', type: 'App\\Notifications\\RequestSubmitted', notifiable_type: 'App\\Models\\User', notifiable_id: 1, data: { type: 'request_submitted', message: 'تم تقديم طلب استيراد جديد بواسطة بنك اليمن الدولي', request_id: 42 }, read_at: null, created_at: '2026-05-22T09:00:00.000Z' },
  { id: 'a2', type: 'App\\Notifications\\RequestApproved', notifiable_type: 'App\\Models\\User', notifiable_id: 1, data: { type: 'request_approved', message: 'تم اعتماد طلب استيراد IMP-2026-042', request_id: 42 }, read_at: '2026-05-21T10:00:00.000Z', created_at: '2026-05-21T08:00:00.000Z' },
  { id: 'a3', type: 'App\\Notifications\\ClaimReleased', notifiable_type: 'App\\Models\\User', notifiable_id: 1, data: { type: 'claim_released', message: 'تم الإفراج عن البضاعة — IMP-2026-040', request_id: 40 }, read_at: null, created_at: '2026-05-20T14:00:00.000Z' },
]

const NOTIFICATIONS_RESPONSE = {
  success: true,
  message: 'ok',
  data: {
    data: NOTIFICATIONS_DATA,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: 1, last_page: 1, path: '/api/notifications', per_page: 20, to: 3, total: 3 },
  },
}

const WORKFLOW_REPORT = {
  counts_by_status: { DRAFT: 10, SUBMITTED: 5, BANK_REVIEW: 8, EXECUTIVE_APPROVED: 25, COMPLETED: 30 },
  counts_by_bank: [
    { bank_id: 1, bank_name: 'بنك اليمن الدولي', total: 42 },
    { bank_id: 2, bank_name: 'بنك التضامن الإسلامي', total: 25 },
  ],
  avg_time_per_stage_hours: { SUBMITTED: 4.5, BANK_REVIEW: 12.0 },
  throughput: { completed: 30, approved: 25, rejected: 8 },
  monthly_trend: [
    { month: '2026-01', total: 12, approved: 8, rejected: 2 },
    { month: '2026-02', total: 18, approved: 12, rejected: 3 },
    { month: '2026-03', total: 22, approved: 16, rejected: 4 },
    { month: '2026-04', total: 15, approved: 10, rejected: 2 },
    { month: '2026-05', total: 19, approved: 13, rejected: 3 },
  ],
  category_distribution: [
    { category: 'إلكترونيات', count: 35 },
    { category: 'نسيج', count: 28 },
    { category: 'آلات', count: 19 },
  ],
  amount_by_currency: [
    { currency: 'USD', amount: 450000 },
    { currency: 'EUR', amount: 280000 },
  ],
  submission_heatmap: [
    { day: 1, slot: 8, count: 12 },
    { day: 2, slot: 10, count: 18 },
    { day: 3, slot: 14, count: 6 },
  ],
  total_financing_value: 850000,
  duplicate_invoice_count: 7,
}

const BANK_REPORT = {
  total_requests: 42,
  approved_count: 28,
  rejected_count: 8,
  pending_count: 6,
  approval_rate: 66.67,
  rejection_rate: 19.05,
  avg_processing_hours: 36.5,
}

const AUDIT_LOGS_RESPONSE = {
  success: true,
  data: {
    data: [
      { id: 1, user: { id: 5, name: 'أحمد علي', email: 'ahmed@cby.ye', role: 'CBY_ADMIN' }, user_id: 5, user_role: 'CBY_ADMIN', action: 'STATUS_TRANSITION', entity_type: 'ImportRequest', entity_id: 42, from_status: 'BANK_REVIEW', to_status: 'BANK_APPROVED', ip_address: '192.168.1.1', user_agent: 'Mozilla/5.0', metadata: null, created_at: '2026-05-20T10:00:00.000Z' },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
  },
}

const AUDIT_STATS_RESPONSE = { success: true, data: { today_count: 24, duplicate_invoice_count: 3 } }

const AUDIT_DUPLICATES_RESPONSE = {
  success: true,
  data: {
    data: [
      { id: 10, ref: 'IMP-2026-0010', importer: 'شركة الأمل', invoice_number: 'INV-2026-101', sibling_id: 11, sibling_ref: 'IMP-2026-0011' },
    ],
  },
}

const AUDIT_RISK_RESPONSE = {
  success: true,
  data: {
    data: [
      { title: 'نمط طلبات غير عادي', body: 'مستخدم u00432 قدّم 14 طلب في 30 دقيقة', level: 'عالية' },
      { title: 'محاولة تسجيل دخول مشبوهة', body: '5 محاولات فاشلة من IP 196.4.112.18', level: 'عالية' },
    ],
  },
}

const ADMIN_SETTINGS = {
  support_claim_ttl: 15, voting_session_timeout: 30, pdf_upload_size_limit: 10,
  login_lockout_duration: 15, notifications_phase_1_enabled: true, search_phase_1_enabled: false,
  customs_print_preview_enabled: false, support_committee_size: 3, executive_committee_size: 5,
  minimum_quorum: 3, review_timeout_hours: 48, secret_voting: false, director_tiebreak: true,
  mfa_required: false, password_expiry_90_days: false, lockout_after_5_attempts: true,
  encrypt_uploads_aes256: true, log_all_audit: true, allow_external_access: false,
}

const SMTP_SETTINGS = { host: 'smtp.cby.gov.ye', port: 587, username: 'notify@cby.gov.ye', password: '••••••••', template: '' }

const MERCHANTS = [
  { id: 1, name: 'شركة الأمل للاستيراد', trade_license: 'TL-2026-001', contact_email: 'amal@merchant.ye', contact_phone: '+9671234567', is_active: true, bank_id: 1, bank_name_ar: 'بنك اليمن الدولي', created_at: '2026-01-01T00:00:00.000Z' },
  { id: 2, name: 'مؤسسة النهضة التجارية', trade_license: 'TL-2026-002', contact_email: 'nahda@merchant.ye', contact_phone: '+9677654321', is_active: false, bank_id: 1, bank_name_ar: 'بنك اليمن الدولي', created_at: '2026-02-01T00:00:00.000Z' },
]

const DOCUMENT_TYPES = [
  { id: 1, name_ar: 'فاتورة تجارية', name_en: 'Commercial Invoice', slug: 'commercial_invoice', is_required: true, is_active: true, sort_order: 1 },
  { id: 2, name_ar: 'شهادة منشأ', name_en: 'Certificate of Origin', slug: 'certificate_of_origin', is_required: true, is_active: true, sort_order: 2 },
  { id: 3, name_ar: 'وثيقة شحن', name_en: 'Bill of Lading', slug: 'bill_of_lading', is_required: false, is_active: true, sort_order: 3 },
]

// ── Auth helpers ───────────────────────────────────────────────────────────────

function makeCbyUser(role: UserRole = UserRole.CBY_ADMIN) {
  return {
    id: 1,
    name: 'مدير النظام',
    email: 'admin@cby.gov.ye',
    role,
    bank_id: null,
    bank_name_ar: null,
    bank_name_en: null,
    is_active: true,
  }
}

function makeBankUser(role: UserRole = UserRole.BANK_ADMIN) {
  return {
    id: 2,
    name: 'مدير البنك',
    email: 'admin@bank.ye',
    role,
    bank_id: 1,
    bank_name_ar: 'بنك اليمن الدولي',
    bank_name_en: 'Yemen International Bank',
    is_active: true,
  }
}

async function setupAuth(page: Page, user: ReturnType<typeof makeCbyUser> | ReturnType<typeof makeBankUser>) {
  await page.addInitScript((u) => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
    localStorage.setItem('auth_user', JSON.stringify(u))
  }, user)
}

async function mockBaseApis(page: Page, user: ReturnType<typeof makeCbyUser> | ReturnType<typeof makeBankUser>) {
  await page.route('**/sanctum/csrf-cookie**', route => route.fulfill({ status: 204, body: '' }))
  await page.route('**/api/auth/me**', route => route.fulfill({ json: { success: true, message: 'ok', data: user } }))
  await page.route('**/api/auth/user**', route => route.fulfill({ json: { success: true, message: 'ok', data: user } }))
  await page.route('**/api/notifications/unread-count**', route =>
    route.fulfill({ json: { success: true, message: 'ok', data: { count: 2 } } }),
  )
  await page.route('**/api/settings**', route =>
    route.fulfill({ json: { success: true, message: 'ok', data: {} } }),
  )
  await page.route('**/api/dashboard**', route =>
    route.fulfill({ json: { success: true, message: 'ok', data: { total: 0, pending: 0, approved: 0, rejected: 0 } } }),
  )
}

// ── Screenshot helpers ─────────────────────────────────────────────────────────

function parityPath(area: string, subdir: string, filename: string): string {
  const base = path.resolve(__dirname, '../../..', '_bmad-output/parity-evidence', area, subdir)
  fs.mkdirSync(base, { recursive: true })
  return path.join(base, filename)
}

async function disableAnimations(page: Page) {
  await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' })
}

async function waitForNuxt(page: Page) {
  await page.waitForSelector('#__nuxt', { timeout: 15000 })
  await page.waitForFunction(() => (document.querySelector('#__nuxt')?.children.length ?? 0) > 0)
}

async function navigateTo(page: Page, targetPath: string) {
  await page.evaluate((p) => {
    const app = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
    app?.$router.push(p)
  }, targetPath)
  await page.waitForURL(`**${targetPath}`, { timeout: 12000 })
}

// ── Notifications ──────────────────────────────────────────────────────────────

test.describe('9.4 notifications/index', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/notifications**', route =>
      route.fulfill({ json: NOTIFICATIONS_RESPONSE }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/notifications')
    await disableAnimations(page)
  })

  test('notifications list desktop — filter tabs + list visible', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toContainText('مركز الإشعارات')
    await expect(page.locator('.filter-tabs')).toBeVisible()
    await expect(page.locator('[data-testid="notifications-list"]')).toBeVisible()
    await page.screenshot({ path: parityPath('notifications', 'index', 'current.png'), fullPage: true })
  })

  test('notifications unread filter shows only unread', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.filter-tabs', { timeout: 10000 })
    await page.locator('.filter-tab').filter({ hasText: 'غير مقروء' }).click()
    const list = page.locator('[data-testid="notifications-list"]')
    await expect(list).toBeVisible()
    const items = list.locator('.notification-item.unread')
    await expect(items).toHaveCount(2) // a1 and a3 are unread
  })
})

test.describe('9.4 notifications/empty', () => {
  test('empty state renders correctly', async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/notifications**', route =>
      route.fulfill({
        json: {
          success: true, message: 'ok',
          data: { data: [], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
        },
      }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/notifications')
    await disableAnimations(page)
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('[data-testid="notifications-empty"]', { timeout: 10000 })
    await expect(page.locator('[data-testid="notifications-empty"]')).toBeVisible()
    await page.screenshot({ path: parityPath('notifications', 'empty', 'current.png'), fullPage: true })
  })
})

test.describe('9.4 notifications/bank-admin', () => {
  test('BANK_ADMIN sees notifications page', async ({ page }) => {
    const user = makeBankUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/notifications**', route =>
      route.fulfill({ json: NOTIFICATIONS_RESPONSE }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/notifications')
    await disableAnimations(page)
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toContainText('مركز الإشعارات')
    await page.screenshot({ path: parityPath('notifications', 'bank-admin', 'current.png'), fullPage: true })
  })
})

// ── Admin: banks ───────────────────────────────────────────────────────────────

test.describe('9.4 admin/banks', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/banks**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: { data: BANKS, meta: { current_page: 1, last_page: 1, per_page: 100, total: BANKS.length } } } }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/banks')
    await disableAnimations(page)
  })

  test('banks list desktop — header, table visible', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toBeVisible()
    await expect(page.locator('.data-table')).toBeVisible()
    await page.screenshot({ path: parityPath('admin', 'banks', 'current.png'), fullPage: true })
  })

  test('banks add modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.btn-primary', { timeout: 10000 })
    await page.locator('.btn-primary').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await page.screenshot({ path: parityPath('admin', 'banks-add', 'current.png'), fullPage: true })
  })

  test('banks view modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.data-table', { timeout: 10000 })
    await page.locator('button.btn-edit').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await page.screenshot({ path: parityPath('admin', 'banks-view', 'current.png'), fullPage: true })
  })
})

// ── Admin: workflow-docs ───────────────────────────────────────────────────────

test.describe('9.4 admin/workflow-docs', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/document-types**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: { data: DOCUMENT_TYPES, meta: { current_page: 1, last_page: 1, per_page: 100, total: DOCUMENT_TYPES.length } } } }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/admin/workflow-docs')
    await disableAnimations(page)
  })

  test('workflow-docs desktop — title and docs list visible', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toBeVisible()
    await expect(page.locator('.data-table, .doc-list, .card')).toBeVisible()
    await page.screenshot({ path: parityPath('admin', 'workflow-docs', 'current.png'), fullPage: true })
  })
})

// ── Admin: cby-staff edit modal ────────────────────────────────────────────────

test.describe('9.4 admin/cby-staff-edit', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/users**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: CBY_USERS } }),
    )
    await page.route('**/api/users/*', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: CBY_USERS[0] } }),
    )
    await page.route('**/api/banks**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: { data: BANKS } } }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/admin/cby-staff')
    await disableAnimations(page)
  })

  test('cby-staff edit modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.data-table', { timeout: 10000 })
    const editBtn = page.locator('button.btn-edit').first()
    await editBtn.click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await page.screenshot({ path: parityPath('admin', 'cby-staff-edit', 'current.png'), fullPage: true })
  })
})

// ── Reports ────────────────────────────────────────────────────────────────────

async function mockReportApis(page: Page) {
  await page.route('**/api/reports/workflow**', route =>
    route.fulfill({ json: { success: true, data: WORKFLOW_REPORT } }),
  )
  await page.route('**/api/reports/bank**', route =>
    route.fulfill({ json: { success: true, data: BANK_REPORT } }),
  )
}

test.describe('9.4 reports/index — CBY_ADMIN', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await mockReportApis(page)
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/reports')
    await disableAnimations(page)
  })

  test('reports index desktop — title, KPI cards, charts visible', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('التقارير والتحليلات المتقدمة')
    await expect(page.locator('[data-testid="kpi-cards"]')).toBeVisible()
    await page.screenshot({ path: parityPath('reports', 'index', 'current.png'), fullPage: true })
  })
})

test.describe('9.4 reports/bank-admin — BANK_ADMIN', () => {
  test('BANK_ADMIN reports page captures current', async ({ page }) => {
    const user = makeBankUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await mockReportApis(page)
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/reports')
    await disableAnimations(page)
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('التقارير')
    await page.screenshot({ path: parityPath('reports', 'bank-admin', 'current.png'), fullPage: true })
  })
})

test.describe('9.4 reports — other roles', () => {
  const ROLE_MAP: Array<{ role: UserRole; key: string; isCby: boolean }> = [
    { role: UserRole.SUPPORT_COMMITTEE, key: 'support-committee', isCby: true },
    { role: UserRole.COMMITTEE_DIRECTOR, key: 'committee-director', isCby: true },
    { role: UserRole.EXECUTIVE_MEMBER, key: 'executive-member', isCby: true },
  ]

  for (const { role, key, isCby } of ROLE_MAP) {
    test(`${role} reports page captures current`, async ({ page }) => {
      const user = isCby ? { ...makeCbyUser(), role } : { ...makeBankUser(), role }
      await setupAuth(page, user)
      await mockBaseApis(page, user)
      await mockReportApis(page)
      await page.goto('/dashboard')
      await waitForNuxt(page)
      await page.waitForURL('**/dashboard', { timeout: 12000 })
      await navigateTo(page, '/reports')
      await disableAnimations(page)
      await page.setViewportSize({ width: 1440, height: 900 })
      await page.waitForLoadState('networkidle')
      await expect(page.locator('h1')).toContainText('التقارير')
      await page.screenshot({ path: parityPath('reports', key, 'current.png'), fullPage: true })
    })
  }
})

// ── Audit ──────────────────────────────────────────────────────────────────────

async function mockAuditApis(page: Page) {
  await page.route('**/api/audit/stats**', route =>
    route.fulfill({ json: AUDIT_STATS_RESPONSE }),
  )
  await page.route('**/api/audit/duplicates**', route =>
    route.fulfill({ json: AUDIT_DUPLICATES_RESPONSE }),
  )
  await page.route('**/api/audit/risk-indicators**', route =>
    route.fulfill({ json: AUDIT_RISK_RESPONSE }),
  )
  await page.route('**/api/audit**', route =>
    route.fulfill({ json: AUDIT_LOGS_RESPONSE }),
  )
}

test.describe('9.4 audit/index', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await mockAuditApis(page)
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/audit')
    await disableAnimations(page)
  })

  test('audit tab 1 (activity log) desktop — title, KPI strip, table', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('التدقيق والامتثال')
    await page.screenshot({ path: parityPath('audit', 'index', 'current.png'), fullPage: true })
  })

  test('audit tab 2 (duplicates) desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForLoadState('networkidle')
    await page.getByRole('tab', { name: 'الفواتير المكررة' }).click()
    await page.waitForLoadState('networkidle')
    await disableAnimations(page)
    await page.screenshot({ path: parityPath('audit', 'tab-2', 'current.png'), fullPage: true })
  })

  test('audit tab 3 (risk indicators) desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForLoadState('networkidle')
    await page.getByRole('tab', { name: 'مؤشرات المخاطر' }).click()
    await page.waitForLoadState('networkidle')
    await disableAnimations(page)
    await page.screenshot({ path: parityPath('audit', 'tab-3', 'current.png'), fullPage: true })
  })
})

test.describe('9.4 audit/committee-director-log', () => {
  test('COMMITTEE_DIRECTOR audit log desktop', async ({ page }) => {
    const user = { ...makeCbyUser(), role: UserRole.COMMITTEE_DIRECTOR }
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await mockAuditApis(page)
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/audit')
    await disableAnimations(page)
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('التدقيق')
    await page.screenshot({ path: parityPath('audit', 'committee-director-log', 'current.png'), fullPage: true })
  })
})

// ── Settings ───────────────────────────────────────────────────────────────────

async function mockSettingsApis(page: Page) {
  await page.route('**/api/admin/settings/smtp**', route =>
    route.fulfill({ json: { success: true, data: SMTP_SETTINGS } }),
  )
  await page.route('**/api/admin/settings**', route =>
    route.fulfill({ json: { success: true, data: ADMIN_SETTINGS } }),
  )
  await page.route('**/api/preferences**', route =>
    route.fulfill({ json: { success: true, data: { language: 'ar', dashboard_view: 'normal', table_density: 'normal', page_size: 25, default_filters: {}, notification_preferences: {} } } }),
  )
}

const SETTINGS_TABS = [
  { key: 'index', tab: 'tab-workflow', label: 'سير العمل' },
  { key: 'tab-2', tab: 'tab-email', label: 'البريد الإلكتروني' },
  { key: 'tab-3', tab: 'tab-notif', label: 'الإشعارات' },
  { key: 'tab-4', tab: 'tab-security', label: 'الأمن' },
  { key: 'tab-5', tab: 'tab-general', label: 'عام' },
]

test.describe('9.4 settings tabs', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await mockSettingsApis(page)
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/settings')
    await disableAnimations(page)
  })

  for (const { key, tab, label } of SETTINGS_TABS) {
    test(`settings ${label} tab desktop`, async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await page.waitForSelector(`[data-testid="${tab}"]`, { timeout: 10000 })
      await page.locator(`[data-testid="${tab}"]`).click()
      await disableAnimations(page)
      await page.screenshot({ path: parityPath('settings', key, 'current.png'), fullPage: true })
    })
  }
})

// ── Profile ────────────────────────────────────────────────────────────────────

test.describe('9.4 profile/index', () => {
  test('CBY_ADMIN profile page desktop', async ({ page }) => {
    const user = makeCbyUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/profile**', route =>
      route.fulfill({ json: { success: true, data: { ...user, phone: '+967111222333', stats: { total: 15, in_progress: 4, completed: 11 }, recent_activity: [{ id: 1, action: 'تسجيل دخول', ref: null, ts: '2026-05-21T10:00:00Z' }] } } }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/profile')
    await disableAnimations(page)
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('[data-testid="profile-layout"]', { timeout: 10000 })
    await expect(page.locator('[data-testid="profile-layout"]')).toBeVisible()
    await page.screenshot({ path: parityPath('profile', 'index', 'current.png'), fullPage: true })
  })
})

// ── Staff (BANK_ADMIN list + modals) ──────────────────────────────────────────

const BANK_STAFF = [
  { id: 10, name: 'محمد أحمد علي', email: 'data@bank.ye', role: UserRole.DATA_ENTRY, bank_id: 1, bank_name_ar: 'بنك اليمن الدولي', is_active: true, last_login_at: '2026-05-19T10:00:00.000Z', created_at: '2026-01-01T00:00:00.000Z' },
  { id: 11, name: 'سارة محمد الزبيري', email: 'reviewer@bank.ye', role: UserRole.BANK_REVIEWER, bank_id: 1, bank_name_ar: 'بنك اليمن الدولي', is_active: true, last_login_at: '2026-05-18T09:00:00.000Z', created_at: '2026-02-15T00:00:00.000Z' },
  { id: 12, name: 'فاطمة حسن مقبل', email: 'data2@bank.ye', role: UserRole.DATA_ENTRY, bank_id: 1, bank_name_ar: 'بنك اليمن الدولي', is_active: false, last_login_at: null, created_at: '2026-03-10T00:00:00.000Z' },
]

test.describe('9.4 staff list + modals', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeBankUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/users**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: BANK_STAFF } }),
    )
    await page.route('**/api/users/*', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: BANK_STAFF[0] } }),
    )
    await page.route('**/api/banks**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: { data: BANKS } } }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/staff')
    await disableAnimations(page)
  })

  test('staff list desktop — stat cards + table', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toBeVisible()
    await expect(page.locator('.data-table')).toBeVisible()
    await page.screenshot({ path: parityPath('staff', 'list', 'current.png'), fullPage: true })
  })

  test('staff edit modal desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.data-table', { timeout: 10000 })
    await page.locator('button.btn-edit').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await page.screenshot({ path: parityPath('staff', 'edit-modal', 'current.png'), fullPage: true })
  })

  test('staff add modal desktop (secondary variant)', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.btn-primary', { timeout: 10000 })
    await page.locator('.btn-primary').first().click()
    await page.waitForSelector('.modal', { timeout: 5000 })
    await disableAnimations(page)
    await expect(page.locator('.modal')).toBeVisible()
    await page.screenshot({ path: parityPath('staff', 'edit-modal-secondary', 'current.png'), fullPage: true })
  })
})

// ── Merchants (BANK_ADMIN) ─────────────────────────────────────────────────────

test.describe('9.4 merchants list + modals', () => {
  test.beforeEach(async ({ page }) => {
    const user = makeBankUser()
    await setupAuth(page, user)
    await mockBaseApis(page, user)
    await page.route('**/api/merchants**', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: { data: MERCHANTS, meta: { current_page: 1, last_page: 1, per_page: 20, total: MERCHANTS.length } } } }),
    )
    await page.route('**/api/merchants/*', route =>
      route.fulfill({ json: { success: true, message: 'ok', data: MERCHANTS[0] } }),
    )
    await page.goto('/dashboard')
    await waitForNuxt(page)
    await page.waitForURL('**/dashboard', { timeout: 12000 })
    await navigateTo(page, '/merchants')
    await disableAnimations(page)
  })

  test('merchants list desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    await expect(page.locator('.page-title')).toBeVisible()
    await page.screenshot({ path: parityPath('merchants', 'list', 'current.png'), fullPage: true })
  })

  test('merchants list suspended filter', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.waitForSelector('.page-title', { timeout: 10000 })
    const filterBtn = page.locator('button, .filter-btn, [role="tab"]').filter({ hasText: /موقوف|معلق|غير نشط/ }).first()
    if (await filterBtn.isVisible()) {
      await filterBtn.click()
      await disableAnimations(page)
    }
    await page.screenshot({ path: parityPath('merchants', 'list-suspended', 'current.png'), fullPage: true })
  })
})
