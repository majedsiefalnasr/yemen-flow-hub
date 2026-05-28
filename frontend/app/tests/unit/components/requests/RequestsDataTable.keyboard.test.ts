// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import RequestsDataTable from '../../../../components/requests/RequestsDataTable.vue'
import { RequestStatus, UserRole } from '../../../../types/enums'
import { makeImportRequest } from '../../fixtures/request-data'

const authUser = ref({
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  role: UserRole.DATA_ENTRY as UserRole,
})

function passthrough(name: string, tag = 'div') {
  return defineComponent({
    name,
    inheritAttrs: false,
    setup(_, { slots, attrs }) {
      return () => h(tag, attrs, slots.default?.())
    },
  })
}

vi.mock('../../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get user() {
      return authUser.value
    },
  }),
}))

vi.mock('../../../../components/ui/dropdown-menu', () => ({
  DropdownMenu: passthrough('DropdownMenu'),
  DropdownMenuTrigger: passthrough('DropdownMenuTrigger'),
  DropdownMenuContent: passthrough('DropdownMenuContent'),
  DropdownMenuItem: passthrough('DropdownMenuItem', 'button'),
  DropdownMenuLabel: passthrough('DropdownMenuLabel'),
  DropdownMenuCheckboxItem: passthrough('DropdownMenuCheckboxItem', 'button'),
  DropdownMenuSeparator: passthrough('DropdownMenuSeparator'),
}))

vi.mock('../../../../components/ui/popover', () => ({
  Popover: passthrough('Popover'),
  PopoverTrigger: passthrough('PopoverTrigger'),
  PopoverContent: passthrough('PopoverContent'),
}))

vi.mock('../../../../components/ui/command', () => ({
  Command: passthrough('Command'),
  CommandEmpty: passthrough('CommandEmpty'),
  CommandGroup: passthrough('CommandGroup'),
  CommandInput: passthrough('CommandInput', 'input'),
  CommandItem: passthrough('CommandItem', 'button'),
  CommandList: passthrough('CommandList'),
  CommandSeparator: passthrough('CommandSeparator'),
}))

vi.mock('../../../../components/ui/select', () => ({
  Select: passthrough('Select'),
  SelectContent: passthrough('SelectContent'),
  SelectItem: passthrough('SelectItem', 'button'),
  SelectTrigger: passthrough('SelectTrigger', 'button'),
  SelectValue: passthrough('SelectValue'),
}))

vi.stubGlobal('useRouter', () => ({ push: vi.fn() }))

describe('RequestsDataTable keyboard focus affordances', () => {
  it('keeps filter, columns, row-actions, and pagination controls keyboard-focusable', async () => {
    const rows = Array.from({ length: 25 }, (_, index) => makeImportRequest({
      id: index + 1,
      reference_number: `YFH-2026-${String(index + 1).padStart(6, '0')}`,
      status: RequestStatus.DRAFT,
      merchant: { id: 5, name: 'مؤسسة النور', commercial_register: null },
    }))

    const wrapper = mount(RequestsDataTable, {
      attachTo: document.body,
      props: {
        role: UserRole.DATA_ENTRY,
        loading: false,
        noData: false,
        data: rows,
      },
    })

    const statusFilter = wrapper.findAll('button').find(btn => btn.text().includes('الحالة'))
    const columnMenu = wrapper.findAll('button').find(btn => btn.text().includes('الأعمدة'))
    const rowActions = wrapper.findAll('button').find(btn => btn.text().includes('فتح القائمة'))
    const prevPage = wrapper.findAll('button').find(btn => btn.text().includes('الصفحة السابقة'))
    const nextPage = wrapper.findAll('button').find(btn => btn.text().includes('الصفحة التالية'))

    expect(statusFilter).toBeTruthy()
    expect(columnMenu).toBeTruthy()
    expect(rowActions).toBeTruthy()
    expect(prevPage).toBeTruthy()
    expect(nextPage).toBeTruthy()

    statusFilter!.element.focus()
    expect(document.activeElement).toBe(statusFilter!.element)

    columnMenu!.element.focus()
    expect(document.activeElement).toBe(columnMenu!.element)

    rowActions!.element.focus()
    expect(document.activeElement).toBe(rowActions!.element)

    nextPage!.element.focus()
    expect(document.activeElement).toBe(nextPage!.element)

    await nextPage!.trigger('click')

    const prevPageAfterNext = wrapper.findAll('button').find(btn => btn.text().includes('الصفحة السابقة'))
    expect(prevPageAfterNext).toBeTruthy()

    prevPageAfterNext!.element.focus()
    expect(document.activeElement).toBe(prevPageAfterNext!.element)

    wrapper.unmount()
  })
})
