// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'
import ScreenGuard from '../../../components/security/ScreenGuard.vue'
import { useAuthStore } from '../../../stores/auth.store'

describe('ScreenGuard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('mounts allowed content from computed screen permissions', () => {
    const auth = useAuthStore()
    auth.screenPermissions = { organizations: ['VIEW'] }

    const wrapper = mount(ScreenGuard, {
      props: { screen: 'organizations', capability: 'VIEW' },
      slots: { default: '<span data-test="allowed">allowed</span>' },
    })

    expect(wrapper.find('[data-test="allowed"]').exists()).toBe(true)
  })

  it('does not mount forbidden content', () => {
    const auth = useAuthStore()
    auth.screenPermissions = { organizations: ['VIEW'] }

    const wrapper = mount(ScreenGuard, {
      props: { screen: 'organizations', capability: 'MANAGE' },
      slots: { default: '<span data-test="forbidden">forbidden</span>' },
    })

    expect(wrapper.find('[data-test="forbidden"]').exists()).toBe(false)
  })
})
