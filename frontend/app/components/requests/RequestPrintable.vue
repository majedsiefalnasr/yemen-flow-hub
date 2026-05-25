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
  <div class="max-w-[900px] mx-auto bg-white text-gray-900 p-6 print:p-0 print:max-w-full" dir="rtl">
    <!-- ─── Section 1: Title + reference + status ─── -->
    <header class="flex items-start justify-between gap-4 mb-2">
      <div class="flex flex-col gap-1">
        <h1 class="font-bold text-2xl text-gray-900 m-0">طلب تمويل واردات</h1>
        <span class="text-primary font-semibold text-base">{{ request.reference_number }}</span>
      </div>
      <span class="text-sm font-semibold px-3 py-1 rounded-full border border-gray-200 text-gray-900 bg-gray-50 whitespace-nowrap flex-shrink-0 print:border-black print:bg-transparent print:text-black">{{ statusLabel(request.status) }}</span>
    </header>

    <div class="h-px bg-border my-4" />

    <!-- ─── Section 2: Bank / user / date ─── -->
    <section class="flex flex-col gap-1.5" aria-label="بيانات الطلب">
      <div class="flex gap-2 text-sm">
        <span class="font-semibold w-36 text-gray-600">البنك:</span>
        <span class="text-gray-900">{{ request.bank_name ?? '—' }}</span>
      </div>
      <div class="flex gap-2 text-sm">
        <span class="font-semibold w-36 text-gray-600">مقدّم الطلب:</span>
        <span class="text-gray-900">{{ actorName(request.created_by_user) }}</span>
      </div>
      <div class="flex gap-2 text-sm">
        <span class="font-semibold w-36 text-gray-600">تاريخ الإنشاء:</span>
        <span class="text-gray-900">{{ formatDate(request.created_at) }}</span>
      </div>
    </section>

    <div class="h-px bg-border my-4" />

    <!-- ─── Section 3: Wizard fields ─── -->
    <section aria-label="تفاصيل الطلب">
      <h2 class="font-bold text-base text-primary mb-3 m-0">تفاصيل الطلب</h2>
      <div class="grid grid-cols-2 gap-y-2.5 gap-x-6">
        <div v-for="field in fields" :key="field.label" class="flex flex-col gap-0.5">
          <span class="text-xs text-gray-600 font-semibold">{{ field.label }}</span>
          <span class="text-sm text-gray-900">{{ field.value }}</span>
        </div>
      </div>
    </section>

    <div class="h-px bg-border my-4" />

    <!-- ─── Section 4: Documents ─── -->
    <section aria-label="المستندات المرفقة">
      <h2 class="font-bold text-base text-primary mb-3 m-0">المستندات المرفقة</h2>
      <p v-if="documents.length === 0" class="text-sm text-gray-600 text-center py-4">لا توجد مستندات مرفقة.</p>
      <table v-else class="w-full border-collapse text-sm">
        <thead>
          <tr>
            <th class="text-right py-2 px-2.5 border border-gray-200 bg-gray-50 font-semibold text-gray-900 print:bg-border">اسم الملف</th>
            <th class="text-right py-2 px-2.5 border border-gray-200 bg-gray-50 font-semibold text-gray-900 print:bg-border">تاريخ الرفع</th>
            <th class="text-right py-2 px-2.5 border border-gray-200 bg-gray-50 font-semibold text-gray-900 print:bg-border">رُفع بواسطة</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id">
            <td class="text-right py-2 px-2.5 border border-gray-200 text-gray-900">{{ doc.original_filename }}</td>
            <td class="text-right py-2 px-2.5 border border-gray-200 text-gray-900">{{ formatDate(doc.uploaded_at) }}</td>
            <td class="text-right py-2 px-2.5 border border-gray-200 text-gray-900">{{ doc.uploaded_by_name ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="h-px bg-border my-4" />

    <!-- ─── Section 5: Workflow timeline ─── -->
    <section aria-label="مسار سير العمل">
      <h2 class="font-bold text-base text-primary mb-3 m-0">مسار سير العمل</h2>
      <p v-if="workflowEntries.length === 0" class="text-sm text-gray-600 text-center py-4">لا توجد مراحل مسجّلة بعد.</p>
      <ol v-else class="list-none p-0 m-0 flex flex-col gap-2.5 print:break-inside-avoid">
        <li v-for="entry in workflowEntries" :key="entry.id" class="workflow-entry grid grid-cols-[1.4fr_1fr_auto] gap-3 items-center p-3 border border-gray-200 rounded-3xl bg-gray-50 print:bg-transparent">
          <span class="text-sm font-semibold text-gray-900">{{ entry.statusLabel }}</span>
          <span class="text-xs text-gray-600">{{ entry.actor }}</span>
          <span class="text-xs text-gray-600 whitespace-nowrap">{{ entry.timestamp }}</span>
        </li>
      </ol>
    </section>

    <div class="h-px bg-border my-4" />

    <!-- ─── Section 6: Audit timeline ─── -->
    <section aria-label="سجل الأحداث" class="print:break-inside-avoid">
      <h2 class="font-bold text-base text-primary mb-3 m-0">سجل الأحداث</h2>
      <AuditTimeline :entries="sortedHistory" />
    </section>
  </div>
</template>
