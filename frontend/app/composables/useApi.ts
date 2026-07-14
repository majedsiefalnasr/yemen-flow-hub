import { getCurrentInstance, onUnmounted } from 'vue'
import type { ApiError } from '../types/models'
import {
  extractApiErrorCode,
  extractApiErrorMessage,
  extractApiFieldErrors,
  extractRequestId,
} from '../utils/apiErrors'

type ApiFetchOptions = NonNullable<Parameters<typeof $fetch>[1]>
type ApiFetchBody = ApiFetchOptions['body']
type ApiFetchMethod = NonNullable<ApiFetchOptions['method']>

/** AbortError is thrown by ofetch when a request is cancelled via signal. */
export function isAbortError(err: unknown): boolean {
  return (
    typeof err === 'object' &&
    err !== null &&
    ('name' in err ? (err as { name?: string }).name === 'AbortError' : false)
  )
}

/**
 * Longest Retry-After (seconds) worth absorbing transparently. A short window
 * means a page-load burst tripped the limiter and one delayed retry will
 * succeed; anything longer is a real rate-limit and must surface to the
 * caller's error state instead of hanging the UI on a silent wait.
 */
const MAX_RATE_LIMIT_RETRY_SECONDS = 3

/**
 * Milliseconds to wait before retrying a 429'd read, or null when the error is
 * not a 429 / the server's Retry-After exceeds the transparent-retry budget.
 * Falls back to 1s when the header is missing or unparsable.
 */
