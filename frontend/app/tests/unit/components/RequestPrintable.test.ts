// @vitest-environment jsdom
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import RequestPrintable from '../../../components/requests/RequestPrintable.vue'
import { RequestStatus } from '../../../types/enums'
import type { ImportRequest, RequestDocument, RequestStageHistory } from '../../../types/models'

const REQUEST_PRINTABLE_SOURCE = readFileSync(
  resolve(process.cwd(), 'app/components/requests/RequestPrintable.vue'),
  'utf8',
)

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 42,
    reference_number: 'REQ-2026-0042',
    bank_id: 1,
    bank_name: 'بنك الأمل',
    merchant: null,
    status: RequestStatus.BANK_REVIEW,
    current_owner_role: null as never,
    currency: 'USD',
    amount: 75000,
    supplier_name: 'Global Supplies Ltd',
    goods_description: 'مواد غذائية متنوعة',
    port_of_entry: 'عدن',
    notes: 'ملاحظات الطلب',
    goods_type: 'مواد غذائية',
    payment_terms: 'LC',
    due_date: '2026-06-01T00:00:00Z',
    invoice_number: 'INV-001',
    invoice_date: '2026-05-01T00:00:00Z',
    origin_country: 'الإمارات',
    arrival_port: 'ميناء عدن',
    shipping_port: 'ميناء دبي',
    customs_office: 'جمارك عدن',
    bl_number: 'BL-2026-001',
    created_by: 1,
    created_by_user: { id: 1, name: 'أحمد محمد' },
    submitted_by: null,
    approved_by: null,
    reviewed_by: null,
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
    swift_uploaded_by: null,
    swift_uploaded_at: null,
    voting_opened_by: null,
    voting_opened_at: null,
    voting_closed_by: null,
    voting_closed_at: null,
    voting_session_status: null,
    executive_decided_at: null,
    customs_issued_at: null,
    bank_return_comment: null,
    bank_reject_comment: null,
    support_return_comment: null,
    revision_count: 0,
    created_at: '2026-05-18T08:00:00Z',
    updated_at: '2026-05-18T08:00:00Z',
    ...overrides,
  }
}

function makeDocuments(): RequestDocument[] {
  return [
    {
      id: 1,
      type: 'invoice',
      original_filename: 'invoice-001.pdf',
      mime_type: 'application/pdf',
      size_bytes: 204800,
      checksum: 'abc123',
      uploaded_by: 1,
      uploaded_by_name: 'أحمد محمد',
      uploaded_at: '2026-05-18T09:00:00Z',
    },
    {
      id: 2,
      type: 'bl',
      original_filename: 'bl-2026-001.pdf',
      mime_type: 'application/pdf',
      size_bytes: 102400,
      checksum: 'def456',
      uploaded_by: 2,
      uploaded_by_name: 'سارة',
      uploaded_at: '2026-05-19T11:00:00Z',
    },
  ]
}

function makeHistory(): RequestStageHistory[] {
  return [
    {
      id: 2,
      request_id: 42,
      from_status: RequestStatus.DRAFT,
      to_status: RequestStatus.BANK_REVIEW,
      from_owner_role: 'DATA_ENTRY',
      to_owner_role: 'BANK_REVIEWER',
      actor_id: 2,
      actor_role: 'BANK_REVIEWER',
      performed_by: { id: 2, name: 'سارة', role: 'BANK_REVIEWER' },
      action: 'bank_review',
      notes: null,
      metadata: null,
      created_at: '2026-05-19T11:00:00Z',
    },
    {
      id: 1,
      request_id: 42,
      from_status: null,
      to_status: RequestStatus.DRAFT,
      from_owner_role: null,
      to_owner_role: 'DATA_ENTRY',
      actor_id: 1,
      actor_role: 'DATA_ENTRY',
      performed_by: { id: 1, name: 'أحمد محمد', role: 'DATA_ENTRY' },
      action: 'create',
      notes: null,
      metadata: null,
      created_at: '2026-05-18T08:00:00Z',
    },
  ]
}

function mountPrintable(
  options: {
    request?: ImportRequest
    documents?: RequestDocument[]
    history?: RequestStageHistory[]
  } = {},
) {
  return mount(RequestPrintable, {
    props: {
      request: options.request ?? makeRequest(),
      documents: options.documents ?? makeDocuments(),
      history: options.history ?? makeHistory(),
    },
    global: {
      stubs: {
        AuditTimeline: defineComponent({
          name: 'AuditTimelineStub',
          props: ['entries'],
          setup(props) {
            return () =>
              h('div', { 'data-testid': 'audit-timeline' }, String(props.entries?.length ?? 0))
          },
        }),
      },
    },
  })
}

describe('RequestPrintable', () => {
  it('renders the request heading, reference, and key metadata', () => {
    const wrapper = mountPrintable()

    expect(wrapper.text()).toContain('طلب تمويل واردات')
    expect(wrapper.text()).toContain('REQ-2026-0042')
    expect(wrapper.text()).toContain('بنك الأمل')
    expect(wrapper.text()).toContain('أحمد محمد')
  })

  it('renders wizard fields and request notes', () => {
    const wrapper = mountPrintable()

    expect(wrapper.text()).toContain('مواد غذائية متنوعة')
    expect(wrapper.text()).toContain('Global Supplies Ltd')
    expect(wrapper.text()).toContain('INV-001')
    expect(wrapper.text()).toContain('ملاحظات الطلب')
  })

  it('renders the document list without exposing download URLs', () => {
    const wrapper = mountPrintable()

    expect(wrapper.text()).toContain('invoice-001.pdf')
    expect(wrapper.text()).toContain('bl-2026-001.pdf')
    expect(wrapper.text()).toContain('أحمد محمد')
    expect(wrapper.text()).toContain('سارة')
    expect(wrapper.text()).not.toContain('/api/documents/')
  })

  it('shows an empty state when there are no attached documents', () => {
    const wrapper = mountPrintable({ documents: [] })
    expect(wrapper.text()).toContain('لا توجد مستندات مرفقة.')
  })

  it('renders workflow entries chronologically with stage, actor, and timestamp data', () => {
    const wrapper = mountPrintable()
    const stageItems = wrapper.findAll('.workflow-entry')

    expect(stageItems).toHaveLength(2)
    expect(stageItems[0]?.text()).toContain('مسودة')
    expect(stageItems[0]?.text()).toContain('أحمد محمد')
    expect(stageItems[1]?.text()).toContain('قيد مراجعة البنك')
    expect(stageItems[1]?.text()).toContain('سارة')
  })

  it('does not render mutation controls inside the printable component', () => {
    const wrapper = mountPrintable()

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('[data-testid="audit-timeline"]').text()).toBe('2')
  })

  it('keeps the request document title without adding an institution-name letterhead', () => {
    expect(REQUEST_PRINTABLE_SOURCE).toContain('طلب تمويل واردات')
    expect(REQUEST_PRINTABLE_SOURCE).not.toContain('البنك المركزي اليمني')
    expect(REQUEST_PRINTABLE_SOURCE).not.toContain('اللجنة الوطنية لتنظيم وتمويل الواردات')
  })
})
