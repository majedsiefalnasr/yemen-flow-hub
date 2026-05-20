<script setup lang="ts">
import { computed } from 'vue'
import { ShieldCheck } from 'lucide-vue-next'
import type { WizardStep1Data, WizardStep2Data, WizardStep3Data } from '../../composables/useRequestWizard'

const PAYMENT_LABELS: Record<string, string> = {
  LC: 'L/C اعتماد مستندي',
  TT: 'T/T تحويل بنكي مباشر',
  CAD: 'CAD نقداً عند التسليم',
}

const props = defineProps<{
  step1: WizardStep1Data
  step2: WizardStep2Data
  step3: WizardStep3Data
  merchantName: string
  acknowledged: boolean
}>()

const emit = defineEmits<{
  'update:acknowledged': [value: boolean]
}>()

const uploadedDocs = computed(() => {
  const entries: Array<{ key: string; label: string; name: string }> = [
    { key: 'proforma_invoice', label: 'الفاتورة الأولية', name: props.step3.proforma_invoice?.name ?? '' },
    { key: 'commercial_register', label: 'السجل التجاري', name: props.step3.commercial_register?.name ?? '' },
    { key: 'tax_card', label: 'البطاقة الضريبية', name: props.step3.tax_card?.name ?? '' },
    { key: 'extra_docs', label: 'مستندات إضافية', name: props.step3.extra_docs?.name ?? '' },
  ]
  return entries.filter(e => e.name)
})

const formattedAmount = computed(() => {
  if (!props.step1.amount) return ''
  return new Intl.NumberFormat('ar-YE').format(props.step1.amount) + ' ' + props.step1.currency
})
</script>

