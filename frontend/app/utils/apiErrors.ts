import type { ApiError } from '@/types/models'

export function extractApiFieldErrors(err: unknown): Record<string, string | undefined> {
  const errors = (err as { data?: ApiError })?.data?.errors ?? {}
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : typeof messages === 'string' ? messages : undefined,
    ]),
  )
}
