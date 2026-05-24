<script setup lang="ts">
import { computed } from 'vue'
import { ShieldCheck, CheckCircle2 } from 'lucide-vue-next'
import type { WizardStep1Data, WizardStep2Data, WizardStep3Data } from '../../composables/useRequestWizard'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card'
import { Checkbox } from '../ui/checkbox'
import { Label } from '../ui/label'

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
  <div class="flex flex-col gap-6" dir="rtl">
    <h2 class="text-2xl font-bold">مراجعة الطلب قبل الإرسال</h2>

    <Card>
      <CardHeader>
        <CardTitle>بيانات الطلب</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-if="step1.goods_type">
            <p class="text-xs text-muted-foreground">نوع الواردات</p>
            <p class="text-sm font-medium text-foreground">{{ step1.goods_type }}</p>
          </div>
          <div>
            <p class="text-xs text-muted-foreground">المستورد</p>
            <p class="text-sm font-medium text-foreground">{{ merchantName || '—' }}</p>
          </div>
          <div v-if="step1.amount">
            <p class="text-xs text-muted-foreground">مبلغ التمويل</p>
            <p class="text-sm font-medium text-foreground">{{ formattedAmount }}</p>
          </div>
          <div v-if="step1.payment_terms">
            <p class="text-xs text-muted-foreground">شروط الدفع</p>
            <p class="text-sm font-medium text-foreground">{{ PAYMENT_LABELS[step1.payment_terms] ?? step1.payment_terms }}</p>
          </div>
          <div v-if="step1.due_date">
            <p class="text-xs text-muted-foreground">تاريخ الاستحقاق</p>
            <p class="text-sm font-medium text-foreground">{{ step1.due_date }}</p>
          </div>
          <div v-if="step1.notes" class="sm:col-span-2">
            <p class="text-xs text-muted-foreground">ملاحظات</p>
            <p class="text-sm font-medium text-foreground">{{ step1.notes }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>بيانات المورد والشحنة</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-muted-foreground">المورد</p>
            <p class="text-sm font-medium text-foreground">{{ step2.supplier_name || '—' }}</p>
          </div>
          <div v-if="step2.origin_country">
            <p class="text-xs text-muted-foreground">بلد المنشأ</p>
            <p class="text-sm font-medium text-foreground">{{ step2.origin_country }}</p>
          </div>
          <div v-if="step2.invoice_number">
            <p class="text-xs text-muted-foreground">رقم الفاتورة</p>
            <p class="text-sm font-medium text-foreground">{{ step2.invoice_number }}</p>
          </div>
          <div v-if="step2.invoice_date">
            <p class="text-xs text-muted-foreground">تاريخ الفاتورة</p>
            <p class="text-sm font-medium text-foreground">{{ step2.invoice_date }}</p>
          </div>
          <div v-if="step2.arrival_port">
            <p class="text-xs text-muted-foreground">ميناء الوصول</p>
            <p class="text-sm font-medium text-foreground">{{ step2.arrival_port }}</p>
          </div>
          <div v-if="step2.bl_number">
            <p class="text-xs text-muted-foreground">رقم بوليصة الشحن</p>
            <p class="text-sm font-medium text-foreground">{{ step2.bl_number }}</p>
          </div>
          <div v-if="step2.customs_office">
            <p class="text-xs text-muted-foreground">الجمارك المختصة</p>
            <p class="text-sm font-medium text-foreground">{{ step2.customs_office }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card v-if="uploadedDocs.length">
      <CardHeader>
        <CardTitle>الوثائق المرفوعة</CardTitle>
      </CardHeader>
      <CardContent>
        <ul class="flex flex-col gap-2">
          <li v-for="doc in uploadedDocs" :key="doc.key" class="flex items-center gap-3">
            <CheckCircle2 class="h-5 w-5 text-success flex-shrink-0" />
            <span class="text-sm text-muted-foreground min-w-36">{{ doc.label }}</span>
            <span class="text-sm font-medium text-foreground">{{ doc.name }}</span>
          </li>
        </ul>
      </CardContent>
    </Card>

    <Card class="border-border bg-primary/10">
      <CardHeader>
        <div class="flex items-center gap-2">
          <ShieldCheck class="h-5 w-5 text-primary" />
          <CardTitle class="text-foreground">إقرار بصحة البيانات</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        <div class="flex gap-3">
          <Checkbox
            :checked="acknowledged"
            @update:checked="emit('update:acknowledged', $event)"
          />
          <Label class="text-sm text-foreground leading-relaxed cursor-pointer">
            أُقر بأن جميع البيانات والمستندات المقدمة صحيحة وكاملة، وأتحمل المسؤولية القانونية عن أي بيانات غير دقيقة أو مستندات مزوّرة، وفقاً للوائح البنك المركزي اليمني.
          </Label>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

