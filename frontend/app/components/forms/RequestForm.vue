<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import type { RequestFormData, Merchant } from '../../types/models'
import { Currency } from '../../types/enums'
import { useMerchants } from '../../composables/useMerchants'
import { requestFormSchema } from '../../schemas/requestForm.schema'
import { Button } from '../ui/button'
import { Input } from '../ui/input'
import { Label } from '../ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select'
import { Textarea } from '../ui/textarea'
import { Alert, AlertDescription } from '../ui/alert'
import { AlertTriangle, RotateCcw } from 'lucide-vue-next'

const props = defineProps<{
  initialValues?: Partial<RequestFormData>
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [data: RequestFormData]
}>()

const { handleSubmit, errors, setValues, values } = useForm({
  validationSchema: toTypedSchema(requestFormSchema),
  initialValues: {
    merchant_id: props.initialValues?.merchant_id ?? undefined,
    currency: (props.initialValues?.currency as Currency | undefined) ?? Currency.USD,
    amount: props.initialValues?.amount ?? undefined,
    goods_type: props.initialValues?.goods_type ?? '',
    payment_terms: (props.initialValues?.payment_terms ?? '') as '' | 'LC' | 'TT' | 'CAD',
    due_date: props.initialValues?.due_date ?? '',
    supplier_name: props.initialValues?.supplier_name ?? '',
    goods_description: props.initialValues?.goods_description ?? '',
    port_of_entry: props.initialValues?.port_of_entry ?? '',
    notes: props.initialValues?.notes ?? '',
    invoice_number: props.initialValues?.invoice_number ?? '',
    invoice_date: props.initialValues?.invoice_date ?? '',
    origin_country: props.initialValues?.origin_country ?? '',
    arrival_port: props.initialValues?.arrival_port ?? '',
    shipping_port: props.initialValues?.shipping_port ?? '',
    customs_office: props.initialValues?.customs_office ?? '',
    bl_number: props.initialValues?.bl_number ?? '',
  },
})

// Reactively hydrate form when initialValues arrive asynchronously (edit mode)
watch(
  () => props.initialValues,
  (next) => {
    if (!next) return
    setValues({
      merchant_id: next.merchant_id ?? undefined,
      currency: (next.currency as Currency | undefined) ?? Currency.USD,
      amount: next.amount ?? undefined,
      goods_type: next.goods_type ?? '',
      payment_terms: (next.payment_terms ?? '') as '' | 'LC' | 'TT' | 'CAD',
      due_date: next.due_date ?? '',
      supplier_name: next.supplier_name ?? '',
      goods_description: next.goods_description ?? '',
      port_of_entry: next.port_of_entry ?? '',
      notes: next.notes ?? '',
      invoice_number: next.invoice_number ?? '',
      invoice_date: next.invoice_date ?? '',
      origin_country: next.origin_country ?? '',
      arrival_port: next.arrival_port ?? '',
      shipping_port: next.shipping_port ?? '',
      customs_office: next.customs_office ?? '',
      bl_number: next.bl_number ?? '',
    })
  },
  { deep: true },
)

const merchants = ref<Merchant[]>([])
const merchantsLoading = ref(false)
const merchantsError = ref(false)

const { fetchMerchants } = useMerchants()

function onCurrencyChange(event: Event) {
  setValues({ currency: (event.target as HTMLSelectElement).value as Currency })
}

async function loadMerchants() {
  merchantsLoading.value = true
  merchantsError.value = false
  try {
    merchants.value = await fetchMerchants()
  }
  catch {
    merchantsError.value = true
  }
  finally {
    merchantsLoading.value = false
  }
}

onMounted(loadMerchants)

