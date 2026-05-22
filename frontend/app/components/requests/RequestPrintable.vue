<script setup lang="ts">
import { computed } from 'vue'
import type { ImportRequest, RequestDocument, RequestStageHistory } from '../../types/models'
import { STATUS_LABELS } from '../../constants/workflow'
import AuditTimeline from '../workflow/AuditTimeline.vue'

const props = defineProps<{
  request: ImportRequest
  history: RequestStageHistory[]
  documents: RequestDocument[]
}>()

const sortedHistory = computed(() =>
  [...props.history].sort((a, b) => a.created_at.localeCompare(b.created_at)),
)

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function formatAmount(amount: number, currency: string): string {
  return `${amount.toLocaleString('ar-YE')} ${currency}`
}

function actorName(user: { name: string } | null | undefined): string {
  return user?.name ?? '—'
}

function statusLabel(status: string): string {
  return STATUS_LABELS[status as keyof typeof STATUS_LABELS] ?? status
}

const workflowEntries = computed(() =>
  sortedHistory.value.map(entry => ({
    id: entry.id,
    statusLabel: entry.to_status ? statusLabel(entry.to_status) : '—',
    actor: entry.performed_by?.name ?? `#${entry.actor_id}`,
    timestamp: formatDate(entry.created_at),
  })),
)

const fields = computed(() => [
  { label: 'العملة', value: props.request.currency },
  { label: 'المبلغ', value: formatAmount(props.request.amount, props.request.currency) },
  { label: 'اسم المورّد', value: props.request.supplier_name },
  { label: 'وصف البضائع', value: props.request.goods_description },
  { label: 'نوع البضائع', value: props.request.goods_type ?? '—' },
  { label: 'ميناء الدخول', value: props.request.port_of_entry },
  { label: 'شروط الدفع', value: props.request.payment_terms ?? '—' },
  { label: 'تاريخ الاستحقاق', value: formatDate(props.request.due_date) },
  { label: 'رقم الفاتورة', value: props.request.invoice_number ?? '—' },
  { label: 'تاريخ الفاتورة', value: formatDate(props.request.invoice_date) },
  { label: 'بلد المنشأ', value: props.request.origin_country ?? '—' },
  { label: 'ميناء الوصول', value: props.request.arrival_port ?? '—' },
  { label: 'ميناء الشحن', value: props.request.shipping_port ?? '—' },
  { label: 'مكتب الجمارك', value: props.request.customs_office ?? '—' },
  { label: 'رقم بوليصة الشحن', value: props.request.bl_number ?? '—' },
  { label: 'ملاحظات', value: props.request.notes ?? '—' },
])
</script>

