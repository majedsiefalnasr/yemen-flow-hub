import { expect, test, type Page } from '@playwright/test'
import { UserRole } from '../../app/types/enums'

// ── Mock API data ──────────────────────────────────────────────────────────────

const WORKFLOW_REPORT = {
  counts_by_status: {
    DRAFT: 10,
    SUBMITTED: 5,
    BANK_REVIEW: 8,
    EXECUTIVE_APPROVED: 25,
    EXECUTIVE_REJECTED: 8,
    COMPLETED: 30,
  },
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
    { category: 'أغذية', count: 14 },
  ],
  amount_by_currency: [
    { currency: 'USD', amount: 450000 },
    { currency: 'EUR', amount: 280000 },
    { currency: 'SAR', amount: 150000 },
  ],
  submission_heatmap: [
    { day: 1, slot: 8, count: 12 },
    { day: 2, slot: 10, count: 18 },
    { day: 3, slot: 12, count: 8 },
    { day: 4, slot: 14, count: 15 },
    { day: 5, slot: 16, count: 6 },
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

// ── Helper: mock all report API calls ─────────────────────────────────────────

async function mockReportApis(page: Page) {
  await page.route('**/api/reports/workflow**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: WORKFLOW_REPORT }),
    })
  })

  await page.route('**/api/reports/bank**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: BANK_REPORT }),
    })
  })
}

// ── Helper: mock auth ─────────────────────────────────────────────────────────

async function mockAuthAs(page: Page, role: UserRole, bankId?: number) {
  const user = {
    id: 1,
    name: role === UserRole.CBY_ADMIN ? 'مدير النظام' : 'مدير البنك',
    email: role === UserRole.CBY_ADMIN ? 'admin@cby.ye' : 'admin@bank.ye',
    role,
    bank_id: bankId ?? null,
    is_active: true,
  }

  await page.route('**/api/user**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: user }),
    })
  })

  await page.route('**/api/auth/me**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: user }),
    })
  })

  // Set auth cookie state
  await page.evaluate((u) => {
    localStorage.setItem('auth_user', JSON.stringify(u))
  }, user)
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('7.8 Reports Parity', () => {
  test('CBY_ADMIN reports page — page title and subtitle match Lovable', async ({ page }) => {
    await mockReportApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)

    await page.goto('/reports')
    await page.waitForLoadState('networkidle')

    await expect(page.locator('h1')).toContainText('التقارير والتحليلات المتقدمة')
    await expect(page.locator('.page-subtitle')).toContainText('مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير')

    await page.screenshot({
      path: 'tests/screenshots/7-8/reports-cby-admin.png',
      fullPage: true,
    })
  })

  test('CBY_ADMIN reports page — 5 KPI cards visible', async ({ page }) => {
    await mockReportApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)

    await page.goto('/reports')
    await page.waitForLoadState('networkidle')

    const kpiContainer = page.locator('[data-testid="kpi-cards"]')
    await expect(kpiContainer).toBeVisible()

    const kpiCards = kpiContainer.locator('.kpi-card')
    await expect(kpiCards).toHaveCount(5)
  })

  test('BANK_ADMIN reports page — page title and KPI cards visible', async ({ page }) => {
    await mockReportApis(page)
    await mockAuthAs(page, UserRole.BANK_ADMIN, 1)

    await page.goto('/reports')
    await page.waitForLoadState('networkidle')

    await expect(page.locator('h1')).toContainText('التقارير والتحليلات المتقدمة')

    const kpiContainer = page.locator('[data-testid="kpi-cards"]')
    await expect(kpiContainer).toBeVisible()

    await page.screenshot({
      path: 'tests/screenshots/7-8/reports-bank-admin.png',
      fullPage: true,
    })
  })

  test('chart SVG elements are present after data load', async ({ page }) => {
    await mockReportApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)

    await page.goto('/reports')
    await page.waitForLoadState('networkidle')

    // Line chart SVG
    const lineChart = page.locator('[data-testid="line-chart"]')
    await expect(lineChart).toBeVisible()
    await expect(lineChart.locator('svg')).toBeVisible()

    // Pie chart SVG
    const pieChart = page.locator('[data-testid="pie-chart"]')
    await expect(pieChart).toBeVisible()
    await expect(pieChart.locator('svg')).toBeVisible()

    // Heatmap grid
    const heatmap = page.locator('[data-testid="heatmap"]')
    await expect(heatmap).toBeVisible()
    await expect(heatmap.locator('.heatmap-grid')).toBeVisible()
  })

  test('date filter preset clears filters and re-fetches data', async ({ page }) => {
    await mockReportApis(page)
    await mockAuthAs(page, UserRole.CBY_ADMIN)

    await page.goto('/reports')
    await page.waitForLoadState('networkidle')

    // Set date range
    await page.locator('#from-date').fill('2026-01-01')
    await page.locator('#to-date').fill('2026-05-31')
    await page.locator('button:has-text("تطبيق")').click()

    await page.waitForLoadState('networkidle')

    // Charts still visible after filter
    await expect(page.locator('[data-testid="line-chart"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-cards"]')).toBeVisible()

    // Clear filters
    await page.locator('button:has-text("مسح")').click()
    await page.waitForLoadState('networkidle')

    await expect(page.locator('[data-testid="kpi-cards"]')).toBeVisible()
  })
})
