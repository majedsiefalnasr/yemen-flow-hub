import { expect, test } from '@playwright/test'

type Role = 'COMMITTEE_DIRECTOR' | 'SWIFT_OFFICER'
type Status =
  | 'EXECUTIVE_VOTING_OPEN'
  | 'EXECUTIVE_VOTING_CLOSED'
  | 'FX_CONFIRMATION_PENDING'
  | 'WAITING_FOR_SWIFT'
  | 'SWIFT_UPLOADED'

function apiResponse<T>(data: T) {
  return { success: true, message: 'OK', data }
}

function makeUser(role: Role) {
  return {
    id: role === 'COMMITTEE_DIRECTOR' ? 5001 : 5002,
    name: role === 'COMMITTEE_DIRECTOR' ? 'مدير اللجنة' : 'موظف السويفت',
    email: role === 'COMMITTEE_DIRECTOR' ? 'director@cby.gov.ye' : 'swift@ybrd.com.ye',
    role,
    bank_id: role === 'SWIFT_OFFICER' ? 11 : null,
    bank_name_ar: role === 'SWIFT_OFFICER' ? 'البنك اليمني للإنشاء والتعمير' : null,
    bank_name_en: role === 'SWIFT_OFFICER' ? 'YBRD' : null,
    is_active: true,
  }
}

function makeRequest(id: number, status: Status) {
  return {
    id,
    reference_number: `YFH-2026-${id.toString().padStart(6, '0')}`,
    bank_id: 11,
    bank_name: 'البنك اليمني للإنشاء والتعمير',
    merchant: { id: 91, name: 'شركة التاجر الذهبي', commercial_register: 'CR-1001' },
    status,
    current_owner_role:
      status === 'WAITING_FOR_SWIFT' || status === 'SWIFT_UPLOADED'
        ? 'SWIFT_OFFICER'
        : 'COMMITTEE_DIRECTOR',
    currency: 'USD',
    amount: 145000,
    supplier_name: 'Global Supplier Co.',
    goods_description: 'معدات صناعية',
    port_of_entry: 'عدن',
    notes: null,
    goods_type: null,
    payment_terms: 'LC 90',
    due_date: null,
    invoice_number: 'INV-7788',
    invoice_date: '2026-05-10',
    origin_country: 'CN',
    arrival_port: 'عدن',
    shipping_port: 'شنغهاي',
    customs_office: null,
    bl_number: 'BL-778899',
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
    can_be_claimed: false,
    submitted_at: '2026-05-11T08:10:00.000Z',
    bank_approved_at: '2026-05-12T09:10:00.000Z',
    support_approved_at: '2026-05-13T10:10:00.000Z',
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
    updated_at: '2026-05-22T08:00:00.000Z',
    ready_to_close: status === 'EXECUTIVE_VOTING_OPEN',
    is_tie: status === 'EXECUTIVE_VOTING_OPEN',
    has_swift_document: status === 'SWIFT_UPLOADED' || status === 'FX_CONFIRMATION_PENDING',
    has_fx_request_document: status === 'SWIFT_UPLOADED' || status === 'FX_CONFIRMATION_PENDING',
    documents: [
      {
        id: 7001,
        type: 'swift_document',
        original_filename: 'swift.pdf',
        mime_type: 'application/pdf',
        size_bytes: 120000,
        checksum: 'abc123',
        uploaded_by: 1,
        uploaded_by_name: 'system',
        uploaded_at: '2026-05-20T10:00:00.000Z',
        download_url: 'http://localhost:8000/api/documents/7001/download',
      },
    ],
  }
}

