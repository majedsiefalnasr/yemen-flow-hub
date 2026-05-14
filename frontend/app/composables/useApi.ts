import type { ApiError } from '../types/models'

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  async function apiFetch<T>(
    path: string,
    options: Parameters<typeof $fetch>[1] = {},
  ): Promise<T> {
    const { headers: extraHeaders, ...restOptions } = options
    return $fetch<T>(path, {
      baseURL,
      credentials: 'include',
      ...restOptions,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(extraHeaders as Record<string, string>),
      },
    })
  }

  async function get<T>(path: string): Promise<T> {
    return apiFetch<T>(path, { method: 'GET' })
  }

  async function post<T>(path: string, body?: unknown): Promise<T> {
    return apiFetch<T>(path, { method: 'POST', body })
  }

  async function put<T>(path: string, body?: unknown): Promise<T> {
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
