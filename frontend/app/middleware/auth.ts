import { useAuthStore } from '../stores/auth.store'
import { waitForAuthReady } from '../composables/useAuthReady'

export default defineNuxtRouteMiddleware(async (to) => {
  const config = useRuntimeConfig()
  if (config.public.visualBypass) {
    return
  }

  await waitForAuthReady()

  const auth = useAuthStore()

  if (!auth.isAuthenticated) {
    return navigateTo({
      path: '/unauthorized',
      query:
        typeof to.fullPath === 'string' && to.fullPath.length > 0
          ? { next: to.fullPath }
          : undefined,
    })
  }

  if (auth.user?.must_change_password && to.path !== '/change-temporary-password') {
    return navigateTo('/change-temporary-password')
  }
})
