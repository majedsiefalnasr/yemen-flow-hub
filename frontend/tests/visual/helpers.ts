/**
 * Shared helpers for Phase 9 visual regression tests.
 *
 * Every visual spec calls `mockApi(page, role)` to intercept all backend
 * requests, then navigates directly to the target page.  No real backend or
 * real auth session is needed.
 */
import type { Page, Route } from '@playwright/test'

// ─── Canonical roles ────────────────────────────────────────────────────────

export type VisualRole =
  | 'DATA_ENTRY'
  | 'BANK_REVIEWER'
  | 'BANK_ADMIN'
  | 'SUPPORT_COMMITTEE'
  | 'SWIFT_OFFICER'
  | 'EXECUTIVE_MEMBER'
  | 'COMMITTEE_DIRECTOR'
  | 'CBY_ADMIN'

// ─── Fixtures ────────────────────────────────────────────────────────────────

export function makeUser(role: VisualRole) {
  const bankRoles: VisualRole[] = ['DATA_ENTRY', 'BANK_REVIEWER', 'BANK_ADMIN']
  return {
    id: 1001,
    name: 'محمد العمري',
    email: `${role.toLowerCase().replace(/_/g, '-')}@cby.gov.ye`,
    role,
    bank_id: bankRoles.includes(role) ? 11 : null,
    bank_name_ar: bankRoles.includes(role) ? 'البنك اليمني للتجارة والاستثمار' : null,
    bank_name_en: bankRoles.includes(role) ? 'YBTI' : null,
    is_active: true,
  }
}

export function makeRequest(id: number, status: string) {
  return {
    id,
    reference_number: `YFH-2026-${String(id).padStart(6, '0')}`,
    bank_id: 11,
    bank_name: 'البنك اليمني للتجارة والاستثمار',
    merchant: { id: 91, name: 'شركة التاجر الذهبي', commercial_register: 'CR-1001' },
    status,
    current_owner_role: 'DATA_ENTRY',
    currency: 'USD',
    amount: 95000,
    supplier_name: 'Global Industrial Co.',
    goods_description: 'معدات صناعية',
    port_of_entry: 'عدن',
    notes: null,
    goods_type: null,
    payment_terms: 'LC 90',
    due_date: null,
    invoice_number: 'INV-5500',
    invoice_date: '2026-05-10',
    origin_country: 'CN',
    arrival_port: 'عدن',
    shipping_port: 'شنغهاي',
    customs_office: null,
    bl_number: 'BL-556677',
    created_by: 42,
    submitted_by: 42,
    reviewed_by: null,
    approved_by: null,
    rejected_by: null,
    resubmitted_by: null,
    claimed_by: null,
    claimed_until: null,
    is_claimed: false,
    is_claimed_by_me: false,
    can_be_claimed: true,
    submitted_at: '2026-05-11T08:10:00.000Z',
    bank_approved_at: null,
    support_approved_at: null,
    swift_uploaded_by: null,
    swift_uploaded_at: null,
    voting_opened_by: null,
    voting_opened_at: null,
    voting_closed_by: null,
    voting_closed_at: null,
    voting_session_status: null,
    executive_decided_at: null,
    customs_issued_at: null,
    customs_declaration: null,
    bank_return_comment: null,
    bank_reject_comment: null,
    support_return_comment: null,
    revision_count: 0,
    created_at: '2026-05-10T08:00:00.000Z',
    updated_at: '2026-05-12T08:00:00.000Z',
    ready_to_close: false,
    is_tie: false,
    has_swift_document: false,
    has_fx_request_document: false,
    documents: [],
  }
}

export const SAMPLE_BANKS = [
  { id: 11, name_ar: 'البنك اليمني للتجارة والاستثمار', name_en: 'YBTI', is_active: true },
  { id: 12, name_ar: 'بنك الكريمي', name_en: 'Alkuraimi Bank', is_active: true },
  { id: 13, name_ar: 'البنك الأهلي اليمني', name_en: 'National Bank of Yemen', is_active: true },
]

function apiOk<T>(data: T) {
  return { success: true, message: 'OK', data }
}

function paginatedOk<T>(items: T[]) {
  return apiOk({ data: items, meta: { current_page: 1, last_page: 1, per_page: 25, total: items.length } })
}

