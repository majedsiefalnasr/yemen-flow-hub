import { useAuthStore } from '../stores/auth.store'

export async function waitForAuthReady(): Promise<void> {
  const auth = useAuthStore()
  if (auth.authReady) return

  await new Promise<void>((resolve) => {
    const unwatch = watch(
      () => auth.authReady,
      (ready) => {
        if (ready) {
          unwatch()
          resolve()
        }
      },
    )
  })
}
