// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import FinancingUtilizationBar from '../../../components/request/FinancingUtilizationBar.vue'

const ledgerState = {
  usedPercent: ref<number | null>(60),
  remainingPercent: ref<number | null>(15),
  blocked: ref(false),
  loading: ref(false),
  error: ref<string | null>(null),
  refresh: vi.fn(),
}

vi.mock('../../../composables/useFinancingLedger', async () => {
  const actual = await vi.importActual<typeof import('../../../composables/useFinancingLedger')>(
    '../../../composables/useFinancingLedger',
  )

  return {
    ...actual,
    useFinancingLedger: () => ledgerState,
  }
})

const stubs = {
  Card: { template: '<div><slot /></div>' },
  CardHeader: { template: '<div><slot /></div>' },
  CardTitle: { template: '<h3><slot /></h3>' },
  CardContent: { template: '<div><slot /></div>' },
  Progress: {
    template: '<div data-test="financing-progress" :data-value="modelValue" />',
    props: ['modelValue'],
  },
  Badge: { template: '<span><slot /></span>' },
  Alert: { template: '<div role="alert"><slot /></div>', props: ['variant'] },
  AlertDescription: { template: '<div><slot /></div>' },
  Skeleton: { template: '<div data-test="skeleton" />' },
}

describe('FinancingUtilizationBar', () => {
  it('renders used and remaining percentages with progress', async () => {
    const wrapper = mount(FinancingUtilizationBar, {
      props: {
        taxNumber: 'TAX-1',
        invoiceNumber: 'INV-1',
        requestPercentage: 10,
      },
      global: { stubs },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('المستخدم: 60.00%')
    expect(wrapper.text()).toContain('المتبقي: 15.00%')
    expect(wrapper.find('[data-test="financing-progress"]').attributes('data-value')).toBe('60')
    expect(wrapper.find('[data-test="financing-low-remaining"]').exists()).toBe(true)
  })

  it('emits advisory-block when requested percentage exceeds remaining', async () => {
    const wrapper = mount(FinancingUtilizationBar, {
      props: {
        taxNumber: 'TAX-1',
        invoiceNumber: 'INV-1',
        requestPercentage: 20,
      },
      global: { stubs },
    })

    await flushPromises()

    expect(wrapper.emitted('advisory-block')?.at(-1)).toEqual([true])
    expect(wrapper.find('[data-test="financing-advisory-message"]').exists()).toBe(true)
  })

  it('shows loading skeleton while fetching', async () => {
    ledgerState.loading.value = true
    const wrapper = mount(FinancingUtilizationBar, {
      props: { taxNumber: 'TAX-1', invoiceNumber: 'INV-1' },
      global: { stubs },
    })

    expect(wrapper.find('[data-test="financing-loading"]').exists()).toBe(true)
    ledgerState.loading.value = false
  })
})
