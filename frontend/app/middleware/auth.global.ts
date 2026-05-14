import { useAuthStore } from '../stores/auth.store'

// Routes that do not require authentication
const PUBLIC_PATHS = new Set(['/login', '/'])

export default defineNuxtRouteMiddleware((to) => {
  if (PUBLIC_PATHS.has(to.path)) return

  const auth = useAuthStore()
  if (!auth.isAuthenticated) return navigateTo('/login')
})
