// @vitest-environment jsdom
import { ref, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useMerchantAutofill } = await import('../../../composables/useMerchantAutofill')

function merchantResponse(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    success: true,
    data: [
      {
        id: 7,
        bank_id: 1,
        bank_name: 'بنك',
        name: 'شركة ثابت إخوان',
        tax_number: 'TAX-1',
        tax_card_expiry: '2027-01-01',
        phone: null,
        address: null,
        status: 'ACTIVE',
        version: 1,
        transaction_count: 0,
        owners: [{ id: 1, name: 'أحمد ثابت', ownership_percentage: 60 }],
        companies: [
          {
            id: 21,
            name: 'شركة ثابت للاستيراد',
            commercial_registration_number: 'CR-100',
            commercial_registration_expiry: '2029-05-01',
            sector_reference_value_id: null,
            is_active: true,
          },
        ],
        created_by: null,
        created_at: null,
        updated_at: null,
        ...overrides,
      },
    ],
  }
}

describe('useMerchantAutofill', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mockGet.mockReset()
  })

  it('does nothing when the tax number is empty', async () => {
    const taxNumber = ref('')
    useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()

    expect(mockGet).not.toHaveBeenCalled()
  })

  it('debounces the lookup by 400ms and queries an exact tax_number match', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const taxNumber = ref('TAX-1')
    useMerchantAutofill(taxNumber)

    await vi.advanceTimersByTimeAsync(399)
    expect(mockGet).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(mockGet).toHaveBeenCalledTimes(1)
    expect(mockGet).toHaveBeenCalledWith(
      '/api/v1/merchants',
      expect.objectContaining({ query: { tax_number: 'TAX-1', per_page: 1 } }),
    )
  })

  it('goes through loading then matched on a successful exact match', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.advanceTimersByTimeAsync(1)
    expect(autofill.status.value).toBe('loading')

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.status.value).toBe('matched')
    expect(autofill.autofillValues.value).toEqual({
      merchantId: 7,
      companyId: 21,
      taxCardExpiry: '2027-01-01',
      commercialRegistrationNumber: 'CR-100',
      commercialRegistrationExpiry: '2029-05-01',
      ownersText: 'أحمد ثابت (60%)',
    })
  })

  it('formats multiple owners as one line per owner', async () => {
    mockGet.mockResolvedValue(
      merchantResponse({
        owners: [
          { id: 1, name: 'أحمد ثابت', ownership_percentage: 60 },
          { id: 2, name: 'سالم ثابت', ownership_percentage: 40 },
        ],
      }),
    )
    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.autofillValues.value.ownersText).toBe('أحمد ثابت (60%)\nسالم ثابت (40%)')
  })

  it('reports no_match and blocks continuation when no merchant matches', async () => {
    mockGet.mockResolvedValue({ success: true, data: [] })
    const taxNumber = ref('TAX-404')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.status.value).toBe('no_match')
    expect(autofill.blocksContinue.value).toBe(true)
    expect(autofill.noMatchMessage).not.toMatch(/error|null|undefined|exception/i)
  })

  it('sets a retryable error state on lookup failure without leaking raw error text', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network request failed'))
    const taxNumber = ref('TAX-ERR')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.status.value).toBe('error')
    expect(autofill.errorMessage).not.toContain('Network request failed')
    expect(autofill.matchedMerchant.value).toBeNull()
  })

  it('auto-selects the single company and does not require a manual choice', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.selectedCompanyId.value).toBe(21)
    expect(autofill.requiresCompanyChoice.value).toBe(false)
  })

  it('requires a manual company choice when the merchant has multiple companies', async () => {
    mockGet.mockResolvedValue(
      merchantResponse({
        companies: [
          {
            id: 21,
            name: 'شركة أ',
            commercial_registration_number: 'CR-100',
            commercial_registration_expiry: null,
            sector_reference_value_id: null,
            is_active: true,
          },
          {
            id: 22,
            name: 'شركة ب',
            commercial_registration_number: 'CR-200',
            commercial_registration_expiry: null,
            sector_reference_value_id: null,
            is_active: true,
          },
        ],
      }),
    )
    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.requiresCompanyChoice.value).toBe(true)
    expect(autofill.selectedCompanyId.value).toBeNull()
    expect(autofill.companies.value).toHaveLength(2)

    autofill.selectedCompanyId.value = 22
    await nextTick()

    expect(autofill.autofillValues.value.commercialRegistrationNumber).toBe('CR-200')
  })

  it('ignores a stale response when a newer lookup has already resolved', async () => {
    let resolveFirst!: (value: unknown) => void
    mockGet
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveFirst = resolve
          }),
      )
      .mockResolvedValueOnce(merchantResponse({ id: 9, tax_number: 'TAX-2' }))

    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.advanceTimersByTimeAsync(400)
    expect(mockGet).toHaveBeenCalledTimes(1)

    taxNumber.value = 'TAX-2'
    await vi.advanceTimersByTimeAsync(400)
    expect(mockGet).toHaveBeenCalledTimes(2)
    await nextTick()
    expect(autofill.status.value).toBe('matched')
    expect(autofill.matchedMerchant.value?.id).toBe(9)

    // The slow first request for TAX-1 resolves after the second one already matched.
    resolveFirst(merchantResponse({ id: 7, tax_number: 'TAX-1' }))
    await nextTick()
    await nextTick()

    expect(autofill.matchedMerchant.value?.id).toBe(9)
  })

  it('clears every dependent value and company option when the tax number changes', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()
    expect(autofill.status.value).toBe('matched')

    taxNumber.value = 'TAX-1-EDITED'
    await nextTick()

    expect(autofill.status.value).toBe('loading')
    expect(autofill.matchedMerchant.value).toBeNull()
    expect(autofill.companies.value).toEqual([])
    expect(autofill.autofillValues.value).toEqual({
      merchantId: null,
      companyId: null,
      taxCardExpiry: null,
      commercialRegistrationNumber: null,
      commercialRegistrationExpiry: null,
      ownersText: null,
    })
  })

  it('goes idle immediately when the tax number is cleared, without a lookup', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const taxNumber = ref('TAX-1')
    const autofill = useMerchantAutofill(taxNumber)

    await vi.runAllTimersAsync()
    await nextTick()
    mockGet.mockClear()

    taxNumber.value = ''
    await nextTick()

    expect(autofill.status.value).toBe('idle')
    expect(autofill.matchedMerchant.value).toBeNull()
    await vi.runAllTimersAsync()
    expect(mockGet).not.toHaveBeenCalled()
  })

  it('confirms an already-saved draft tax number without first clearing it as idle', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const taxNumber = ref('TAX-1') // simulates draft hydration with a saved value

    const autofill = useMerchantAutofill(taxNumber)

    // Must not have gone through 'idle' with cleared dependents before the lookup resolves.
    expect(autofill.status.value).not.toBe('idle')

    await vi.runAllTimersAsync()
    await nextTick()

    expect(autofill.status.value).toBe('matched')
    expect(autofill.autofillValues.value.merchantId).toBe(7)
  })
})
