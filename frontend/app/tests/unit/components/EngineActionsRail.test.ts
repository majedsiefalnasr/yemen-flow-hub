// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import EngineActionsRail from '../../../components/workflow/EngineActionsRail.vue'

describe('EngineActionsRail', () => {
  it('renders stage-neutral claim button copy', () => {
    const wrapper = mount(EngineActionsRail, {
      props: {
        availableActions: [],
        canAct: false,
        claimRequiredButNotHeld: false,
        showClaimButton: true,
        busy: false,
        viewOnly: false,
      },
    })

    expect(wrapper.text()).toContain('المتابعة على هذا الطلب')
    expect(wrapper.text()).not.toContain('بدء المراجعة')
  })

  it('hides claim button when showClaimButton is false', () => {
    const wrapper = mount(EngineActionsRail, {
      props: {
        availableActions: [],
        canAct: false,
        claimRequiredButNotHeld: false,
        showClaimButton: false,
        busy: false,
        viewOnly: false,
      },
    })

    expect(wrapper.text()).not.toContain('المتابعة على هذا الطلب')
  })

  it('emits claim event when claim button is clicked', async () => {
    const wrapper = mount(EngineActionsRail, {
      props: {
        availableActions: [],
        canAct: false,
        claimRequiredButNotHeld: false,
        showClaimButton: true,
        busy: false,
        viewOnly: false,
      },
    })

    const claimButton = wrapper.find('button')
    await claimButton.trigger('click')

    expect(wrapper.emitted('claim')).toHaveLength(1)
  })
})
