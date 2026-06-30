// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import InactivityBanner from '../../../components/layout/InactivityBanner.vue'

describe('InactivityBanner', () => {
  it('does not render outside the warning state', () => {
    const wrapper = mount(InactivityBanner, {
      props: { visible: false },
    })

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  it('renders the required warning copy and a11y attributes', () => {
    const wrapper = mount(InactivityBanner, {
      props: { visible: true },
    })

    expect(wrapper.get('[role="status"]').attributes('aria-live')).toBe('polite')
    expect(wrapper.get('[role="status"]').attributes('dir')).toBe('rtl')
    expect(wrapper.text()).toContain('أنت على وشك الخروج بسبب عدم النشاط')
    expect(wrapper.text()).toContain('يرجى التفاعل للبقاء')
    expect(wrapper.text()).toContain('متابعة الجلسة')
  })

  it('emits extend when the continue-session button is clicked', async () => {
    const wrapper = mount(InactivityBanner, {
      props: { visible: true },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('extend')).toHaveLength(1)
  })
})
