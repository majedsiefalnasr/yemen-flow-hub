import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useReferenceCacheStore } from '../../../stores/referenceCache.store'

describe('useReferenceCacheStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('calls the fetcher on a cache miss and caches the result', async () => {
    const store = useReferenceCacheStore()
    const fetcher = vi.fn().mockResolvedValue(['a', 'b'])

    const result = await store.remember('banks', fetcher)

    expect(result).toEqual(['a', 'b'])
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('returns the cached value without calling the fetcher again within the TTL', async () => {
    const store = useReferenceCacheStore()
    const fetcher = vi.fn().mockResolvedValue(['a', 'b'])

    await store.remember('banks', fetcher)
    const second = await store.remember('banks', fetcher)

    expect(second).toEqual(['a', 'b'])
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('re-fetches once the TTL has elapsed', async () => {
    vi.useFakeTimers()
    const store = useReferenceCacheStore()
    const fetcher = vi.fn().mockResolvedValueOnce(['a']).mockResolvedValueOnce(['a', 'b'])

    const first = await store.remember('banks', fetcher, 1000)
    vi.advanceTimersByTime(1001)
    const second = await store.remember('banks', fetcher, 1000)

    expect(first).toEqual(['a'])
    expect(second).toEqual(['a', 'b'])
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('keeps separate cache entries per key', async () => {
    const store = useReferenceCacheStore()
    const banksFetcher = vi.fn().mockResolvedValue(['bank'])
    const orgsFetcher = vi.fn().mockResolvedValue(['org'])

    await store.remember('banks', banksFetcher)
    await store.remember('organizations', orgsFetcher)
    await store.remember('banks', banksFetcher)
    await store.remember('organizations', orgsFetcher)

    expect(banksFetcher).toHaveBeenCalledTimes(1)
    expect(orgsFetcher).toHaveBeenCalledTimes(1)
  })

  it('invalidate() forces the next remember() to re-fetch', async () => {
    const store = useReferenceCacheStore()
    const fetcher = vi.fn().mockResolvedValueOnce(['stale']).mockResolvedValueOnce(['fresh'])

    await store.remember('banks', fetcher)
    store.invalidate('banks')
    const result = await store.remember('banks', fetcher)

    expect(result).toEqual(['fresh'])
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('clear() invalidates every cached key', async () => {
    const store = useReferenceCacheStore()
    const banksFetcher = vi.fn().mockResolvedValueOnce(['a']).mockResolvedValueOnce(['a2'])
    const orgsFetcher = vi.fn().mockResolvedValueOnce(['b']).mockResolvedValueOnce(['b2'])

    await store.remember('banks', banksFetcher)
    await store.remember('organizations', orgsFetcher)
    store.clear()
    await store.remember('banks', banksFetcher)
    await store.remember('organizations', orgsFetcher)

    expect(banksFetcher).toHaveBeenCalledTimes(2)
    expect(orgsFetcher).toHaveBeenCalledTimes(2)
  })
})
