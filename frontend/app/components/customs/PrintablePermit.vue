<script setup lang="ts">
import type { ImportRequest } from '@/types/models'
import { cn } from '@/lib/utils'

defineProps<{
  request: ImportRequest
  watermark?: boolean
  formatDate: (value?: string | null) => string
  formatDay: (value?: string | null) => string
}>()
</script>

<template>
  <div
    dir="rtl"
    :class="cn(
      'relative mx-auto shadow-card print:m-0 print:shadow-none',
      'bg-card text-foreground print:bg-white print:text-black'
    )"
    style="width: 210mm; min-height: 297mm; padding: 20mm;"
  >
    <div
      v-if="watermark"
      class="pointer-events-none absolute inset-0 grid place-items-center print:hidden"
    >
      <div class="-rotate-12 select-none text-[140px] font-black tracking-widest text-muted print:text-muted/50">
        مسودة
      </div>
    </div>

    <header :class="cn('relative flex items-start justify-between pb-4', 'border-b-2 border-foreground print:border-black')">
      <div>
        <div class="text-xs">
          الجمهورية اليمنية
        </div>
        <div class="text-lg font-bold">
          البنك المركزي اليمني
        </div>
        <div class="text-xs">
          إدارة تنظيم وتمويل الواردات
        </div>
      </div>
      <div class="text-center">
        <div class="text-base font-bold">
          إذن إصدار بيان جمركي
        </div>
        <div class="text-xs">
          Customs Declaration Permit
        </div>
        <div
          v-if="request.customs_declaration"
          class="mt-1 inline-flex items-center gap-1 rounded border border-success px-2 py-0.5 text-[10px] text-success"
        >
          موقّع إلكترونياً
        </div>
      </div>
      <div class="text-start text-xs">
        <div>
          رقم البيان: <span class="font-bold">{{ request.customs_declaration?.declaration_number ?? '—' }}</span>
        </div>
        <div>التاريخ: {{ formatDay(request.customs_declaration?.issued_at) }}</div>
        <div>المرجع: {{ request.reference_number }}</div>
      </div>
    </header>

    <section class="relative mt-6">
      <h2 :class="cn('mb-2 px-2 py-1 text-sm font-bold', 'bg-foreground text-background print:bg-black print:text-white')">
        بيانات المستورد والجهة الممولة
      </h2>
      <table class="w-full border-collapse text-xs">
        <tbody>
          <tr>
            <td :class="cn('w-1/4 border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              المستورد
            </td>
            <td :class="cn('w-1/4 border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.merchant?.name ?? '—' }}
            </td>
            <td :class="cn('w-1/4 border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              البنك / الجهة
            </td>
            <td :class="cn('w-1/4 border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.bank_name ?? '—' }}
            </td>
          </tr>
          <tr>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              المبلغ
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.amount.toLocaleString('en-US') }} {{ request.currency }}
            </td>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              رقم الفاتورة
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.invoice_number ?? '—' }}
            </td>
          </tr>
          <tr>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              المورد
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.supplier_name }}
            </td>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              ميناء الوصول
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.port_of_entry }}
            </td>
          </tr>
          <tr>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              نوع البضاعة
            </td>
            <td
              :class="cn('border p-2 colspan', 'text-foreground border-border print:text-black print:border-border')"
              colspan="3"
            >
              {{ request.goods_description }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="relative mt-6">
      <h2 :class="cn('mb-2 px-2 py-1 text-sm font-bold', 'bg-foreground text-background print:bg-black print:text-white')">
        دورة الاعتماد
      </h2>
      <table class="w-full border-collapse text-xs">
        <tbody>
          <tr>
            <td :class="cn('w-1/4 border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              اعتماد المساندة
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              تم
            </td>
            <td :class="cn('w-1/4 border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              رقم السويفت
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ request.swift_uploaded_at ? formatDay(request.swift_uploaded_at) : '—' }}
            </td>
          </tr>
          <tr>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              اعتماد التصويت التنفيذي
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              تم
            </td>
            <td :class="cn('border p-2', 'bg-muted text-foreground print:bg-muted print:text-black border-border print:border-border')">
              تاريخ الإصدار
            </td>
            <td :class="cn('border p-2', 'text-foreground border-border print:text-black print:border-border')">
              {{ formatDate(request.customs_declaration?.issued_at) }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="relative mt-10 grid grid-cols-3 gap-6">
      <div class="text-center">
        <div class="mt-12 border-t border-foreground pt-2 text-xs print:border-black">
          توقيع عضو اللجنة التنفيذية
        </div>
      </div>
      <div class="text-center">
        <div class="mt-12 border-t border-foreground pt-2 text-xs print:border-black">
          الختم الرسمي
        </div>
      </div>
      <div class="text-center">
        <div class="mt-12 border-t border-foreground pt-2 text-xs print:border-black">
          تأشيرة الجمارك
        </div>
      </div>
    </section>

    <footer :class="cn('relative mt-12 border-t pt-2 text-center text-[10px]', 'border-border text-muted-foreground print:border-border print:text-muted-foreground')">
      هذه الوثيقة صادرة إلكترونياً من منصة إدارة الواردات — البنك المركزي اليمني. للتحقق من الصحة، يرجى مراجعة سجل المنصة باستخدام رقم البيان.
    </footer>
  </div>
</template>
