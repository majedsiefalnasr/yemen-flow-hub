<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useRequests } from '../../../composables/useRequests'
import type { CustomsDeclaration } from '../../../types/models'

definePageMeta({
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const { fetchCustomsPreview } = useRequests()

const rawId = route.params.id
const requestId = Number(Array.isArray(rawId) ? rawId[0] : rawId)

const declaration = ref<CustomsDeclaration | null>(null)
const loading = ref(true)
const errorStatus = ref<number | null>(null)
const downloading = ref(false)
const downloadError = ref('')

onMounted(async () => {
  try {
    declaration.value = await fetchCustomsPreview(requestId)
  } catch (err: unknown) {
    const status =
      (err as { statusCode?: number; status?: number })?.statusCode ??
      (err as { statusCode?: number; status?: number })?.status ??
      500
    if (status === 403) {
      router.push('/dashboard')
      return
    }
    errorStatus.value = status
  } finally {
    loading.value = false
  }
})

const metadata = computed(() => {
  const m = declaration.value?.metadata as Record<string, unknown> | null | undefined
  return m ?? {}
})

const bankName = computed(() => {
  const bank = metadata.value.bank as { name?: string; code?: string } | undefined
  if (!bank) return '—'
  return bank.code ? `${bank.name} (${bank.code})` : (bank.name ?? '—')
})

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatAmount(amount: unknown, currency: unknown): string {
  if (amount == null) return '—'
  return `${Number(amount).toLocaleString('ar-YE')} ${currency ?? ''}`
}

function triggerPrint() {
  window.print()
}

async function triggerDownload() {
  if (!declaration.value) return
  downloadError.value = ''
  downloading.value = true
  try {
    const { downloadCustomsDeclaration: dl } = useRequests()
    const blob = await dl(declaration.value.id)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `customs-declaration-${declaration.value.declaration_number}.pdf`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  } catch {
    downloadError.value = 'تعذر تنزيل ملف PDF الرسمي الآن. أعد المحاولة بعد قليل.'
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <div class="preview-page">
    <!-- Loading -->
    <div v-if="loading" class="preview-loading" aria-busy="true" aria-label="جارٍ التحميل">
      <div class="loading-spinner" />
      <p>جارٍ تنزيل البيان الجمركي…</p>
    </div>

    <!-- 404 — no declaration -->
    <div v-else-if="!loading && !declaration && errorStatus === 404" class="preview-empty">
      <p class="empty-title">لا يوجد بيان جمركي</p>
      <p class="empty-body">لم يتم إصدار بيان جمركي لهذا الطلب بعد.</p>
      <a :href="`/requests/${requestId}`" class="back-link">← العودة إلى الطلب</a>
    </div>

    <!-- Other error -->
    <div v-else-if="!loading && !declaration && errorStatus !== 404" class="preview-empty">
      <p class="empty-title">تعذر فتح البيان الجمركي</p>
      <p class="empty-body">
        تعذر تنزيل البيان الجمركي الآن. ارجع إلى الطلب ثم أعد المحاولة بعد قليل.
      </p>
      <a :href="`/requests/${requestId}`" class="back-link">← العودة إلى الطلب</a>
    </div>

    <!-- Preview content -->
    <template v-else-if="declaration">
      <!-- Action bar (hidden on print) -->
      <div class="preview-actions no-print">
        <a :href="`/requests/${requestId}`" class="back-link">← العودة إلى الطلب</a>
        <div class="action-buttons">
          <button class="btn btn-secondary" @click="triggerPrint">طباعة</button>
          <button class="btn btn-primary" :disabled="downloading" @click="triggerDownload">
            {{ downloading ? 'جارٍ التنزيل…' : 'تنزيل PDF الرسمي' }}
          </button>
        </div>
        <p v-if="downloadError" class="download-error" role="alert">{{ downloadError }}</p>
      </div>

      <!-- Watermark / notice banner (shown in preview, hidden in print output) -->
      <div class="preview-watermark no-print" role="note">
        معاينة تشغيلية، وملف PDF الرسمي هو الوثيقة القانونية المعتمدة
      </div>

      <!-- Declaration content (printed) -->
      <div class="customs-preview-content print-content">
        <!-- Header -->
        <div class="decl-header">
          <div class="decl-logo">CBY</div>
          <h1 class="decl-title">البنك المركزي اليمني</h1>
          <p class="decl-subtitle">بيان جمركي للإفراج عن تمويل الاستيراد</p>
        </div>

        <!-- Meta info -->
        <div class="decl-meta">
          <p><strong>رقم البيان:</strong> {{ declaration.declaration_number }}</p>
          <p><strong>تاريخ الإصدار:</strong> {{ formatDate(declaration.issued_at) }}</p>
          <p>
            <strong>رقم طلب التمويل:</strong> {{ declaration.request?.reference_number ?? '—' }}
          </p>
          <p><strong>الجهة المصدرة:</strong> {{ declaration.issuer?.name ?? '—' }}</p>
        </div>

        <!-- Main data table -->
        <table class="decl-table">
          <tbody>
            <tr>
              <th>البنك التجاري</th>
              <td>{{ bankName }}</td>
            </tr>
            <tr>
              <th>اسم المورد</th>
              <td>{{ (metadata.supplier_name as string) ?? '—' }}</td>
            </tr>
            <tr>
              <th>المبلغ</th>
              <td>{{ formatAmount(metadata.amount, metadata.currency) }}</td>
            </tr>
            <tr>
              <th>وصف البضائع</th>
              <td>{{ (metadata.goods_description as string) ?? '—' }}</td>
            </tr>
            <tr>
              <th>منفذ الدخول</th>
              <td>{{ (metadata.port_of_entry as string) ?? '—' }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Approval dates table -->
        <table class="decl-table">
          <tbody>
            <tr>
              <th>تاريخ موافقة البنك</th>
              <td>{{ formatDate(metadata.bank_approved_at as string | null) }}</td>
            </tr>
            <tr>
              <th>تاريخ موافقة لجنة الدعم</th>
              <td>{{ formatDate(metadata.support_approved_at as string | null) }}</td>
            </tr>
            <tr>
              <th>تاريخ القرار التنفيذي</th>
              <td>{{ formatDate(metadata.executive_decided_at as string | null) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Official notice -->
        <div class="decl-notice">
          بناءً على اكتمال الموافقات النظامية والتنفيذية، يصدر هذا البيان الجمركي كوثيقة رسمية
          نهائية وغير قابلة للتعديل ضمن منصة البنك المركزي اليمني.
        </div>

        <!-- Signatures -->
        <div class="decl-signatures">
          <div class="signature-block">
            <p>توقيع مدير اللجنة</p>
            <p class="signature-name">{{ declaration.issuer?.name ?? '—' }}</p>
            <div class="signature-line" />
          </div>
          <div class="signature-block">
            <p>ختم البنك المركزي</p>
            <div class="signature-line" />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* ─── Page shell ───────────────────────────────────────────────────────────── */
.preview-page {
  min-height: 100vh;
  background: var(--muted);
  padding: 24px;
  font-family: var(--font-sans);
  direction: rtl;
}

/* ─── Action bar ───────────────────────────────────────────────────────────── */
.preview-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
  padding: 12px 16px;
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.back-link {
  color: var(--primary);
  text-decoration: none;
  font-family: var(--font-section);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.25rem;
}

.back-link:hover {
  text-decoration: underline;
}

.btn {
  padding: 8px 16px;
  border-radius: 8px;
  font-family: var(--font-section);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.25rem;
  cursor: pointer;
  border: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary);
  color: var(--primary-foreground);
}

.btn-primary:hover:not(:disabled) {
  background: color-mix(in srgb, var(--primary) 85%, black);
}

.btn-secondary {
  background: var(--muted);
  color: var(--foreground);
  border: 1px solid var(--border);
}

.btn-secondary:hover {
  background: var(--accent);
}

.download-error {
  width: 100%;
  color: var(--destructive);
  font-size: 0.8125rem;
  line-height: 1.25rem;
  margin: 0;
}

/* ─── Watermark banner ─────────────────────────────────────────────────────── */
.preview-watermark {
  background: color-mix(in srgb, var(--color-warning) 10%, var(--background));
  border: 1px solid color-mix(in srgb, var(--color-warning) 40%, transparent);
  border-radius: 8px;
  padding: 10px 16px;
  font-family: var(--font-section);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.25rem;
  color: var(--color-warning);
  margin-bottom: 20px;
  text-align: center;
}

/* ─── Loading / empty states ───────────────────────────────────────────────── */
.preview-loading,
.preview-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  gap: 12px;
  color: var(--muted-foreground);
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty-title {
  font-family: var(--font-heading);
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.5rem;
  color: var(--foreground);
  margin: 0;
}

.empty-body {
  font-size: 0.875rem;
  line-height: 1.5rem;
  margin: 0;
}

/* ─── Declaration content ──────────────────────────────────────────────────── */
.customs-preview-content {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 40px;
  max-width: 860px;
  margin: 0 auto;
}

/* Header */
.decl-header {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 2px solid var(--foreground);
}

.decl-logo {
  width: 64px;
  height: 64px;
  border: 1px solid var(--color-locked);
  border-radius: 8px;
  margin: 0 auto 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-section);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  line-height: 1rem;
  color: var(--muted-foreground);
}

.decl-title {
  font-family: var(--font-heading);
  font-size: 1.375rem;
  font-weight: 600;
  line-height: 1.75rem;
  color: var(--foreground);
  margin: 6px 0 4px;
}

.decl-subtitle {
  font-family: var(--font-section);
  font-size: 0.875rem;
  line-height: 1.5rem;
  color: var(--muted-foreground);
  margin: 0;
}

/* Meta */
.decl-meta {
  margin-bottom: 20px;
}

.decl-meta p {
  margin: 4px 0;
  font-size: 0.875rem;
  line-height: 1.5rem;
  color: var(--foreground);
}

/* Tables */
.decl-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 16px;
  font-size: 0.875rem;
  line-height: 1.5rem;
}

.decl-table th,
.decl-table td {
  border: 1px solid var(--foreground);
  padding: 8px 12px;
  vertical-align: top;
  text-align: right;
}

.decl-table th {
  background: var(--muted);
  width: 30%;
  font-family: var(--font-section);
  font-weight: 600;
  color: var(--foreground);
}

.decl-table td {
  color: var(--foreground);
}

/* Notice */
.decl-notice {
  border: 1px solid var(--foreground);
  padding: 12px 16px;
  margin: 16px 0;
  background: var(--card);
  font-size: 0.8125rem;
  color: var(--foreground);
  line-height: 1.6;
  border-radius: 4px;
}

/* Signatures */
.decl-signatures {
  display: flex;
  justify-content: space-around;
  margin-top: 40px;
  gap: 32px;
}

.signature-block {
  flex: 1;
  text-align: center;
  font-size: 0.8125rem;
  line-height: 1.25rem;
  color: var(--foreground);
}

.signature-block p {
  margin: 0 0 4px;
}

.signature-name {
  font-family: var(--font-section);
  font-weight: 600;
}

.signature-line {
  margin-top: 32px;
  border-top: 1px solid var(--foreground);
  width: 80%;
  margin-inline: auto;
}

/* ─── Print CSS ────────────────────────────────────────────────────────────── */
@media print {
  :global(.app-header),
  :global(.sidebar),
  :global(.sidebar-overlay) {
    display: none !important;
  }

  :global(.app-main) {
    margin-inline-end: 0 !important;
  }

  :global(.app-content) {
    padding: 0 !important;
    max-width: none !important;
  }

  .no-print {
    display: none !important;
  }

  .preview-page {
    background: white;
    padding: 0;
  }

  .customs-preview-content {
    border: none;
    border-radius: 0;
    padding: 20px;
    max-width: 100%;
    margin: 0;
  }

  .decl-table th,
  .decl-table td {
    border-color: #000000;
  }

  .decl-header {
    border-bottom-color: #000000;
  }
}
</style>
