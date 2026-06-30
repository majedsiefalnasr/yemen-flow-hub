// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import BasicInfoTab from '../../../components/request/tabs/BasicInfoTab.vue'

const lookupTrader = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({ lookupTrader }),
}))

const stubs = {
  Button: { template: '<button v-bind="$attrs"><slot /></button>' },
  Input: {
    template:
      '<input v-bind="$attrs" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue'],
  },
  Label: { template: '<label><slot /></label>' },
  Skeleton: { template: '<div data-test="skeleton" />' },
  Alert: { template: '<div role="alert"><slot /></div>' },
  AlertDescription: { template: '<div><slot /></div>' },
  Textarea: {
    template:
      '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue'],
  },
  Select: { template: '<div><slot /></div>' },
  SelectTrigger: { template: '<button type="button"><slot /></button>' },
  SelectValue: { template: '<span><slot /></span>' },
  SelectContent: { template: '<div><slot /></div>' },
  SelectItem: { template: '<div><slot /></div>', props: ['value'] },
  NuxtLink: { template: '<a :href="to"><slot /></a>', props: ['to'] },
}

describe('BasicInfoTab', () => {
  beforeEach(() => lookupTrader.mockReset())

  it('renders the tax number input and calls lookup on search', async () => {
    lookupTrader.mockResolvedValue(null)
    const wrapper = mount(BasicInfoTab, { props: { modelValue: {} }, global: { stubs } })

    expect(wrapper.text()).toContain('رقم الوعاء الضريبي')
    await wrapper.find('input').setValue('12345')
    await wrapper.find('button').trigger('click')

    expect(lookupTrader).toHaveBeenCalledWith('12345')
  })

  it('emits trader snapshot values on successful lookup', async () => {
    lookupTrader.mockResolvedValue({
      trader: {
        id: 7,
        trader_name: 'شركة الاختبار',
        tax_number: 'TAX-7',
        tax_card_expiry: '2026-12-31',
        commercial_registration_number: 'CR-7',
        commercial_registration_expiry: '2027-01-31',
      },
      companies: [{ id: 1, company_name: 'فرع عدن' }],
      owners: [{ id: 2, full_name: 'مالك رئيسي', ownership_percentage: 30 }],
    })
    const wrapper = mount(BasicInfoTab, { props: { modelValue: {} }, global: { stubs } })

    await wrapper.find('input').setValue('TAX-7')
    await wrapper.find('button').trigger('click')
    await flushPromises()

    const update = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as Record<string, unknown>
    expect(update.trader_id).toBe(7)
    expect(update.trader_snapshot_name).toBe('شركة الاختبار')
    expect(update.trader_snapshot_tax_number).toBe('TAX-7')
    expect(wrapper.text()).toContain('شركة الاختبار')
    expect(wrapper.text()).toContain('فرع عدن')
    expect(wrapper.text()).toContain('مالك رئيسي')
  })

  it('shows the not-found message when lookup returns null', async () => {
    lookupTrader.mockResolvedValue(null)
    const wrapper = mount(BasicInfoTab, { props: { modelValue: {} }, global: { stubs } })

    await wrapper.find('input').setValue('missing')
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('لم يتم العثور على تاجر بهذا الرقم')
    expect(wrapper.find('a[href="/traders/new"]').exists()).toBe(true)
  })
})
