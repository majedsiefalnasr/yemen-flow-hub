import { useAuthStore } from '../stores/auth.store'

export default defineNuxtRouteMiddleware((to) => {
  const config = useRuntimeConfig()
  if (config.public.visualBypass) {
    return
  }

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
