import { useAuthStore } from '../stores/auth.store'

export default defineNuxtRouteMiddleware(() => {
  const auth = useAuthStore()

  if (auth.isAuthenticated) {
    return navigateTo('/')
  }
})
