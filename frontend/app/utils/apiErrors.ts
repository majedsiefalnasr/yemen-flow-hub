import type { ApiError } from '@/types/models'

type ApiErrorPayload = {
  message?: string
  error_code?: string
  errors?: Record<string, string[] | string>
  request_id?: string
  error?: {
    code?: string
    message?: string
    fields?: Record<string, string[] | string>
    request_id?: string
  }
}

function readErrorPayload(err: unknown): ApiErrorPayload | undefined {
  const data = (err as { data?: ApiErrorPayload })?.data
  if (data && typeof data === 'object') {
    return data
  }

  return undefined
}

function normalizeFieldErrors(
  fields: Record<string, string[] | string> | undefined,
): Record<string, string | undefined> {
  if (!fields) return {}

  return Object.fromEntries(
    Object.entries(fields).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : typeof messages === 'string' ? messages : undefined,
    ]),
  )
}

/**
 * Pull a human-readable message out of an API error. Governance endpoints return
 * `{ error: { code, message, fields } }`; older endpoints return `{ message }`.
 * Falls back to the provided default when neither is present.
 */
export function extractApiErrorMessage(err: unknown, fallback: string): string {
  const data = readErrorPayload(err)
  return data?.error?.message ?? data?.message ?? fallback
}

export function extractApiErrorCode(err: unknown): string | null {
  const data = readErrorPayload(err)
  return data?.error?.code ?? data?.error_code ?? null
}

export function extractRequestId(err: unknown): string | null {
  const data = readErrorPayload(err)
  return data?.error?.request_id ?? data?.request_id ?? null
}

/**
 * Read the HTTP status off an ofetch error, which exposes it as `status`,
 * `statusCode`, or `response.status` depending on the failure path. Returns null
 * for non-HTTP errors (network, abort).
 */
export function extractHttpStatus(err: unknown): number | null {
  if (typeof err !== 'object' || err === null) return null
  const candidate = err as {
    status?: number
    statusCode?: number
    response?: { status?: number }
  }
  return candidate.status ?? candidate.statusCode ?? candidate.response?.status ?? null
}

export function extractApiFieldErrors(err: unknown): Record<string, string | undefined> {
  const data = readErrorPayload(err) as ApiError | ApiErrorPayload | undefined
  if (!data) return {}

  const richFields = (data as ApiErrorPayload).error?.fields
  if (richFields) {
    return normalizeFieldErrors(richFields)
  }

  return normalizeFieldErrors((data as ApiError).errors)
}
