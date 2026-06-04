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
    :class="
      cn(
        'relative mx-auto shadow print:m-0 print:shadow-none',
        'bg-white text-gray-900 print:bg-white print:text-black',
      )
    "
    style="width: 210mm; min-height: 297mm; padding: 20mm"
  >
    <div
      v-if="watermark"
      class="pointer-events-none absolute inset-0 grid place-items-center print:hidden"
    >
      <div
        class="-rotate-12 text-[140px] font-black tracking-widest text-gray-500 select-none print:text-gray-500/50"
      >
        مسودة
      </div>
    </div>

    <header
      :class="
        cn(
          'relative flex items-start justify-between pb-4',
          'border-b-2 border-gray-900 print:border-black',
        )
      "
    >
      <div>
        <div class="text-xs">الجمهورية اليمنية</div>
        <div class="text-lg font-bold">البنك المركزي اليمني</div>
        <div class="text-xs">إدارة تنظيم وتمويل الواردات</div>
      </div>
      <div class="text-center">
        <div class="text-base font-bold">إذن إصدار بيان جمركي</div>
        <div class="text-xs">Customs Declaration Permit</div>
        <div
          v-if="request.customs_declaration"
          class="mt-1 inline-flex items-center gap-1 rounded border border-green-200 px-2 py-0.5 text-[10px] text-green-700"
        >
          موقّع إلكترونياً
        </div>
      </div>
      <div class="text-start text-xs">
        <div>
          رقم البيان:
          <span class="font-bold">{{
            request.customs_declaration?.declaration_number ?? '—'
          }}</span>
        </div>
        <div>التاريخ: {{ formatDay(request.customs_declaration?.issued_at) }}</div>
        <div>المرجع: {{ request.reference_number }}</div>
      </div>
    </header>

    <section class="relative mt-6">
      <h2
        :class="
          cn(
            'mb-2 px-2 py-1 text-sm font-bold',
            'bg-gray-900 text-white print:bg-black print:text-white',
          )
        "
      >
        بيانات المستورد والجهة الممولة
      </h2>
      <table class="w-full border-collapse text-xs">
        <tbody>
          <tr>
            <td
              :class="
                cn(
                  'w-1/4 border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              المستورد
            </td>
            <td
              :class="
                cn(
                  'w-1/4 border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.merchant?.name ?? '—' }}
            </td>
            <td
              :class="
                cn(
                  'w-1/4 border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              البنك / الجهة
            </td>
            <td
              :class="
                cn(
                  'w-1/4 border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.bank_name ?? '—' }}
            </td>
          </tr>
          <tr>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              المبلغ
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.amount.toLocaleString('en-US') }} {{ request.currency }}
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              رقم الفاتورة
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.invoice_number ?? '—' }}
            </td>
          </tr>
          <tr>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              المورد
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.supplier_name }}
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              ميناء الوصول
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.port_of_entry }}
            </td>
          </tr>
          <tr>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              نوع البضاعة
            </td>
            <td
              :class="
                cn(
                  'colspan border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
              colspan="3"
            >
              {{ request.goods_description }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="relative mt-6">
      <h2
        :class="
          cn(
            'mb-2 px-2 py-1 text-sm font-bold',
            'bg-gray-900 text-white print:bg-black print:text-white',
          )
        "
      >
        دورة الاعتماد
      </h2>
      <table class="w-full border-collapse text-xs">
        <tbody>
          <tr>
            <td
              :class="
                cn(
                  'w-1/4 border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              اعتماد المساندة
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              تم
            </td>
            <td
              :class="
                cn(
                  'w-1/4 border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              رقم السويفت
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ request.swift_uploaded_at ? formatDay(request.swift_uploaded_at) : '—' }}
            </td>
          </tr>
          <tr>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              اعتماد التصويت التنفيذي
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              تم
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 bg-gray-50 text-gray-900 print:border-gray-200 print:bg-gray-50 print:text-black',
                )
              "
            >
              تاريخ الإصدار
            </td>
            <td
              :class="
                cn(
                  'border p-2',
                  'border-gray-200 text-gray-900 print:border-gray-200 print:text-black',
                )
              "
            >
              {{ formatDate(request.customs_declaration?.issued_at) }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="relative mt-10 grid grid-cols-3 gap-6">
      <div class="text-center">
        <div class="mt-12 border-t border-gray-900 pt-2 text-xs print:border-black">
          توقيع عضو اللجنة التنفيذية
        </div>
      </div>
      <div class="text-center">
        <div class="mt-12 border-t border-gray-900 pt-2 text-xs print:border-black">
          الختم الرسمي
        </div>
      </div>
      <div class="text-center">
        <div class="mt-12 border-t border-gray-900 pt-2 text-xs print:border-black">
          تأشيرة الجمارك
        </div>
      </div>
    </section>

    <footer
      :class="
        cn(
          'relative mt-12 border-t pt-2 text-center text-[10px]',
          'border-gray-200 text-gray-600 print:border-gray-200 print:text-gray-600',
        )
      "
    >
      هذه الوثيقة صادرة إلكترونياً من منصة إدارة الواردات — البنك المركزي اليمني. للتحقق من الصحة،
      يرجى مراجعة سجل المنصة باستخدام رقم البيان.
    </footer>
  </div>
</template>