const onSubmit = handleSubmit((v) => {
  emit('submit', {
    merchant_id: v.merchant_id as number,
      currency: v.currency,
      amount: v.amount as number,
      goods_type: v.goods_type || null,
      payment_terms: v.payment_terms || null,
      due_date: v.due_date || null,
      supplier_name: v.supplier_name,
      goods_description: v.goods_description,
      port_of_entry: v.port_of_entry,
      notes: v.notes ?? '',
      invoice_number: v.invoice_number || null,
      invoice_date: v.invoice_date || null,
      origin_country: v.origin_country || null,
      arrival_port: v.arrival_port || null,
      shipping_port: v.shipping_port || null,
      customs_office: v.customs_office || null,
      bl_number: v.bl_number || null,
    })
  })
</script>

<template>
  <form class="flex flex-col gap-6" dir="rtl" novalidate @submit.prevent="onSubmit">

    <!-- Importer / Merchant Section -->
    <section class="border border-border rounded-md p-6 flex flex-col gap-6">
      <h2 class="text-base font-semibold">بيانات المستورد</h2>

      <div class="flex flex-col gap-2">
        <Label for="merchant-select" class="text-sm">
          المستورد
          <span class="text-red-700">*</span>
        </Label>

        <!-- Merchant fetch error with retry -->
        <Alert v-if="merchantsError" variant="destructive" class="mb-2">
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription>
            <div class="flex items-center justify-between gap-2">
              <span>تعذّر تحميل قائمة التجار.</span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                @click="loadMerchants"
                class="whitespace-nowrap"
              >
                <RotateCcw class="h-3 w-3 me-1" />
                إعادة المحاولة
              </Button>
            </div>
          </AlertDescription>
        </Alert>

        <template v-else>
          <Select
            :model-value="String(values.merchant_id ?? '')"
            :disabled="merchantsLoading || loading"
            @update:model-value="(val) => setValues({ merchant_id: val ? Number(val) : undefined })"
          >
            <SelectTrigger id="merchant-select" :class="{ 'border-destructive': errors.merchant_id }">
              <SelectValue :placeholder="merchantsLoading ? 'جاري التحميل...' : 'اختر المستورد...'" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="m in merchants" :key="m.id" :value="String(m.id)">
                {{ m.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.merchant_id" class="text-sm text-red-700">{{ errors.merchant_id }}</p>
        </template>
      </div>
    </section>

    <!-- Supplier / Exporter Section -->
    <section class="border border-border rounded-md p-6 flex flex-col gap-6">
      <h2 class="text-base font-semibold">بيانات المورد / المُصدِّر</h2>

      <div class="flex flex-col gap-2">
        <Label for="supplier-name" class="text-sm">
          اسم المورد
          <span class="text-red-700">*</span>
        </Label>
        <Input
          id="supplier-name"
          :value="values.supplier_name"
          type="text"
          :disabled="loading"
          :class="{ 'border-destructive': errors.supplier_name }"
          placeholder="أدخل اسم المورد"
          @input="(e) => setValues({ supplier_name: (e.target as HTMLInputElement).value })"
        />
        <p v-if="errors.supplier_name" class="text-sm text-red-700">{{ errors.supplier_name }}</p>
      </div>
    </section>

    <!-- Goods Section -->
    <section class="border border-border rounded-md p-6 flex flex-col gap-6">
      <h2 class="text-base font-semibold">بيانات البضائع</h2>

      <div class="flex flex-col gap-2">
        <Label for="goods-description" class="text-sm">
          وصف البضائع
          <span class="text-red-700">*</span>
        </Label>
        <Textarea
          id="goods-description"
          :value="values.goods_description"
          :disabled="loading"
          :class="{ 'border-destructive': errors.goods_description }"
          placeholder="أدخل وصفاً تفصيلياً للبضائع"
          rows="3"
          @input="(e) => setValues({ goods_description: (e.target as HTMLTextAreaElement).value })"
        />
        <p v-if="errors.goods_description" class="text-sm text-red-700">{{ errors.goods_description }}</p>
      </div>

      <div class="flex flex-col gap-2">
        <Label for="port-of-entry" class="text-sm">
          ميناء الدخول
          <span class="text-red-700">*</span>
        </Label>
        <Input
          id="port-of-entry"
          :value="values.port_of_entry"
          type="text"
          :disabled="loading"
          :class="{ 'border-destructive': errors.port_of_entry }"
          placeholder="مثال: ميناء عدن"
          @input="(e) => setValues({ port_of_entry: (e.target as HTMLInputElement).value })"
        />
        <p v-if="errors.port_of_entry" class="text-sm text-red-700">{{ errors.port_of_entry }}</p>
      </div>
    </section>

    <!-- Financial Section -->
    <section class="border border-border rounded-md p-6 flex flex-col gap-6">
      <h2 class="text-base font-semibold">البيانات المالية</h2>

      <div class="flex flex-col gap-6 sm:flex-row">
        <div class="flex flex-col gap-2 flex-1">
          <Label for="amount" class="text-sm">
            المبلغ
            <span class="text-red-700">*</span>
          </Label>
          <Input
            id="amount"
            :value="values.amount"
            type="number"
            min="0.01"
            step="0.01"
            :disabled="loading"
            :class="{ 'border-destructive': errors.amount }"
            placeholder="0.00"
            @input="(e) => setValues({ amount: Number((e.target as HTMLInputElement).value) || undefined })"
          />
          <p v-if="errors.amount" class="text-sm text-red-700">{{ errors.amount }}</p>
        </div>

        <div class="flex flex-col gap-2 flex-1">
          <Label for="currency" class="text-sm">
            العملة
            <span class="text-red-700">*</span>
          </Label>
          <Select
            :model-value="values.currency"
            :disabled="loading"
            @update:model-value="(val) => setValues({ currency: val as Currency })"
          >
            <SelectTrigger id="currency" :class="{ 'border-destructive': errors.currency }">
              <SelectValue :placeholder="values.currency" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="c in Object.values(Currency)" :key="c" :value="c">
                {{ c }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.currency" class="text-sm text-red-700">{{ errors.currency }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-6 sm:flex-row">
        <div class="flex flex-col gap-2 flex-1">
          <Label for="goods-type" class="text-sm">نوع البضائع</Label>
          <Input
            id="goods-type"
            :value="values.goods_type"
            type="text"
            :disabled="loading"
            :class="{ 'border-destructive': errors.goods_type }"
            placeholder="مثال: مواد غذائية"
            @input="(e) => setValues({ goods_type: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.goods_type" class="text-sm text-red-700">{{ errors.goods_type }}</p>
        </div>

        <div class="flex flex-col gap-2 flex-1">
          <Label for="payment-terms" class="text-sm">شروط الدفع</Label>
          <Select
            :model-value="values.payment_terms || ''"
            :disabled="loading"
            @update:model-value="(val) => setValues({ payment_terms: (val || '') as '' | 'LC' | 'TT' | 'CAD' })"
          >
            <SelectTrigger id="payment-terms" :class="{ 'border-destructive': errors.payment_terms }">
              <SelectValue placeholder="اختر شروط الدفع" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">بدون شروط</SelectItem>
              <SelectItem value="LC">LC</SelectItem>
              <SelectItem value="TT">TT</SelectItem>
              <SelectItem value="CAD">CAD</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.payment_terms" class="text-sm text-red-700">{{ errors.payment_terms }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <Label for="due-date" class="text-sm">تاريخ الاستحقاق</Label>
        <Input
          id="due-date"
          :value="values.due_date"
          type="date"
          :disabled="loading"
          :class="{ 'border-destructive': errors.due_date }"
          @input="(e) => setValues({ due_date: (e.target as HTMLInputElement).value })"
        />
        <p v-if="errors.due_date" class="text-sm text-red-700">{{ errors.due_date }}</p>
      </div>
    </section>

    <section class="border border-border rounded-md p-6 flex flex-col gap-6">
      <h2 class="text-base font-semibold">بيانات الشحنة والفاتورة</h2>

      <div class="flex flex-col gap-6 sm:flex-row">
        <div class="flex flex-col gap-2 flex-1">
          <Label for="invoice-number" class="text-sm">رقم الفاتورة</Label>
          <Input
            id="invoice-number"
            :value="values.invoice_number"
            type="text"
            :disabled="loading"
            :class="{ 'border-destructive': errors.invoice_number }"
            @input="(e) => setValues({ invoice_number: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.invoice_number" class="text-sm text-red-700">{{ errors.invoice_number }}</p>
        </div>

        <div class="flex flex-col gap-2 flex-1">
          <Label for="invoice-date" class="text-sm">تاريخ الفاتورة</Label>
          <Input
            id="invoice-date"
            :value="values.invoice_date"
            type="date"
            :disabled="loading"
            :class="{ 'border-destructive': errors.invoice_date }"
            @input="(e) => setValues({ invoice_date: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.invoice_date" class="text-sm text-red-700">{{ errors.invoice_date }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-6 sm:flex-row">
        <div class="flex flex-col gap-2 flex-1">
          <Label for="origin-country" class="text-sm">بلد المنشأ</Label>
          <Input
            id="origin-country"
            :value="values.origin_country"
            type="text"
            :disabled="loading"
            :class="{ 'border-destructive': errors.origin_country }"
            @input="(e) => setValues({ origin_country: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.origin_country" class="text-sm text-red-700">{{ errors.origin_country }}</p>
        </div>

        <div class="flex flex-col gap-2 flex-1">
          <Label for="arrival-port" class="text-sm">ميناء الوصول</Label>
          <Input
            id="arrival-port"
            :value="values.arrival_port"
            type="text"
            :disabled="loading"
            :class="{ 'border-destructive': errors.arrival_port }"
            @input="(e) => setValues({ arrival_port: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.arrival_port" class="text-sm text-red-700">{{ errors.arrival_port }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-6 sm:flex-row">
        <div class="flex flex-col gap-2 flex-1">
          <Label for="shipping-port" class="text-sm">ميناء الشحن</Label>
          <Input
            id="shipping-port"
            :value="values.shipping_port"
            type="text"
            :disabled="loading"
            :class="{ 'border-destructive': errors.shipping_port }"
            @input="(e) => setValues({ shipping_port: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.shipping_port" class="text-sm text-red-700">{{ errors.shipping_port }}</p>
        </div>

        <div class="flex flex-col gap-2 flex-1">
          <Label for="customs-office" class="text-sm">المكتب الجمركي</Label>
          <Input
            id="customs-office"
            :value="values.customs_office"
            type="text"
            :disabled="loading"
            :class="{ 'border-destructive': errors.customs_office }"
            @input="(e) => setValues({ customs_office: (e.target as HTMLInputElement).value })"
          />
          <p v-if="errors.customs_office" class="text-sm text-red-700">{{ errors.customs_office }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <Label for="bl-number" class="text-sm">رقم بوليصة الشحن</Label>
        <Input
          id="bl-number"
          :value="values.bl_number"
          type="text"
          :disabled="loading"
          :class="{ 'border-destructive': errors.bl_number }"
          @input="(e) => setValues({ bl_number: (e.target as HTMLInputElement).value })"
        />
        <p v-if="errors.bl_number" class="text-sm text-red-700">{{ errors.bl_number }}</p>
      </div>
    </section>

    <!-- Notes Section -->
    <section class="border border-border rounded-md p-6 flex flex-col gap-6">
      <h2 class="text-base font-semibold">ملاحظات</h2>

      <div class="flex flex-col gap-2">
        <Label for="notes" class="text-sm">ملاحظات إضافية</Label>
        <Textarea
          id="notes"
          :value="values.notes"
          :disabled="loading"
          placeholder="أي ملاحظات إضافية (اختياري)"
          rows="3"
          @input="(e) => setValues({ notes: (e.target as HTMLTextAreaElement).value })"
        />
      </div>
    </section>

    <!-- Actions -->
    <div class="flex justify-start gap-3">
      <slot name="actions">
        <Button type="submit" :disabled="loading">
          {{ loading ? 'جاري الحفظ...' : 'حفظ الطلب' }}
        </Button>
      </slot>
    </div>
  </form>
</template>

