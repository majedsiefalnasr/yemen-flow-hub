import { vi, describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// ── Mocks ─────────────────────────────────────────────────────────────────────

const mockCreateRequest = vi.fn()
const mockUpdateRequest = vi.fn()
const mockPerformWorkflowAction = vi.fn()
const mockFetch = vi.fn().mockResolvedValue({})

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    createRequest: mockCreateRequest,
    updateRequest: mockUpdateRequest,
    performWorkflowAction: mockPerformWorkflowAction,
    uploadDocument: vi.fn(),
  }),
}))

vi.mock('../../../composables/useMerchants', () => ({
  useMerchants: () => ({
    fetchMerchants: vi.fn().mockResolvedValue([]),
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { role: 'BANK_ADMIN', bank_id: 1 },
  }),
}))

vi.mock('#app', () => ({
  useRuntimeConfig: () => ({ public: { apiBase: 'http://localhost' } }),
}))

// Prevent $fetch from being called in tests
globalThis.$fetch = Object.assign(mockFetch, {
  raw: vi.fn(),
  create: vi.fn(() => mockFetch),
}) as unknown as typeof $fetch

const { useRequestWizard } = await import('../../../composables/useRequestWizard')

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeWizard() {
  return useRequestWizard()
}

function fillStep1(wizard: ReturnType<typeof makeWizard>): void {
  wizard.step1.value = {
    goods_type: 'مواد غذائية',
    amount: 50000,
    currency: 'USD',
    payment_terms: 'LC',
    due_date: '',
    merchant_id: 5,
    notes: '',
  }
}

function fillStep2(wizard: ReturnType<typeof makeWizard>): void {
  wizard.step2.value = {
    supplier_name: 'Cargill Inc.',
    invoice_number: 'INV-001',
    origin_country: 'الولايات المتحدة',
    invoice_date: '2025-01-01',
    arrival_port: 'ميناء عدن',
    shipping_port: '',
    customs_office: 'جمارك عدن',
    bl_number: '',
  }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('useRequestWizard — initial state', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('starts at step 1', () => {
    const wizard = makeWizard()
    expect(wizard.currentStep.value).toBe(1)
  })

  it('has 4 total steps', () => {
    const wizard = makeWizard()
    expect(wizard.totalSteps).toBe(4)
  })

  it('first step is active, rest future', () => {
    const wizard = makeWizard()
    expect(wizard.stepStatuses.value).toEqual(['active', 'future', 'future', 'future'])
  })

  it('acknowledged starts false', () => {
    const wizard = makeWizard()
    expect(wizard.acknowledged.value).toBe(false)
  })
})

describe('useRequestWizard — navigation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('nextStep with invalid step1 returns false and stays on step 1', () => {
    const wizard = makeWizard()
    const ok = wizard.nextStep()
    expect(ok).toBe(false)
    expect(wizard.currentStep.value).toBe(1)
  })

  it('nextStep with valid step1 advances to step 2', () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    const ok = wizard.nextStep()
    expect(ok).toBe(true)
    expect(wizard.currentStep.value).toBe(2)
  })

  it('prevStep decrements step', () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    wizard.nextStep()
    wizard.prevStep()
    expect(wizard.currentStep.value).toBe(1)
  })

  it('prevStep does nothing on step 1', () => {
    const wizard = makeWizard()
    wizard.prevStep()
    expect(wizard.currentStep.value).toBe(1)
  })

  it('goToStep navigates to a completed step', () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    wizard.nextStep()
    wizard.goToStep(1)
    expect(wizard.currentStep.value).toBe(1)
  })

  it('goToStep does not jump forward beyond current', () => {
    const wizard = makeWizard()
    wizard.goToStep(3)
    expect(wizard.currentStep.value).toBe(1)
  })

  it('step statuses update correctly after advancing', () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    wizard.nextStep()
    expect(wizard.stepStatuses.value[0]).toBe('completed')
    expect(wizard.stepStatuses.value[1]).toBe('active')
  })
})

describe('useRequestWizard — step1 validation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('validates and returns errors for empty step1', () => {
    const wizard = makeWizard()
    const ok = wizard.validateStep1()
    expect(ok).toBe(false)
    expect(Object.keys(wizard.step1Errors.value).length).toBeGreaterThan(0)
  })

  it('clears errors on valid step1', () => {
    const wizard = makeWizard()
    wizard.validateStep1()
    fillStep1(wizard)
    const ok = wizard.validateStep1()
    expect(ok).toBe(true)
    expect(Object.keys(wizard.step1Errors.value).length).toBe(0)
  })

  it('errors contain goods_type when missing', () => {
    const wizard = makeWizard()
    wizard.validateStep1()
    expect(wizard.step1Errors.value.goods_type).toBeDefined()
  })

  it('errors contain merchant_id when missing', () => {
    const wizard = makeWizard()
    wizard.validateStep1()
    expect(wizard.step1Errors.value.merchant_id).toBeDefined()
  })
})

