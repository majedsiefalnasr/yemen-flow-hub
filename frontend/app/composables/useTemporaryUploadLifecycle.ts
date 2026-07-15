import { getCurrentInstance, onUnmounted, reactive } from 'vue'
import type { UploadLifecycleEntry, UploadLifecycleState } from '@/types/models'
import { useTemporaryUploads } from '@/composables/useTemporaryUploads'
import { extractApiErrorMessage } from '@/utils/apiErrors'

const SCAN_POLL_INTERVAL_MS = 2_000

const ARABIC_MESSAGE: Record<Extract<UploadLifecycleState, 'infected' | 'failed'>, string> = {
  infected: 'تم رفض الملف: تم اكتشاف محتوى ضار أثناء الفحص.',
  failed: 'تعذّر فحص الملف. احذفه وأعد رفعه مرة أخرى.',
}

/**
 * Tracks the full client-side lifecycle of pre-submission uploads (upload →
 * async scan → clean/infected/failed), one UploadLifecycleEntry per field
 * key. The wizard reads `isResolved`/`hasBlockingUpload` to gate
 * Next/Review/Submit, and only 'clean' tokens are ever safe to include in
 * the final submission's upload_tokens list.
 */
export function useTemporaryUploadLifecycle() {
  const { upload, status } = useTemporaryUploads()
  const entries = reactive(new Map<string, UploadLifecycleEntry>())
  const pollTimers = new Map<string, ReturnType<typeof setTimeout>>()
  // Bumped every time a field starts a new poll loop (fresh upload) or stops
  // one (removeEntry/unmount). A poll's in-flight status() request captures
  // the generation it was issued under; if that number no longer matches
  // when the request resolves, the loop that issued it has since been
  // superseded or torn down, so the result is discarded instead of applied
  // out of order or racing a newer loop's own next tick.
  const pollGenerations = new Map<string, number>()

  function stopPolling(fieldKey: string): void {
    const timer = pollTimers.get(fieldKey)
    if (timer !== undefined) {
      clearTimeout(timer)
      pollTimers.delete(fieldKey)
    }
    pollGenerations.set(fieldKey, (pollGenerations.get(fieldKey) ?? 0) + 1)
  }

  function stopAllPolling(): void {
    for (const fieldKey of [...pollTimers.keys()]) stopPolling(fieldKey)
  }

  function setEntry(fieldKey: string, patch: Partial<UploadLifecycleEntry>): void {
    const current = entries.get(fieldKey)
    entries.set(fieldKey, {
      fieldKey,
      token: current?.token ?? null,
      fileName: current?.fileName ?? '',
      state: current?.state ?? 'uploading',
      errorMessage: current?.errorMessage ?? null,
      ...patch,
    })
  }

  /**
   * Single-flight: each tick waits for its own status() request to settle
   * before scheduling the next one, so a slow response can never overlap a
   * later poll or have a later poll's result overwritten by its own
   * late-arriving reply. The generation check below additionally guards
   * against a request that was already in flight when stopPolling() ran
   * (removeEntry, unmount, or a fresh upload restarting the loop) from
   * applying its result after the fact.
   */
  function pollScanStatus(fieldKey: string, token: string): void {
    stopPolling(fieldKey)
    const generation = pollGenerations.get(fieldKey) ?? 0

    async function tick(): Promise<void> {
      try {
        const result = await status(token)
        if (pollGenerations.get(fieldKey) !== generation) return

        if (result.scan_status === 'clean') {
          stopPolling(fieldKey)
          setEntry(fieldKey, { state: 'clean', errorMessage: null })
          return
        }
        if (result.scan_status === 'infected' || result.scan_status === 'failed') {
          stopPolling(fieldKey)
          setEntry(fieldKey, {
            state: result.scan_status,
            errorMessage: ARABIC_MESSAGE[result.scan_status],
          })
          return
        }
        // Still 'pending' (or null while the scan job hasn't started yet):
        // schedule the next check only now that this one has finished.
        pollTimers.set(fieldKey, setTimeout(tick, SCAN_POLL_INTERVAL_MS))
      } catch {
        if (pollGenerations.get(fieldKey) !== generation) return
        stopPolling(fieldKey)
        setEntry(fieldKey, {
          state: 'upload_error',
          errorMessage: 'تعذّر التحقق من حالة فحص الملف. حاول مرة أخرى.',
        })
      }
    }

    pollTimers.set(fieldKey, setTimeout(tick, SCAN_POLL_INTERVAL_MS))
  }

  /**
   * Uploads a file for a field and tracks it through to a terminal scan
   * state. Returns nothing — read `entries`/`isResolved`/`hasBlockingUpload`
   * reactively instead, since the scan resolves asynchronously well after
   * this call returns.
   */
  async function uploadAndTrack(
    fieldKey: string,
    file: File,
    workflowVersionId: number,
    fieldId: number,
    uploadSessionToken: string,
  ): Promise<void> {
    setEntry(fieldKey, {
      token: null,
      fileName: file.name,
      state: 'uploading',
      errorMessage: null,
    })
    try {
      const result = await upload(file, workflowVersionId, fieldId, uploadSessionToken)
      setEntry(fieldKey, { token: result.token, state: 'scan_pending', errorMessage: null })
      pollScanStatus(fieldKey, result.token)
    } catch (cause) {
      setEntry(fieldKey, {
        state: 'upload_error',
        errorMessage: extractApiErrorMessage(cause, 'تعذّر رفع الملف. حاول مرة أخرى.'),
      })
    }
  }

  function removeEntry(fieldKey: string): void {
    stopPolling(fieldKey)
    entries.delete(fieldKey)
  }

  function entryFor(fieldKey: string): UploadLifecycleEntry | undefined {
    return entries.get(fieldKey)
  }

  /** True only once every tracked upload has reached the 'clean' state. */
  function isResolved(): boolean {
    return [...entries.values()].every((entry) => entry.state === 'clean')
  }

  /** True while any upload is still uploading or awaiting its scan result. */
  function hasPendingUpload(): boolean {
    return [...entries.values()].some(
      (entry) => entry.state === 'uploading' || entry.state === 'scan_pending',
    )
  }

  /**
   * True while any tracked upload is not 'clean' — pending OR a terminal
   * error (infected/failed/upload_error). Gating on this (not just
   * hasPendingUpload) matters for an optional FILE field: an infected file
   * never enters the field's own value (see DynamicForm's clean-only
   * watcher), so the field alone would validate fine and silently let the
   * user advance past a rejected upload unless the wizard also checks this.
   */
  function hasBlockingUpload(): boolean {
    return [...entries.values()].some((entry) => entry.state !== 'clean')
  }

  /** Clean tokens only — infected/failed/errored tokens must never submit. */
  function cleanTokens(): string[] {
    return [...entries.values()]
      .filter((entry) => entry.state === 'clean' && entry.token !== null)
      .map((entry) => entry.token as string)
  }

  if (getCurrentInstance()) {
    onUnmounted(stopAllPolling)
  }

  return {
    entries,
    uploadAndTrack,
    removeEntry,
    entryFor,
    isResolved,
    hasPendingUpload,
    hasBlockingUpload,
    cleanTokens,
    stopAllPolling,
  }
}
