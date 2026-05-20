// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WorkflowProgress from '../../../components/workflow/WorkflowProgress.vue'
import { RequestStatus, UserRole } from '../../../types/enums'

describe('WorkflowProgress', () => {
  it('uses DATA_ENTRY simplified status labels and progress', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.WAITING_FOR_SWIFT,
        userRole: UserRole.DATA_ENTRY,
      },
    })

    expect(wrapper.text()).toContain('قيد معالجة CBY')
    expect(wrapper.text()).toContain('35%')
  })

  it('marks waiting-for-voting-open as the current executive step', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.WAITING_FOR_VOTING_OPEN,
        userRole: UserRole.EXECUTIVE_MEMBER,
      },
    })

    const currentStep = wrapper.get('.wp-step--current')
    expect(currentStep.text()).toContain('انتظار فتح التصويت')
  })

  it('maps BANK_APPROVED to the pending SWIFT bucket for SWIFT_OFFICER', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.BANK_APPROVED,
        userRole: UserRole.SWIFT_OFFICER,
      },
    })

    const currentStep = wrapper.get('.wp-step--current')
    expect(currentStep.text()).toContain('انتظار رفع SWIFT')
  })
})
