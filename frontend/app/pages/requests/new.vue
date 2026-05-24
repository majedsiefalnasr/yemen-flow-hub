<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Check,
  ChevronLeft,
  ChevronRight,
  Eye,
  FileCheck2,
  FileText,
  Save,
  Send,
  ShieldCheck,
  Trash2,
  Upload,
} from 'lucide-vue-next'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'
import { useMerchants } from '@/composables/useMerchants'
import type { Merchant } from '@/types/models'

type FormState = {
  merchant_id: number | null
  goods_description: string
  amount: string
  currency: 'USD' | 'EUR' | 'SAR'
  payment_terms: string
  due_date: string
  notes: string
  supplier_name: string
  origin_country: string
  invoice_number: string
  invoice_date: string
  shipping_port: string
  arrival_port: string
  bl_number: string
  customs_office: string
}

type UploadedDoc = {
  fileName: string
  mime: string
  size: number
  file: File
  previewUrl: string
}

const STEPS = ['بيانات الطلب', 'بيانات المورد والشحنة', 'الوثائق المطلوبة', 'المراجعة والإرسال']

const PORT_OPTIONS = [
  { value: 'ميناء عدن', label: 'ميناء عدن' },
  { value: 'ميناء الحديدة', label: 'ميناء الحديدة' },
  { value: 'ميناء المكلا', label: 'ميناء المكلا' },
]

const GOODS_OPTIONS = [
  { value: 'مواد غذائية', label: 'مواد غذائية' },
  { value: 'أدوية ومستلزمات طبية', label: 'أدوية ومستلزمات طبية' },
  { value: 'مشتقات نفطية', label: 'مشتقات نفطية' },
  { value: 'قطع غيار', label: 'قطع غيار' },
  { value: 'مواد بناء', label: 'مواد بناء' },
  { value: 'آلات ومعدات', label: 'آلات ومعدات' },
]

const COUNTRY_OPTIONS = [
  { value: 'الولايات المتحدة', label: 'الولايات المتحدة' },
  { value: 'الصين', label: 'الصين' },
  { value: 'الهند', label: 'الهند' },
  { value: 'المملكة العربية السعودية', label: 'المملكة العربية السعودية' },
  { value: 'تركيا', label: 'تركيا' },
  { value: 'الإمارات العربية المتحدة', label: 'الإمارات العربية المتحدة' },
]

const INITIAL: FormState = {
  merchant_id: null,
  goods_description: '',
  amount: '',
  currency: 'USD',
  payment_terms: 'LC',
  due_date: '',
  notes: '',
  supplier_name: '',
  origin_country: '',
  invoice_number: '',
  invoice_date: '',
  shipping_port: '',
  arrival_port: '',
  bl_number: '',
  customs_office: '',
}

const authStore = useAuthStore()
const requestsStore = useRequestsStore()
const { fetchMerchants } = useMerchants()
const router = useRouter()
const { notify, error: toastError } = useToast()

const user = computed(() => authStore.user)
const step = ref(0)
const form = reactive<FormState>({ ...INITIAL })
const uploads = ref<Record<string, UploadedDoc>>({})
const preview = ref<{ name: string, mime: string, url: string } | null>(null)
const previewOpen = computed({
  get: () => Boolean(preview.value),
  set: (value: boolean) => {
    if (!value) {
      if (preview.value?.url.startsWith('blob:')) URL.revokeObjectURL(preview.value.url)
      preview.value = null
    }
  },
})
const saving = ref(false)
const merchants = ref<Merchant[]>([])

onMounted(async () => {
  merchants.value = await fetchMerchants()
  if (merchants.value.length > 0 && !form.merchant_id) {
    form.merchant_id = merchants.value[0]!.id
  }
})

const docSpecs = computed(() => {
  const isOilOrMed = form.goods_description === 'مشتقات نفطية' || form.goods_description === 'أدوية ومستلزمات طبية'
  const list = [
    { name: 'الفاتورة الأولية (Proforma Invoice)', required: true },
    { name: 'السجل التجاري', required: true },
    { name: 'البطاقة الضريبية', required: true },
  ]
  if (isOilOrMed) list.push({ name: `الترخيص (${form.goods_description})`, required: true })
  list.push({ name: 'مستندات إضافية', required: false })
  return list
})