async function mockApi(page: Parameters<typeof test>[0]['page'], role: Role) {
  const directorRequest = makeRequest(9001, 'FX_CONFIRMATION_PENDING')
  const swiftWaitingRequest = makeRequest(9101, 'WAITING_FOR_SWIFT')
  const swiftDoneRequest = makeRequest(9102, 'SWIFT_UPLOADED')
  const user = makeUser(role)
  const corsHeaders = {
    'access-control-allow-origin': 'http://localhost:3000',
    'access-control-allow-credentials': 'true',
  }

  const fulfillJson = async (
    route: Parameters<Parameters<typeof page.route>[1]>[0],
    body: unknown,
  ) => {
    await route.fulfill({
      status: 200,
      headers: {
        ...corsHeaders,
        'content-type': 'application/json',
      },
      body: JSON.stringify(body),
    })
  }

  await page.route('**/sanctum/csrf-cookie', async (route) => {
    const url = new URL(route.request().url())
    if (!['localhost:8000', '127.0.0.1:8000'].includes(url.host)) {
      await route.fallback()
      return
    }
    await route.fulfill({
      status: 204,
      headers: {
        ...corsHeaders,
        'set-cookie': 'XSRF-TOKEN=test-xsrf; Path=/',
      },
      body: '',
    })
  })

  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname
    if (!['localhost:8000', '127.0.0.1:8000'].includes(url.host)) {
      await route.fallback()
      return
    }

    if (path === '/api/auth/me') {
      await fulfillJson(route, apiResponse(user))
      return
    }

    if (path === '/api/auth/login') {
      await fulfillJson(
        route,
        apiResponse({ user, token: null, token_type: null, mode: 'cookie', requires_mfa: false }),
      )
      return
    }

    if (path === '/api/dashboard/stats') {
      const data =
        role === 'COMMITTEE_DIRECTOR'
          ? {
              active_voting_sessions: 2,
              fx_confirmation_pending: 1,
              finalized_approved: 3,
              finalized_rejected: 1,
              sessions_ready_to_close: 1,
              sessions_with_tie: 1,
              voting_lifecycle_queue: [
                {
                  ...makeRequest(9201, 'EXECUTIVE_VOTING_OPEN'),
                  votes_cast: 6,
                  total_voters: 6,
                  my_vote: null,
                  ready_to_close: true,
                  is_tie: true,
                },
              ],
              fx_confirmation_queue: [directorRequest],
            }
          : {
              pending_swift_upload: 1,
              uploaded: 1,
              final_approved: 2,
              final_rejected: 1,
              swift_queue: [swiftWaitingRequest, swiftDoneRequest],
            }
      await fulfillJson(route, apiResponse(data))
      return
    }

    if (path === '/api/requests') {
      const items =
        role === 'COMMITTEE_DIRECTOR' ? [directorRequest] : [swiftWaitingRequest, swiftDoneRequest]
      await fulfillJson(
        route,
        apiResponse({
          data: items,
          meta: { current_page: 1, last_page: 1, per_page: 25, total: items.length },
        }),
      )
      return
    }

    if (path === '/api/requests/9001') {
      await fulfillJson(route, apiResponse(directorRequest))
      return
    }

    if (path === '/api/requests/9101') {
      await fulfillJson(route, apiResponse(swiftWaitingRequest))
      return
    }

    if (path === '/api/requests/9102') {
      await fulfillJson(route, apiResponse(swiftDoneRequest))
      return
    }

    if (path.endsWith('/history')) {
      await fulfillJson(route, apiResponse([]))
      return
    }

    if (path.includes('/api/voting/')) {
      await fulfillJson(
        route,
        apiResponse({
          request: directorRequest,
          tally: { approve_count: 3, reject_count: 3, abstain_count: 0, auto_abstain_count: 0 },
          votes: [],
          total_members: 6,
          my_vote: null,
        }),
      )
      return
    }

    await fulfillJson(route, apiResponse({}))
  })
}