describe('useRequestWizard — step2 validation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('validates and returns errors for empty step2', () => {
    const wizard = makeWizard()
    const ok = wizard.validateStep2()
    expect(ok).toBe(false)
    expect(Object.keys(wizard.step2Errors.value).length).toBeGreaterThan(0)
  })

  it('clears errors on valid step2', () => {
    const wizard = makeWizard()
    fillStep2(wizard)
    const ok = wizard.validateStep2()
    expect(ok).toBe(true)
    expect(Object.keys(wizard.step2Errors.value).length).toBe(0)
  })
})

describe('useRequestWizard — step3 validation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('fails when required uploads missing', () => {
    const wizard = makeWizard()
    const ok = wizard.validateStep3()
    expect(ok).toBe(false)
    expect(wizard.step3Errors.value.proforma_invoice).toBeDefined()
    expect(wizard.step3Errors.value.commercial_register).toBeDefined()
    expect(wizard.step3Errors.value.tax_card).toBeDefined()
  })

  it('passes when all required uploads present', () => {
    const wizard = makeWizard()
    const file = new File(['content'], 'test.pdf', { type: 'application/pdf' })
    wizard.step3.value = {
      proforma_invoice: file,
      commercial_register: file,
      tax_card: file,
      extra_docs: null,
    }
    const ok = wizard.validateStep3()
    expect(ok).toBe(true)
    expect(Object.keys(wizard.step3Errors.value).length).toBe(0)
  })

  it('passes when optional extra_docs is null', () => {
    const wizard = makeWizard()
    const file = new File(['content'], 'test.pdf', { type: 'application/pdf' })
    wizard.step3.value = {
      proforma_invoice: file,
      commercial_register: file,
      tax_card: file,
      extra_docs: null,
    }
    expect(wizard.validateStep3()).toBe(true)
  })
})

describe('useRequestWizard — port auto-fill', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('auto-fills customs_office when arrival_port is set', () => {
    const wizard = makeWizard()
    wizard.onArrivalPortChange('ميناء عدن')
    expect(wizard.step2.value.arrival_port).toBe('ميناء عدن')
    expect(wizard.step2.value.customs_office).toBe('جمارك عدن')
  })

  it('sets autoFillChip to true after port change', () => {
    const wizard = makeWizard()
    wizard.onArrivalPortChange('ميناء الحديدة')
    expect(wizard.autoFillChip.value).toBe(true)
  })

  it('does not set customs_office for unknown port', () => {
    const wizard = makeWizard()
    wizard.step2.value.customs_office = ''
    wizard.onArrivalPortChange('ميناء مجهول')
    expect(wizard.step2.value.customs_office).toBe('')
  })
})

describe('useRequestWizard — saveDraft', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('calls createRequest with built payload on first save', async () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    fillStep2(wizard)
    const fakeRequest = { id: 42, status: 'DRAFT' }
    mockCreateRequest.mockResolvedValueOnce(fakeRequest)

    const result = await wizard.saveDraft()

    expect(mockCreateRequest).toHaveBeenCalledOnce()
    expect(result).toEqual(fakeRequest)
    expect(wizard.savedRequestId.value).toBe(42)
  })

  it('calls updateRequest on second save', async () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    fillStep2(wizard)
    wizard.savedRequestId.value = 42
    const fakeRequest = { id: 42 }
    mockUpdateRequest.mockResolvedValueOnce(fakeRequest)

    await wizard.saveDraft()

    expect(mockUpdateRequest).toHaveBeenCalledWith(42, expect.any(Object))
    expect(mockCreateRequest).not.toHaveBeenCalled()
  })

  it('sets saveError on failure', async () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    mockCreateRequest.mockRejectedValueOnce(new Error('network error'))

    const result = await wizard.saveDraft()

    expect(result).toBeNull()
    expect(wizard.saveError.value).toBeTruthy()
  })
})

describe('useRequestWizard — buildPayload', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('includes all step1 and step2 fields', () => {
    const wizard = makeWizard()
    fillStep1(wizard)
    fillStep2(wizard)
    const payload = wizard.buildPayload()

    expect(payload.merchant_id).toBe(5)
    expect(payload.currency).toBe('USD')
    expect(payload.amount).toBe(50000)
    expect(payload.goods_type).toBe('مواد غذائية')
    expect(payload.payment_terms).toBe('LC')
    expect(payload.supplier_name).toBe('Cargill Inc.')
    expect(payload.invoice_number).toBe('INV-001')
    expect(payload.origin_country).toBe('الولايات المتحدة')
    expect(payload.arrival_port).toBe('ميناء عدن')
    expect(payload.customs_office).toBe('جمارك عدن')
  })
})
