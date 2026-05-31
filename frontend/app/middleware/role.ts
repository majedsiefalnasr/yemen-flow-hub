import { useAuthStore } from '../stores/auth.store'
import type { UserRole } from '../types/enums'
import { resolveRouteRoles } from '../constants/workflow'

declare module '#app' {
  interface PageMeta {
    requiredRoles?: UserRole[]
  }
}

export default defineNuxtRouteMiddleware((to) => {
  const config = useRuntimeConfig()
  if (config.public.visualBypass) {
    return
  }

  const auth = useAuthStore()
  const requiredRoles = (to.meta.requiredRoles as UserRole[] | undefined) ?? resolveRouteRoles(to.path)

  if (!requiredRoles || requiredRoles.length === 0) return

  if (!auth.user || !requiredRoles.includes(auth.user.role)) {
    return navigateTo({
      path: '/forbidden',
      query: {
        path: to.path,
      },
    })
  }
})
