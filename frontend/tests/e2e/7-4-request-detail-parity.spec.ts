import { expect, test, type Page } from '@playwright/test'
import { UserRole, RequestStatus, VotingSessionStatus } from '../../app/types/enums'

// ── Fixtures ──────────────────────────────────────────────────────────────────

const NOTIFICATIONS_STUB = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: null, last_page: 1, path: '/api/notifications', per_page: 20, to: null, total: 0 },
}

const BASE_REQUEST = {
  id: 42,
  reference_number: 'YFH-2026-000042',
  bank_id: 1,
  bank_name: 'بنك اليمن الدولي',
  merchant: { id: 10, name: 'مؤسسة النور التجارية', commercial_register: 'CR-2026-001' },
  currency: 'USD',
  amount: 75000,
  supplier_name: 'Global Supply Co.',
  goods_description: 'معدات طبية متطورة',
  goods_type: 'معدات طبية',
  port_of_entry: 'ميناء عدن',
  invoice_number: 'INV-2026-0042',
  invoice_date: '2026-04-15',
  origin_country: 'Germany',
  arrival_port: 'Aden',
  shipping_port: 'Hamburg',
  customs_office: 'مكتب جمارك عدن',
  bl_number: 'BL-2026-042',
  notes: 'استيراد عاجل',
  payment_terms: 'LC',
  due_date: '2026-06-30',
  created_by: 1,
  created_by_user: { id: 1, name: 'علي حسن' },
  submitted_by: 2,
  submitted_by_user: { id: 2, name: 'علي حسن' },
  reviewed_by: null,
  reviewed_by_user: null,
  approved_by: null,
  approved_by_user: null,
  rejected_by: null,
  rejected_by_user: null,
  resubmitted_by: null,
  resubmitted_by_user: null,
  swift_uploaded_by: null,
  swift_uploaded_by_user: null,
  claimed_by: null,
  claimed_until: null,
  is_claimed: false,
  is_claimed_by_me: false,
  can_be_claimed: false,
  submitted_at: '2026-05-10T10:00:00.000Z',
  bank_approved_at: null,
  support_approved_at: null,
  swift_uploaded_at: null,
  voting_opened_by: null,
  voting_opened_at: null,
  voting_closed_by: null,
  voting_closed_at: null,
  voting_session_status: null,
  executive_decided_at: null,
  customs_issued_at: null,
  customs_declaration: null,
  revision_count: 0,
  created_at: '2026-05-08T09:00:00.000Z',
  updated_at: '2026-05-18T14:00:00.000Z',
  documents: [],
}

const BASE_CUSTOMS_DECLARATION = {
  id: 70,
  declaration_number: 'CD-2026-70',
  issued_at: '2026-05-18T10:00:00.000Z',
  issuer: { id: 12, name: 'مدير اللجنة' },
  download_url: '/api/customs/70/download',
}

function makeRequest(overrides: Record<string, unknown> = {}) {
  return { ...BASE_REQUEST, ...overrides }
}

function makeUser(role: UserRole) {
  const isBankRole = [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.SWIFT_OFFICER].includes(role)
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

const EMPTY_VOTING_DETAIL = {
  request: BASE_REQUEST,
  tally: { approve_count: 0, reject_count: 0, abstain_count: 0, auto_abstain_count: 0, total_cast: 0, is_decided: false, result: 'PENDING' },
  votes: [],
  total_members: 6,
  my_vote: null,
}

const EMPTY_HISTORY = { success: true, message: 'OK', data: [] }
const EMPTY_DOCUMENTS = { success: true, message: 'OK', data: [] }

// ── Route mocking ─────────────────────────────────────────────────────────────

async function mockDetailApi(page: Page, role: UserRole, requestOverrides: Record<string, unknown> = {}) {
  const user = makeUser(role)
  const request = makeRequest({ current_owner_role: role, ...requestOverrides })

  await page.route('**/*', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname

    if (path === '/sanctum/csrf-cookie') {
      await route.fulfill({ status: 204, body: '' })
      return
    }

    if (path === '/api/auth/me') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: user }),
      })
      return
    }

    if (path === `/api/requests/${BASE_REQUEST.id}`) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: request }),
      })
      return
    }

    if (path === `/api/requests/${BASE_REQUEST.id}/documents`) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(EMPTY_DOCUMENTS),
      })
      return
    }

    if (path === `/api/requests/${BASE_REQUEST.id}/history`) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(EMPTY_HISTORY),
      })
      return
    }

    if (path === `/api/voting/${BASE_REQUEST.id}`) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: EMPTY_VOTING_DETAIL }),
      })
      return
    }

    if (path === '/api/notifications/unread-count') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: { count: 0 } }),
      })
      return
    }

    if (path === '/api/notifications') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: NOTIFICATIONS_STUB }),
      })
      return
    }

    if (path === '/api/notifications/read-all') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: null }) })
      return
    }

    if (path === '/api/settings') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: null }),
      })
      return
    }

    if (path === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: { total: 0, pending: 0, approved: 0, rejected: 0 } }),
      })
      return
    }

    await route.continue()
  })
}

