// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import Input from '../../../../components/ui/input/Input.vue'

describe('Input', () => {
  it('does not write modelValue into file inputs', () => {
    const wrapper = mount(Input, {
      props: {
        type: 'file',
        modelValue: 'invoice.pdf',
      },
    })

    const input = wrapper.get('input[type="file"]').element as HTMLInputElement

    expect(input.value).toBe('')
  })

  it('emits text input updates for regular inputs', async () => {
    const wrapper = mount(Input, {
      props: {
        type: 'text',
        modelValue: '',
      },
    })

    await wrapper.get('input[type="text"]').setValue('merchant')

    expect(wrapper.emitted('update:modelValue')).toEqual([['merchant']])
  })
})
