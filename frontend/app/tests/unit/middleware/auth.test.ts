import { beforeEach, describe, expect, it, vi } from 'vitest'

const isAuthenticated = { value: false }
const navigateTo = vi.fn((target: unknown) => target)

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    get isAuthenticated() {
      return isAuthenticated.value
    },
  }),
}))

vi.stubGlobal('defineNuxtRouteMiddleware', (guard: any) => guard)
vi.stubGlobal('navigateTo', navigateTo)

describe('auth middleware', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('redirects guests to unauthorized page with next path', async () => {
    isAuthenticated.value = false
    const middleware = (await import('../../../middleware/auth')).default

    middleware(
      {
        fullPath: '/requests?tab=active',
      } as any,
      {} as any,
    )

    expect(navigateTo).toHaveBeenCalledWith({
      path: '/unauthorized',
      query: { next: '/requests?tab=active' },
    })
  })

  it('allows authenticated users', async () => {
    isAuthenticated.value = true
    const middleware = (await import('../../../middleware/auth')).default

    const result = middleware(
      {
        fullPath: '/dashboard',
      } as any,
      {} as any,
    )

    expect(navigateTo).not.toHaveBeenCalled()
    expect(result).toBeUndefined()
  })
})
