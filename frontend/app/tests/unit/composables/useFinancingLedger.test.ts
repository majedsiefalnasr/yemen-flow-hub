// @vitest-environment jsdom
import { ref, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useFinancingLedger } = await import('../../../composables/useFinancingLedger')

describe('useFinancingLedger', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mockGet.mockReset()
    mockGet.mockResolvedValue({
      success: true,
      data: {
        used_percent: 60,
        remaining_percent: 40,
        blocked: false,
      },
    })
  })

  it('maps successful utilization response', async () => {
    const taxNumber = ref('TAX-1')
    const invoiceNumber = ref('INV-1')
    const ledger = useFinancingLedger({ taxNumber, invoiceNumber })

    await vi.runAllTimersAsync()
    await nextTick()

    expect(mockGet).toHaveBeenCalledWith(
      '/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1',
    )
    expect(ledger.usedPercent.value).toBe(60)
    expect(ledger.remainingPercent.value).toBe(40)
    expect(ledger.blocked.value).toBe(false)
    expect(ledger.loading.value).toBe(false)
  })

  it('does not fetch when tax number or invoice number is incomplete', async () => {
    const taxNumber = ref('TAX-1')
    const invoiceNumber = ref('')
    useFinancingLedger({ taxNumber, invoiceNumber })

    await vi.runAllTimersAsync()

    expect(mockGet).not.toHaveBeenCalled()
  })

  it('debounces refetch when keys change', async () => {
    const taxNumber = ref('TAX-1')
    const invoiceNumber = ref('INV-1')
    useFinancingLedger({ taxNumber, invoiceNumber })

    await vi.advanceTimersByTimeAsync(400)
    expect(mockGet).toHaveBeenCalledTimes(1)

    taxNumber.value = 'TAX-2'
    invoiceNumber.value = 'INV-2'

    await vi.advanceTimersByTimeAsync(400)
    expect(mockGet).toHaveBeenCalledTimes(2)
    expect(mockGet).toHaveBeenLastCalledWith(
      '/api/financing/utilization?tax_number=TAX-2&invoice_number=INV-2',
    )
  })

  it('sets error state on fetch failure without mutating anything', async () => {
    mockGet.mockRejectedValueOnce(new Error('network'))
    const taxNumber = ref('TAX-ERR')
    const invoiceNumber = ref('INV-ERR')
    const ledger = useFinancingLedger({ taxNumber, invoiceNumber })

    await vi.runAllTimersAsync()
    await nextTick()

    expect(ledger.error.value).toContain('تعذّر تحميل')
    expect(ledger.usedPercent.value).toBeNull()
    expect(ledger.blocked.value).toBe(false)
  })

  it('passes exclude_request_id when provided', async () => {
    const taxNumber = ref('TAX-3')
    const invoiceNumber = ref('INV-3')
    const excludeRequestId = ref(42)
    useFinancingLedger({ taxNumber, invoiceNumber, excludeRequestId })

    await vi.runAllTimersAsync()

    expect(mockGet).toHaveBeenCalledWith(
      '/api/financing/utilization?tax_number=TAX-3&invoice_number=INV-3&exclude_request_id=42',
    )
  })
})
