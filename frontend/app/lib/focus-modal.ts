const INPUT_CANDIDATE_SELECTOR = [
  '[data-autofocus]:not([disabled])',
  '[autofocus]:not([disabled])',
  'input:not([type="hidden"]):not([disabled])',
  'textarea:not([disabled])',
  'select:not([disabled])',
  '[contenteditable="true"]',
].join(', ')

const TAB_CANDIDATE_SELECTOR = [
  'button:not([disabled])',
  '[href]',
  'input:not([type="hidden"]):not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
  '[contenteditable="true"]',
].join(', ')

function isVisible(el: HTMLElement): boolean {
  return el.offsetParent !== null || getComputedStyle(el).position === 'fixed'
}

function focusFirstIn(el: HTMLElement): boolean {
  const firstInput = Array.from(el.querySelectorAll<HTMLElement>(INPUT_CANDIDATE_SELECTOR)).find(
    (node) => isVisible(node),
  )
  if (firstInput) {
    firstInput.focus()
    return true
  }

  const firstTabbable = Array.from(el.querySelectorAll<HTMLElement>(TAB_CANDIDATE_SELECTOR)).find(
    (node) => isVisible(node),
  )
  if (firstTabbable) {
    firstTabbable.focus()
    return true
  }

  return false
}

export function focusPopupFirstInput(event: Event): void {
  const root = event.currentTarget as HTMLElement | null
  if (!root) return

  // Override default focus target so we can move focus to the first input reliably.
  event.preventDefault()

  // Try immediately first so focus doesn't stay on hidden background content.
  if (focusFirstIn(root)) return

  // Retry after transition + teleported children are mounted.
  requestAnimationFrame(() => {
    if (focusFirstIn(root)) return

    requestAnimationFrame(() => {
      focusFirstIn(root)
    })
  })
}
