// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, it, expect, vi } from 'vitest'
import EngineActionsRail from '../../../components/workflow/EngineActionsRail.vue'
import type { WorkflowGraphEdge } from '@/types/models'

function passthrough(name: string, tag = 'div') {
  return defineComponent({
    name,
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
      return () => h(tag, attrs, slots.default?.())
    },
  })
}

vi.mock('../../../components/ui/alert-dialog', () => ({
  AlertDialog: passthrough('AlertDialog'),
  AlertDialogContent: passthrough('AlertDialogContent'),
  AlertDialogHeader: passthrough('AlertDialogHeader'),
  AlertDialogTitle: passthrough('AlertDialogTitle'),
  AlertDialogDescription: passthrough('AlertDialogDescription'),
  AlertDialogFooter: passthrough('AlertDialogFooter'),
  AlertDialogCancel: passthrough('AlertDialogCancel', 'button'),
  AlertDialogAction: passthrough('AlertDialogAction', 'button'),
}))

const baseEdge: WorkflowGraphEdge = {
  id: 1,
  from_stage_id: 10,
  to_stage_id: 11,
  action_id: 1,
  action_code: 'APPROVE',
  action_name: 'موافقة',
  requires_comment: false,
  is_self_loop: false,
  is_return: false,
}

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

  it('emits run immediately when action does not need confirmation', async () => {
    const wrapper = mount(EngineActionsRail, {
      props: {
        availableActions: [baseEdge],
        canAct: true,
        claimRequiredButNotHeld: false,
        showClaimButton: false,
        busy: false,
        viewOnly: false,
      },
    })

    const actionButton = wrapper.findAll('button').find((b) => b.text().includes('موافقة'))
    await actionButton!.trigger('click')

    expect(wrapper.emitted('run')).toEqual([[1, false]])
  })

  it('gates destructive actions behind confirmation dialog', async () => {
    const destructiveEdge: WorkflowGraphEdge = {
      ...baseEdge,
      id: 2,
      action_code: 'REJECT',
      action_name: 'رفض',
      is_destructive: true,
      confirmation_message: 'هل أنت متأكد من الرفض؟',
    }

    const wrapper = mount(EngineActionsRail, {
      attachTo: document.body,
      props: {
        availableActions: [destructiveEdge],
        canAct: true,
        claimRequiredButNotHeld: false,
        showClaimButton: false,
        busy: false,
        viewOnly: false,
      },
    })

    const rejectButton = wrapper.findAll('button').find((b) => b.text().includes('رفض'))
    await rejectButton!.trigger('click')

    expect(wrapper.emitted('run')).toBeUndefined()
    expect(wrapper.text()).toContain('هل أنت متأكد من الرفض؟')

    const confirmButton = wrapper.findAll('button').find((b) => b.text().includes('تأكيد'))
    await confirmButton!.trigger('click')

    expect(wrapper.emitted('run')).toEqual([[2, false]])
    wrapper.unmount()
  })
})