<template>
  <div class="step-content" dir="rtl">
    <h2 class="section-title">مراجعة الطلب قبل الإرسال</h2>

    <!-- Summary card -->
    <div class="summary-card">
      <!-- بيانات الطلب -->
      <div class="summary-section">
        <h3 class="summary-heading">بيانات الطلب</h3>
        <div class="summary-divider" />
        <div class="summary-grid">
          <div v-if="step1.goods_type" class="summary-cell">
            <span class="summary-cell__label">نوع الواردات</span>
            <span class="summary-cell__value">{{ step1.goods_type }}</span>
          </div>
          <div class="summary-cell">
            <span class="summary-cell__label">المستورد</span>
            <span class="summary-cell__value">{{ merchantName || '—' }}</span>
          </div>
          <div v-if="step1.amount" class="summary-cell">
            <span class="summary-cell__label">مبلغ التمويل</span>
            <span class="summary-cell__value">{{ formattedAmount }}</span>
          </div>
          <div v-if="step1.payment_terms" class="summary-cell">
            <span class="summary-cell__label">شروط الدفع</span>
            <span class="summary-cell__value">{{ PAYMENT_LABELS[step1.payment_terms] ?? step1.payment_terms }}</span>
          </div>
          <div v-if="step1.due_date" class="summary-cell">
            <span class="summary-cell__label">تاريخ الاستحقاق</span>
            <span class="summary-cell__value">{{ step1.due_date }}</span>
          </div>
          <div v-if="step1.notes" class="summary-cell summary-cell--full">
            <span class="summary-cell__label">ملاحظات</span>
            <span class="summary-cell__value">{{ step1.notes }}</span>
          </div>
        </div>
      </div>

      <!-- بيانات المورد والشحنة -->
      <div class="summary-section">
        <h3 class="summary-heading">بيانات المورد والشحنة</h3>
        <div class="summary-divider" />
        <div class="summary-grid">
          <div class="summary-cell">
            <span class="summary-cell__label">المورد</span>
            <span class="summary-cell__value">{{ step2.supplier_name || '—' }}</span>
          </div>
          <div v-if="step2.origin_country" class="summary-cell">
            <span class="summary-cell__label">بلد المنشأ</span>
            <span class="summary-cell__value">{{ step2.origin_country }}</span>
          </div>
          <div v-if="step2.invoice_number" class="summary-cell">
            <span class="summary-cell__label">رقم الفاتورة</span>
            <span class="summary-cell__value">{{ step2.invoice_number }}</span>
          </div>
          <div v-if="step2.invoice_date" class="summary-cell">
            <span class="summary-cell__label">تاريخ الفاتورة</span>
            <span class="summary-cell__value">{{ step2.invoice_date }}</span>
          </div>
          <div v-if="step2.arrival_port" class="summary-cell">
            <span class="summary-cell__label">ميناء الوصول</span>
            <span class="summary-cell__value">{{ step2.arrival_port }}</span>
          </div>
          <div v-if="step2.bl_number" class="summary-cell">
            <span class="summary-cell__label">رقم بوليصة الشحن</span>
            <span class="summary-cell__value">{{ step2.bl_number }}</span>
          </div>
          <div v-if="step2.customs_office" class="summary-cell">
            <span class="summary-cell__label">الجمارك المختصة</span>
            <span class="summary-cell__value">{{ step2.customs_office }}</span>
          </div>
        </div>
      </div>

      <!-- الوثائق المرفوعة -->
      <div v-if="uploadedDocs.length" class="summary-section">
        <h3 class="summary-heading">الوثائق المرفوعة</h3>
        <div class="summary-divider" />
        <ul class="docs-list">
          <li v-for="doc in uploadedDocs" :key="doc.key" class="doc-item">
            <span class="doc-check">✓</span>
            <span class="doc-label">{{ doc.label }}</span>
            <span class="doc-name">{{ doc.name }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Acknowledgment checkbox -->
    <div class="ack-container">
      <div class="ack-header">
        <ShieldCheck class="ack-icon" :size="20" aria-hidden="true" />
        <span class="ack-header-text">إقرار بصحة البيانات</span>
      </div>
      <label class="ack-label">
        <input
          type="checkbox"
          class="ack-checkbox"
          :checked="acknowledged"
          @change="emit('update:acknowledged', ($event.target as HTMLInputElement).checked)"
        />
        <span class="ack-text">
          أُقر بأن جميع البيانات والمستندات المقدمة صحيحة وكاملة، وأتحمل المسؤولية القانونية عن أي بيانات غير دقيقة أو مستندات مزوّرة، وفقاً للوائح البنك المركزي اليمني.
        </span>
      </label>
    </div>
  </div>
</template>

<style scoped>
.step-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-title {
  font-family: 'Tajawal', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: #1c222b;
  margin: 0;
}

/* Summary card */
.summary-card {
  border: 1px solid #cccccc;
  border-radius: 16px;
  background: #ffffff;
  overflow: hidden;
}

.summary-section {
  padding: 16px 20px;
}

.summary-section + .summary-section {
  border-top: 1px solid #cccccc;
}

.summary-heading {
  font-family: 'Tajawal', sans-serif;
  font-size: 16px;
  font-weight: 700;
  color: #1c222b;
  margin: 0 0 8px;
}

.summary-divider {
  height: 1px;
  background: #cccccc;
  margin-bottom: 12px;
}

/* 2-column summary grid */
.summary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 24px;
}

.summary-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.summary-cell--full {
  grid-column: span 2;
}

.summary-cell__label {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 12px;
  color: #6c757d;
  font-weight: 400;
}

.summary-cell__value {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  font-weight: 500;
  color: #1c222b;
}

/* Docs list */
.docs-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.doc-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.doc-check {
  color: #1b5e20;
  font-weight: 700;
  font-size: 14px;
}

.doc-label {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #6c757d;
  min-width: 140px;
}

.doc-name {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #1c222b;
}

/* Acknowledgment — blue info tone */
.ack-container {
  background: #e3f2fd;
  border: 1px solid #bbdefb;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 8px;
}

.ack-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.ack-icon {
  color: #0066cc;
  flex-shrink: 0;
}

.ack-header-text {
  font-family: 'Tajawal', sans-serif;
  font-size: 15px;
  font-weight: 700;
  color: #0d47a1;
}

.ack-label {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  cursor: pointer;
}

.ack-checkbox {
  width: 20px;
  height: 20px;
  border-radius: 4px;
  border: 2px solid #90caf9;
  accent-color: #0066cc;
  flex-shrink: 0;
  margin-top: 2px;
  cursor: pointer;
}

.ack-text {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #0d47a1;
  line-height: 1.6;
}

@media (max-width: 600px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }
  .summary-cell--full {
    grid-column: span 1;
  }
}
</style>
