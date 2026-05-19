import { expect, test, type Page } from '@playwright/test'
import { UserRole, RequestStatus } from '../../app/types/enums'

// ── Shared fixture request ────────────────────────────────────────────────────

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

// ── Role-specific mock stats ──────────────────────────────────────────────────

const STATS_BY_ROLE: Record<string, unknown> = {
  [UserRole.DATA_ENTRY]: {
    draft: 3,
    returned: 1,
    under_cby_processing: 5,
    completed: 8,
    returned_requests: [makeReq(10, RequestStatus.DRAFT_REJECTED_INTERNAL)],
    recent_requests: [
      makeReq(11, RequestStatus.SUBMITTED),
      makeReq(12, RequestStatus.DRAFT),
    ],
  },
  [UserRole.BANK_REVIEWER]: {
    pending_review: 4,
    at_cby: 6,
    returned_by_support: 1,
    approved_completed: 9,
    review_queue: [
      makeReq(20, RequestStatus.SUBMITTED),
      makeReq(21, RequestStatus.BANK_REVIEW),
    ],
  },
  [UserRole.BANK_ADMIN]: {
    total: 42,
    pending: 7,
    approved: 18,
    rejected: 3,
    total_financed_amount: 2_100_000,
    monthly_requests: [
      { month: '2025-12', count: 4 },
      { month: '2026-01', count: 6 },
      { month: '2026-02', count: 5 },
      { month: '2026-03', count: 8 },
      { month: '2026-04', count: 7 },
      { month: '2026-05', count: 12 },
    ],
    recent_requests: [
      makeReq(30, RequestStatus.SUBMITTED),
      makeReq(31, RequestStatus.BANK_REVIEW),
      makeReq(32, RequestStatus.EXECUTIVE_APPROVED),
    ],
  },
  [UserRole.SWIFT_OFFICER]: {
    pending_swift_upload: 3,
    uploaded: 7,
    final_approved: 12,
    final_rejected: 2,
    swift_queue: [
      makeReq(40, RequestStatus.WAITING_FOR_SWIFT),
      makeReq(41, RequestStatus.WAITING_FOR_SWIFT),
    ],
  },
  [UserRole.SUPPORT_COMMITTEE]: {
    waiting_for_claim: 5,
    active_by_me: 1,
    claimed_by_others: 2,
    recently_approved: 4,
    support_queue: [
      makeReq(50, RequestStatus.SUPPORT_REVIEW_PENDING),
      makeReq(51, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, {
        claimed_by: { id: 999, name: 'علي الزبيري' },
        is_claimed: true,
        is_claimed_by_me: true,
      }),
    ],
  },
  [UserRole.EXECUTIVE_MEMBER]: {
    waiting_for_voting_open: 2,
    active_voting_sessions: 3,
    decisions_approved: 14,
    decisions_rejected: 4,
    finalized_decisions: 18,
    voting_queue: [
      makeReq(60, RequestStatus.WAITING_FOR_VOTING_OPEN),
      makeReq(61, RequestStatus.EXECUTIVE_VOTING_OPEN),
    ],
  },
  [UserRole.COMMITTEE_DIRECTOR]: {
    waiting_for_voting_open: 2,
    active_voting_sessions: 3,
    decisions_approved: 14,
    decisions_rejected: 4,
    finalized_decisions: 18,
    voting_queue: [makeReq(70, RequestStatus.EXECUTIVE_VOTING_OPEN)],
    customs_declaration_pending: [makeReq(71, RequestStatus.EXECUTIVE_APPROVED)],
  },
  [UserRole.CBY_ADMIN]: {
    total: 124,
    approved: 51,
    in_process: 38,
    rejected: 11,
    compliance_alerts: {
      duplicate_suppliers: [{ supplier_name: 'شركة البحر الأحمر', count: 2 }],
      high_amount_requests: [{
        id: 77,
        reference_number: 'YFH-2026-000077',
        amount: 1_450_000,
        currency: 'USD',
        bank_name: 'بنك اليمن الدولي',
      }],
      stale_pending_requests: [{
        id: 78,
        reference_number: 'YFH-2026-000078',
        bank_name: 'بنك عدن',
        updated_at: '2026-05-01T10:00:00.000Z',
      }],
    },
    most_active_banks: [
      { bank_id: 1, bank_name: 'بنك اليمن الدولي', request_count: 34 },
      { bank_id: 2, bank_name: 'بنك عدن', request_count: 19 },
      { bank_id: 3, bank_name: 'بنك التضامن', request_count: 12 },
    ],
    monthly_requests: [
      { month: '2025-12', submitted: 12, approved: 8 },
      { month: '2026-01', submitted: 15, approved: 11 },
      { month: '2026-02', submitted: 18, approved: 13 },
      { month: '2026-03', submitted: 22, approved: 16 },
      { month: '2026-04', submitted: 20, approved: 14 },
      { month: '2026-05', submitted: 25, approved: 18 },
    ],
    category_distribution: [
      { label: 'USD', count: 70, color: '#0066cc' },
      { label: 'EUR', count: 30, color: '#1b5e20' },
      { label: 'SAR', count: 15, color: '#f57f17' },
    ],
    recent_requests: [
      makeReq(80, RequestStatus.SUBMITTED),
      makeReq(81, RequestStatus.BANK_REVIEW),
      makeReq(82, RequestStatus.SUPPORT_REVIEW_IN_PROGRESS),
    ],
  },
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

// ── API mock factory ──────────────────────────────────────────────────────────

function buildUser(role: UserRole, bankId: number | null = null) {
  return {
    id: 9000 + Object.values(UserRole).indexOf(role),
    name: `مستخدم ${role}`,
    email: `${role.toLowerCase()}@test.ye`,
    role,
    bank_id: bankId,
    bank_name_ar: bankId ? 'بنك اليمن الدولي' : null,
    bank_name_en: bankId ? 'Yemen International Bank' : null,
    is_active: true,
  }
}

async function mockApiForRole(page: Page, role: UserRole) {
  const bankId = [
    UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.SWIFT_OFFICER,
  ].includes(role) ? 1 : null
  const user = buildUser(role, bankId)

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

    if (url.pathname === '/api/dashboard/stats') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: STATS_BY_ROLE[role] }),
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

