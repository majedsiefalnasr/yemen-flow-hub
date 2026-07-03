import { useAuthStore } from '../stores/auth.store'
import { useThemingStore } from '../stores/theming.store'

export default defineNuxtPlugin(async () => {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  if (config.public.visualBypass) {
    auth.authReady = true
    return
  }

  const hasAuthHint = localStorage.getItem('yfh-authenticated') === '1'

  if (!hasAuthHint) {
    auth.authReady = true
    return
  }

  auth.authReady = false
  await auth.fetchUser()
  auth.authReady = true
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