async function waitForDetailPage(page: Page) {
  await page.waitForSelector('#__nuxt')
  await page.waitForFunction(() => (document.querySelector('#__nuxt')?.children.length ?? 0) > 0)
}

async function openDetailPage(page: Page, _role: UserRole) {
  await page.addInitScript(() => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
  })
  await page.goto('/dashboard')
  await waitForDetailPage(page)
  await page.waitForURL('**/dashboard', { timeout: 12000 })
  await page.evaluate(() => {
    const nuxtApp = (window as unknown as { useNuxtApp?: () => { $router: { push: (path: string) => void } } }).useNuxtApp?.()
    nuxtApp?.$router.push('/requests/42')
  })
  await page.waitForURL('**/requests/42', { timeout: 12000 })
  await page.waitForSelector('.detail-page', { timeout: 12000 })
}

// ── Desktop screenshots (1440×900) ────────────────────────────────────────────

test('7.4 DATA_ENTRY draft request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.DRAFT,
    current_owner_role: UserRole.DATA_ENTRY,
    submitted_at: null,
    submitted_by: null,
    submitted_by_user: null,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'data-entry-draft-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 DATA_ENTRY submitted request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.SUBMITTED,
    current_owner_role: UserRole.BANK_REVIEWER,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'data-entry-submitted-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 BANK_REVIEWER bank-review request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.BANK_REVIEWER, {
    status: RequestStatus.BANK_REVIEW,
    current_owner_role: UserRole.BANK_REVIEWER,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.BANK_REVIEWER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'bank-reviewer-review-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 SUPPORT_COMMITTEE pending-claim request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.SUPPORT_COMMITTEE, {
    status: RequestStatus.SUPPORT_REVIEW_PENDING,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    can_be_claimed: true,
    is_claimed: false,
    is_claimed_by_me: false,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.SUPPORT_COMMITTEE)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'support-committee-pending-claim-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 SWIFT_OFFICER waiting-swift request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.SWIFT_OFFICER, {
    status: RequestStatus.WAITING_FOR_SWIFT,
    current_owner_role: UserRole.SWIFT_OFFICER,
    bank_approved_at: '2026-05-11T10:00:00.000Z',
    support_approved_at: '2026-05-13T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.SWIFT_OFFICER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'swift-officer-waiting-swift-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 EXECUTIVE_MEMBER voting-open request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.EXECUTIVE_MEMBER, {
    status: RequestStatus.EXECUTIVE_VOTING_OPEN,
    current_owner_role: UserRole.EXECUTIVE_MEMBER,
    voting_session_status: VotingSessionStatus.OPEN,
    voting_opened_at: '2026-05-15T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.EXECUTIVE_MEMBER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'executive-member-voting-open-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 COMMITTEE_DIRECTOR voting-open request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.COMMITTEE_DIRECTOR, {
    status: RequestStatus.EXECUTIVE_VOTING_OPEN,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    voting_session_status: VotingSessionStatus.OPEN,
    voting_opened_at: '2026-05-15T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.COMMITTEE_DIRECTOR)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'committee-director-voting-open-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 CBY_ADMIN bank-approved request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, {
    status: RequestStatus.BANK_APPROVED,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    bank_approved_at: '2026-05-12T10:00:00.000Z',
    approved_by: 5,
    approved_by_user: { id: 5, name: 'محمد الزبيري' },
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'cby-admin-bank-approved-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 DATA_ENTRY rejected request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.DRAFT_REJECTED_INTERNAL,
    current_owner_role: UserRole.DATA_ENTRY,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'data-entry-rejected-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 DATA_ENTRY completed request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.COMPLETED,
    current_owner_role: UserRole.DATA_ENTRY,
    customs_declaration: BASE_CUSTOMS_DECLARATION,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'data-entry-completed-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 BANK_ADMIN documents tab request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.BANK_ADMIN, {
    status: RequestStatus.WAITING_FOR_SWIFT,
    current_owner_role: UserRole.SWIFT_OFFICER,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.BANK_ADMIN)
  await page.locator('.tab-btn', { hasText: 'الوثائق' }).click()
  await expect(page).toHaveScreenshot(['7-4', 'bank-admin-documents-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 BANK_ADMIN support-rejected request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.BANK_ADMIN, {
    status: RequestStatus.SUPPORT_REJECTED,
    current_owner_role: UserRole.BANK_REVIEWER,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.BANK_ADMIN)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'bank-admin-support-rejected-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 SUPPORT_COMMITTEE approved request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.SUPPORT_COMMITTEE, {
    status: RequestStatus.SUPPORT_APPROVED,
    current_owner_role: UserRole.SWIFT_OFFICER,
    support_approved_at: '2026-05-14T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.SUPPORT_COMMITTEE)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'support-committee-approved-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 SWIFT_OFFICER swift-uploaded request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.SWIFT_OFFICER, {
    status: RequestStatus.SWIFT_UPLOADED,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    swift_uploaded_at: '2026-05-15T10:00:00.000Z',
    swift_uploaded_by: 7,
    swift_uploaded_by_user: { id: 7, name: 'مسؤول سويفت' },
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.SWIFT_OFFICER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'swift-officer-swift-uploaded-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 EXECUTIVE_MEMBER voting-pending request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.EXECUTIVE_MEMBER, {
    status: RequestStatus.WAITING_FOR_VOTING_OPEN,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.EXECUTIVE_MEMBER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'executive-member-voting-pending-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 EXECUTIVE_MEMBER rejected request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.EXECUTIVE_MEMBER, {
    status: RequestStatus.EXECUTIVE_REJECTED,
    current_owner_role: UserRole.EXECUTIVE_MEMBER,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.EXECUTIVE_MEMBER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'executive-member-rejected-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 COMMITTEE_DIRECTOR voting-pending request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.COMMITTEE_DIRECTOR, {
    status: RequestStatus.WAITING_FOR_VOTING_OPEN,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.COMMITTEE_DIRECTOR)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'committee-director-voting-pending-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 COMMITTEE_DIRECTOR waiting-customs request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.COMMITTEE_DIRECTOR, {
    status: RequestStatus.EXECUTIVE_APPROVED,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    executive_decided_at: '2026-05-16T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.COMMITTEE_DIRECTOR)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'committee-director-waiting-customs-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 COMMITTEE_DIRECTOR documents-tab-customs request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.COMMITTEE_DIRECTOR, {
    status: RequestStatus.CUSTOMS_DECLARATION_ISSUED,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    customs_declaration: BASE_CUSTOMS_DECLARATION,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.COMMITTEE_DIRECTOR)
  await page.locator('.tab-btn', { hasText: 'الوثائق' }).click()
  await expect(page).toHaveScreenshot(['7-4', 'committee-director-documents-customs-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 CBY_ADMIN parties tab request detail desktop', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, {
    status: RequestStatus.BANK_APPROVED,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    approved_by: 5,
    approved_by_user: { id: 5, name: 'محمد الزبيري' },
    support_reviewed_by: 9,
    support_reviewed_by_user: { id: 9, name: 'مراجع الدعم' },
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)
  await page.locator('.tab-btn', { hasText: 'الأطراف' }).click()
  await expect(page).toHaveScreenshot(['7-4', 'cby-admin-parties-desktop.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

// ── Mobile screenshots (390×844) ─────────────────────────────────────────────

test('7.4 DATA_ENTRY draft request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.DRAFT,
    submitted_at: null,
    submitted_by: null,
    submitted_by_user: null,
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.DATA_ENTRY)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'data-entry-draft-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 BANK_REVIEWER bank-review request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.BANK_REVIEWER, {
    status: RequestStatus.BANK_REVIEW,
    current_owner_role: UserRole.BANK_REVIEWER,
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.BANK_REVIEWER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'bank-reviewer-review-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 CBY_ADMIN completed request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, {
    status: RequestStatus.COMPLETED,
    current_owner_role: UserRole.CBY_ADMIN,
    bank_approved_at: '2026-05-12T10:00:00.000Z',
    support_approved_at: '2026-05-14T10:00:00.000Z',
    executive_decided_at: '2026-05-16T10:00:00.000Z',
    customs_issued_at: '2026-05-18T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.CBY_ADMIN)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'cby-admin-completed-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 DATA_ENTRY rejected request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.DRAFT_REJECTED_INTERNAL,
    current_owner_role: UserRole.DATA_ENTRY,
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.DATA_ENTRY)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'data-entry-rejected-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 SUPPORT_COMMITTEE pending-claim request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.SUPPORT_COMMITTEE, {
    status: RequestStatus.SUPPORT_REVIEW_PENDING,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    can_be_claimed: true,
    is_claimed: false,
    is_claimed_by_me: false,
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.SUPPORT_COMMITTEE)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'support-committee-pending-claim-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 EXECUTIVE_MEMBER voting-open request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.EXECUTIVE_MEMBER, {
    status: RequestStatus.EXECUTIVE_VOTING_OPEN,
    current_owner_role: UserRole.EXECUTIVE_MEMBER,
    voting_session_status: VotingSessionStatus.OPEN,
    voting_opened_at: '2026-05-15T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.EXECUTIVE_MEMBER)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'executive-member-voting-open-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

test('7.4 COMMITTEE_DIRECTOR waiting-customs request detail mobile', async ({ page }) => {
  await mockDetailApi(page, UserRole.COMMITTEE_DIRECTOR, {
    status: RequestStatus.EXECUTIVE_APPROVED,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    executive_decided_at: '2026-05-16T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.COMMITTEE_DIRECTOR)
  await expect(page.locator('.detail-page')).toBeVisible()
  await expect(page).toHaveScreenshot(['7-4', 'committee-director-waiting-customs-mobile.png'], {
    animations: 'disabled',
    fullPage: false,
    maxDiffPixelRatio: 0.02,
  })
})

// ── Behavioral tests ──────────────────────────────────────────────────────────

test('7.4 detail page shows breadcrumbs with reference number', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, { status: RequestStatus.BANK_REVIEW })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  await expect(page.locator('.breadcrumbs')).toBeVisible()
  await expect(page.locator('.breadcrumb-current')).toContainText('YFH-2026-000042')
})

test('7.4 detail page header shows reference number as title', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, { status: RequestStatus.DRAFT })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)

  await expect(page.locator('.page-title')).toContainText('YFH-2026-000042')
})

test('7.4 detail page header subtitle keeps merchant, goods type, and bank context', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.DRAFT,
    bank_name: 'بنك اليمن الدولي',
    goods_type: 'معدات طبية',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)

  await expect(page.locator('.page-subtitle')).toContainText('مؤسسة النور التجارية')
  await expect(page.locator('.page-subtitle')).toContainText('معدات طبية')
  await expect(page.locator('.page-subtitle')).toContainText('بنك اليمن الدولي')
})

