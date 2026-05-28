import { useEventListener } from '@vueuse/core'
import type { Ref } from 'vue'

export function useTableKeyboard(searchRef: Ref<HTMLInputElement | null>) {
  useEventListener('keydown', (e: KeyboardEvent) => {
    const target = e.target as HTMLElement
    const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable

    if (e.key === '/' && !isTyping && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault()
      searchRef.value?.focus()
      return
    }

    if (e.key === 'Escape' && target === searchRef.value) {
      searchRef.value.blur()
    }
  })
}
