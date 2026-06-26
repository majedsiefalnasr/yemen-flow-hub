// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DynamicFormField from '@/components/workflow/DynamicFormField.vue'
import type { ResolvedFieldDefinition } from '@/types/models'

function baseField(overrides: Partial<ResolvedFieldDefinition>): ResolvedFieldDefinition {
  return {
    id: 1,
    key: 'field_key',
    label: 'حقل',
    type: 'TEXT',
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

describe('DynamicFormField', () => {
  it('renders an input for TEXT fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT' }), modelValue: '' },
    })
    expect(wrapper.find('input').exists()).toBe(true)
  })

  it('renders a disabled input when is_editable is false', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT', is_editable: false }), modelValue: 'locked' },
    })
    const input = wrapper.find('input')
    expect(input.attributes('disabled')).toBeDefined()
  })

  it('renders a textarea for TEXTAREA fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXTAREA' }), modelValue: '' },
    })
    expect(wrapper.find('textarea').exists()).toBe(true)
  })

  it('renders a number input for NUMBER fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'NUMBER' }), modelValue: 0 },
    })
    const input = wrapper.find('input[type="number"]')
    expect(input.exists()).toBe(true)
  })

  it('renders a date input for DATE fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'DATE' }), modelValue: '' },
    })
    const input = wrapper.find('input[type="date"]')
    expect(input.exists()).toBe(true)
  })

  it('renders the field label', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT', label: 'اسم المورد' }), modelValue: '' },
    })
    expect(wrapper.text()).toContain('اسم المورد')
  })

  it('shows required asterisk when is_required', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT', is_required: true }), modelValue: '' },
    })
    expect(wrapper.text()).toContain('*')
  })

  it('renders an error message when error prop is set', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT' }), modelValue: '', error: 'هذا الحقل مطلوب.' },
    })
    expect(wrapper.text()).toContain('هذا الحقل مطلوب.')
  })

  it('emits update:modelValue when the input changes', async () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT' }), modelValue: '' },
    })
    await wrapper.find('input').setValue('new value')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['new value'])
  })

  it('renders help_text when present', () => {
    const wrapper = mount(DynamicFormField, {
      props: {
        field: baseField({ type: 'TEXT', help_text: 'ملاحظة مساعدة' }),
        modelValue: '',
      },
    })
    expect(wrapper.text()).toContain('ملاحظة مساعدة')
  })
})
