import { ref } from 'vue'
import { useRuntimeConfig, navigateTo } from '#app'
import type { FetchError } from 'ofetch'

const HEARTBEAT_INTERVAL_MS = 60_000

// Module-level singleton registry: one active heartbeat per request id per browser tab.
// Prevents duplicate intervals during Nuxt route transition overlaps where two component
// instances of the same page coexist briefly.
const activeHeartbeats = new Map<number, ReturnType<typeof setInterval>>()

function httpStatus(err: unknown): number | undefined {
  return (err as FetchError)?.response?.status
}

export function useClaimLifecycle() {
  const isClaiming = ref(false)
  const isReleasing = ref(false)
  const claimError = ref<string | null>(null)
  const sessionExpired = ref(false)

  async function claimRequest(id: number): Promise<boolean> {
    isClaiming.value = true
    claimError.value = null
    sessionExpired.value = false

    try {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch(`/api/workflow/${id}/claim-support-review`, {
        method: 'POST',
        baseURL,
        credentials: 'include',
      })
      return true
    }
    catch (err: unknown) {
      const status = httpStatus(err)
      if (status === 409) {
        claimError.value = 'الطلب محجوز بواسطة مراجع آخر.'
      }
      else if (status === 401 || status === 403) {
        sessionExpired.value = true
        claimError.value = 'انتهت جلستك. يرجى تسجيل الدخول مرة أخرى.'
      }
      else {
        claimError.value = 'تعذّرت المطالبة بالطلب. يرجى المحاولة مرة أخرى.'
      }
      return false
    }
    finally {
      isClaiming.value = false
    }
  }

  async function releaseRequest(id: number): Promise<void> {
    isReleasing.value = true
    try {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch(`/api/workflow/${id}/claim-support-review`, {
        method: 'DELETE',
        baseURL,
        credentials: 'include',
      })
    }
    catch (err: unknown) {
      // Best-effort release — TTL auto-expire recovers missed releases.
      if (import.meta.dev) {
        console.warn('[useClaimLifecycle] releaseRequest failed (best-effort):', httpStatus(err), err)
      }
    }
    finally {
      isReleasing.value = false
    }
  }

  // Verify with the server that the current user still holds an active claim.
  // Used on page resume (is_claimed_by_me=true path) to confirm the Redis TTL
  // has not expired between sessions. Returns true if the heartbeat succeeds.
  async function verifyClaimAlive(id: number): Promise<boolean> {
    try {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch(`/api/workflow/${id}/claim-support-review/heartbeat`, {
        method: 'POST',
        baseURL,
        credentials: 'include',
      })
      return true
    }
    catch (err: unknown) {
      const status = httpStatus(err)
      if (status === 401 || status === 403) {
        sessionExpired.value = true
        claimError.value = 'انتهت جلستك. يرجى تسجيل الدخول مرة أخرى.'
      }
      else {
        // 404 = claim already expired, 422 = not the holder, any other error
        claimError.value = 'لم يعد الطلب محجوزاً باسمك.'
      }
      return false
    }
  }

  function startHeartbeat(id: number, onSessionExpired?: () => void): void {
    // Clear any existing heartbeat for this id (singleton enforcement)
    stopHeartbeat(id)

    const timer = setInterval(async () => {
      try {
        const config = useRuntimeConfig()
        const baseURL = config.public.apiBase as string
        await $fetch(`/api/workflow/${id}/claim-support-review/heartbeat`, {
          method: 'POST',
          baseURL,
          credentials: 'include',
        })
      }
      catch (err: unknown) {
        const status = httpStatus(err)

        if (import.meta.dev) {
          console.warn('[useClaimLifecycle] heartbeat failed:', status ?? 'network error', err)
        }

        if (status === 401 || status === 403) {
          // Auth failure is not a transient error — stop heartbeat and notify caller
          stopHeartbeat(id)
          sessionExpired.value = true
          claimError.value = 'انتهت جلستك أثناء المراجعة. يرجى تسجيل الدخول مرة أخرى.'
          onSessionExpired?.()
        }
        // For transient network errors or 5xx: stay silent — TTL handles missed beats
      }
    }, HEARTBEAT_INTERVAL_MS)

    activeHeartbeats.set(id, timer)
  }

  function stopHeartbeat(id?: number): void {
    if (id !== undefined) {
      const timer = activeHeartbeats.get(id)
      if (timer !== undefined) {
        clearInterval(timer)
        activeHeartbeats.delete(id)
      }
    }
    else {
      // Clear all (fallback for callers that don't track id)
      activeHeartbeats.forEach((t) => clearInterval(t))
      activeHeartbeats.clear()
    }
  }

  return {
    isClaiming,
    isReleasing,
    claimError,
    sessionExpired,
    claimRequest,
    releaseRequest,
    verifyClaimAlive,
    startHeartbeat,
    stopHeartbeat,
  }
}
