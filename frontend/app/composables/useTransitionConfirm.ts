import { ref } from 'vue'
import type { WorkflowGraphEdge } from '@/types/models'

const DEFAULT_MESSAGE = 'هل أنت متأكد من تنفيذ هذا الإجراء؟'

export function needsConfirmation(
  edge: Pick<WorkflowGraphEdge, 'is_destructive' | 'confirmation_message'>,
): boolean {
  if (edge.is_destructive) return true
  return Boolean(edge.confirmation_message?.trim())
}

export function defaultConfirmMessage(
  edge: Pick<WorkflowGraphEdge, 'confirmation_message'>,
): string {
  const message = edge.confirmation_message?.trim()
  return message || DEFAULT_MESSAGE
}

export function useTransitionConfirm() {
  const confirmOpen = ref(false)
  const pendingEdge = ref<WorkflowGraphEdge | null>(null)
  let resolvePending: ((confirmed: boolean) => void) | null = null

  const pendingMessage = ref(DEFAULT_MESSAGE)

  function confirmIfNeeded(edge: WorkflowGraphEdge): Promise<boolean> {
    if (!needsConfirmation(edge)) {
      return Promise.resolve(true)
    }

    pendingEdge.value = edge
    pendingMessage.value = defaultConfirmMessage(edge)
    confirmOpen.value = true

    return new Promise((resolve) => {
      resolvePending = resolve
    })
  }

  function confirmPending() {
    confirmOpen.value = false
    pendingEdge.value = null
    resolvePending?.(true)
    resolvePending = null
  }

  function cancelPending() {
    confirmOpen.value = false
    pendingEdge.value = null
    resolvePending?.(false)
    resolvePending = null
  }

  return {
    confirmOpen,
    pendingEdge,
    pendingMessage,
    needsConfirmation,
    confirmIfNeeded,
    confirmPending,
    cancelPending,
  }
}
