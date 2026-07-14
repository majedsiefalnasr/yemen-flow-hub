// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import type { ResolvedFieldGroup } from '@/types/models'

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    upload: vi.fn().mockResolvedValue({ id: 1, original_name: 'a.pdf' }),
    remove: vi.fn(),
    documents: { value: [] },
    fetchDocuments: vi.fn(),
    loading: { value: false },
    error: { value: null },
    downloadUrl: vi.fn(),
  }),
}))

const groups: ResolvedFieldGroup[] = [
  {
    id: 1,
    name: 'g1',
    label: 'بيانات أساسية',
    sort_order: 0,
    fields: [
      {
        id: 1,
        key: 'invoice_amount',
        semantic_tag: null,
        label: 'مبلغ الفاتورة',
        type: 'NUMBER',
        placeholder: null,
        help_text: null,
        default_value: null,
        min_value: 1,
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
        is_required: true,
        dynamic_options: null,
      },
    ],
  },
]

describe('DynamicForm', () => {
  it('renders group labels as section headings', () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.text()).toContain('بيانات أساسية')
  })

  it('renders field labels from field definitions', () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.text()).toContain('مبلغ الفاتورة')
  })

  it('validate() returns valid:false when required field is missing', async () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    const result = await wrapper.vm.validate()
    expect(result.valid).toBe(false)
  })

  it('validate() returns valid:true when all required fields are present', async () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: { invoice_amount: 50 }, mode: 'edit' },
    })
    const result = await wrapper.vm.validate()
    expect(result.valid).toBe(true)
  })

  it('skips invisible fields', () => {
    const hiddenGroups: ResolvedFieldGroup[] = [
      {
        ...groups[0]!,
        fields: [{ ...groups[0]!.fields[0]!, is_visible: false }],
      },
    ]
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: hiddenGroups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.text()).not.toContain('مبلغ الفاتورة')
  })

  it('does not show a required-field error on initial render', () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows the error for a field once it has been edited', async () => {
    const textGroups: ResolvedFieldGroup[] = [
      {
        ...groups[0]!,
        fields: [{ ...groups[0]!.fields[0]!, key: 'tax_number', type: 'TEXT', is_required: true }],
      },
    ]
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: textGroups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    await wrapper.find('input').setValue('123')
    await wrapper.find('input').setValue('')
    // vee-validate debounces schema validation by 5ms internally; flushPromises
    // (microtasks only) never observes it, so this needs a real timer tick.
    await new Promise((resolve) => setTimeout(resolve, 20))
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })

  it('shows all required-field errors after validate() is called', async () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    await wrapper.vm.validate()
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })
})
