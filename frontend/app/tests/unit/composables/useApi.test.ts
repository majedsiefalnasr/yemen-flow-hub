import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mockFetch = vi.fn()
let cookieJar = ''

vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost:8000' } }))
vi.stubGlobal('document', {
  get cookie() {
    return cookieJar
  },
  set cookie(value: string) {
    cookieJar = value.split(';')[0] ?? ''
  },
})

function setClientFlag(value: boolean | undefined) {
  Object.defineProperty(process, 'client', {
    configurable: true,
    value,
  })
}

describe('useApi', () => {
  beforeEach(() => {
    cookieJar = ''
    mockFetch.mockReset()
    setClientFlag(true)
  })

  afterEach(() => {
    setClientFlag(undefined)
  })

  it('initializes the Sanctum CSRF cookie before unsafe browser requests', async () => {
    mockFetch.mockImplementation(async (path: string) => {
      if (path === '/sanctum/csrf-cookie') {
        document.cookie = 'XSRF-TOKEN=fresh-token'
        return null
      }

      return { success: true }
    })

    const { useApi } = await import('../../../composables/useApi')
    const { post } = useApi()

    await post('/api/notifications/abc-123/read')

    expect(mockFetch).toHaveBeenNthCalledWith(1, '/sanctum/csrf-cookie', expect.objectContaining({
      credentials: 'include',
    }))
    expect(mockFetch).toHaveBeenNthCalledWith(2, '/api/notifications/abc-123/read', expect.objectContaining({
      method: 'POST',
      headers: expect.objectContaining({
        'x-xsrf-token': 'fresh-token',
      }),
    }))
  })

  it('refreshes the CSRF cookie and retries once after a 419', async () => {
    cookieJar = 'XSRF-TOKEN=stale-token'
    mockFetch.mockImplementation(async (path: string) => {
      if (path === '/sanctum/csrf-cookie') {
        document.cookie = 'XSRF-TOKEN=fresh-token'
        return null
      }

      if (path === '/api/notifications/abc-123/read' && mockFetch.mock.calls.filter(([callPath]) => callPath === path).length === 1) {
        throw { statusCode: 419 }
      }

      return { success: true }
    })

    const { useApi } = await import('../../../composables/useApi')
    const { post } = useApi()

    await post('/api/notifications/abc-123/read')

    expect(mockFetch).toHaveBeenCalledTimes(3)
    expect(mockFetch).toHaveBeenNthCalledWith(2, '/sanctum/csrf-cookie', expect.any(Object))
    expect(mockFetch).toHaveBeenNthCalledWith(3, '/api/notifications/abc-123/read', expect.objectContaining({
      headers: expect.objectContaining({
        'x-xsrf-token': 'fresh-token',
      }),
    }))
  })
})
