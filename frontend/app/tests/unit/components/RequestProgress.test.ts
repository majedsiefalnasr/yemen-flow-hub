// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import RequestProgress from '../../../components/requests/RequestProgress.vue'
import { RequestStatus, UserRole } from '../../../types/enums'
import { getStatusProgress } from '../../../constants/workflow'

describe('RequestProgress', () => {
  it('renders canonical progress for operational roles', () => {
    const wrapper = mount(RequestProgress, {
      props: {
        status: RequestStatus.EXECUTIVE_APPROVED,
        role: UserRole.CBY_ADMIN,
      },
    })

    expect(wrapper.text()).toContain(
      `${getStatusProgress(RequestStatus.EXECUTIVE_APPROVED, UserRole.CBY_ADMIN)}%`,
    )
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('92')
  })

  it('uses simplified progress for data entry users', () => {
    const wrapper = mount(RequestProgress, {
      props: {
        status: RequestStatus.EXECUTIVE_APPROVED,
        role: UserRole.DATA_ENTRY,
      },
    })

    expect(wrapper.text()).toContain('100%')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('100')
  })
})