async function waitForNuxtHydration(page: Page) {
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt') as Record<string, unknown> | null
    return Boolean(root && '__vue_app__' in root)
  })
}

async function openDashboardForRole(page: Page, role: UserRole) {
  await page.addInitScript(({ roleValue }) => {
    localStorage.setItem('yfh-authenticated', '1')
    localStorage.setItem('sidebar_collapsed', 'false')
    // Store role for debugging
    window.__e2e_role = roleValue
  }, { roleValue: role })

  await page.goto('/dashboard')
  await waitForNuxtHydration(page)
  // Wait for dashboard content to render (KPI cards or quick-actions)
  await page.waitForSelector('.kpi-grid, .error-card', { timeout: 10000 })
}

// ── Role label helpers for assertions ────────────────────────────────────────

const ROLE_HEADING_PATTERNS: Partial<Record<UserRole, string | RegExp>> = {
  [UserRole.CBY_ADMIN]: 'لوحة إدارة النظام',
  [UserRole.BANK_ADMIN]: 'لوحة مدير البنك',
  [UserRole.DATA_ENTRY]: 'لوحة إدخال البيانات',
  [UserRole.BANK_REVIEWER]: 'لوحة المراجعة البنكية',
  [UserRole.SWIFT_OFFICER]: 'لوحة ضابط السويفت',
  [UserRole.SUPPORT_COMMITTEE]: 'لوحة لجنة الدعم',
  [UserRole.EXECUTIVE_MEMBER]: 'لوحة المجلس التنفيذي',
  [UserRole.COMMITTEE_DIRECTOR]: 'لوحة المجلس التنفيذي',
}

// ── Desktop tests (1440×900) ──────────────────────────────────────────────────

const bankRoles: [UserRole, string][] = [
  [UserRole.DATA_ENTRY, 'data-entry'],
  [UserRole.BANK_REVIEWER, 'bank-reviewer'],
  [UserRole.BANK_ADMIN, 'bank-admin'],
  [UserRole.SWIFT_OFFICER, 'swift-officer'],
]

const cbyRoles: [UserRole, string][] = [
  [UserRole.SUPPORT_COMMITTEE, 'support-committee'],
  [UserRole.EXECUTIVE_MEMBER, 'executive-member'],
  [UserRole.COMMITTEE_DIRECTOR, 'committee-director'],
  [UserRole.CBY_ADMIN, 'cby-admin'],
]

for (const [role, slug] of [...bankRoles, ...cbyRoles]) {
  test(`7.2 dashboard desktop — ${slug}`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await mockApiForRole(page, role)
    await openDashboardForRole(page, role)

    await expect(page).toHaveScreenshot(['7-2', `${slug}-desktop.png`], {
      animations: 'disabled',
      fullPage: false,
    })
  })
}

// ── Mobile tests (390×844) ────────────────────────────────────────────────────

for (const [role, slug] of [...bankRoles, ...cbyRoles]) {
  test(`7.2 dashboard mobile — ${slug}`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await mockApiForRole(page, role)
    await openDashboardForRole(page, role)

    await expect(page).toHaveScreenshot(['7-2', `${slug}-mobile.png`], {
      animations: 'disabled',
      fullPage: false,
    })
  })
}