function dashboardStats(role: VisualRole) {
  const req1 = makeRequest(1001, 'SUBMITTED')
  const req2 = makeRequest(1002, 'BANK_REVIEW')
  const req3 = makeRequest(1003, 'BANK_RETURNED')
  const req4 = makeRequest(1004, 'SUPPORT_REVIEW_PENDING')
  const req5 = makeRequest(1005, 'WAITING_FOR_SWIFT')
  const req6 = makeRequest(1006, 'EXECUTIVE_VOTING_OPEN')
  const req7 = makeRequest(1007, 'FX_CONFIRMATION_PENDING')

  switch (role) {
    case 'DATA_ENTRY':
      return { draft: 3, returned: 1, under_cby_processing: 5, completed: 12, draft_requests: [makeRequest(1, 'DRAFT')], returned_requests: [req3], recent_requests: [req1, req2] }
    case 'BANK_REVIEWER':
      return { pending_review: 4, at_cby: 2, returned_by_support: 1, approved_completed: 8, review_queue: [req2, makeRequest(1008, 'BANK_REVIEW')], downstream_queue: [req4] }
    case 'BANK_ADMIN':
      return { total: 25, pending: 4, approved: 18, rejected: 3, total_financed_amount: 4250000, monthly_requests: [{ month: 'مايو', count: 8 }, { month: 'أبريل', count: 6 }], recent_requests: [req1, req2] }
    case 'SUPPORT_COMMITTEE':
      return { waiting_for_claim: 3, active_by_me: 1, claimed_by_others: 2, recently_approved: 5, support_queue: [req4, makeRequest(1009, 'SUPPORT_REVIEW_PENDING')] }
    case 'SWIFT_OFFICER':
      return { pending_swift_upload: 2, uploaded: 1, final_approved: 7, final_rejected: 1, swift_queue: [req5, makeRequest(1010, 'SWIFT_UPLOADED')] }
    case 'EXECUTIVE_MEMBER':
      return { waiting_for_voting_open: 1, active_voting_sessions: 2, decisions_approved: 14, decisions_rejected: 3, finalized_decisions: 17, pending_my_vote: 2, voting_queue: [{ ...req6, my_vote: null, votes_cast: 3, total_voters: 6 }] }
    case 'COMMITTEE_DIRECTOR':
      return { waiting_for_voting_open: 0, active_voting_sessions: 1, decisions_approved: 14, decisions_rejected: 3, finalized_decisions: 17, sessions_ready_to_close: 1, sessions_with_tie: 0, fx_confirmation_pending: 1, finalized_approved: 14, finalized_rejected: 3, voting_lifecycle_queue: [{ ...req6, my_vote: null, votes_cast: 6, total_voters: 6, ready_to_close: true }], fx_confirmation_queue: [req7] }
    case 'CBY_ADMIN':
      return {
        total_requests: 158,
        pending_requests: 12,
        approved_requests: 130,
        rejected_requests: 16,
        total_financed_amount_usd: 18500000,
        active_banks: 13,
        sla_breach_count: 2,
        monthly_trend: [{ month: 'مارس', submitted: 25, approved: 22 }, { month: 'أبريل', submitted: 30, approved: 27 }, { month: 'مايو', submitted: 18, approved: 14 }],
        category_distribution: [{ label: 'مواد غذائية', count: 45, color: '#0066cc' }, { label: 'أدوية', count: 28, color: '#5856d6' }],
        bank_risk: [{ bank_name: 'YBTI', total: 30, pending: 3, rejected: 2, risk_score: 0.2 }],
        compliance_alerts: { duplicate_suppliers: [], high_amount_requests: [], stale_pending_requests: [] },
        workflow_pressure: [],
        voting_sessions: [],
        compliance_signals: [],
        critical_events: [],
        kpis: [],
      }
    default:
      return {}
  }
}

// ─── API mock ─────────────────────────────────────────────────────────────────

export async function mockApi(page: Page, role: VisualRole) {
  const user = makeUser(role)
  const corsHeaders = {
    'access-control-allow-origin': 'http://localhost:3000',
    'access-control-allow-credentials': 'true',
  }

  const json = async (route: Route, body: unknown, status = 200) => {
    await route.fulfill({ status, headers: { ...corsHeaders, 'content-type': 'application/json' }, body: JSON.stringify(body) })
  }

  await page.route('**/sanctum/csrf-cookie', async (route) => {
    const url = new URL(route.request().url())
    if (!['localhost:8000', '127.0.0.1:8000'].includes(url.host)) { await route.fallback(); return }
    await route.fulfill({ status: 204, headers: { ...corsHeaders, 'set-cookie': 'XSRF-TOKEN=test-xsrf; Path=/' }, body: '' })
  })

  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url())
    if (!['localhost:8000', '127.0.0.1:8000'].includes(url.host)) { await route.fallback(); return }

    const path = url.pathname

    if (path === '/api/auth/me') { await json(route, apiOk(user)); return }
    if (path === '/api/auth/login') { await json(route, apiOk({ user, token: null, token_type: null, mode: 'cookie', requires_mfa: false })); return }
    if (path === '/api/dashboard/stats') { await json(route, apiOk(dashboardStats(role))); return }
    if (path === '/api/requests') { await json(route, paginatedOk([makeRequest(2001, 'SUBMITTED'), makeRequest(2002, 'BANK_REVIEW'), makeRequest(2003, 'BANK_APPROVED'), makeRequest(2004, 'SUPPORT_REVIEW_PENDING')])); return }
    if (path.match(/^\/api\/requests\/\d+$/)) { await json(route, apiOk(makeRequest(parseInt(path.split('/').pop()!), 'SUBMITTED'))); return }
    if (path === '/api/banks') { await json(route, apiOk(SAMPLE_BANKS)); return }
    if (path === '/api/notifications') { await json(route, paginatedOk([])); return }
    if (path === '/api/notifications/unread-count') { await json(route, apiOk({ count: 2 })); return }
    if (path.endsWith('/history')) { await json(route, apiOk([])); return }

    // Fallback — return empty success for any unmapped endpoint
    await json(route, apiOk(null))
  })
}
