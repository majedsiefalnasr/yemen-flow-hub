import { expect, test, type Page } from '@playwright/test'
import { UserRole, RequestStatus } from '../../app/types/enums'

// ── Request fixture factory ───────────────────────────────────────────────────

function makeReq(id: number, status: string, extra: Record<string, unknown> = {}) {
  return {
    id,
    reference_number: `YFH-2026-${String(id).padStart(6, '0')}`,
    bank_id: 1,
    bank_name: 'بنك اليمن الدولي',
    merchant: { id: 10 + id, name: `تاجر رقم ${id}`, commercial_register: `CR-${id}` },
    status,
    current_owner_role: UserRole.DATA_ENTRY,
    currency: 'USD',
    amount: 50000 + id * 1000,
    supplier_name: `شركة المورد ${id}`,
    goods_type: 'أجهزة طبية',
    invoice_number: `INV-2026-${id}`,
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

// ── Role-specific request fixtures ───────────────────────────────────────────

const REQUESTS_BY_ROLE: Record<string, unknown[]> = {
  [UserRole.BANK_ADMIN]: [
    makeReq(10, RequestStatus.DRAFT),
    makeReq(11, RequestStatus.SUBMITTED),
    makeReq(12, RequestStatus.BANK_REVIEW),
    makeReq(13, RequestStatus.BANK_APPROVED),
    makeReq(14, RequestStatus.EXECUTIVE_APPROVED),
  ],
  [UserRole.SWIFT_OFFICER]: [
    makeReq(20, RequestStatus.WAITING_FOR_SWIFT),
    makeReq(21, RequestStatus.WAITING_FOR_SWIFT),
    makeReq(22, RequestStatus.SWIFT_UPLOADED),
  ],
  [UserRole.SUPPORT_COMMITTEE]: [
    makeReq(30, RequestStatus.SUPPORT_REVIEW_PENDING),
    makeReq(31, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, {
      is_claimed: true,
      is_claimed_by_me: true,
      claimed_by: { id: 999, name: 'أحمد الزبيري' },
    }),
    makeReq(32, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, {
      is_claimed: true,
      is_claimed_by_me: false,
      claimed_by: { id: 888, name: 'منى الحكيمي' },
    }),
    makeReq(33, RequestStatus.SUPPORT_APPROVED),
  ],
  [UserRole.EXECUTIVE_MEMBER]: [
    makeReq(40, RequestStatus.EXECUTIVE_VOTING_OPEN),
    makeReq(41, RequestStatus.EXECUTIVE_VOTING_OPEN),
    makeReq(42, RequestStatus.EXECUTIVE_APPROVED),
  ],
  [UserRole.COMMITTEE_DIRECTOR]: [
    makeReq(50, RequestStatus.EXECUTIVE_VOTING_OPEN),
    makeReq(51, RequestStatus.EXECUTIVE_APPROVED),
    makeReq(52, RequestStatus.CUSTOMS_DECLARATION_ISSUED),
  ],
  [UserRole.CBY_ADMIN]: [
    makeReq(60, RequestStatus.SUBMITTED),
    makeReq(61, RequestStatus.BANK_REVIEW),
    makeReq(62, RequestStatus.SUPPORT_REVIEW_PENDING),
    makeReq(63, RequestStatus.EXECUTIVE_VOTING_OPEN),
    makeReq(64, RequestStatus.COMPLETED),
  ],
}

const NOTIFICATIONS_STUB = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: null, last_page: 1, path: '/api/notifications', per_page: 20, to: null, total: 0 },
}

const DASHBOARD_STUB = { total: 0, pending: 0, approved: 0, rejected: 0 }

// ── API mock factory ──────────────────────────────────────────────────────────

function buildUser(role: UserRole) {
  const isBankRole = [
    UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.SWIFT_OFFICER,
  ].includes(role)
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

function buildRequestsPage(role: UserRole) {
  const data = REQUESTS_BY_ROLE[role] ?? []
  return {
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: data.length,
    },
  }
}

async function mockApiForRole(page: Page, role: UserRole) {
  const user = buildUser(role)

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
        body: JSON.stringify({ success: true, message: 'OK', data: user }),
      })
      return
    }

    if (url.pathname === '/api/requests') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: buildRequestsPage(role) }),
      })
      return
    }

    if (url.pathname === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: DASHBOARD_STUB }),
      })
      return
    }

    if (url.pathname === '/api/notifications/unread-count') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: { count: 0 } }),
      })
      return
    }

    if (url.pathname === '/api/notifications') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: NOTIFICATIONS_STUB }),
      })
      return
    }

    if (url.pathname === '/api/notifications/read-all') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: null }) })
      return
    }

    if (url.pathname === '/api/settings') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: null }),
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

