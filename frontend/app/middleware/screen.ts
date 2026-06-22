import { useScreenPermissions } from '@/composables/useScreenPermissions'
import type { ScreenCapability } from '@/types/models'

declare module '#app' {
  interface PageMeta {
    requiredScreen?: string
    requiredCapability?: ScreenCapability
  }
}

export default defineNuxtRouteMiddleware((to) => {
  const screen = to.meta.requiredScreen
  if (!screen) return

  // Screen permissions are hydrated client-side from /auth/me; on the server
  // (or before hydration) they are empty, which would falsely bounce a
  // legitimately-permitted user to /forbidden. Defer the guard to the client,
  // where `useScreenPermissions` reads the hydrated store.
  if (import.meta.server) return

  const { can } = useScreenPermissions()
  if (!can(screen, to.meta.requiredCapability ?? 'VIEW')) {
    return navigateTo({
      path: '/forbidden',
      query: { path: to.path },
    })
  }
})
