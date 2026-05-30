import { beforeEach, describe, expect, it, vi } from 'vitest'
import { UserRole } from '../../../types/enums'

const authState = {
  user: null as null | { role: UserRole },
}
const navigateTo = vi.fn((target: unknown) => target)

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => authState,
}))

vi.stubGlobal('defineNuxtRouteMiddleware', (guard: any) => guard)
vi.stubGlobal('navigateTo', navigateTo)

describe('role middleware', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('redirects forbidden role to forbidden page with route path', async () => {
    authState.user = { role: UserRole.DATA_ENTRY }
    const middleware = (await import('../../../middleware/role')).default

    middleware({
      path: '/admin/banks',
      meta: { requiredRoles: [UserRole.CBY_ADMIN] },
    } as any, {} as any)

    expect(navigateTo).toHaveBeenCalledWith({
      path: '/forbidden',
      query: { path: '/admin/banks' },
    })
  })

  it('allows permitted role', async () => {
    authState.user = { role: UserRole.CBY_ADMIN }
    const middleware = (await import('../../../middleware/role')).default

    const result = middleware({
      path: '/admin/banks',
      meta: { requiredRoles: [UserRole.CBY_ADMIN] },
    } as any, {} as any)

    expect(navigateTo).not.toHaveBeenCalled()
    expect(result).toBeUndefined()
  })
})
