import { useEventListener } from '@vueuse/core'
import type { Ref } from 'vue'

type UseTableKeyboardOptions = {
  onEscape?: () => void
}

export function useTableKeyboard(
  searchRef: Ref<HTMLInputElement | null>,
  options: UseTableKeyboardOptions = {},
) {
  useEventListener('keydown', (e: KeyboardEvent) => {
    if (e.isComposing) return

    const target = e.target as HTMLElement | null
    if (!target) return

    const isTyping =
      ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable

    if (e.key === '/' && !isTyping && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault()
      searchRef.value?.focus()
      return
    }

    if (e.key === 'Escape' && target === searchRef.value) {
      options.onEscape?.()
      searchRef.value.blur()
    }
  })
}
