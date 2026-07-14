// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { ResolvedFieldGroup } from '@/types/models'

const mockGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    upload: vi.fn(),
    remove: vi.fn(),
    documents: { value: [] },
    fetchDocuments: vi.fn(),
    loading: { value: false },
    error: { value: null },
    downloadUrl: vi.fn(),
  }),
}))

const { default: DynamicForm } = await import('@/components/workflow/DynamicForm.vue')

function textField(overrides: Partial<ResolvedFieldGroup['fields'][number]>) {
  return {
    id: 1,
    key: 'field',
    semantic_tag: null,
    label: 'حقل',
    type: 'TEXT' as const,
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: false,
    is_visible: true,
    is_editable: true,
    is_required: false,
    dynamic_options: null,
    ...overrides,
  }
}

const merchantGroups: ResolvedFieldGroup[] = [
  {
    id: 1,
    name: 'g1',
    label: 'المعلومات الأساسية',
    sort_order: 0,
    fields: [
      textField({
        id: 1,
        key: 'tax_number',
        semantic_tag: 'MERCHANT_TAX_NUMBER',
        label: 'الرقم الضريبي',
      }),
      textField({
        id: 2,
        key: 'merchant_id',
        semantic_tag: 'MERCHANT_ID',
        label: 'اسم التاجر',
        type: 'DYNAMIC_SELECT',
        dynamic_options: [],
      }),
      textField({
        id: 3,
        key: 'company_id',
        semantic_tag: 'MERCHANT_COMPANY_ID',
        label: 'الشركة المرتبطة',
        type: 'DYNAMIC_SELECT',
        dynamic_options: [],
        is_required: true,
      }),
      textField({
        id: 4,
        key: 'tax_card_expiry',
        semantic_tag: 'MERCHANT_TAX_CARD_EXPIRY',
        label: 'تاريخ انتهاء البطاقة الضريبية',
        type: 'DATE',
      }),
      textField({
        id: 5,
        key: 'commercial_registration_number',
        semantic_tag: 'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER',
        label: 'رقم السجل التجاري',
      }),
      textField({
        id: 6,
        key: 'commercial_registration_expiry',
        semantic_tag: 'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY',
        label: 'تاريخ انتهاء السجل التجاري',
        type: 'DATE',
      }),
      textField({
        id: 7,
        key: 'owners',
        semantic_tag: 'MERCHANT_OWNERS',
        label: 'الملاك والمساهمون',
        type: 'TEXTAREA',
      }),
    ],
  },
]

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