test.describe('Story 12-3 gate verification', () => {
  async function loginAs(page: Parameters<typeof test>[0]['page'], email: string) {
    await page.goto('/login')
    await page.fill('#email', email)
    await page.fill('#password', 'password')
    await page.click('button.submit-btn')
    await expect(page).toHaveURL(/\/dashboard/)
  }

  test('Committee Director role surfaces and gating', async ({ page }) => {
    await mockApi(page, 'COMMITTEE_DIRECTOR')
    await loginAs(page, 'director@cby.gov.ye')
    await expect(page.getByText(/جلسات تصويت اكتملت وتنتظر الإغلاق/)).toBeVisible()
    await expect(page.getByText(/جلسات تصويت بتعادل — يتطلب حسماً/)).toBeVisible()
    await expect(page.getByText('قائمة انتظار تأكيد المصارفة الخارجية')).toBeVisible()
    await expect(page.getByText('طابور السويفت')).toHaveCount(0)
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/director-dashboard.png',
      fullPage: true,
    })

    await page
      .getByRole('link', { name: /طلبات التمويل/ })
      .first()
      .click()
    await expect(page).toHaveURL(/\/requests/)
    await expect(page.getByRole('tab', { name: 'جاهزة للإغلاق' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'جاهزة للإصدار النهائي' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'تعادل — يحتاج حسماً' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'بانتظار تأكيد المصارفة' })).toBeVisible()
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/director-requests.png',
      fullPage: true,
    })

    await page.getByText('YFH-2026-009001').first().click()
    await expect(page).toHaveURL(/\/requests\/9001/)
    await expect(page.getByRole('tab', { name: 'المعلومات' })).toBeVisible()
    await expect(page.getByText('بانتظار تأكيد المصارفة')).toBeVisible()
    await expect(page.getByText('رفع وثائق السويفت')).toHaveCount(0)
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/director-request-detail-fx.png',
      fullPage: true,
    })
  })

  test('SWIFT Officer role surfaces, submit gate, and denied state', async ({ page }) => {
    await mockApi(page, 'SWIFT_OFFICER')
    await loginAs(page, 'swift@ybrd.com.ye')
    await expect(page.getByText(/طلبات بانتظار رفع وثائق السويفت/)).toBeVisible()
    await expect(page.getByText('طلب تأكيد المصارفة').first()).toBeVisible()
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/swift-dashboard.png',
      fullPage: true,
    })

    await page
      .getByRole('link', { name: /طلبات التمويل/ })
      .first()
      .click()
    await expect(page).toHaveURL(/\/requests/)
    await expect(page.getByRole('tab', { name: 'بانتظار رفع السويفت' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'تم رفع السويفت' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'مكتمل' })).toBeVisible()
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/swift-requests.png',
      fullPage: true,
    })

    await page.getByText('YFH-2026-009101').first().click()
    await expect(page).toHaveURL(/\/requests\/9101/)
    await page.getByRole('link', { name: 'رفع وثائق السويفت' }).first().click()
    await expect(page).toHaveURL(/\/requests\/9101\/swift/)
    await expect(page.getByText('ملخص بيانات الطلب (مقفلة)')).toBeVisible()
    await expect(page.getByRole('button', { name: 'تسليم وثائق السويفت' })).toBeDisabled()
    await expect(page.getByText('أدخل رقم مرجع السويفت أولاً')).toBeVisible()
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/swift-upload-gate.png',
      fullPage: true,
    })

    await page.goBack()
    await expect(page).toHaveURL(/\/requests\/9101/)
    await page
      .getByRole('link', { name: /طلبات التمويل/ })
      .first()
      .click()
    await page.getByText('YFH-2026-009102').first().click()
    await expect(page).toHaveURL(/\/requests\/9102/)
    await expect(
      page.getByText(
        'تم تسليم السويفت — انتقلت المسؤولية إلى مدير اللجنة التنفيذية لإتمام تأكيد المصارفة الخارجية.',
      ),
    ).toBeVisible()
    await page.screenshot({
      path: '../docs/ui-parity/screenshots/12-3/after/swift-upload-denied.png',
      fullPage: true,
    })
  })
})
