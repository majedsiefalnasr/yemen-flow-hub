// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WorkflowProgress from '../../../components/workflow/WorkflowProgress.vue'
import { RequestStatus, UserRole } from '../../../types/enums'
import { NOT_ELIGIBLE_LABEL, NOT_ELIGIBLE_ROUTE_STOPPED_LABEL } from '../../../constants/workflow'

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

    const currentStep = wrapper.get('[aria-current="true"]')
    expect(currentStep.text()).toContain('انتظار فتح التصويت')
  })

  it('maps BANK_APPROVED to the pending SWIFT bucket for SWIFT_OFFICER', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.BANK_APPROVED,
        userRole: UserRole.SWIFT_OFFICER,
      },
    })

    const currentStep = wrapper.get('[aria-current="true"]')
    expect(currentStep.text()).toContain('انتظار رفع SWIFT')
  })

  it('keeps terminal bank rejection as the current workflow branch', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.BANK_REJECTED,
        userRole: UserRole.BANK_REVIEWER,
      },
    })

    const currentStep = wrapper.get('[aria-current="true"]')
    const stageLabels = wrapper.findAll('h4').map((label) => label.text())
    expect(currentStep.text()).toContain(NOT_ELIGIBLE_LABEL)
    expect(currentStep.text()).toContain(NOT_ELIGIBLE_ROUTE_STOPPED_LABEL)
    expect(currentStep.get('button').classes()).toContain('bg-[var(--severity-red)]')
    expect(currentStep.get('button').classes()).toContain('ring-[var(--severity-red)]/35')
    expect(currentStep.text()).not.toContain('قيد المراجعة')
    expect(stageLabels).not.toContain('مكتمل')
  })

  it('uses terminal completion text instead of generic current-stage copy', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.COMPLETED,
        userRole: UserRole.CBY_ADMIN,
      },
    })

    const currentStep = wrapper.get('[aria-current="true"]')
    expect(currentStep.text()).toContain('اكتمل المسار بنجاح')
    expect(currentStep.get('button').classes()).toContain('bg-[var(--severity-green)]')
    expect(currentStep.get('button').classes()).toContain('ring-[var(--severity-green)]/35')
    expect(currentStep.text()).not.toContain('المرحلة الحالية')
  })

  it('uses waiting text for handoff statuses', () => {
    const wrapper = mount(WorkflowProgress, {
      props: {
        currentStatus: RequestStatus.WAITING_FOR_SWIFT,
        userRole: UserRole.SWIFT_OFFICER,
      },
    })

    const currentStep = wrapper.get('[aria-current="true"]')
    expect(currentStep.text()).toContain('بانتظار إجراء من الجهة المسؤولة')
    expect(currentStep.get('button').classes()).toContain('bg-[var(--severity-amber)]')
    expect(currentStep.get('button').classes()).toContain('ring-[var(--severity-amber)]/35')
    expect(currentStep.text()).not.toContain('المرحلة الحالية')
  })
})
