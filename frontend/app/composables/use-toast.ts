export type ToastVariant = 'default' | 'success' | 'error' | 'info'

export type ToastItem = {
  id: string
  title: string
  description?: string
  variant: ToastVariant
}

type ToastInput = string | {
  title: string
  description?: string
}

function normalizeToast(input: ToastInput) {
  return typeof input === 'string' ? { title: input } : input
}

export function useToast() {
  const toasts = useState<ToastItem[]>('toasts', () => [])

  function push(input: ToastInput, variant: ToastVariant = 'default') {
    const item = normalizeToast(input)
    const id = `toast_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`
    toasts.value = [{ id, variant, ...item }, ...toasts.value].slice(0, 4)

    if (import.meta.client) {
      window.setTimeout(() => dismiss(id), 4_000)
    }

    return id
  }

  function dismiss(id: string) {
    toasts.value = toasts.value.filter(toast => toast.id !== id)
  }

  return {
    toasts,
    dismiss,
    toast: {
      default: (input: ToastInput) => push(input),
      success: (input: ToastInput) => push(input, 'success'),
      error: (input: ToastInput) => push(input, 'error'),
      info: (input: ToastInput) => push(input, 'info'),
    },
  }
}
