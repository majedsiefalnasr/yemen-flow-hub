import type { ApiError } from '../types/models'

type ApiFetchOptions = NonNullable<Parameters<typeof $fetch>[1]>
type ApiFetchBody = ApiFetchOptions['body']
type ApiFetchMethod = NonNullable<ApiFetchOptions['method']>

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

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
    const { headers: extraHeaders, ...restOptions } = options
    const method = String(options.method ?? 'GET').toUpperCase()

    if (import.meta.client && isUnsafeMethod(method) && !getXsrfToken()) {
      await ensureCsrfCookie()
    }

    const request = () =>
      $fetch<T>(path, {
        baseURL,
        credentials: 'include',
        ...restOptions,
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
      throw err
    }
  }

  async function get<T>(path: string, options: Omit<ApiFetchOptions, 'method'> = {}): Promise<T> {
    return apiFetch<T>(path, { ...options, method: 'GET' })
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

  return { get, post, put, patch, del, isApiError }
}
