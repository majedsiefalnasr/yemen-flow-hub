// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { UserRole } from '../../../types/enums'
import staffPage from '../../../pages/staff.vue'

vi.stubGlobal('definePageMeta', vi.fn())

const fetchUsersMock = vi.hoisted(() => vi.fn())
const createUserMock = vi.hoisted(() => vi.fn())
const updateUserMock = vi.hoisted(() => vi.fn())
const getUserMock = vi.hoisted(() => vi.fn())

vi.mock('../../../composables/useUsers', () => ({
  useUsers: () => ({
    fetchUsers: fetchUsersMock,
    createUser: createUserMock,
    updateUser: updateUserMock,
    getUser: getUserMock,
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 1, bank_id: 1, role: UserRole.BANK_ADMIN },
  }),
}))

function makeStaff(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    name: 'أحمد السالمي',
    email: 'ahmed@bank.ye',
    role: UserRole.DATA_ENTRY,
    role_label: 'إدخال البيانات',
    bank_id: 1,
    bank_name_ar: 'بنك عدن',
    bank_name_en: 'Aden Bank',
    is_active: true,
    created_at: '2026-05-01T10:30:00Z',
    ...overrides,
  }
}

describe('staff.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchUsersMock.mockResolvedValue([])
    createUserMock.mockResolvedValue(makeStaff({ id: 2 }))
    updateUserMock.mockResolvedValue(makeStaff())
    getUserMock.mockResolvedValue(makeStaff())
  })

  it('renders the required staff empty state variant', async () => {
    const wrapper = mount(staffPage, {
      global: { stubs: { Teleport: true } },
    })
    await flushPromises()

    const emptyState = wrapper.find('[data-empty-state-variant="staff"]')
    expect(emptyState.exists()).toBe(true)
    expect(wrapper.text()).toContain('لا يوجد موظفون مسجّلون')
  })

  it('renders join date from API created_at field', async () => {
    fetchUsersMock.mockResolvedValue([makeStaff()])
    const wrapper = mount(staffPage, {
      global: { stubs: { Teleport: true } },
    })
    await flushPromises()

    const dateCell = wrapper.findAll('tbody td').at(4)
    expect(dateCell?.text()).toBeTruthy()
    expect(dateCell?.text()).not.toBe('—')
  })

  it('deactivates staff via PUT flow using freshly fetched user state', async () => {
    fetchUsersMock.mockResolvedValue([makeStaff({ id: 8 })])
    getUserMock.mockResolvedValue(makeStaff({ id: 8, name: 'محدّث خارجياً' }))
    updateUserMock.mockResolvedValue(makeStaff({ id: 8, is_active: false }))
    const wrapper = mount(staffPage, {
      global: { stubs: { Teleport: true } },
    })
    await flushPromises()

    await wrapper.get('.btn-deactivate').trigger('click')
    await wrapper.get('.btn-danger').trigger('click')
    await flushPromises()

    expect(getUserMock).toHaveBeenCalledWith(8)
    expect(updateUserMock).toHaveBeenCalledWith(8, expect.objectContaining({
      name: 'محدّث خارجياً',
      is_active: false,
    }))
    expect(wrapper.text()).toContain('غير نشط')
  })
})
