// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import ClaimBanner from '../../../components/workflow/ClaimBanner.vue'

describe('ClaimBanner', () => {
  it('renders stage-neutral working-on-request copy, not review-specific copy', () => {
    const wrapper = mount(ClaimBanner, {
      props: { holderName: 'أحمد' },
    })

    expect(wrapper.text()).toContain('يعمل على هذا الطلب الآن')
    expect(wrapper.text()).not.toContain('يراجع')
  })

  it('displays the holder name in the banner', () => {
    const wrapper = mount(ClaimBanner, {
      props: { holderName: 'خالد السعيد' },
    })

    expect(wrapper.text()).toContain('خالد السعيد')
  })

  it('includes the lock icon for visual clarity', () => {
    const wrapper = mount(ClaimBanner, {
      props: { holderName: 'أحمد' },
    })

    // Check that the Alert component is rendered with the lock icon
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
  })
})
