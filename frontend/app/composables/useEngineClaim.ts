import { computed, getCurrentInstance, onUnmounted, ref, watch, type Ref } from 'vue'
import type { EngineRequest } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorCode } from '@/utils/apiErrors'

const HEARTBEAT_INTERVAL_MS = 60_000

/**
 * Manages the engine stage-claim soft-lock for a single request: claim,
 * release, heartbeat, and the reactive holder state derived from each
 * response. While the claim is held by the current user, a 60s heartbeat
 * keeps the server-side TTL alive; it stops automatically on release,
 * claim loss, or component unmount.
 */
export function useEngineClaim(requestId: Ref<number>, currentUserId: Ref<number | null>) {
  const api = useApi()

  const claimedBy = ref<number | null>(null)
  const heartbeatActive = ref(false)
  const claimLost = ref(false)
  const claimLostCode = ref<string | null>(null)
  let heartbeatTimer: ReturnType<typeof setInterval> | null = null

  const isHeldByMe = computed(
    () => !claimLost.value && claimedBy.value !== null && claimedBy.value === currentUserId.value,
  )
  const heldByOther = computed(
    () => claimedBy.value !== null && claimedBy.value !== currentUserId.value,
  )

  function applyResponse(data: Pick<EngineRequest, 'id'> & { claimed_by?: number | null }): void {
    claimedBy.value = data.claimed_by ?? null
  }

  function markClaimLost(code: string | null): void {
    claimLost.value = true
    claimLostCode.value = code
    claimedBy.value = null
    stopHeartbeat()
  }

  function handleClaimError(err: unknown): void {
    const code = extractApiErrorCode(err)
    if (code === 'CLAIM_NOT_HELD') {
      markClaimLost(code)
    }
    throw err
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

  function resetClaimLost(): void {
    claimLost.value = false
    claimLostCode.value = null
  }

  async function claim(): Promise<void> {
    resetClaimLost()
    try {
      const response = await api.post<{ success: boolean; data: EngineRequest }>(
        `/api/v1/engine-requests/${requestId.value}/claim`,
      )
      applyResponse(response.data)
    } catch (err) {
      handleClaimError(err)
    }
  }

  async function release(): Promise<void> {
    stopHeartbeat()
    try {
      const response = await api.del<{ success: boolean; data: EngineRequest }>(
        `/api/v1/engine-requests/${requestId.value}/claim`,
      )
      applyResponse(response.data)
    } catch (err) {
      handleClaimError(err)
    }
  }

  async function heartbeat(): Promise<void> {
    try {
      const response = await api.post<{ success: boolean; data: EngineRequest }>(
        `/api/v1/engine-requests/${requestId.value}/claim/heartbeat`,
      )
      applyResponse(response.data)
    } catch (err) {
      handleClaimError(err)
    }
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
    claimLost,
    claimLostCode,
    claim,
    release,
    heartbeat,
    markClaimLost,
    resetClaimLost,
  }
}
