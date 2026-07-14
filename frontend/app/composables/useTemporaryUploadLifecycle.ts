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
  const pollTimers = new Map<string, ReturnType<typeof setInterval>>()

  function stopPolling(fieldKey: string): void {
    const timer = pollTimers.get(fieldKey)
    if (timer !== undefined) {
      clearInterval(timer)
      pollTimers.delete(fieldKey)
    }
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

  function pollScanStatus(fieldKey: string, token: string): void {
    stopPolling(fieldKey)
    const timer = setInterval(async () => {
      try {
        const result = await status(token)
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
        // keep polling.
      } catch {
        stopPolling(fieldKey)
        setEntry(fieldKey, {
          state: 'upload_error',
          errorMessage: 'تعذّر التحقق من حالة فحص الملف. حاول مرة أخرى.',
        })
      }
    }, SCAN_POLL_INTERVAL_MS)
    pollTimers.set(fieldKey, timer)
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
