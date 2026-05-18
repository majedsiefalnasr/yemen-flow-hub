import { useAuthStore } from '../stores/auth.store'

export default defineNuxtPlugin(async () => {
  const hasAuthHint = localStorage.getItem('yfh-authenticated') === '1'

  if (!hasAuthHint) {
    return
  }

  const auth = useAuthStore()
  await auth.fetchUser()
})
