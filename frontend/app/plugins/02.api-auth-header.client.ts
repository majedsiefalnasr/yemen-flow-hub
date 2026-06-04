const ACCESS_TOKEN_STORAGE_KEY = 'yfh-api-token'

function isApiRequest(requestUrl: string, apiBase: string): boolean {
  if (requestUrl.startsWith('/api/') || requestUrl.startsWith('/sanctum/')) {
    return true
  }

  try {
    const requestOrigin = new URL(requestUrl, window.location.origin).origin
    const apiOrigin = new URL(apiBase).origin
    return requestOrigin === apiOrigin
  } catch {
    return false
  }
}

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase as string

  const baseFetch = globalThis.$fetch
  const authAwareFetch = baseFetch.create({
    onRequest({ request, options }) {
      const token = localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY)
      if (!token) return

      const requestUrl =
        typeof request === 'string' ? request : request instanceof Request ? request.url : ''

      if (!requestUrl || !isApiRequest(requestUrl, apiBase)) return

      const headers = new Headers(options.headers as HeadersInit | undefined)
      if (!headers.has('Authorization')) {
        headers.set('Authorization', `Bearer ${token}`)
      }
      options.headers = headers
    },
  })

  globalThis.$fetch = authAwareFetch as typeof globalThis.$fetch
})