const requiredDocsMissing = computed(() =>
  docSpecs.value.filter(doc => doc.required && !uploads.value[doc.name]).map(doc => doc.name),
)

function goNext() { step.value = Math.min(STEPS.length - 1, step.value + 1) }
function goPrev() { step.value = Math.max(0, step.value - 1) }

function inputId(name: string) {
  return `upload-${name.replace(/[^؀-ۿ\w-]+/g, '-')}`
}

function openPicker(name: string) {
  const input = document.getElementById(inputId(name)) as HTMLInputElement | null
  input?.click()
}

function onFilePicked(name: string, event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  if (file.size > 10 * 1024 * 1024) {
    toastError('حجم الملف يتجاوز 10MB')
    target.value = ''
    return
  }

  const previewUrl = URL.createObjectURL(file)
  uploads.value = {
    ...uploads.value,
    [name]: { fileName: file.name, mime: file.type || 'application/pdf', size: file.size, file, previewUrl },
  }
  target.value = ''
  notify(`تم رفع: ${file.name}`)
}

function removeUpload(name: string) {
  const doc = uploads.value[name]
  if (doc?.previewUrl.startsWith('blob:')) URL.revokeObjectURL(doc.previewUrl)
  const next = { ...uploads.value }
  delete next[name]
  uploads.value = next
}

async function persist(asDraft: boolean) {
  if (!user.value) return

  if (!form.merchant_id || !form.amount || !form.goods_description || !form.supplier_name) {
    step.value = 0
    toastError('يرجى إكمال البيانات الأساسية')
    return
  }

  if (!asDraft && requiredDocsMissing.value.length > 0) {
    step.value = 2
    toastError('يرجى إرفاق المستندات المطلوبة')
    return
  }

  saving.value = true
  try {
    await requestsStore.createRequest({
      merchant_id: form.merchant_id!,
      currency: form.currency,
      amount: Number(form.amount) || 0,
      supplier_name: form.supplier_name,
      goods_description: form.goods_description,
      port_of_entry: form.arrival_port,
      notes: form.notes,
      invoice_number: form.invoice_number || undefined,
      invoice_date: form.invoice_date || undefined,
      origin_country: form.origin_country || undefined,
      arrival_port: form.arrival_port || undefined,
      shipping_port: form.shipping_port || undefined,
      customs_office: form.customs_office || undefined,
      bl_number: form.bl_number || undefined,
      payment_terms: form.payment_terms || undefined,
      due_date: form.due_date || undefined,
    })
    notify(asDraft ? 'تم حفظ المسودة' : 'تم تقديم الطلب للمراجعة الداخلية')
    router.push('/requests')
  }
  catch {
    toastError('حدث خطأ أثناء حفظ الطلب')
  }
  finally {
    saving.value = false
  }
}

const selectedMerchantName = computed(() => {
  const m = merchants.value.find(m => m.id === form.merchant_id)
  return m?.name ?? '—'
})

const reviewRows = computed(() => ({
  request: [
    ['نوع الواردات', form.goods_description || '—'],
    ['المستورد', selectedMerchantName.value],
    ['مبلغ التمويل', `${Number(form.amount || 0).toLocaleString('en-US')} ${form.currency}`],
    ['شروط الدفع', form.payment_terms || '—'],
  ],
  shipment: [
    ['المورد', form.supplier_name || '—'],
    ['رقم الفاتورة', form.invoice_number || '—'],
    ['ميناء الوصول', form.arrival_port || '—'],
    ['البلد', form.origin_country || '—'],
  ],
}))

const canCreate = computed(() =>
  user.value?.role === UserRole.DATA_ENTRY || user.value?.role === UserRole.BANK_ADMIN,
)
</script>

