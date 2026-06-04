/**
 * Keyboard navigation for form fields.
 *
 * Attach `onFieldKeydown` to the `@keydown` event of any plain text <Input>.
 * Pressing Enter moves focus to the next focusable sibling within the same
 * container. Textarea, Select, date, and combobox inputs are intentionally
 * excluded — callers simply don't attach the handler to those elements.
 *
 * The `enterkeyhint` attribute is also exported as a constant so callers can
 * add it to every text input, changing the mobile virtual-keyboard Enter label
 * to "Next" (→) without affecting desktop behavior.
 */

const FOCUSABLE = [
  'input:not([type="hidden"]):not([disabled])',
  'button:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ')

export function useFormFieldNav() {
  /**
   * Add to text <Input> @keydown. On Enter, moves focus to the next focusable
   * element inside the nearest ancestor that contains all form fields.
   * Falls back to the form's submit button if no next sibling is found.
   */
  function onFieldKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter') return

    const target = event.target as HTMLElement
    // Only handle plain text/number inputs — leave everything else alone
    if (target.tagName !== 'INPUT') return
    const inputType = (target as HTMLInputElement).type
    if (['date', 'file', 'checkbox', 'radio', 'submit', 'button'].includes(inputType)) return

    event.preventDefault()

    // Find the root form container — walk up to the nearest [data-field-nav]
    // or fall back to the document body as the search scope.
    const scope: Element = target.closest('[data-field-nav]') ?? document.body

    const focusable = Array.from(scope.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
      (el) => !el.closest('[aria-hidden="true"]') && getComputedStyle(el).display !== 'none',
    )

    const currentIndex = focusable.indexOf(target)
    if (currentIndex === -1) return

    const next = focusable[currentIndex + 1]
    if (next) {
      next.focus()
    }
  }

  /** Add to every text <Input> as :enterkeyhint="enterKeyHint" */
  const enterKeyHint = 'next' as const

  return { onFieldKeydown, enterKeyHint }
}