async function openRequestsForRole(page: Page, _role: UserRole) {
  await page.addInitScript(() => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
  })
  // Navigate to dashboard first so the auth plugin (01.auth.client.ts) can run fetchUser.
  // The global middleware redirects unauthenticated users to /login; after auth hydration,
  // guest middleware on /login redirects to /dashboard. SPA navigation from there avoids
  // another full-page reload (which would reset Pinia state and re-trigger the middleware).
  await page.goto('/dashboard')
  await waitForNuxtHydration(page)
  await page.waitForURL('**/dashboard', { timeout: 12000 })
  // Use Nuxt's router via useNuxtApp() to navigate within the SPA — avoids full page reload
  await page.evaluate(() => {
    const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
    nuxtApp?.$router.push('/requests')
  })
  await page.waitForURL('**/requests', { timeout: 12000 })
  await page.waitForSelector('.requests-page', { timeout: 12000 })
}

// ── Desktop screenshots (1440×900) ────────────────────────────────────────────

const DESKTOP_ROLES: UserRole[] = [
  UserRole.BANK_ADMIN,
  UserRole.SWIFT_OFFICER,
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

for (const role of DESKTOP_ROLES) {
  test(`7.3 ${role} requests list desktop`, async ({ page }) => {
    await mockApiForRole(page, role)
    await page.setViewportSize({ width: 1440, height: 900 })
    await openRequestsForRole(page, role)
    await expect(page.locator('.requests-page')).toBeVisible()
    await expect(page).toHaveScreenshot([`7-3`, `${role.toLowerCase().replace(/_/g, '-')}-requests-desktop.png`], {
      animations: 'disabled',
      fullPage: false,
      maxDiffPixelRatio: 0.02,
    })
  })
}

// ── Mobile screenshots (390×844) ─────────────────────────────────────────────

for (const role of DESKTOP_ROLES) {
  test(`7.3 ${role} requests list mobile`, async ({ page }) => {
    await mockApiForRole(page, role)
    await page.setViewportSize({ width: 390, height: 844 })
    await openRequestsForRole(page, role)
    await expect(page.locator('.requests-page')).toBeVisible()
    await expect(page).toHaveScreenshot([`7-3`, `${role.toLowerCase().replace(/_/g, '-')}-requests-mobile.png`], {
      animations: 'disabled',
      fullPage: false,
      maxDiffPixelRatio: 0.02,
    })
  })
}

// ── Behavioral tests ──────────────────────────────────────────────────────────

test('7.3 SUPPORT_COMMITTEE shows claim badges on rows', async ({ page }) => {
  await mockApiForRole(page, UserRole.SUPPORT_COMMITTEE)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.SUPPORT_COMMITTEE)

  // Claimed-by-me badge
  await expect(page.getByText('محجوز لك')).toBeVisible()
  // Claimed-by-other badge
  await expect(page.getByText('محجوز: منى الحكيمي')).toBeVisible()
})

test('7.3 EXECUTIVE_MEMBER shows voting-open badge on correct rows', async ({ page }) => {
  await mockApiForRole(page, UserRole.EXECUTIVE_MEMBER)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.EXECUTIVE_MEMBER)
  await expect(page.getByText('باب التصويت مفتوح').first()).toBeVisible()
})

test('7.3 BANK_ADMIN does not see voting-open badge', async ({ page }) => {
  await mockApiForRole(page, UserRole.BANK_ADMIN)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.BANK_ADMIN)
  await expect(page.getByText('باب التصويت مفتوح')).not.toBeVisible()
})

test('7.3 CBY_ADMIN sees bank filter', async ({ page }) => {
  await mockApiForRole(page, UserRole.CBY_ADMIN)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.CBY_ADMIN)
  // Bank filter select should be visible
  await expect(page.locator('.filter-select--bank')).toBeVisible()
})

test('7.3 BANK_ADMIN does not see bank filter', async ({ page }) => {
  await mockApiForRole(page, UserRole.BANK_ADMIN)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.BANK_ADMIN)
  await expect(page.locator('.filter-select--bank')).not.toBeVisible()
})

test('7.3 page shows stage tabs for roles with buckets', async ({ page }) => {
  await mockApiForRole(page, UserRole.SUPPORT_COMMITTEE)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.SUPPORT_COMMITTEE)
  await expect(page.locator('.stage-tabs-card')).toBeVisible()
  // الكل tab always present
  await expect(page.getByRole('button', { name: /الكل/ })).toBeVisible()
})

test('7.3 pagination footer shows item count', async ({ page }) => {
  await mockApiForRole(page, UserRole.BANK_ADMIN)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.BANK_ADMIN)
  await expect(page.locator('.pagination-footer')).toBeVisible()
  await expect(page.locator('.pagination-info')).toContainText('طلب')
})

test('7.3 table shows invoice number below reference', async ({ page }) => {
  await mockApiForRole(page, UserRole.BANK_ADMIN)
  await page.setViewportSize({ width: 1440, height: 900 })
  await openRequestsForRole(page, UserRole.BANK_ADMIN)
  // INV-2026-10 is the invoice for request id=10
  await expect(page.locator('.invoice-number').first()).toContainText('INV-2026')
})
