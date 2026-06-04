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
  sortedHistory.value.map((entry) => ({
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
  <div
    class="bg-background text-foreground mx-auto max-w-[900px] p-6 print:max-w-full print:bg-white print:p-0 print:text-black"
  >
    <!-- ─── Section 1: Title + reference + status ─── -->
    <header class="mb-2 flex items-start justify-between gap-4">
      <div class="flex min-w-0 flex-col gap-1">
        <h1
          class="font-heading text-foreground m-0 text-2xl leading-tight font-semibold print:text-black"
        >
          طلب تمويل واردات
        </h1>
        <span
          class="text-primary text-base leading-6 font-semibold break-all tabular-nums print:text-black"
          >{{ request.reference_number }}</span
        >
      </div>
      <span
        class="border-border bg-muted text-foreground flex-shrink-0 rounded-full border px-3 py-1 text-sm leading-5 font-medium whitespace-nowrap print:border-black print:bg-transparent print:text-black"
        >{{ statusLabel(request.status) }}</span
      >
    </header>

    <div class="bg-border my-4 h-px" />

    <!-- ─── Section 2: Bank / user / date ─── -->
    <section class="flex flex-col gap-1.5" aria-label="بيانات الطلب">
      <div class="flex gap-2 text-sm leading-6">
        <span
          class="font-section text-muted-foreground w-36 text-xs leading-6 font-medium print:text-black"
          >البنك:</span
        >
        <span class="text-foreground min-w-0 break-words print:text-black">{{
          request.bank_name ?? '—'
        }}</span>
      </div>
      <div class="flex gap-2 text-sm leading-6">
        <span
          class="font-section text-muted-foreground w-36 text-xs leading-6 font-medium print:text-black"
          >مقدّم الطلب:</span
        >
        <span class="text-foreground min-w-0 break-words print:text-black">{{
          actorName(request.created_by_user)
        }}</span>
      </div>
      <div class="flex gap-2 text-sm leading-6">
        <span
          class="font-section text-muted-foreground w-36 text-xs leading-6 font-medium print:text-black"
          >تاريخ الإنشاء:</span
        >
        <span class="text-foreground min-w-0 break-words print:text-black">{{
          formatDate(request.created_at)
        }}</span>
      </div>
    </section>

    <div class="bg-border my-4 h-px" />

    <!-- ─── Section 3: Wizard fields ─── -->
    <section aria-label="تفاصيل الطلب">
      <h2
        class="font-heading text-primary m-0 mb-3 text-base leading-6 font-semibold print:text-black"
      >
        تفاصيل الطلب
      </h2>
      <div class="grid grid-cols-2 gap-x-6 gap-y-2.5">
        <div v-for="field in fields" :key="field.label" class="flex min-w-0 flex-col gap-0.5">
          <span
            class="font-section text-muted-foreground text-xs leading-5 font-medium print:text-black"
            >{{ field.label }}</span
          >
          <span class="text-foreground text-sm leading-6 break-words print:text-black">{{
            field.value
          }}</span>
        </div>
      </div>
    </section>

    <div class="bg-border my-4 h-px" />

    <!-- ─── Section 4: Documents ─── -->
    <section aria-label="المستندات المرفقة">
      <h2
        class="font-heading text-primary m-0 mb-3 text-base leading-6 font-semibold print:text-black"
      >
        المستندات المرفقة
      </h2>
      <p
        v-if="documents.length === 0"
        class="text-muted-foreground py-4 text-center text-sm leading-6 print:text-black"
      >
        لا توجد مستندات مرفقة.
      </p>
      <table v-else class="w-full border-collapse text-sm">
        <thead>
          <tr>
            <th
              class="border-border bg-muted font-section text-foreground print:bg-border border px-2.5 py-2 text-right text-xs leading-5 font-medium print:text-black"
            >
              اسم الملف
            </th>
            <th
              class="border-border bg-muted font-section text-foreground print:bg-border border px-2.5 py-2 text-right text-xs leading-5 font-medium print:text-black"
            >
              تاريخ الرفع
            </th>
            <th
              class="border-border bg-muted font-section text-foreground print:bg-border border px-2.5 py-2 text-right text-xs leading-5 font-medium print:text-black"
            >
              رُفع بواسطة
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id">
            <td
              class="border-border text-foreground border px-2.5 py-2 text-right leading-6 break-all print:text-black"
            >
              {{ doc.original_filename }}
            </td>
            <td
              class="border-border text-foreground border px-2.5 py-2 text-right leading-6 print:text-black"
            >
              {{ formatDate(doc.uploaded_at) }}
            </td>
            <td
              class="border-border text-foreground border px-2.5 py-2 text-right leading-6 break-words print:text-black"
            >
              {{ doc.uploaded_by_name ?? '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="bg-border my-4 h-px" />

    <!-- ─── Section 5: Workflow timeline ─── -->
    <section aria-label="مسار سير العمل">
      <h2
        class="font-heading text-primary m-0 mb-3 text-base leading-6 font-semibold print:text-black"
      >
        مسار سير العمل
      </h2>
      <p
        v-if="workflowEntries.length === 0"
        class="text-muted-foreground py-4 text-center text-sm leading-6 print:text-black"
      >
        لا توجد مراحل مسجّلة بعد.
      </p>
      <ol v-else class="m-0 flex list-none flex-col gap-2.5 p-0 print:break-inside-avoid">
        <li
          v-for="entry in workflowEntries"
          :key="entry.id"
          class="workflow-entry border-border bg-muted grid grid-cols-[1.4fr_1fr_auto] items-center gap-3 rounded-xl border p-3 print:bg-transparent"
        >
          <span
            class="text-foreground text-sm leading-6 font-medium break-words print:text-black"
            >{{ entry.statusLabel }}</span
          >
          <span class="text-muted-foreground text-xs leading-5 break-words print:text-black">{{
            entry.actor
          }}</span>
          <span
            class="text-muted-foreground text-xs leading-5 whitespace-nowrap tabular-nums print:text-black"
            >{{ entry.timestamp }}</span
          >
        </li>
      </ol>
    </section>

    <div class="bg-border my-4 h-px" />

    <!-- ─── Section 6: Audit timeline ─── -->
    <section aria-label="سجل الأحداث" class="print:break-inside-avoid">
      <h2
        class="font-heading text-primary m-0 mb-3 text-base leading-6 font-semibold print:text-black"
      >
        سجل الأحداث
      </h2>
      <AuditTimeline :entries="sortedHistory" />
    </section>
  </div>
</template>