test('7.4 information tab is active by default', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, { status: RequestStatus.BANK_REVIEW })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  await expect(page.locator('.tab-btn--active')).toContainText('المعلومات')
})

test('7.4 documents tab loads when clicked', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, { status: RequestStatus.BANK_REVIEW })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  await page.locator('.tab-btn', { hasText: 'الوثائق' }).click()
  await expect(page.locator('.tab-btn--active')).toContainText('الوثائق')
})

test('7.4 parties tab loads on activation', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, { status: RequestStatus.BANK_REVIEW })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  await page.locator('.tab-btn', { hasText: 'الأطراف' }).click()
  await expect(page.locator('.tab-btn--active')).toContainText('الأطراف')
})

test('7.4 VotingPanel shown inline for EXECUTIVE_MEMBER in voting-open stage', async ({ page }) => {
  await mockDetailApi(page, UserRole.EXECUTIVE_MEMBER, {
    status: RequestStatus.EXECUTIVE_VOTING_OPEN,
    voting_session_status: VotingSessionStatus.OPEN,
    voting_opened_at: '2026-05-15T10:00:00.000Z',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.EXECUTIVE_MEMBER)

  // VotingPanel is inline above tabs, not a separate tab button
  await expect(page.locator('.voting-inline')).toBeVisible()
})

test('7.4 VotingPanel not shown for BANK_REVIEWER', async ({ page }) => {
  await mockDetailApi(page, UserRole.BANK_REVIEWER, {
    status: RequestStatus.BANK_REVIEW,
    current_owner_role: UserRole.BANK_REVIEWER,
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.BANK_REVIEWER)

  await expect(page.locator('.voting-inline')).not.toBeVisible()
})

test('7.4 actor names shown in parties tab (no #1 fallback when name available)', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, {
    status: RequestStatus.BANK_APPROVED,
    created_by: 1,
    created_by_user: { id: 1, name: 'علي حسن' },
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  await page.locator('.tab-btn', { hasText: 'الأطراف' }).click()
  await expect(page.locator('.tab-panel')).toContainText('علي حسن')
})

test('7.4 unauthorized customs download buttons stay hidden on the detail page', async ({ page }) => {
  await mockDetailApi(page, UserRole.DATA_ENTRY, {
    status: RequestStatus.COMPLETED,
    customs_declaration: {
      id: 70,
      declaration_number: 'CD-2026-70',
      issued_at: '2026-05-18T10:00:00.000Z',
      issuer: { id: 12, name: 'مدير اللجنة' },
      download_url: '/api/customs/70/download',
    },
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.DATA_ENTRY)

  await expect(page.locator('.download-btn')).toHaveCount(0)
  await expect(page.locator('.customs-download')).toHaveCount(0)
})

test('7.4 two-column layout visible at desktop width', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, { status: RequestStatus.BANK_REVIEW })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  await expect(page.locator('.detail-layout')).toBeVisible()
  await expect(page.locator('.detail-main')).toBeVisible()
  await expect(page.locator('.detail-rail')).toBeVisible()
})

test('7.4 SUPPORT_COMMITTEE claimed-by-other shows ClaimedByOthersBanner', async ({ page }) => {
  await mockDetailApi(page, UserRole.SUPPORT_COMMITTEE, {
    status: RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
    current_owner_role: UserRole.SUPPORT_COMMITTEE,
    can_be_claimed: false,
    is_claimed: true,
    is_claimed_by_me: false,
    claimed_by: { id: 888, name: 'منى الحكيمي' },
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openDetailPage(page, UserRole.SUPPORT_COMMITTEE)

  // ClaimedByOthersBanner should show the name of the claimer
  await expect(page.locator('.banner-area')).toBeVisible({ timeout: 10000 })
  await expect(page.locator('.banner-area')).toContainText('منى الحكيمي')
})

test('7.4 mobile one-column layout collapses rail below main', async ({ page }) => {
  await mockDetailApi(page, UserRole.CBY_ADMIN, { status: RequestStatus.BANK_REVIEW })
  await page.setViewportSize({ width: 390, height: 844 })
  await openDetailPage(page, UserRole.CBY_ADMIN)

  const mainBox = await page.locator('.detail-main').boundingBox()
  const railBox = await page.locator('.detail-rail').boundingBox()

  // In RTL single-column on mobile, both columns should be full-width (x near 0 or equal)
  // and rail comes after main (higher Y)
  if (mainBox && railBox) {
    // Both should span full width; rail top should be at or below main bottom
    expect(railBox.y).toBeGreaterThanOrEqual(mainBox.y + mainBox.height - 10)
  }
})
