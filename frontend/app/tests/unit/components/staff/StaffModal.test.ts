// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import StaffModal from '../../../../components/staff/StaffModal.vue'
import { UserRole } from '../../../../types/enums'
import type { User } from '../../../../types/models'

const STAFF_FIXTURE: User = {
  id: 7,
  name: 'محمد العمري',
  email: 'mohamad@bank.ye',
  role: UserRole.DATA_ENTRY,
  role_label: 'إدخال البيانات',
  bank_id: 1,
  bank_name_ar: 'بنك عدن',
  bank_name_en: 'Aden Bank',
  is_active: true,
}

function mountModal(staff: User | null = null, saving = false) {
  return mount(StaffModal, {
    props: { staff, saving, serverError: null },
    global: { stubs: { Teleport: true } },
  })
}

describe('StaffModal', () => {
  it('renders create mode shell with required fields', async () => {
    const wrapper = mountModal()
    await flushPromises()
    expect(wrapper.text()).toContain('إضافة موظف جديد')
    expect(wrapper.find('#staff-name').exists()).toBe(true)
    expect(wrapper.find('#staff-email').exists()).toBe(true)
    expect(wrapper.find('#staff-role').exists()).toBe(true)
    expect(wrapper.find('#staff-password').exists()).toBe(true)
  })

  it('renders edit mode title when staff is provided', async () => {
    const wrapper = mountModal(STAFF_FIXTURE)
    await flushPromises()
    expect(wrapper.text()).toContain('تعديل بيانات الموظف')
  })

  it('limits role selection to DATA_ENTRY and BANK_REVIEWER', async () => {
    const wrapper = mountModal(STAFF_FIXTURE)
    await flushPromises()
    const options = wrapper.findAll('#staff-role option').map(opt => opt.text())
    expect(options).toContain('إدخال البيانات')
    expect(options).toContain('مراجع البنك')
    expect(options).not.toContain('مسؤول البنك المركزي')
  })

  it('does not emit close while saving', async () => {
    const wrapper = mountModal(STAFF_FIXTURE, true)
    await flushPromises()
    await wrapper.get('.close-btn').trigger('click')
    expect(wrapper.emitted('close')).toBeFalsy()
  })

  it('emits close when not saving', async () => {
    const wrapper = mountModal(STAFF_FIXTURE, false)
    await flushPromises()
    await wrapper.get('.close-btn').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
