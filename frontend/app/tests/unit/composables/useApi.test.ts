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

    await post('/api/v1/notifications/abc-123/read')

    expect(mockFetch).toHaveBeenNthCalledWith(
      1,
      '/sanctum/csrf-cookie',
      expect.objectContaining({
        credentials: 'include',
      }),
    )
    expect(mockFetch).toHaveBeenNthCalledWith(
      2,
      '/api/v1/notifications/abc-123/read',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'x-xsrf-token': 'fresh-token',
        }),
      }),
    )
  })

  it('refreshes the CSRF cookie and retries once after a 419', async () => {
    cookieJar = 'XSRF-TOKEN=stale-token'
    mockFetch.mockImplementation(async (path: string) => {
      if (path === '/sanctum/csrf-cookie') {
        document.cookie = 'XSRF-TOKEN=fresh-token'
        return null
      }

      if (
        path === '/api/v1/notifications/abc-123/read' &&
        mockFetch.mock.calls.filter(([callPath]) => callPath === path).length === 1
      ) {
        throw { statusCode: 419 }
      }

      return { success: true }
    })

    const { useApi } = await import('../../../composables/useApi')
    const { post } = useApi()

    await post('/api/v1/notifications/abc-123/read')

    expect(mockFetch).toHaveBeenCalledTimes(3)
    expect(mockFetch).toHaveBeenNthCalledWith(2, '/sanctum/csrf-cookie', expect.any(Object))
    expect(mockFetch).toHaveBeenNthCalledWith(
      3,
      '/api/v1/notifications/abc-123/read',
      expect.objectContaining({
        headers: expect.objectContaining({
          'x-xsrf-token': 'fresh-token',
        }),
      }),
    )
  })
})

// FE-001: getAbortable() threads an AbortController signal through GET so a
// caller (or the composable itself, on component unmount) can cancel an
// in-flight read instead of letting it complete for nobody.
describe('useApi — getAbortable (FE-001)', () => {
  beforeEach(() => {
    cookieJar = ''
    mockFetch.mockReset()
    setClientFlag(true)
  })

  afterEach(() => {
    setClientFlag(undefined)
  })

  it('passes an AbortController signal to the underlying fetch call', async () => {
    mockFetch.mockResolvedValueOnce({ data: [] })
    const { useApi } = await import('../../../composables/useApi')
    const { getAbortable } = useApi()

    await getAbortable('/api/v1/engine-requests')

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/v1/engine-requests',
      expect.objectContaining({
        method: 'GET',
        signal: expect.any(AbortSignal),
      }),
    )
  })

  it('rejects with an AbortError-shaped error when the signal is aborted before the response resolves', async () => {
    let capturedSignal: AbortSignal | undefined
    mockFetch.mockImplementation((_path: string, options: { signal?: AbortSignal }) => {
      capturedSignal = options.signal
      return new Promise((_resolve, reject) => {
        options.signal?.addEventListener('abort', () => {
          const err = new Error('The operation was aborted.')
          err.name = 'AbortError'
          reject(err)
        })
      })
    })

    const { useApi, isAbortError } = await import('../../../composables/useApi')
    const { getAbortable } = useApi()

    const promise = getAbortable('/api/v1/engine-requests')
    capturedSignal?.dispatchEvent(new Event('abort'))

    await expect(promise).rejects.toThrow('aborted')
    let caught: unknown
    try {
      await promise
    } catch (err) {
      caught = err
    }
    expect(isAbortError(caught)).toBe(true)
  })

  it('isAbortError returns false for a regular error', async () => {
    const { isAbortError } = await import('../../../composables/useApi')
    expect(isAbortError(new Error('network down'))).toBe(false)
    expect(isAbortError(null)).toBe(false)
    expect(isAbortError('not an error object')).toBe(false)
  })
})
