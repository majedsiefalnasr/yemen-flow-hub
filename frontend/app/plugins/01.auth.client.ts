import { useAuthStore } from '../stores/auth.store'
import { useThemingStore } from '../stores/theming.store'

export default defineNuxtPlugin(async () => {
  const config = useRuntimeConfig()
  if (config.public.visualBypass) {
    return
  }

  const hasAuthHint = localStorage.getItem('yfh-authenticated') === '1'

  if (!hasAuthHint) {
    return
  }

  const auth = useAuthStore()
  await auth.fetchUser()
  if (auth.isAuthenticated) {
    await auth.fetchUserPreferences()
    useThemingStore().applyAppearanceSettings(auth.userPreferences?.theming)
  }
})