describe('DynamicForm — merchant tax-number autofill', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mockGet.mockReset()
  })

  it('autofills merchant, company, tax-card expiry, commercial registration, its expiry, and owners on match', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-1')
    await vi.runAllTimersAsync()
    await flushPromises()

    const emitted = wrapper.emitted('update:modelValue')
    const last = emitted?.[emitted.length - 1]?.[0] as Record<string, unknown>
    expect(last.merchant_id).toBe(7)
    expect(last.company_id).toBe(21)
    expect(last.tax_card_expiry).toBe('2027-01-01')
    expect(last.commercial_registration_number).toBe('CR-100')
    expect(last.commercial_registration_expiry).toBe('2029-05-01')
    expect(last.owners).toBe('أحمد ثابت (60%)')
  })

  it('locks merchant master-data fields read-only once matched, without mutating Designer metadata', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-1')
    await vi.runAllTimersAsync()
    await flushPromises()

    // The Designer schema itself must remain untouched.
    expect(merchantGroups[0]!.fields.find((f) => f.key === 'merchant_id')!.is_editable).toBe(true)

    // The commercial-registration-number, its expiry, and owners inputs are
    // disabled at render time only.
    expect(
      wrapper.find('input#commercial_registration_number').attributes('disabled'),
    ).toBeDefined()
    expect(
      wrapper.find('input#commercial_registration_expiry').attributes('disabled'),
    ).toBeDefined()
    expect(wrapper.find('textarea#owners').attributes('disabled')).toBeDefined()
  })

  it('shows a loading indicator while the lookup is in flight', async () => {
    mockGet.mockImplementation(() => new Promise(() => {}))
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-1')
    await vi.advanceTimersByTimeAsync(400)
    await flushPromises()

    expect(wrapper.text()).toContain('جارٍ البحث')
  })

  it('shows a non-destructive Arabic warning and blocks continuation on no match', async () => {
    mockGet.mockResolvedValue({ success: true, data: [] })
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-404')
    await vi.advanceTimersByTimeAsync(500)
    await flushPromises()

    expect(wrapper.text()).toContain('لم يتم العثور على تاجر')
    expect(wrapper.text()).not.toMatch(/null|undefined|NaN|\[object/i)

    const validatePromise = wrapper.vm.validate()
    await vi.advanceTimersByTimeAsync(50)
    const result = await validatePromise
    expect(result.valid).toBe(false)
  })

  it('shows a retryable Arabic error on lookup failure, never the raw exception text', async () => {
    mockGet.mockRejectedValue(new Error('ECONNRESET low-level socket failure'))
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-ERR')
    await vi.runAllTimersAsync()
    await flushPromises()

    expect(wrapper.text()).not.toContain('ECONNRESET')
    expect(wrapper.text()).toContain('تعذّر')
  })

  it('auto-selects a single company and requires manual choice for multiple companies', async () => {
    mockGet.mockResolvedValueOnce(
      merchantResponse({
        companies: [
          {
            id: 31,
            name: 'شركة أ',
            commercial_registration_number: 'CR-A',
            commercial_registration_expiry: null,
            sector_reference_value_id: null,
            is_active: true,
          },
          {
            id: 32,
            name: 'شركة ب',
            commercial_registration_number: 'CR-B',
            commercial_registration_expiry: null,
            sector_reference_value_id: null,
            is_active: true,
          },
        ],
      }),
    )
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-1')
    await vi.advanceTimersByTimeAsync(500)
    await flushPromises()

    const emitted = wrapper.emitted('update:modelValue')
    const last = emitted?.[emitted.length - 1]?.[0] as Record<string, unknown>
    // No single company to auto-select — the wizard must not guess.
    expect(last.company_id).toBeUndefined()

    // A required-field validation attempt must not silently pass without a choice.
    const validatePromise = wrapper.vm.validate()
    await vi.advanceTimersByTimeAsync(50)
    const result = await validatePromise
    expect(result.valid).toBe(false)
  })

  it('clears dependent fields and read-only state when the tax number changes', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: merchantGroups, modelValue: {}, mode: 'edit' },
    })

    await wrapper.find('input#tax_number').setValue('TAX-1')
    await vi.runAllTimersAsync()
    await flushPromises()
    expect(
      wrapper.find('input#commercial_registration_number').attributes('disabled'),
    ).toBeDefined()

    await wrapper.find('input#tax_number').setValue('TAX-1-CHANGED')
    await flushPromises()

    const emitted = wrapper.emitted('update:modelValue')
    const afterChange = emitted?.[emitted.length - 1]?.[0] as Record<string, unknown>
    expect(afterChange.commercial_registration_number).toBeUndefined()
    expect(
      wrapper.find('input#commercial_registration_number').attributes('disabled'),
    ).toBeUndefined()
  })

  it('confirms an already-saved draft tax number without clearing its saved dependent values first', async () => {
    mockGet.mockResolvedValue(merchantResponse())
    const wrapper = mount(DynamicForm, {
      props: {
        fieldGroups: merchantGroups,
        modelValue: {
          tax_number: 'TAX-1',
          merchant_id: 7,
          company_id: 21,
          tax_card_expiry: '2027-01-01',
          commercial_registration_number: 'CR-100',
        },
        mode: 'edit',
      },
    })

    // Dependent values must still read as their saved draft values immediately.
    expect(
      (wrapper.find('input#commercial_registration_number').element as HTMLInputElement).value,
    ).toBe('CR-100')

    await vi.runAllTimersAsync()
    await flushPromises()

    expect(wrapper.text()).not.toContain('لم يتم العثور على تاجر')
  })

  it('stays inert for a workflow schema without merchant semantic tags', async () => {
    const groups: ResolvedFieldGroup[] = [
      { id: 1, name: 'g1', label: 'بيانات', sort_order: 0, fields: [textField({ key: 'notes' })] },
    ]
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })

    await vi.runAllTimersAsync()
    expect(mockGet).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('جارٍ البحث')
  })
})
