import { ref } from 'vue'
import { useRuntimeConfig } from '#app'
import type { FetchError } from 'ofetch'

const HEARTBEAT_INTERVAL_MS = 60_000
const LOGOUT_IN_PROGRESS_STORAGE_KEY = 'yfh-logout-in-progress'

// Module-level singleton registry: one active heartbeat per request id per browser tab.
// Prevents duplicate intervals during Nuxt route transition overlaps where two component
// instances of the same page coexist briefly.
const activeHeartbeats = new Map<number, ReturnType<typeof setInterval>>()
// Paired stop-flag setters: called before clearInterval so any in-flight fetch
// that resolves after clearInterval knows not to invoke onClaimLost callbacks.
const stopFlags = new Map<number, () => void>()

function httpStatus(err: unknown): number | undefined {
  return (err as FetchError)?.response?.status
}

function getXsrfToken(): string | null {
  if (!import.meta.client) return null
  const raw = document.cookie
    .split(';')
    .map(c => c.trim())
    .find(c => c.startsWith('XSRF-TOKEN='))
    ?.split('=')
    .slice(1)
    .join('=')
  return raw ? decodeURIComponent(raw) : null
}

function claimHeaders(): Record<string, string> {
  const token = getXsrfToken()
  return {
    Accept: 'application/json',
    ...(token ? { 'X-XSRF-TOKEN': token } : {}),
  }
}

function isLogoutInProgress(): boolean {
  if (!import.meta.client) return false
  return sessionStorage.getItem(LOGOUT_IN_PROGRESS_STORAGE_KEY) === '1'
}

/**
 * Generic claim lifecycle composable.
 * Pass `claimEndpoint` to target a specific claim route (e.g. 'claim-bank-review').
 * Defaults to 'claim-support-review' to keep existing call-sites unchanged.
 *
 * Backend endpoints required per role:
 *   SUPPORT_COMMITTEE  → claim-support-review          (exists)
 *   BANK_REVIEWER      → claim-bank-review             (TODO: backend)
 *   COMMITTEE_DIRECTOR → claim-director-review         (TODO: backend)
 *   EXECUTIVE_MEMBER   → claim-executive-review        (TODO: backend)
 */
export function useClaimLifecycle(claimEndpoint = 'claim-support-review') {
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
      await $fetch(`/api/workflow/${id}/${claimEndpoint}`, {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: claimHeaders(),
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

  // Returns true if the server confirmed the release; false on network/server
  // failure. Callers may still treat release as best-effort (TTL=15min recovers
  // a missed release) but at the call site they can choose to delay local state
  // mutation until the server confirms.
  async function releaseRequest(id: number): Promise<boolean> {
    if (isLogoutInProgress()) return false

    isReleasing.value = true
    try {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch(`/api/workflow/${id}/${claimEndpoint}`, {
        method: 'DELETE',
        baseURL,
        credentials: 'include',
        headers: claimHeaders(),
      })
      return true
    }
    catch (err: unknown) {
      // Best-effort release — TTL auto-expire recovers missed releases.
      if (import.meta.dev) {
        console.warn('[useClaimLifecycle] releaseRequest failed (best-effort):', httpStatus(err), err)
      }
      return false
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
      await $fetch(`/api/workflow/${id}/${claimEndpoint}/heartbeat`, {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: claimHeaders(),
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

  function startHeartbeat(
    id: number,
    onSessionExpired?: () => void | Promise<void>,
    onClaimLost?: () => void | Promise<void>,
  ): void {
    // Clear any existing heartbeat for this id (singleton enforcement)
    stopHeartbeat(id)

    // intentionallyStopped is set true by stopHeartbeat so a tick that fires
    // between clearInterval and the async fetch resolving does not trigger
    // onClaimLost callbacks after the component has already torn down.
    let intentionallyStopped = false

    const timer = setInterval(async () => {
      if (intentionallyStopped || isLogoutInProgress()) {
        stopHeartbeat(id)
        return
      }

      try {
        const config = useRuntimeConfig()
        const baseURL = config.public.apiBase as string
        await $fetch(`/api/workflow/${id}/${claimEndpoint}/heartbeat`, {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: claimHeaders(),
        })
      }
      catch (err: unknown) {
        // If stopHeartbeat was called while this fetch was in-flight (e.g. the
        // component unmounted and released the claim), ignore the error silently.
        if (intentionallyStopped) return

        const status = httpStatus(err)

        if (import.meta.dev) {
          console.warn('[useClaimLifecycle] heartbeat failed:', status ?? 'network error', err)
        }

        if (status === 401) {
          stopHeartbeat(id)
          sessionExpired.value = true
          claimError.value = 'انتهت جلستك أثناء المراجعة. يرجى تسجيل الدخول مرة أخرى.'
          onSessionExpired?.()
        }
        else if (status === 403 || status === 404 || status === 409 || status === 422) {
          // Claim ownership was lost or expired on the server side (not by us).
          stopHeartbeat(id)
          claimError.value = 'لم يعد الطلب محجوزاً باسمك.'
          onClaimLost?.()
        }
        // Transient network errors or 5xx: stay silent — TTL handles missed beats
      }
    }, HEARTBEAT_INTERVAL_MS)

    activeHeartbeats.set(id, timer)
    stopFlags.set(id, () => { intentionallyStopped = true })

    activeHeartbeats.set(id, timer)
  }

  function stopHeartbeat(id?: number): void {
    if (id !== undefined) {
      // Flip the intentionallyStopped flag first so any in-flight fetch that
      // resolves after clearInterval does not invoke onClaimLost callbacks.
      stopFlags.get(id)?.()
      stopFlags.delete(id)
      const timer = activeHeartbeats.get(id)
      if (timer !== undefined) {
        clearInterval(timer)
        activeHeartbeats.delete(id)
      }
    }
    else {
      stopFlags.forEach(fn => fn())
      stopFlags.clear()
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
