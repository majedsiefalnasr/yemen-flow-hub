// Dedicated, narrow file (not folded into useApi.test.ts, which is a known-red
// baseline quarantine — see vitest.config.ts) covering one specific
// regression: a FormData body (file uploads) must not get a forced
// application/json Content-Type, which silently strips the multipart
// boundary and empties the request as far as the server is concerned.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mockFetch = vi.fn()
let cookieJar = 'XSRF-TOKEN=existing-token'

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
  Object.defineProperty(process, 'client', { configurable: true, value })
}

describe('useApi — FormData Content-Type', () => {
  beforeEach(() => {
    mockFetch.mockReset()
    mockFetch.mockResolvedValue({ success: true })
    setClientFlag(true)
  })

  afterEach(() => {
    setClientFlag(undefined)
  })

  it('does not set Content-Type when the body is FormData, letting the browser set the multipart boundary', async () => {
    const { useApi } = await import('@/composables/useApi')
    const { post } = useApi()

    const formData = new FormData()
    formData.append('file', new File(['x'], 'a.pdf'))

    await post('/api/v1/temporary-uploads', formData)

    const call = mockFetch.mock.calls.find((c) => c[0] === '/api/v1/temporary-uploads') as unknown[]
    const options = call[1] as { headers: Record<string, string> }
    expect(options.headers['content-type']).toBeUndefined()
  })

  it('still sets Content-Type: application/json for a plain object body', async () => {
    const { useApi } = await import('@/composables/useApi')
    const { post } = useApi()

    await post('/api/v1/engine-requests', { workflow_version_id: 1 })

    const call = mockFetch.mock.calls.find((c) => c[0] === '/api/v1/engine-requests') as unknown[]
    const options = call[1] as { headers: Record<string, string> }
    expect(options.headers['content-type']).toBe('application/json')
  })
})
