import { onMounted, onUnmounted } from 'vue'

export interface KeyboardShortcutDef {
  /** Key label shown in legend (e.g. 'Ctrl+Enter') */
  label: string
  /** Arabic description shown in legend */
  description: string
  /** Handler invoked when shortcut fires */
  handler: () => void
  /** If true, fires even when a dialog / modal is open */
  global?: boolean
}

type ShortcutMap = Map<string, KeyboardShortcutDef>

/**
 * Register keyboard shortcuts for a page / component scope.
 *
 * Each call automatically cleans up its listeners on unmount.
 *
 * @param shortcuts  Record whose keys are shortcut strings in the form:
 *                   `key` | `ctrl+key` | `shift+key` | `ctrl+shift+key`
 *                   Key values use `e.key` (case-insensitive for letters).
 */
export function useKeyboardShortcut(
  shortcuts: Record<string, Omit<KeyboardShortcutDef, 'label'>>,
  options: { label?: (key: string) => string } = {},
) {
  const map: ShortcutMap = new Map()

  for (const [rawKey, def] of Object.entries(shortcuts)) {
    const normalised = rawKey.toLowerCase()
    map.set(normalised, {
      label: options.label ? options.label(rawKey) : rawKey,
      ...def,
    })
  }

  function onKeydown(e: KeyboardEvent) {
    // Build a normalised key string
    const parts: string[] = []
    if (e.ctrlKey || e.metaKey) parts.push('ctrl')
    if (e.shiftKey) parts.push('shift')
    parts.push(e.key.toLowerCase())
    const combo = parts.join('+')

    const def = map.get(combo)
    if (!def) return

    // Don't fire on text inputs unless explicitly global
    if (!def.global) {
      const tag = (e.target as HTMLElement).tagName
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return
      if ((e.target as HTMLElement).isContentEditable) return
    }

    e.preventDefault()
    def.handler()
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))

  return { shortcuts: map }
}
