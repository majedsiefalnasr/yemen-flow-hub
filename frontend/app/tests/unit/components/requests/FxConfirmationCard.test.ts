// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import FxConfirmationCard from '../../../../components/requests/FxConfirmationCard.vue'
import { RequestStatus, UserRole } from '../../../../types/enums'
import type { ImportRequest } from '../../../../types/models'
import { useRequestsStore } from '../../../../stores/requests.store'

const mocks = vi.hoisted(() => ({
  downloadFxConfirmationTemplate: vi.fn(),
  toastSuccess: vi.fn(),
  toastError: vi.fn(),
}))

vi.mock('../../../../composables/useRequests', () => ({
  useRequests: () => ({ downloadFxConfirmationTemplate: mocks.downloadFxConfirmationTemplate }),
}))

vi.mock('vue-sonner', () => ({
  toast: {
    success: mocks.toastSuccess,
    error: mocks.toastError,
  },
}))

const uiStubs = {
  Alert: { template: '<div><slot /></div>' },
  AlertDescription: { template: '<div><slot /></div>' },
  AlertDialog: { template: '<div><slot /></div>' },
  AlertDialogAction: { template: '<button type="button"><slot /></button>' },
  AlertDialogCancel: { template: '<button type="button"><slot /></button>' },
  AlertDialogContent: { template: '<div><slot /></div>' },
  AlertDialogDescription: { template: '<div><slot /></div>' },
  AlertDialogFooter: { template: '<div><slot /></div>' },
  AlertDialogHeader: { template: '<div><slot /></div>' },
  AlertDialogTitle: { template: '<div><slot /></div>' },
  AlertDialogTrigger: { template: '<div><slot /></div>' },
  Button: { template: '<button type="button" :disabled="$attrs.disabled"><slot /></button>' },
  Card: { template: '<section><slot /></section>' },
  CardContent: { template: '<div><slot /></div>' },
  CardHeader: { template: '<header><slot /></header>' },
  CardTitle: { template: '<h2><slot /></h2>' },
}

function makeRequest(status: RequestStatus): ImportRequest {
  return {
    id: 42,
    reference_number: 'YFH-2026-000042',
    status,
    current_owner_role: UserRole.COMMITTEE_DIRECTOR,
    bank_id: 1,
    bank_name: 'بنك اليمن',
    created_by: 1,
    currency: 'USD',
    amount: 10000,
    supplier_name: 'Supplier Co.',
    goods_description: 'معدات صناعية',
    port_of_entry: 'ميناء عدن',
    documents: [],
    created_at: '2026-06-03T09:00:00Z',
    updated_at: '2026-06-03T09:00:00Z',
  } as unknown as ImportRequest
}

function makeFile(name: string, type: string, sizeBytes: number): File {
  const blob = new Blob(['x'.repeat(Math.min(sizeBytes, 32))], { type })
  return Object.defineProperties(new File([blob], name, { type }), {
    size: { value: sizeBytes, configurable: true },
  })
}

function mountCard(status = RequestStatus.EXECUTIVE_APPROVED) {
  return mount(FxConfirmationCard, {
    props: { request: makeRequest(status) },
    global: { stubs: uiStubs },
  })
}

describe('FxConfirmationCard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the three-step layout for EXECUTIVE_APPROVED requests', () => {
    const wrapper = mountCard()

    expect(wrapper.text()).toContain('الخطوة 1')
    expect(wrapper.text()).toContain('الخطوة 2')
    expect(wrapper.text()).toContain('الخطوة 3')
    expect(wrapper.text()).toContain('تحميل النموذج المعبأ')
  })

  it('keeps issue disabled on EXECUTIVE_APPROVED until upload succeeds', () => {
    const wrapper = mountCard()

    const issueButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('إصدار وثيقة'))
    expect(issueButton?.attributes('disabled')).toBeDefined()
  })

  it('enables issue when the request is already FX_CONFIRMATION_PENDING', () => {
    const wrapper = mountCard(RequestStatus.FX_CONFIRMATION_PENDING)

    expect(wrapper.text()).toContain('تم رفع الوثيقة الموقعة في جلسة سابقة')
    const issueButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('إصدار وثيقة'))
    expect(issueButton?.attributes('disabled')).toBeUndefined()
  })

  it('rejects non-PDF uploads with Arabic error text', async () => {
    const wrapper = mountCard()
    const input = wrapper.get('input[type="file"]')

    Object.defineProperty(input.element, 'files', {
      value: [makeFile('scan.jpg', 'image/jpeg', 1024)],
      configurable: true,
    })
    await input.trigger('change')

    expect(wrapper.text()).toContain('يجب أن يكون الملف بصيغة PDF فقط')
  })

  it('rejects files over 10MB', async () => {
    const wrapper = mountCard()
    const input = wrapper.get('input[type="file"]')

    Object.defineProperty(input.element, 'files', {
      value: [makeFile('large.pdf', 'application/pdf', 11 * 1024 * 1024)],
      configurable: true,
    })
    await input.trigger('change')

    expect(wrapper.text()).toContain('حجم الملف يتجاوز 10MB')
  })

  it('enables issue after a successful upload in the current session', async () => {
    const wrapper = mountCard()
    const store = useRequestsStore()
    store.uploadSignedFxDoc = vi.fn(async () => {
      store.signedFxUploaded = true
    }) as typeof store.uploadSignedFxDoc

    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', {
      value: [makeFile('signed.pdf', 'application/pdf', 1024)],
      configurable: true,
    })
    await input.trigger('change')

    const uploadButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('رفع الوثيقة'))
    await uploadButton?.trigger('click')

    const issueButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('إصدار وثيقة'))
    expect(issueButton?.attributes('disabled')).toBeUndefined()
  })
})
