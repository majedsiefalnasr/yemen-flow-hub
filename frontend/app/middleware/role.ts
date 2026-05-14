import { useAuthStore } from '../stores/auth.store'
import type { UserRole } from '../types/enums'

declare module '#app' {
  interface PageMeta {
    requiredRoles?: UserRole[]
  }
}

export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const requiredRoles = to.meta.requiredRoles as UserRole[] | undefined

  if (!requiredRoles || requiredRoles.length === 0) return

  if (!auth.user || !requiredRoles.includes(auth.user.role)) {
    return navigateTo('/')
  }
})