<template>
  <div v-if="user && canCreate">
    <PageHeader
      title="تقديم طلب تمويل واردات جديد"
      subtitle="املأ البيانات بدقة وأرفق المستندات المطلوبة"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/' },
        { label: 'الطلبات', to: '/requests' },
        { label: 'طلب جديد' },
      ]"
    />

    <Card class="mb-6 border-0 p-6 shadow">
      <div class="flex items-center justify-between gap-2">
        <div
          v-for="(label, index) in STEPS"
          :key="label"
          class="flex flex-1 items-center"
        >
          <div class="flex flex-col items-center text-center">
            <div
              :class="[
                'grid h-10 w-10 place-items-center rounded-full text-sm font-semibold transition-colors',
                index < step ? 'bg-green-50 text-green-700-foreground' : index === step ? 'bg-blue-600 text-blue-600-foreground ring-4 ring-primary/15' : 'bg-gray-50 text-gray-600',
              ]"
            >
              <Check v-if="index < step" class="h-5 w-5" />
              <span v-else>{{ index + 1 }}</span>
            </div>
            <div
              :class="[
                'mt-2 max-w-[110px] text-xs',
                index === step ? 'font-semibold text-gray-900' : 'text-gray-600',
              ]"
            >
              {{ label }}
            </div>
          </div>
          <div
            v-if="index < STEPS.length - 1"
            :class="['mx-2 h-0.5 flex-1 transition-colors', index < step ? 'bg-green-50' : 'bg-gray-50']"
          />
        </div>
      </div>
    </Card>

    <Card class="border-0 p-6 shadow">
      <!-- Step 0: Request basics -->
      <div v-if="step === 0" class="space-y-6">
        <h3 class="font-semibold">معلومات الطلب الأساسية</h3>
        <div class="grid gap-5 md:grid-cols-2">
          <div class="space-y-2">
            <Label>نوع الواردات</Label>
            <Select v-model="form.goods_description">
              <SelectTrigger><SelectValue placeholder="اختر نوع الواردات" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="opt in GOODS_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>المستورد (التاجر)</Label>
            <Select v-model="form.merchant_id">
              <SelectTrigger>
                <SelectValue :placeholder="merchants.length ? 'اختر التاجر' : 'لا يوجد تجار مسجلون'" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="merchant in merchants" :key="merchant.id" :value="merchant.id">
                  {{ merchant.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="merchants.length === 0" class="text-xs text-gray-600">
              يجب إضافة تجار أولاً من شاشة سجل التجار.
            </p>
          </div>

          <div class="space-y-2">
            <Label>مبلغ التمويل</Label>
            <Input v-model="form.amount" type="number" min="0" />
          </div>

          <div class="space-y-2">
            <Label>العملة</Label>
            <Select v-model="form.currency">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="USD">دولار أمريكي (USD)</SelectItem>
                <SelectItem value="EUR">يورو (EUR)</SelectItem>
                <SelectItem value="SAR">ريال سعودي (SAR)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>شروط الدفع</Label>
            <Select v-model="form.payment_terms">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="LC">اعتماد مستندي L/C</SelectItem>
                <SelectItem value="DP">دفع مقابل مستندات D/P</SelectItem>
                <SelectItem value="TT">حوالة برقية T/T</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>تاريخ الاستحقاق المتوقع</Label>
            <Input v-model="form.due_date" type="date" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>ملاحظات إضافية</Label>
          <Textarea v-model="form.notes" rows="3" />
        </div>
      </div>

      <!-- Step 1: Supplier & shipment -->
      <div v-else-if="step === 1" class="space-y-6">
        <h3 class="font-semibold">بيانات المورد والشحنة</h3>
        <div class="grid gap-5 md:grid-cols-2">
          <div class="space-y-2">
            <Label>اسم المورد</Label>
            <Input v-model="form.supplier_name" />
          </div>

          <div class="space-y-2">
            <Label>بلد المنشأ</Label>
            <Select v-model="form.origin_country">
              <SelectTrigger><SelectValue placeholder="اختر البلد" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="opt in COUNTRY_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>رقم الفاتورة</Label>
            <Input v-model="form.invoice_number" />
          </div>

          <div class="space-y-2">
            <Label>تاريخ الفاتورة</Label>
            <Input v-model="form.invoice_date" type="date" />
          </div>

          <div class="space-y-2">
            <Label>ميناء الشحن</Label>
            <Input v-model="form.shipping_port" />
          </div>

          <div class="space-y-2">
            <Label>ميناء الوصول</Label>
            <Select v-model="form.arrival_port">
              <SelectTrigger><SelectValue placeholder="اختر الميناء" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="opt in PORT_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>رقم بوليصة الشحن</Label>
            <Input v-model="form.bl_number" />
          </div>

          <div class="space-y-2">
            <Label>الجمارك المختصة</Label>
            <Select v-model="form.customs_office">
              <SelectTrigger><SelectValue placeholder="اختر الجمارك" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="جمارك عدن">جمارك عدن</SelectItem>
                <SelectItem value="جمارك الحديدة">جمارك الحديدة</SelectItem>
                <SelectItem value="جمارك المكلا">جمارك المكلا</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>

      <!-- Step 2: Documents -->
      <div v-else-if="step === 2" class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">رفع الوثائق المطلوبة</h3>
          <Badge v-if="requiredDocsMissing.length > 0" class="border-0 bg-amber-50/15 text-amber-600">
            ينقص {{ requiredDocsMissing.length }} مستند
          </Badge>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div
            v-for="doc in docSpecs"
            :key="doc.name"
            :class="[
              'rounded-xl border-2 border-dashed p-5 transition-colors',
              uploads[doc.name] ? 'border-green-200/40 bg-green-50/5' : 'border-gray-200 hover:border-accent/40',
            ]"
          >
            <Input
              :id="inputId(doc.name)"
              type="file"
              accept=".pdf"
              class="hidden"
              @change="onFilePicked(doc.name, $event)"
            />

            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div
                  :class="[
                    'grid h-11 w-11 place-items-center rounded-lg',
                    uploads[doc.name] ? 'bg-green-50/15 text-green-700' : 'bg-gray-50 text-gray-600',
                  ]"
                >
                  <FileCheck2 v-if="uploads[doc.name]" class="h-5 w-5" />
                  <Upload v-else class="h-5 w-5" />
                </div>
                <div>
                  <div class="text-sm font-medium">{{ doc.name }}</div>
                  <div class="text-xs text-gray-600">
                    {{ doc.required ? 'مطلوب' : 'اختياري' }} · PDF فقط (حد أقصى 10MB)
                  </div>
                </div>
              </div>
              <Badge v-if="doc.required" variant="destructive" class="text-[10px]">إلزامي</Badge>
            </div>

            <div
              v-if="uploads[doc.name]"
              class="mt-4 flex items-center justify-between gap-2 border-t border-green-200/20 pt-4 text-xs"
            >
              <div class="flex min-w-0 items-center gap-2">
                <FileText class="h-4 w-4 shrink-0 text-green-700" />
                <span class="truncate font-medium">{{ uploads[doc.name]?.fileName }}</span>
                <Badge variant="secondary" class="gap-1 text-[10px]">
                  <ShieldCheck class="h-3 w-3" />
                  آمن
                </Badge>
              </div>
              <div class="flex gap-1">
                <Button
                  size="icon"
                  variant="ghost"
                  class="h-7 w-7"
                  aria-label="معاينة"
                  @click="preview = uploads[doc.name] ? { name: uploads[doc.name]!.fileName, mime: uploads[doc.name]!.mime, url: uploads[doc.name]!.previewUrl } : null"
                >
                  <Eye class="h-3.5 w-3.5" />
                </Button>
                <Button
                  size="icon"
                  variant="ghost"
                  class="h-7 w-7 text-red-700"
                  aria-label="حذف"
                  @click="removeUpload(doc.name)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>

            <Button
              v-else
              variant="outline"
              size="sm"
              class="mt-4 w-full"
              @click="openPicker(doc.name)"
            >
              <Upload class="ms-1 h-4 w-4" />
              اضغط للرفع
            </Button>
          </div>
        </div>
      </div>

      <!-- Step 3: Review -->
      <div v-else class="space-y-6">
        <h3 class="font-semibold">مراجعة الطلب قبل الإرسال</h3>

        <div class="space-y-5 rounded-xl border bg-gray-50/30 p-6">
          <div>
            <div class="mb-3 border-b pb-2 text-sm font-medium">بيانات الطلب</div>
            <div class="grid gap-x-8 gap-y-2 text-sm md:grid-cols-2">
              <div v-for="[key, value] in reviewRows.request" :key="key" class="flex justify-between gap-4">
                <span class="text-gray-600">{{ key }}</span>
                <span class="font-medium">{{ value }}</span>
              </div>
            </div>
          </div>

          <div>
            <div class="mb-3 border-b pb-2 text-sm font-medium">بيانات المورد والشحنة</div>
            <div class="grid gap-x-8 gap-y-2 text-sm md:grid-cols-2">
              <div v-for="[key, value] in reviewRows.shipment" :key="key" class="flex justify-between gap-4">
                <span class="text-gray-600">{{ key }}</span>
                <span class="font-medium">{{ value }}</span>
              </div>
            </div>
          </div>

          <div>
            <div class="mb-3 border-b pb-2 text-sm font-medium">الوثائق المرفوعة</div>
            <div class="space-y-2 text-sm">
              <div
                v-for="doc in docSpecs"
                :key="doc.name"
                class="flex items-center justify-between rounded-lg border px-3 py-2"
              >
                <span>{{ doc.name }}</span>
                <Badge :variant="uploads[doc.name] ? 'secondary' : 'outline'">
                  {{ uploads[doc.name] ? 'مرفوع' : (doc.required ? 'مطلوب' : 'اختياري') }}
                </Badge>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-start gap-3 rounded-lg border border-info/20 bg-info/5 p-4">
          <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-info" />
          <div class="text-sm">
            <div class="font-medium">إقرار وتعهد</div>
            <p class="mt-1 text-xs leading-relaxed text-gray-600">
              أُقر بأن جميع البيانات والمستندات المقدمة صحيحة وكاملة، وسيتم إخضاع الطلب للتدقيق الآلي للتحقق من الفواتير المكررة والامتثال.
            </p>
          </div>
        </div>
      </div>

      <div class="mt-8 flex justify-between border-t pt-6">
        <Button variant="outline" :disabled="step === 0" @click="goPrev">
          <ChevronRight class="ms-1 h-4 w-4" />
          السابق
        </Button>

        <div class="flex gap-2">
          <Button variant="outline" :disabled="saving" @click="persist(true)">
            <Save class="ms-1 h-4 w-4" />
            حفظ كمسودة
          </Button>
          <Button v-if="step < STEPS.length - 1" @click="goNext">
            التالي
            <ChevronLeft class="me-1 h-4 w-4" />
          </Button>
          <Button v-else :disabled="saving" @click="persist(false)">
            <Send class="ms-1 h-4 w-4" />
            إرسال للمراجعة
          </Button>
        </div>
      </div>
    </Card>

    <Dialog v-model:open="previewOpen">
      <DialogContent class="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{{ preview?.name }}</DialogTitle>
        </DialogHeader>
        <template v-if="preview">
          <iframe
            v-if="preview.mime === 'application/pdf'"
            :src="preview.url"
            :title="preview.name"
            class="h-[70vh] w-full rounded-md border"
          />
          <img
            v-else-if="preview.mime.startsWith('image/')"
            :src="preview.url"
            :alt="preview.name"
            class="max-h-[70vh] w-full rounded-md bg-gray-50 object-contain"
          >
          <div v-else class="p-6 text-center text-sm text-gray-600">
            لا يمكن المعاينة داخل المتصفح.
          </div>
        </template>
      </DialogContent>
    </Dialog>
  </div>

  <div v-else>
    <PageHeader
      title="غير مصرح بإنشاء طلب"
      subtitle="هذه الصفحة متاحة لمُدخِل البيانات أو مسؤول البنك فقط."
    />
    <Card class="border-0 p-6 shadow">
      <Button variant="outline" @click="router.push('/requests')">
        العودة لقائمة الطلبات
      </Button>
    </Card>
  </div>
</template>
