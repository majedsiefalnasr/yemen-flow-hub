<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { UserRole } from '../../../types/enums'
import type { CustomsDeclaration } from '../../../types/models'
import { useRequests } from '../../../composables/useRequests'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.COMMITTEE_DIRECTOR],
  layout: 'default',
})

const route = useRoute()
const { fetchCustomsPreview } = useRequests()

const declaration = ref<CustomsDeclaration | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const zoom = ref(1)
const showConfirmDialog = ref(false)

const requestId = Number(route.params.id)

async function loadDeclaration() {
  loading.value = true
  error.value = null
  try {
    declaration.value = await fetchCustomsPreview(requestId)
  }
  catch {
    error.value = 'تعذّر تحميل بيانات البيان الجمركي.'
  }
  finally {
    loading.value = false
  }
}

function zoomIn() {
  zoom.value = Math.min(zoom.value + 0.1, 2)
}

function zoomOut() {
  zoom.value = Math.max(zoom.value - 0.1, 0.5)
}

function resetZoom() {
  zoom.value = 1
}

function confirmPrint() {
  showConfirmDialog.value = true
}

function executePrint() {
  showConfirmDialog.value = false
  window.print()
}

function cancelPrint() {
  showConfirmDialog.value = false
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

onMounted(loadDeclaration)
</script>

<template>
  <div class="print-page">
    <!-- Print controls (hidden on print) -->
    <div class="print-controls no-print">
      <div class="controls-bar">
        <h1 class="controls-title">معاينة البيان الجمركي</h1>
        <div class="zoom-controls">
          <button class="zoom-btn" aria-label="تصغير" :disabled="zoom <= 0.5" @click="zoomOut">−</button>
          <span class="zoom-level">{{ Math.round(zoom * 100) }}%</span>
          <button class="zoom-btn" aria-label="تكبير" :disabled="zoom >= 2" @click="zoomIn">+</button>
          <button class="zoom-btn zoom-reset" aria-label="إعادة ضبط التكبير" @click="resetZoom">↺</button>
        </div>
        <button class="print-btn" :disabled="!declaration || loading" @click="confirmPrint">
          طباعة
        </button>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="print-loading no-print">
      <span>جارٍ التحميل...</span>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="print-error no-print">
      <p>{{ error }}</p>
    </div>

    <!-- Paper preview -->
    <div v-else-if="declaration" class="paper-viewport">
      <div class="paper-sheet" :style="{ transform: `scale(${zoom})` }">
        <!-- RTL A4 content -->
        <div class="declaration-doc" dir="rtl">
          <header class="doc-header">
            <div class="doc-logo-area">
              <span class="doc-logo">🏦</span>
              <div>
                <p class="doc-org-name">البنك المركزي اليمني</p>
                <p class="doc-org-sub">Central Bank of Yemen</p>
              </div>
            </div>
            <div class="doc-title-area">
              <h2 class="doc-title">بيان جمركي</h2>
              <p class="doc-subtitle">Customs Declaration</p>
            </div>
          </header>

          <div class="doc-divider" />

          <section class="doc-meta">
            <div class="doc-meta-row">
              <span class="meta-label">رقم البيان:</span>
              <span class="meta-value doc-number">{{ declaration.declaration_number }}</span>
            </div>
            <div class="doc-meta-row">
              <span class="meta-label">تاريخ الإصدار:</span>
              <span class="meta-value">{{ formatDate(declaration.issued_at) }}</span>
            </div>
            <div v-if="declaration.request" class="doc-meta-row">
              <span class="meta-label">رقم الطلب المرجعي:</span>
              <span class="meta-value">{{ declaration.request.reference_number }}</span>
            </div>
            <div v-if="declaration.request?.bank_name" class="doc-meta-row">
              <span class="meta-label">البنك المصدر:</span>
              <span class="meta-value">{{ declaration.request.bank_name }}</span>
            </div>
            <div v-if="declaration.issuer" class="doc-meta-row">
              <span class="meta-label">أصدره:</span>
              <span class="meta-value">{{ declaration.issuer.name }}</span>
            </div>
          </section>

          <div class="doc-divider" />

          <section class="doc-body">
            <p class="doc-body-text">
              يُشهد بموجب هذا البيان أن الطلب المشار إليه أعلاه قد استوفى جميع الشروط والمتطلبات المقررة
              وصدر القرار بالموافقة عليه من قِبَل اللجنة التنفيذية للبنك المركزي اليمني وفقاً للأنظمة المعمول بها.
            </p>
          </section>

          <div class="doc-divider" />

          <footer class="doc-footer">
            <div class="signature-area">
              <p class="signature-label">توقيع المدير</p>
              <div class="signature-line" />
            </div>
            <div class="doc-stamp">
              <span>ختم رسمي</span>
            </div>
          </footer>
        </div>
      </div>
    </div>

    <!-- Confirmation dialog -->
    <div v-if="showConfirmDialog" class="confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
      <div class="confirm-card">
        <h3 id="confirm-title" class="confirm-title">تأكيد الطباعة</h3>
        <p class="confirm-body">هل تريد طباعة البيان الجمركي رقم <strong>{{ declaration?.declaration_number }}</strong>؟</p>
        <div class="confirm-actions">
          <button class="confirm-btn confirm-btn--cancel" @click="cancelPrint">إلغاء</button>
          <button class="confirm-btn confirm-btn--print" @click="executePrint">طباعة</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.print-page {
  min-height: 100vh;
  background-color: var(--color-surface-dim);
}

/* ─── Controls bar ─── */
.print-controls {
  position: sticky;
  top: 0;
  z-index: 10;
  background-color: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  padding: 12px 24px;
}

.controls-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  max-width: 900px;
  margin: 0 auto;
  flex-direction: row-reverse;
}

