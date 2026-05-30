<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useRequests } from '../../../composables/useRequests'
import type { ImportRequest, RequestStageHistory } from '../../../types/models'
import RequestPrintable from '../../../components/requests/RequestPrintable.vue'

definePageMeta({
  middleware: ['auth'],
  layout: 'print',
})

const route = useRoute()
const { fetchRequest, fetchRequestHistory } = useRequests()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)
const hasValidId = Number.isInteger(id) && id > 0

const request = ref<ImportRequest | null>(null)
const history = ref<RequestStageHistory[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
let printTimer: ReturnType<typeof setTimeout> | null = null

function clearPrintTimer() {
  if (printTimer !== null) {
    clearTimeout(printTimer)
    printTimer = null
  }
}

function triggerPrint() {
  window.print()
}

async function loadData() {
  if (!hasValidId) {
    error.value = 'معرّف الطلب غير صالح.'
    return
  }

  loading.value = true
  error.value = null
  clearPrintTimer()
  try {
    const [req, hist] = await Promise.all([
      fetchRequest(id),
      fetchRequestHistory(id),
    ])
    request.value = req
    history.value = [...hist].sort((a, b) => a.created_at.localeCompare(b.created_at))

    // AC5: auto-trigger print after data finishes loading
    printTimer = setTimeout(() => {
      printTimer = null
      triggerPrint()
    }, 300)
  }
  catch (err: unknown) {
    const status = (err as { statusCode?: number; status?: number })?.statusCode
      ?? (err as { statusCode?: number; status?: number })?.status

    error.value = status === 403
      ? 'ليس لديك صلاحية طباعة هذا الطلب.'
      : 'تعذّر تحميل بيانات الطلب.'
  }
  finally {
    loading.value = false
  }
}

onMounted(loadData)
onBeforeUnmount(clearPrintTimer)
</script>

<template>
  <div class="print-page" >
    <!-- Controls bar — hidden on print -->
    <div class="print-controls no-print">
      <div class="controls-inner">
        <NuxtLink :to="`/requests/${id}`" class="back-link" aria-label="العودة إلى الطلب">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M19 12H5" />
            <polyline points="12 19 5 12 12 5" />
          </svg>
          العودة
        </NuxtLink>
        <h1 class="controls-title">معاينة طلب تمويل واردات</h1>
        <button class="print-btn" :disabled="loading || !!error" @click="triggerPrint">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="6 9 6 2 18 2 18 9" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect x="6" y="14" width="12" height="8" />
          </svg>
          طباعة
        </button>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="state-message" role="status" aria-live="polite">
      <span>جارٍ التحميل…</span>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="state-message state-message--error" role="alert">
      <span>{{ error }}</span>
    </div>

    <!-- Print content -->
    <div v-else-if="request" class="print-body">
      <RequestPrintable
        :request="request"
        :history="history"
        :documents="request.documents ?? []"
      />
    </div>
  </div>
</template>

<style scoped>
.print-page {
  min-height: 100vh;
}

/* ─── Controls bar ─── */
.print-controls {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--background);
  border-bottom: 1px solid var(--border);
  padding: 12px 24px;
}

.controls-inner {
  display: flex;
  align-items: center;
  gap: 16px;
  max-width: 960px;
  margin: 0 auto;
  flex-direction: row-reverse;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--muted-foreground);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  transition: background-color 120ms ease;
}

.back-link:hover {
  background-color: var(--muted);
}

.controls-title {
  flex: 1;
  font-size: 15px;
  font-weight: 600;
  color: var(--foreground);
  margin: 0;
  text-align: right;
}

.print-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  background-color: var(--primary);
  color: var(--primary-foreground);
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  cursor: pointer;
  transition: opacity 120ms ease;
  flex-shrink: 0;
}

.print-btn:hover:not(:disabled) {
  opacity: 0.9;
}

.print-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ─── States ─── */
.state-message {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 64px;
  font-size: 15px;
  color: var(--muted-foreground);
}

.state-message--error {
  color: var(--destructive);
}

/* ─── Print body ─── */
.print-body {
  max-width: 960px;
  margin: 0 auto;
  padding: 32px 24px;
}

/* ─── Print media ─── */
@media print {
  .no-print {
    display: none !important;
  }

  .print-page {
    min-height: unset;
    background: transparent;
  }

  .print-body {
    padding: 0;
    max-width: 100%;
  }

  @page {
    size: A4 portrait;
    margin: 20mm;
  }
}
</style>
