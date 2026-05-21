import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

// ── Mock API data ──────────────────────────────────────────────────────────────

const AUDIT_LOGS_RESPONSE = {
  success: true,
  data: {
    data: [
      {
        id: 1,
        user: { id: 5, name: 'أحمد علي', email: 'ahmed@cby.ye', role: 'CBY_ADMIN' },
        user_id: 5,
        user_role: 'CBY_ADMIN',
        action: 'STATUS_TRANSITION',
        entity_type: 'ImportRequest',
        entity_id: 42,
        from_status: 'BANK_REVIEW',
        to_status: 'BANK_APPROVED',
        ip_address: '192.168.1.1',
        user_agent: 'Mozilla/5.0 (Macintosh) Chrome/124.0',
        metadata: null,
        created_at: '2026-05-20T10:00:00.000Z',
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
  },
}

const AUDIT_STATS_RESPONSE = {
  success: true,
  data: { today_count: 24, duplicate_invoice_count: 3 },
}

const AUDIT_DUPLICATES_RESPONSE = {
  success: true,
  data: {
    data: [
      { id: 10, ref: 'IMP-2026-0010', importer: 'شركة الأمل', invoice_number: 'INV-2026-101', sibling_id: 11, sibling_ref: 'IMP-2026-0011' },
      { id: 11, ref: 'IMP-2026-0011', importer: 'شركة الأمل', invoice_number: 'INV-2026-101', sibling_id: 10, sibling_ref: 'IMP-2026-0010' },
    ],
  },
}

const AUDIT_RISK_RESPONSE = {
  success: true,
  data: {
    data: [
      { title: 'نمط طلبات غير عادي', body: 'مستخدم u00432 قدّم 14 طلب في 30 دقيقة', level: 'عالية' },
      { title: 'محاولة تسجيل دخول مشبوهة', body: '5 محاولات فاشلة من IP 196.4.112.18', level: 'عالية' },
      { title: 'تعديل فاتورة بعد الاعتماد', body: 'تعديل على IMP-2025-1011', level: 'متوسطة' },
      { title: 'وثيقة بصلاحية منتهية', body: 'شهادة منشأ على IMP-2025-1027', level: 'منخفضة' },
    ],
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

async function mockAuditApis(page: Page) {
  await page.route('**/api/audit/stats**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUDIT_STATS_RESPONSE) })
  })
  await page.route('**/api/audit/duplicates**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUDIT_DUPLICATES_RESPONSE) })
  })
  await page.route('**/api/audit/risk-indicators**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUDIT_RISK_RESPONSE) })
  })
  await page.route('**/api/audit**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUDIT_LOGS_RESPONSE) })
  })
}

async function mockAuthAs(page: Page, role: UserRole) {
  const user = {
    id: 1,
    name: role === UserRole.CBY_ADMIN ? 'مدير النظام' : 'مدير اللجنة',
    email: role === UserRole.CBY_ADMIN ? 'admin@cby.ye' : 'director@cby.ye',
    role,
    bank_id: null,
    is_active: true,
  }

  await page.route('**/api/user**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: user }) })
  })
  await page.route('**/api/auth/me**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: user }) })
  })
  await page.evaluate((u) => {
    localStorage.setItem('auth_user', JSON.stringify(u))
  }, user)
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('7.9 Audit 1:1 Parity', () => {
  test('CBY_ADMIN — Tab 1 (activity log): title, KPI strip, and table visible', async ({ page }) => {
    await mockAuditApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)
    await page.goto('/audit')
    await page.waitForLoadState('networkidle')

    await expect(page.locator('h1')).toContainText('التدقيق والامتثال')
    await expect(page.locator('[data-testid="kpi-strip"]')).toBeVisible()
    await expect(page.locator('[data-testid="audit-table"]')).toBeVisible()

    await page.screenshot({
      path: 'tests/screenshots/7-9/audit-tab1-cby-admin.png',
      fullPage: true,
    })
  })

  test('CBY_ADMIN — Tab 2 (duplicates): click tab and duplicate banner visible', async ({ page }) => {
    await mockAuditApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)
    await page.goto('/audit')
    await page.waitForLoadState('networkidle')

    await page.getByRole('tab', { name: 'الفواتير المكررة' }).click()
    await page.waitForLoadState('networkidle')

    await expect(page.locator('[data-testid="dup-banner"]')).toBeVisible()

    await page.screenshot({
      path: 'tests/screenshots/7-9/audit-tab2-duplicates.png',
      fullPage: true,
    })
  })

  test('CBY_ADMIN — Tab 3 (risk indicators): click tab and risk list visible', async ({ page }) => {
    await mockAuditApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)
    await page.goto('/audit')
    await page.waitForLoadState('networkidle')

    await page.getByRole('tab', { name: 'مؤشرات المخاطر' }).click()
    await page.waitForLoadState('networkidle')

    await expect(page.locator('[data-testid="risk-list"]')).toBeVisible()

    await page.screenshot({
      path: 'tests/screenshots/7-9/audit-tab3-risk.png',
      fullPage: true,
    })
  })
})
