/**
 * Keyboard shortcuts for data tables:
 * - `/` — focus the search input
 * - `Escape` — clear the search input and blur
 */
export function useTableKeyboard(searchInputRef: Ref<HTMLInputElement | null>) {
  const themingStore = useThemingStore()

  function onKeydown(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement)?.tagName
    const isEditing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)

    // `/` focuses search — only if not already editing and modifier not required or already held
    if (e.key === '/' && !isEditing) {
      const requiresModifier = themingStore.shortcutsRequireModifier
      if (!requiresModifier || e.ctrlKey || e.metaKey) {
        e.preventDefault()
        searchInputRef.value?.focus()
      }
    }

    // Escape clears search and blurs
    if (e.key === 'Escape' && searchInputRef.value && document.activeElement === searchInputRef.value) {
      const el = searchInputRef.value as HTMLInputElement
      el.value = ''
      el.dispatchEvent(new Event('input'))
      el.blur()
    }
  }

  onMounted(() => document.addEventListener('keydown', onKeydown))
  onUnmounted(() => document.removeEventListener('keydown', onKeydown))

  return { onKeydown }
}
