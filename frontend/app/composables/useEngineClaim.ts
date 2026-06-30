import { computed, getCurrentInstance, onUnmounted, ref, watch, type Ref } from 'vue'
import type { EngineRequest } from '@/types/models'
import { useApi } from '@/composables/useApi'

const HEARTBEAT_INTERVAL_MS = 60_000

/**
 * Manages the engine stage-claim soft-lock for a single request: claim,
 * release, heartbeat, and the reactive holder state derived from each
 * response. While the claim is held by the current user, a 60s heartbeat
 * keeps the server-side TTL alive; it stops automatically on release or
 * component unmount.
 */
export function useEngineClaim(requestId: Ref<number>, currentUserId: Ref<number | null>) {
  const api = useApi()

  const claimedBy = ref<number | null>(null)
  const heartbeatActive = ref(false)
  let heartbeatTimer: ReturnType<typeof setInterval> | null = null

  const isHeldByMe = computed(
    () => claimedBy.value !== null && claimedBy.value === currentUserId.value,
  )
  const heldByOther = computed(
    () => claimedBy.value !== null && claimedBy.value !== currentUserId.value,
  )

  function applyResponse(data: Pick<EngineRequest, 'id'> & { claimed_by?: number | null }): void {
    claimedBy.value = data.claimed_by ?? null
  }

  function stopHeartbeat(): void {
    if (heartbeatTimer !== null) {
      clearInterval(heartbeatTimer)
      heartbeatTimer = null
    }
    heartbeatActive.value = false
  }

  function startHeartbeat(): void {
    if (heartbeatTimer !== null) return
    heartbeatActive.value = true
    heartbeatTimer = setInterval(() => {
      void heartbeat()
    }, HEARTBEAT_INTERVAL_MS)
  }

  async function claim(): Promise<void> {
    const response = await api.post<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${requestId.value}/claim`,
    )
    applyResponse(response.data)
  }

  async function release(): Promise<void> {
    stopHeartbeat()
    const response = await api.del<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${requestId.value}/claim`,
    )
    applyResponse(response.data)
  }

  async function heartbeat(): Promise<void> {
    const response = await api.post<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${requestId.value}/claim/heartbeat`,
    )
    applyResponse(response.data)
  }

  watch(isHeldByMe, (held) => {
    if (held) {
      startHeartbeat()
    } else {
      stopHeartbeat()
    }
  })

  if (getCurrentInstance()) {
    onUnmounted(() => {
      stopHeartbeat()
    })
  }

  return {
    claimedBy,
    isHeldByMe,
    heldByOther,
    heartbeatActive,
    claim,
    release,
    heartbeat,
  }
}
