<script setup lang="ts">
import { computed } from 'vue'
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
        <dl class="summary-list">
          <div v-if="step1.goods_type" class="summary-row">
            <dt class="summary-key">نوع الواردات</dt>
            <dd class="summary-val">{{ step1.goods_type }}</dd>
          </div>
          <div class="summary-row">
            <dt class="summary-key">المستورد</dt>
            <dd class="summary-val">{{ merchantName || '—' }}</dd>
          </div>
          <div v-if="step1.amount" class="summary-row">
            <dt class="summary-key">مبلغ التمويل</dt>
            <dd class="summary-val">{{ formattedAmount }}</dd>
          </div>
          <div v-if="step1.payment_terms" class="summary-row">
            <dt class="summary-key">شروط الدفع</dt>
            <dd class="summary-val">{{ PAYMENT_LABELS[step1.payment_terms] ?? step1.payment_terms }}</dd>
          </div>
          <div v-if="step1.due_date" class="summary-row">
            <dt class="summary-key">تاريخ الاستحقاق</dt>
            <dd class="summary-val">{{ step1.due_date }}</dd>
          </div>
          <div v-if="step1.notes" class="summary-row">
            <dt class="summary-key">ملاحظات</dt>
            <dd class="summary-val">{{ step1.notes }}</dd>
          </div>
        </dl>
      </div>

      <!-- بيانات المورد والشحنة -->
      <div class="summary-section">
        <h3 class="summary-heading">بيانات المورد والشحنة</h3>
        <div class="summary-divider" />
        <dl class="summary-list">
          <div class="summary-row">
            <dt class="summary-key">المورد</dt>
            <dd class="summary-val">{{ step2.supplier_name || '—' }}</dd>
          </div>
          <div v-if="step2.invoice_number" class="summary-row">
            <dt class="summary-key">رقم الفاتورة</dt>
            <dd class="summary-val">{{ step2.invoice_number }}</dd>
          </div>
          <div v-if="step2.invoice_date" class="summary-row">
            <dt class="summary-key">تاريخ الفاتورة</dt>
            <dd class="summary-val">{{ step2.invoice_date }}</dd>
          </div>
          <div v-if="step2.arrival_port" class="summary-row">
            <dt class="summary-key">ميناء الوصول</dt>
            <dd class="summary-val">{{ step2.arrival_port }}</dd>
          </div>
          <div v-if="step2.origin_country" class="summary-row">
            <dt class="summary-key">بلد المنشأ</dt>
            <dd class="summary-val">{{ step2.origin_country }}</dd>
          </div>
          <div v-if="step2.customs_office" class="summary-row">
            <dt class="summary-key">الجمارك المختصة</dt>
            <dd class="summary-val">{{ step2.customs_office }}</dd>
          </div>
          <div v-if="step2.bl_number" class="summary-row">
            <dt class="summary-key">رقم بوليصة الشحن</dt>
            <dd class="summary-val">{{ step2.bl_number }}</dd>
          </div>
        </dl>
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

.summary-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
}

.summary-row {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}

.summary-key {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #6c757d;
  min-width: 140px;
  flex-shrink: 0;
}

.summary-val {
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

/* Acknowledgment */
.ack-container {
  background: #fff8e1;
  border: 1px solid #ffe082;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 8px;
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
  border: 2px solid #cccccc;
  accent-color: #0066cc;
  flex-shrink: 0;
  margin-top: 2px;
  cursor: pointer;
}

.ack-text {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #1c222b;
  line-height: 1.6;
}
</style>