<template>
  <div class="printable" dir="rtl">
    <!-- ─── Section 1: Title + reference + status ─── -->
    <header class="printable-header">
      <div class="printable-header__title-group">
        <h1 class="printable-title">طلب تمويل واردات</h1>
        <span class="printable-ref">{{ request.reference_number }}</span>
      </div>
      <span class="printable-status-badge">{{ statusLabel(request.status) }}</span>
    </header>

    <div class="printable-divider" />

    <!-- ─── Section 2: Bank / user / date ─── -->
    <section class="printable-meta" aria-label="بيانات الطلب">
      <div class="meta-row">
        <span class="meta-label">البنك:</span>
        <span class="meta-value">{{ request.bank_name ?? '—' }}</span>
      </div>
      <div class="meta-row">
        <span class="meta-label">مقدّم الطلب:</span>
        <span class="meta-value">{{ actorName(request.created_by_user) }}</span>
      </div>
      <div class="meta-row">
        <span class="meta-label">تاريخ الإنشاء:</span>
        <span class="meta-value">{{ formatDate(request.created_at) }}</span>
      </div>
    </section>

    <div class="printable-divider" />

    <!-- ─── Section 3: Wizard fields ─── -->
    <section class="printable-fields" aria-label="تفاصيل الطلب">
      <h2 class="section-title">تفاصيل الطلب</h2>
      <div class="fields-grid">
        <div v-for="field in fields" :key="field.label" class="field-item">
          <span class="field-label">{{ field.label }}</span>
          <span class="field-value">{{ field.value }}</span>
        </div>
      </div>
    </section>

    <div class="printable-divider" />

    <!-- ─── Section 4: Documents ─── -->
    <section class="printable-documents" aria-label="المستندات المرفقة">
      <h2 class="section-title">المستندات المرفقة</h2>
      <p v-if="documents.length === 0" class="empty-state">لا توجد مستندات مرفقة.</p>
      <table v-else class="doc-table">
        <thead>
          <tr>
            <th>اسم الملف</th>
            <th>تاريخ الرفع</th>
            <th>رُفع بواسطة</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id">
            <td>{{ doc.original_filename }}</td>
            <td>{{ formatDate(doc.uploaded_at) }}</td>
            <td>{{ doc.uploaded_by_name ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="printable-divider" />

    <!-- ─── Section 5: Workflow timeline ─── -->
    <section class="printable-timeline" aria-label="مسار سير العمل">
      <h2 class="section-title">مسار سير العمل</h2>
      <p v-if="workflowEntries.length === 0" class="empty-state">لا توجد مراحل مسجّلة بعد.</p>
      <ol v-else class="workflow-list">
        <li v-for="entry in workflowEntries" :key="entry.id" class="workflow-entry">
          <span class="workflow-entry__status">{{ entry.statusLabel }}</span>
          <span class="workflow-entry__actor">{{ entry.actor }}</span>
          <span class="workflow-entry__timestamp">{{ entry.timestamp }}</span>
        </li>
      </ol>
    </section>

    <div class="printable-divider" />

    <!-- ─── Section 6: Audit timeline ─── -->
    <section class="printable-audit" aria-label="سجل الأحداث">
      <h2 class="section-title">سجل الأحداث</h2>
      <AuditTimeline :entries="sortedHistory" />
    </section>
  </div>
</template>

<style scoped>
.printable {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  color: #1c222b;
  background: #ffffff;
  padding: 24px;
  max-width: 900px;
  margin: 0 auto;
}

/* ─── Header ─── */
.printable-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 8px;
}

.printable-header__title-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.printable-title {
  font-family: 'Cairo', sans-serif;
  font-size: 22px;
  font-weight: 700;
  margin: 0;
  color: #1c222b;
}

.printable-ref {
  font-size: 15px;
  color: #0066cc;
  font-weight: 600;
}

.printable-status-badge {
  font-size: 12px;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 20px;
  border: 1px solid #cccccc;
  color: #1c222b;
  background: #f5f5f7;
  white-space: nowrap;
  flex-shrink: 0;
}

/* ─── Divider ─── */
.printable-divider {
  height: 1px;
  background: #cccccc;
  margin: 16px 0;
}

/* ─── Meta ─── */
.printable-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.meta-row {
  display: flex;
  gap: 8px;
  font-size: 14px;
}

.meta-label {
  font-weight: 600;
  min-width: 140px;
  color: #505050;
}

.meta-value {
  color: #1c222b;
}

/* ─── Section titles ─── */
.section-title {
  font-family: 'Tajawal', sans-serif;
  font-size: 16px;
  font-weight: 700;
  color: #0066cc;
  margin: 0 0 12px;
}

/* ─── Fields grid ─── */
.fields-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px 24px;
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.field-label {
  font-size: 12px;
  color: #6c757d;
  font-weight: 600;
}

.field-value {
  font-size: 14px;
  color: #1c222b;
}

/* ─── Document table ─── */
.doc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.doc-table th,
.doc-table td {
  text-align: right;
  padding: 8px 10px;
  border: 1px solid #cccccc;
}

.doc-table th {
  background: #f5f5f7;
  font-weight: 600;
  color: #1c222b;
}

.doc-table td {
  color: #1c222b;
}

.empty-state {
  font-size: 14px;
  color: #6c757d;
  text-align: center;
  padding: 16px;
}

/* ─── Timeline sections ─── */
.printable-timeline,
.printable-audit {
  overflow: hidden;
}

.workflow-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.workflow-entry {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  background: #f9fafb;
}

.workflow-entry__status {
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
}

.workflow-entry__actor {
  font-size: 13px;
  color: #505050;
}

.workflow-entry__timestamp {
  font-size: 12px;
  color: #6c757d;
  white-space: nowrap;
}

/* ─── Print media ─── */
@media print {
  .printable {
    padding: 0;
    max-width: 100%;
  }

  .printable-status-badge {
    border: 1px solid #000000;
    background: transparent;
    color: #000000;
  }

  .doc-table th {
    background: #e0e0e0;
  }

  .workflow-entry {
    background: transparent;
    break-inside: avoid;
  }

  .printable-timeline,
  .printable-audit {
    page-break-inside: avoid;
  }
}
</style>
