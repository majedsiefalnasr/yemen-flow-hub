import type { ApiError } from '../types/models'

type ApiFetchOptions = NonNullable<Parameters<typeof $fetch>[1]>
type ApiFetchBody = ApiFetchOptions['body']

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  function getXsrfToken(): string | null {
    if (!process.client) return null
    const raw = document.cookie
      .split(';')
      .map(cookie => cookie.trim())
      .find(cookie => cookie.startsWith('XSRF-TOKEN='))
      ?.split('=')
      .slice(1)
      .join('=')

    return raw ? decodeURIComponent(raw) : null
  }

  async function apiFetch<T>(
    path: string,
    options: ApiFetchOptions = {},
  ): Promise<T> {
    const { headers: extraHeaders, ...restOptions } = options
    const method = String(options.method ?? 'GET').toUpperCase()
    const xsrfToken = getXsrfToken()
    return $fetch<T>(path, {
      baseURL,
      credentials: 'include',
      ...restOptions,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(xsrfToken && method !== 'GET' && method !== 'HEAD' ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        ...(extraHeaders as Record<string, string>),
      },
    })
  }

  async function get<T>(path: string): Promise<T> {
    return apiFetch<T>(path, { method: 'GET' })
  }

  async function post<T>(path: string, body?: ApiFetchBody): Promise<T> {
    return apiFetch<T>(path, { method: 'POST', body })
  }

  async function put<T>(path: string, body?: ApiFetchBody): Promise<T> {
    return apiFetch<T>(path, { method: 'PUT', body })
  }

  async function del<T>(path: string): Promise<T> {
    return apiFetch<T>(path, { method: 'DELETE' })
  }

  function isApiError(err: unknown): err is { data: ApiError } {
    return (
      typeof err === 'object' &&
      err !== null &&
      'data' in err &&
      typeof (err as { data: unknown }).data === 'object'
    )
  }

  return { get, post, put, del, isApiError }
}