.controls-title {
  flex: 1;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-primary);
  margin: 0;
  text-align: right;
}

.zoom-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.zoom-btn {
  width: 32px;
  height: 32px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text-primary);
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 120ms ease;
}

.zoom-btn:hover:not(:disabled) {
  background-color: var(--color-surface-dim);
}

.zoom-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.zoom-reset {
  font-size: 14px;
}

.zoom-level {
  font-size: 13px;
  color: var(--color-text-secondary);
  min-width: 44px;
  text-align: center;
}

.print-btn {
  padding: 8px 20px;
  background-color: var(--color-primary);
  color: var(--color-on-primary);
  border: none;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-family: var(--font-body);
  cursor: pointer;
  transition: opacity 120ms ease;
}

.print-btn:hover:not(:disabled) {
  opacity: 0.9;
}

.print-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ─── Paper viewport ─── */
.paper-viewport {
  display: flex;
  justify-content: center;
  padding: 32px 16px;
}

.paper-sheet {
  transform-origin: top center;
  transition: transform 200ms ease;
}

/* ─── A4 document ─── */
.declaration-doc {
  width: 210mm;
  min-height: 297mm;
  background: #ffffff;
  color: #1c222b;
  padding: 24mm 20mm;
  box-shadow: var(--shadow-lg);
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 11pt;
  line-height: 1.7;
}

.doc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8mm;
}

.doc-logo-area {
  display: flex;
  align-items: center;
  gap: 8px;
}

.doc-logo {
  font-size: 32px;
}

.doc-org-name {
  font-size: 13pt;
  font-weight: 700;
  margin: 0;
  font-family: 'Cairo', sans-serif;
}

.doc-org-sub {
  font-size: 9pt;
  color: #6c757d;
  margin: 0;
  font-family: 'Inter', sans-serif;
}

.doc-title-area {
  text-align: left;
}

.doc-title {
  font-size: 18pt;
  font-weight: 700;
  margin: 0;
  font-family: 'Cairo', sans-serif;
  color: #0066cc;
}

.doc-subtitle {
  font-size: 9pt;
  color: #6c757d;
  margin: 0;
  font-family: 'Inter', sans-serif;
}

.doc-divider {
  height: 1px;
  background-color: #cccccc;
  margin: 6mm 0;
}

.doc-meta {
  display: flex;
  flex-direction: column;
  gap: 3mm;
}

.doc-meta-row {
  display: flex;
  gap: 8px;
}

.meta-label {
  font-weight: 600;
  min-width: 160px;
  color: #505050;
}

.meta-value {
  color: #1c222b;
}

.doc-number {
  font-weight: 700;
  font-size: 12pt;
  color: #0066cc;
}

.doc-body {
  margin: 4mm 0;
}

.doc-body-text {
  text-align: justify;
  line-height: 2;
  color: #1c222b;
}

.doc-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 16mm;
}

.signature-area {
  text-align: center;
}

.signature-label {
  font-size: 10pt;
  color: #6c757d;
  margin-bottom: 8mm;
}

.signature-line {
  width: 48mm;
  height: 1px;
  background-color: #1c222b;
}

.doc-stamp {
  width: 32mm;
  height: 32mm;
  border: 2px dashed #cccccc;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9pt;
  color: #6c757d;
}

/* ─── Loading / error ─── */
.print-loading,
.print-error {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 64px;
  color: var(--color-text-secondary);
  font-size: 15px;
}

.print-error {
  color: var(--color-error-text);
}

/* ─── Confirmation dialog ─── */
.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(12, 18, 26, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.confirm-card {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 32px;
  width: 380px;
  box-shadow: var(--shadow-lg);
  text-align: right;
}

.confirm-title {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 12px;
  color: var(--color-text-primary);
  font-family: var(--font-headline);
}

.confirm-body {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0 0 24px;
  line-height: 1.6;
}

.confirm-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-start;
  flex-direction: row-reverse;
}

.confirm-btn {
  padding: 10px 20px;
  border-radius: var(--radius-lg);
  border: none;
  font-size: 14px;
  font-family: var(--font-body);
  cursor: pointer;
  transition: opacity 120ms ease;
}

.confirm-btn--print {
  background-color: var(--color-primary);
  color: var(--color-on-primary);
}

.confirm-btn--cancel {
  background-color: var(--color-surface-dim);
  color: var(--color-text-secondary);
  border: 1px solid var(--color-border);
}

.confirm-btn:hover {
  opacity: 0.85;
}

/* ─── Print media ─── */
@media print {
  .no-print {
    display: none !important;
  }

  .print-page {
    background: transparent;
    min-height: unset;
  }

  .paper-viewport {
    padding: 0;
  }

  .paper-sheet {
    transform: none !important;
    box-shadow: none;
  }

  .declaration-doc {
    box-shadow: none;
    width: 100%;
    padding: 0;
  }
}
</style>