function rateLimitRetryDelayMs(err: unknown): number | null {
  if (typeof err !== 'object' || err === null) return null
  const response = (err as { response?: { status?: number; headers?: Headers } }).response
  if (response?.status !== 429) return null

  const header = response.headers?.get?.('Retry-After')
  const parsed = Number(header)
  const seconds = Number.isFinite(parsed) && parsed > 0 ? parsed : 1
  return seconds <= MAX_RATE_LIMIT_RETRY_SECONDS ? seconds * 1000 : null
}

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string
  // FE-001: controllers registered via getAbortable() below, aborted together
  // on component unmount so a GET in flight when the user navigates away
  // doesn't keep consuming bandwidth/connection slots for a response nobody
  // will read. Mutations (post/put/patch/del) never register here — never
  // cancel a write mid-flight.
  const pendingControllers = new Set<AbortController>()

  if (getCurrentInstance()) {
    onUnmounted(() => {
      for (const controller of pendingControllers) controller.abort()
      pendingControllers.clear()
    })
  }

  function getXsrfToken(): string | null {
    if (!import.meta.client) return null
    const raw = document.cookie
      .split(';')
      .map((cookie) => cookie.trim())
      .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
      ?.split('=')
      .slice(1)
      .join('=')

    return raw ? decodeURIComponent(raw) : null
  }

  function isUnsafeMethod(method: string): boolean {
    return method !== 'GET' && method !== 'HEAD'
  }

  function isCsrfMismatch(err: any): boolean {
    if (typeof err !== 'object' || err === null) return false
    const candidate = err as {
      status?: number
      statusCode?: number
      response?: { status?: number }
    }

    return (
      candidate.status === 419 || candidate.statusCode === 419 || candidate.response?.status === 419
    )
  }

  async function ensureCsrfCookie(): Promise<void> {
    if (!import.meta.client) return

    await $fetch('/sanctum/csrf-cookie', {
      baseURL,
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
  }

  /**
   * Remove empty/null/undefined values from a query object so ofetch does not
   * serialize them as bare keys (e.g. `?search`), which Laravel rejects on
   * `string`-validated params. Empty strings, null, undefined dropped.
   */
  function stripEmptyQueryParams(
    query: ApiFetchOptions['query'],
  ): Record<string, unknown> | undefined {
    if (query == null || typeof query !== 'object') return undefined
    const result: Record<string, unknown> = {}
    for (const [key, value] of Object.entries(query)) {
      if (value === null || value === undefined || value === '') continue
      result[key] = value
    }
    return result
  }

  function buildHeaders(
    method: string,
    extraHeaders?: ApiFetchOptions['headers'],
  ): Record<string, string> {
    const headers = new Headers(extraHeaders as HeadersInit | undefined)
    if (!headers.has('Accept')) headers.set('Accept', 'application/json')
    if (!headers.has('Content-Type')) headers.set('Content-Type', 'application/json')

    const xsrfToken = getXsrfToken()
    if (xsrfToken && isUnsafeMethod(method) && !headers.has('X-XSRF-TOKEN')) {
      headers.set('X-XSRF-TOKEN', xsrfToken)
    }

    return Object.fromEntries(headers.entries())
  }

  async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
    const { headers: extraHeaders, query, ...restOptions } = options
    const method = String(options.method ?? 'GET').toUpperCase()

    if (import.meta.client && isUnsafeMethod(method) && !getXsrfToken()) {
      await ensureCsrfCookie()
    }

    // Drop empty/null/undefined query params so they aren't serialized as bare
    // keys (e.g. `?search`) which Laravel rejects on `string`-typed rules.
    const cleanQuery = stripEmptyQueryParams(query)

    const request = () =>
      $fetch<T>(path, {
        baseURL,
        credentials: 'include',
        ...restOptions,
        query: cleanQuery,
        method: method as ApiFetchMethod,
        headers: buildHeaders(method, extraHeaders),
      })

    try {
      return await request()
    } catch (err) {
      if (import.meta.client && isUnsafeMethod(method) && isCsrfMismatch(err)) {
        await ensureCsrfCookie()
        return request()
      }
      // Reads burst-throttled by the shared api-default limiter retry once
      // after the server's Retry-After. Writes never auto-retry: the caller
      // must stay in control of re-submitting a mutation.
      if (!isUnsafeMethod(method)) {
        const delayMs = rateLimitRetryDelayMs(err)
        if (delayMs !== null) {
          await new Promise((resolve) => setTimeout(resolve, delayMs))
          return request()
        }
      }
      throw err
    }
  }

  async function get<T>(path: string, options: Omit<ApiFetchOptions, 'method'> = {}): Promise<T> {
    return apiFetch<T>(path, { ...options, method: 'GET' })
  }

  /**
   * FE-001: same as get(), but the request is auto-aborted if the component
   * that called it unmounts before the response arrives (rapid navigation,
   * fast tab switching) — the in-flight bytes stop being consumed instead of
   * completing for a component that no longer exists. Only use for GET reads;
   * never use for a call whose side effect must always run to completion.
   */
  async function getAbortable<T>(
    path: string,
    options: Omit<ApiFetchOptions, 'method' | 'signal'> = {},
  ): Promise<T> {
    const controller = new AbortController()
    pendingControllers.add(controller)
    try {
      return await apiFetch<T>(path, { ...options, method: 'GET', signal: controller.signal })
    } finally {
      pendingControllers.delete(controller)
    }
  }

  async function post<T>(
    path: string,
    body?: ApiFetchBody,
    options: Omit<ApiFetchOptions, 'method' | 'body'> = {},
  ): Promise<T> {
    return apiFetch<T>(path, { ...options, method: 'POST', body })
  }

  async function put<T>(
    path: string,
    body?: ApiFetchBody,
    options: Omit<ApiFetchOptions, 'method' | 'body'> = {},
  ): Promise<T> {
    return apiFetch<T>(path, { ...options, method: 'PUT', body })
  }

  async function patch<T>(
    path: string,
    body?: ApiFetchBody,
    options: Omit<ApiFetchOptions, 'method' | 'body'> = {},
  ): Promise<T> {
    return apiFetch<T>(path, { ...options, method: 'PATCH', body })
  }

  async function del<T>(path: string, options: Omit<ApiFetchOptions, 'method'> = {}): Promise<T> {
    return apiFetch<T>(path, { ...options, method: 'DELETE' })
  }

  function isApiError(err: any): err is { data: ApiError } {
    return (
      typeof err === 'object' &&
      err !== null &&
      'data' in err &&
      typeof (err as { data: any }).data === 'object'
    )
  }

  return {
    get,
    getAbortable,
    post,
    put,
    patch,
    del,
    isApiError,
    extractApiErrorMessage,
    extractApiErrorCode,
    extractApiFieldErrors,
    extractRequestId,
  }
}
