import { ref } from 'vue'
import { useRuntimeConfig } from '#app'

const HEARTBEAT_INTERVAL_MS = 60_000

export function useClaimLifecycle() {
  const isClaiming = ref(false)
  const isReleasing = ref(false)
  const claimError = ref<string | null>(null)

  let heartbeatTimer: ReturnType<typeof setInterval> | null = null

  async function claimRequest(id: number): Promise<boolean> {
    isClaiming.value = true
    claimError.value = null

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
      const status = (err as { response?: { status?: number } })?.response?.status
      if (status === 409) {
        claimError.value = 'الطلب محجوز بواسطة مراجع آخر.'
      }
      else {
        claimError.value = 'تعذّرت المطالبة بالطلب.'
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
    catch {
      // Best-effort release; TTL auto-expire will recover any missed releases
    }
    finally {
      isReleasing.value = false
    }
  }

  function startHeartbeat(id: number): void {
    stopHeartbeat()
    heartbeatTimer = setInterval(async () => {
      try {
        const config = useRuntimeConfig()
        const baseURL = config.public.apiBase as string
        await $fetch(`/api/workflow/${id}/claim-support-review/heartbeat`, {
          method: 'POST',
          baseURL,
          credentials: 'include',
        })
      }
      catch {
        // Silent — TTL will expire if heartbeat cannot reach server
      }
    }, HEARTBEAT_INTERVAL_MS)
  }

  function stopHeartbeat(): void {
    if (heartbeatTimer !== null) {
      clearInterval(heartbeatTimer)
      heartbeatTimer = null
    }
  }

  return {
    isClaiming,
    isReleasing,
    claimError,
    claimRequest,
    releaseRequest,
    startHeartbeat,
    stopHeartbeat,
  }
}
