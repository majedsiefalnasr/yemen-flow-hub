// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import DataTableRowActions from '../../../../../components/ui/data-table/DataTableRowActions.vue'

function passthrough(name: string, tag = 'div') {
  return defineComponent({
    name,
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
      return () => h(tag, attrs, slots.default?.())
    },
  })
}

vi.mock('../../../../../components/ui/dropdown-menu', () => ({
  DropdownMenu: passthrough('DropdownMenu'),
  DropdownMenuContent: passthrough('DropdownMenuContent'),
  DropdownMenuItem: passthrough('DropdownMenuItem', 'button'),
  DropdownMenuSeparator: passthrough('DropdownMenuSeparator'),
  DropdownMenuTrigger: passthrough('DropdownMenuTrigger'),
}))

vi.mock('../../../../../components/ui/alert-dialog', () => ({
  AlertDialog: passthrough('AlertDialog'),
  AlertDialogContent: passthrough('AlertDialogContent'),
  AlertDialogHeader: passthrough('AlertDialogHeader'),
  AlertDialogTitle: passthrough('AlertDialogTitle'),
  AlertDialogDescription: passthrough('AlertDialogDescription'),
  AlertDialogFooter: passthrough('AlertDialogFooter'),
  AlertDialogCancel: passthrough('AlertDialogCancel', 'button'),
  AlertDialogAction: passthrough('AlertDialogAction', 'button'),
}))

describe('DataTableRowActions dialog keyboard checks', () => {
  it('keeps confirm-dialog actions keyboard-focusable and executes confirmed action', async () => {
    const onClick = vi.fn()
    const wrapper = mount(DataTableRowActions as any, {
      attachTo: document.body,
      props: {
        row: {} as any,
        actions: [
          {
            label: 'حذف',
            destructive: true,
            confirm: {
              title: 'تأكيد الحذف',
              description: 'لن يمكن التراجع.',
            },
            onClick,
          },
        ],
      },
      global: {
        stubs: {
          Button: passthrough('Button', 'button'),
        },
      },
    })

    const actionItem = wrapper.findAll('button').find(button => button.text().includes('حذف'))
    expect(actionItem).toBeTruthy()
    await actionItem!.trigger('click')

    const cancelButton = wrapper.findAll('button').find(button => button.text().includes('إلغاء'))
    const confirmButton = wrapper.findAll('button').find(button => button.text().includes('تأكيد'))

    expect(cancelButton).toBeTruthy()
    expect(confirmButton).toBeTruthy()

    ;(cancelButton!.element as HTMLElement).focus()
    expect(document.activeElement).toBe(cancelButton!.element)

    ;(confirmButton!.element as HTMLElement).focus()
    expect(document.activeElement).toBe(confirmButton!.element)

    await confirmButton!.trigger('click')
    expect(onClick).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })
})
