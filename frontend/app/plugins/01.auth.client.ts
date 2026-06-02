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
    const theming = useThemingStore()
    theming.applyAppearanceSettings(auth.userPreferences?.theming)
    // Push state to DOM so the app shell renders with the correct
    // user theme. Without this, loadSettings()'s loadFromCache() can
    // briefly overwrite the state and useHead() would read stale values.
    theming.applyTheme()
    theming.applyFont()
    theming.applyBranding()
    theming.applyHighContrast()
    theming.applyRadius()
    theming.applyDensity()
  }
})
