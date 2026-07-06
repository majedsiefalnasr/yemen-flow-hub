import type { ApiError } from '@/types/models'

/**
 * Pull a human-readable message out of an API error. Governance endpoints return
 * `{ error: { code, message, fields } }`; older endpoints return `{ message }`.
 * Falls back to the provided default when neither is present.
 */
export function extractApiErrorMessage(err: unknown, fallback: string): string {
  const data = (err as { data?: { error?: { message?: string }; message?: string } })?.data
  return data?.error?.message ?? data?.message ?? fallback
}

export function extractApiErrorCode(err: unknown): string | null {
  const data = (err as { data?: { error_code?: string; error?: { code?: string } } })?.data
  return data?.error_code ?? data?.error?.code ?? null
}

export function extractApiFieldErrors(err: unknown): Record<string, string | undefined> {
  const errors = (err as { data?: ApiError })?.data?.errors ?? {}
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : typeof messages === 'string' ? messages : undefined,
    ]),
  )
}
