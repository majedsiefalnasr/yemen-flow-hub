<script setup lang="ts">
import { computed } from 'vue'
import { ShieldCheck, CheckCircle2 } from 'lucide-vue-next'
import type {
  WizardStep1Data,
  WizardStep2Data,
  WizardStep3Data,
} from '../../composables/useRequestWizard'
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card'
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

const acknowledgedValue = computed({
  get: () => props.acknowledged,
  set: (value: boolean | 'indeterminate') => {
    emit('update:acknowledged', value === true)
  },
})

const uploadedDocs = computed(() => {
  const entries: Array<{ key: string; label: string; name: string }> = [
    {
      key: 'confirmation_request',
      label: 'طلب وثيقة التأكيد (مختوم)',
      name: props.step3.confirmation_request?.name ?? '',
    },
    {
      key: 'proforma_invoice',
      label: 'الفاتورة الأولية',
      name: props.step3.proforma_invoice?.name ?? '',
    },
    {
      key: 'commercial_register',
      label: 'السجل التجاري',
      name: props.step3.commercial_register?.name ?? '',
    },
    { key: 'tax_card', label: 'البطاقة الضريبية', name: props.step3.tax_card?.name ?? '' },
    { key: 'extra_docs', label: 'مستندات إضافية', name: props.step3.extra_docs?.name ?? '' },
  ]
  return entries.filter((e) => e.name)
})

const formattedAmount = computed(() => {
  if (!props.step1.amount) return ''
  return new Intl.NumberFormat('ar-YE').format(props.step1.amount) + ' ' + props.step1.currency
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <h2 class="font-heading text-foreground text-xl leading-snug font-semibold">
      مراجعة الطلب قبل الإرسال
    </h2>

    <Card>
      <CardHeader>
        <CardTitle>بيانات الطلب</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div v-if="step1.goods_type">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              نوع الواردات
            </p>
            <p class="text-foreground text-sm leading-6 font-medium">{{ step1.goods_type }}</p>
          </div>
          <div>
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">المستورد</p>
            <p class="text-foreground text-sm leading-6 font-medium">{{ merchantName || '—' }}</p>
          </div>
          <div v-if="step1.amount">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              مبلغ التمويل
            </p>
            <p class="text-foreground text-sm leading-6 font-medium tabular-nums">
              {{ formattedAmount }}
            </p>
          </div>
          <div v-if="step1.payment_terms">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              شروط الدفع
            </p>
            <p class="text-foreground text-sm leading-6 font-medium">
              {{ PAYMENT_LABELS[step1.payment_terms] ?? step1.payment_terms }}
            </p>
          </div>
          <div v-if="step1.due_date">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              تاريخ الاستحقاق
            </p>
            <p class="text-foreground text-sm leading-6 font-medium tabular-nums">
              {{ step1.due_date }}
            </p>
          </div>
          <div v-if="step1.notes" class="sm:col-span-2">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">ملاحظات</p>
            <p
              class="text-foreground max-w-[75ch] text-sm leading-6 font-normal break-words whitespace-pre-wrap"
            >
              {{ step1.notes }}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>بيانات المورد والشحنة</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">المورد</p>
            <p class="text-foreground text-sm leading-6 font-medium">
              {{ step2.supplier_name || '—' }}
            </p>
          </div>
          <div v-if="step2.origin_country">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              بلد المنشأ
            </p>
            <p class="text-foreground text-sm leading-6 font-medium">{{ step2.origin_country }}</p>
          </div>
          <div v-if="step2.invoice_number">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              رقم الفاتورة
            </p>
            <p class="text-foreground text-sm leading-6 font-medium break-all tabular-nums">
              {{ step2.invoice_number }}
            </p>
          </div>
          <div v-if="step2.invoice_date">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              تاريخ الفاتورة
            </p>
            <p class="text-foreground text-sm leading-6 font-medium tabular-nums">
              {{ step2.invoice_date }}
            </p>
          </div>
          <div v-if="step2.arrival_port">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              ميناء الوصول
            </p>
            <p class="text-foreground text-sm leading-6 font-medium">{{ step2.arrival_port }}</p>
          </div>
          <div v-if="step2.bl_number">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              رقم بوليصة الشحن
            </p>
            <p class="text-foreground text-sm leading-6 font-medium break-all tabular-nums">
              {{ step2.bl_number }}
            </p>
          </div>
          <div v-if="step2.customs_office">
            <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
              الجمارك المختصة
            </p>
            <p class="text-foreground text-sm leading-6 font-medium">{{ step2.customs_office }}</p>
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
            <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-[var(--color-text-success)]" />
            <span
              class="font-section text-muted-foreground min-w-36 text-xs leading-5 font-medium"
              >{{ doc.label }}</span
            >
            <span class="text-foreground min-w-0 text-sm leading-6 font-medium break-all">{{
              doc.name
            }}</span>
          </li>
        </ul>
      </CardContent>
    </Card>

    <Card class="border-border bg-primary/10">
      <CardHeader>
        <div class="flex items-center gap-2">
          <ShieldCheck class="text-primary h-5 w-5" />
          <CardTitle>إقرار بصحة البيانات</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        <div class="flex gap-3">
          <Checkbox
            v-model:checked="acknowledgedValue"
            @click="emit('update:acknowledged', !acknowledged)"
          />
          <Label
            class="text-foreground max-w-[75ch] cursor-pointer text-sm leading-7 font-normal"
            @click="emit('update:acknowledged', !acknowledged)"
          >
            أُقر بأن جميع البيانات والمستندات المقدمة صحيحة وكاملة، وأتحمل المسؤولية القانونية عن أي
            بيانات غير دقيقة أو مستندات مزوّرة، وفقاً للوائح البنك المركزي اليمني.
          </Label>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
