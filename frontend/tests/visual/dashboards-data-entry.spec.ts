/**
 * Visual regression spec — dashboards/data-entry (Story 9.5)
 *
 * Baseline source: _bmad-output/parity-evidence/dashboards/data-entry/current.png
 * Parity verdict:  PASS (matrix row: dashboards/data-entry)
 *
 * Dual-update rule: if you intentionally change the DATA_ENTRY dashboard, you
 * MUST update both this baseline and the evidence triplet in the same PR commit.
 */
import { expect, test, type Page } from '@playwright/test'
import { UserRole, RequestStatus } from '../../app/types/enums'

// ── Fixtures ──────────────────────────────────────────────────────────────────

function makeReq(id: number, status: string, extra: Record<string, unknown> = {}) {
  return {
    id,
    reference_number: `YFH-2026-${String(id).padStart(6, '0')}`,
    bank_id: 1,
    bank_name: 'بنك اليمن الدولي',
    merchant: null,
    status,
    current_owner_role: UserRole.DATA_ENTRY,
    currency: 'USD',
    amount: 50000,
    supplier_name: 'شركة الاستيراد اليمنية',
    goods_description: 'معدات صناعية',
    port_of_entry: 'ميناء عدن',
    notes: null,
    created_by: 1,
    submitted_by: null,
    reviewed_by: null,
    approved_by: null,
    rejected_by: null,
    resubmitted_by: null,
    claimed_by: null,
    claimed_until: null,
    is_claimed: false,
    is_claimed_by_me: false,
    can_be_claimed: false,
    submitted_at: null,
    bank_approved_at: null,
    support_approved_at: null,
    swift_uploaded_at: null,
    executive_decided_at: null,
    customs_issued_at: null,
    revision_count: 0,
    created_at: '2026-05-10T10:00:00.000Z',
    updated_at: '2026-05-18T14:00:00.000Z',
    ...extra,
  }
}

const DATA_ENTRY_STATS = {
  draft: 3,
  returned: 1,
  under_cby_processing: 5,
  completed: 8,
  draft_requests: [
    makeReq(13, RequestStatus.DRAFT),
    makeReq(14, RequestStatus.DRAFT),
  ],
  returned_requests: [makeReq(10, RequestStatus.DRAFT_REJECTED_INTERNAL)],
  recent_requests: [
    makeReq(11, RequestStatus.SUBMITTED),
    makeReq(12, RequestStatus.DRAFT),
  ],
}

const DATA_ENTRY_USER = {
  id: 9001,
  name: 'مستخدم إدخال البيانات',
  email: 'data_entry@test.ye',
  role: UserRole.DATA_ENTRY,
  bank_id: 1,
  bank_name_ar: 'بنك اليمن الدولي',
  bank_name_en: 'Yemen International Bank',
  is_active: true,
}

const NOTIFICATIONS_FIXTURE = [
  {
    id: 'n1',
    type: 'request_submitted',
    data: {
      type: 'request_submitted',
      message: 'تم تقديم طلب جديد',
      request_id: 1,
      reference_number: 'YFH-2026-000001',
    },
    read_at: null,
    created_at: '2026-05-19T08:00:00.000Z',
  },
]

// ── API mock ──────────────────────────────────────────────────────────────────

async function mockDataEntryApi(page: Page) {
  await page.route('**/*', async (route) => {
    const url = new URL(route.request().url())

    if (url.pathname === '/sanctum/csrf-cookie') {
      await route.fulfill({ status: 204, body: '' })
      return
    }
    if (url.pathname === '/api/auth/me') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: DATA_ENTRY_USER }),
      })
      return
    }
    if (url.pathname === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: DATA_ENTRY_STATS }),
      })
      return
    }
    if (url.pathname === '/api/notifications/unread-count') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: { count: 1 } }),
      })
      return
    }
    if (url.pathname === '/api/notifications') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            data: NOTIFICATIONS_FIXTURE,
            links: { first: null, last: null, prev: null, next: null },
            meta: { current_page: 1, from: 1, last_page: 1, path: '/api/notifications', per_page: 20, to: 1, total: 1 },
          },
        }),
      })
      return
    }
    if (url.pathname === '/api/notifications/read-all') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: null }) })
      return
    }

    await route.continue()
  })
}

async function openDashboard(page: Page) {
  await page.addInitScript(() => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
  })
  await page.goto('/dashboard')
  await page.waitForSelector('#__nuxt')
  await page.waitForFunction(() => (document.querySelector('#__nuxt')?.children.length ?? 0) > 0)
  // Wait for the success-path KPI grid only. Accepting `.error-card` here
  // would let a missed API mock baseline the dashboard's error state instead
  // of its normal render — let the timeout fail loudly if state is wrong.
  await page.waitForSelector('.kpi-grid', { timeout: 10_000 })
  // Await font loading before screenshot to prevent font-rendering races
  await page.evaluate(() => document.fonts.ready)
}

// Nuxt devtools injects dynamic elements whose content (ms timings, inspector overlays)
// changes every run. Mask them all to prevent spurious baseline diffs.
const DEVTOOLS_MASK = (page: Page) => [
  page.locator('#vue-tracer-overlay, nuxt-devtools-inspect-panel, nuxt-devtools-anchor, #nuxt-devtools-container'),
]

// ── Desktop (1440×900) ───────────────────────────────────────────────────────

test.describe('dashboards/data-entry — desktop', () => {
  test.use({ viewport: { width: 1440, height: 900 } })

  test('visual baseline — desktop', async ({ page }) => {
    await mockDataEntryApi(page)
    await openDashboard(page)
    await expect(page).toHaveScreenshot('desktop.png', {
      animations: 'disabled',
      fullPage: false,
      mask: DEVTOOLS_MASK(page),
    })
  })
})

// ── Mobile (390×844) ─────────────────────────────────────────────────────────

test.describe('dashboards/data-entry — mobile', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  test('visual baseline — mobile', async ({ page }) => {
    await mockDataEntryApi(page)
    await openDashboard(page)
    await expect(page).toHaveScreenshot('mobile.png', {
      animations: 'disabled',
      fullPage: false,
      mask: DEVTOOLS_MASK(page),
    })
  })
})
