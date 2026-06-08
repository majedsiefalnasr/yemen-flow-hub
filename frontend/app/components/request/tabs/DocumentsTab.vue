<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { CheckCircle2, Eye, FileText, RefreshCw, Trash2, Upload } from 'lucide-vue-next'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useRequests } from '@/composables/useRequests'
import type { RequestDocument } from '@/types/models'

interface DocumentSlot {
  id: string
  labelAr: string
  required: boolean
  documentType: string
}

const DOCUMENT_SLOTS: DocumentSlot[] = [
  {
    id: 'proforma_invoice',
    labelAr: 'الفاتورة المبدئية (Proforma Invoice)',
    required: true,
    documentType: 'proforma_invoice',
  },
  {
    id: 'commercial_register',
    labelAr: 'السجل التجاري (Commercial Registry)',
    required: true,
    documentType: 'commercial_register',
  },
  {
    id: 'tax_card',
    labelAr: 'البطاقة الضريبية (Tax Card)',
    required: true,
    documentType: 'tax_card',
  },
  {
    id: 'bill_of_lading',
    labelAr: 'بوليصة الشحن (Bill of Lading)',
    required: true,
    documentType: 'bill_of_lading',
  },
  {
    id: 'certificate_of_origin',
    labelAr: 'شهادة المنشأ (Certificate of Origin)',
    required: true,
    documentType: 'certificate_of_origin',
  },
  {
    id: 'letter_of_credit',
    labelAr: 'اعتماد مستندي (Letter of Credit)',
    required: false,
    documentType: 'letter_of_credit',
  },
  {
    id: 'sales_contract',
    labelAr: 'عقد البيع (Sales Contract)',
    required: false,
    documentType: 'sales_contract',
  },
  {
    id: 'commercial_invoice',
    labelAr: 'فاتورة تجارية (Commercial Invoice)',
    required: false,
    documentType: 'commercial_invoice',
  },
  {
    id: 'packing_list',
    labelAr: 'قائمة التعبئة (Packing List)',
    required: false,
    documentType: 'packing_list',
  },
  {
    id: 'import_license',
    labelAr: 'رخصة استيراد (Import License)',
    required: false,
    documentType: 'import_license',
  },
  {
    id: 'conformity_certificate',
    labelAr: 'شهادة مطابقة (Conformity Certificate)',
    required: false,
    documentType: 'conformity_certificate',
  },
  {
    id: 'sector_license',
    labelAr: 'رخصة قطاعية (Sector License)',
    required: false,
    documentType: 'sector_license',
  },
  {
    id: 'inspection_report',
    labelAr: 'تقرير المعاينة (Inspection Report)',
    required: false,
    documentType: 'inspection_report',
  },
  {
    id: 'additional_supporting_document',
    labelAr: 'مستند داعم إضافي (Additional Supporting Document)',
    required: false,
    documentType: 'additional_supporting_document',
  },
]

const props = defineProps<{
  requestId: number | null
}>()

const emit = defineEmits<{
  completeness: [complete: boolean]
}>()

const { deleteDocument, downloadDocument, fetchRequestDocuments, uploadDocument } = useRequests()
const documents = ref<RequestDocument[]>([])
const uploadErrors = ref<Record<string, string>>({})
const uploadingSlot = ref<string | null>(null)
const locked = ref(false)

const documentsBySlot = computed(() => {
  const map = new Map<string, RequestDocument>()
  for (const document of documents.value) {
    if (document.document_sub_type) map.set(document.document_sub_type, document)
  }
  return map
})

const missingMandatoryCount = computed(
  () =>
    DOCUMENT_SLOTS.filter((slot) => slot.required && !documentsBySlot.value.has(slot.documentType))
      .length,
)

const mandatoryComplete = computed(() => missingMandatoryCount.value === 0)

watch(mandatoryComplete, (complete) => emit('completeness', complete), { immediate: true })

onMounted(async () => {
  if (props.requestId) documents.value = await fetchRequestDocuments(props.requestId)
})

function formatBytes(bytes: number): string {
  const mb = bytes / (1024 * 1024)
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`
}

function validateFile(file: File): string | null {
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
  if (!isPdf) return 'صيغة الملف غير مدعومة — يجب أن تكون PDF فقط'
  if (file.size > 10 * 1024 * 1024) return 'حجم الملف يتجاوز الحد الأقصى (10 ميغابايت)'
  return null
}

async function onInputChange(slot: DocumentSlot, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !props.requestId) return

  const validationError = validateFile(file)
  if (validationError) {
    uploadErrors.value = { ...uploadErrors.value, [slot.id]: validationError }
    return
  }

  uploadErrors.value = { ...uploadErrors.value, [slot.id]: '' }
  uploadingSlot.value = slot.id
  try {
    const uploaded = await uploadDocument(props.requestId, file, slot.documentType)
    documents.value = [
      ...documents.value.filter((document) => document.document_sub_type !== slot.documentType),
      uploaded,
    ]
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number }; statusCode?: number }).response?.status
    if (status === 403 || (err as { statusCode?: number }).statusCode === 403) {
      locked.value = true
      return
    }
    uploadErrors.value = {
      ...uploadErrors.value,
      [slot.id]: 'تعذّر رفع الملف. أعد المحاولة.',
    }
  } finally {
    uploadingSlot.value = null
  }
}

function triggerInput(slot: DocumentSlot) {
  document.getElementById(`document-slot-${slot.id}`)?.click()
}

async function previewDocument(slot: DocumentSlot) {
  const document = documentsBySlot.value.get(slot.documentType)
  if (!document) return
  const blob = await downloadDocument(document.id)
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener,noreferrer')
  window.setTimeout(() => URL.revokeObjectURL(url), 30_000)
}

async function removeDocument(slot: DocumentSlot) {
  const document = documentsBySlot.value.get(slot.documentType)
  if (!document) return
  await deleteDocument(document.id)
  documents.value = documents.value.filter((item) => item.id !== document.id)
}
</script>

<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h3 class="text-base font-semibold">الوثائق المطلوبة</h3>
        <p class="text-muted-foreground text-sm">
          ارفع ملفات PDF فقط، بحجم أقصى 10 ميغابايت للملف.
        </p>
      </div>
      <Badge v-if="mandatoryComplete" class="bg-[var(--severity-green)] text-white">مكتمل</Badge>
      <Badge v-else variant="secondary">ناقص: {{ missingMandatoryCount }} مستندات إلزامية</Badge>
    </div>

    <Alert v-if="locked" variant="destructive">
      <AlertDescription>حالة الطلب لا تسمح برفع وثائق إضافية حالياً.</AlertDescription>
    </Alert>

    <Alert v-if="!requestId">
      <AlertDescription>احفظ الطلب كمسودة أولاً لتفعيل رفع الوثائق.</AlertDescription>
    </Alert>

    <div class="grid gap-4 md:grid-cols-2">
      <Card v-for="slot in DOCUMENT_SLOTS" :key="slot.id" class="border-border">
        <CardHeader class="space-y-2">
          <div class="flex items-start justify-between gap-3">
            <CardTitle class="text-sm">{{ slot.labelAr }}</CardTitle>
            <Badge :variant="slot.required ? 'destructive' : 'secondary'">
              {{ slot.required ? 'إلزامي' : 'اختياري' }}
            </Badge>
          </div>
        </CardHeader>
        <CardContent class="grid gap-3">
          <input
            :id="`document-slot-${slot.id}`"
            class="sr-only"
            type="file"
            accept=".pdf,application/pdf"
            :disabled="locked || !requestId || uploadingSlot === slot.id"
            @change="onInputChange(slot, $event)"
          />

          <template v-if="documentsBySlot.get(slot.documentType)">
            <div class="border-border bg-muted/30 rounded-lg border p-3">
              <div class="flex items-start gap-3">
                <FileText class="mt-1 h-5 w-5 text-[var(--severity-green)]" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">
                    {{ documentsBySlot.get(slot.documentType)?.original_filename }}
                  </p>
                  <p class="text-muted-foreground text-xs">
                    {{ formatBytes(documentsBySlot.get(slot.documentType)?.size_bytes ?? 0) }}
                  </p>
                </div>
                <Badge variant="secondary" class="gap-1">
                  <CheckCircle2 class="h-3 w-3" />
                  تم التحقق
                </Badge>
              </div>
            </div>
            <div class="flex gap-2">
              <Button type="button" variant="outline" size="sm" @click="previewDocument(slot)">
                <Eye class="me-1 h-4 w-4" />
                معاينة
              </Button>
              <Button type="button" variant="outline" size="sm" @click="removeDocument(slot)">
                <Trash2 class="me-1 h-4 w-4" />
                إزالة
              </Button>
            </div>
          </template>

          <template v-else>
            <Button
              type="button"
              variant="outline"
              :disabled="locked || !requestId || uploadingSlot === slot.id"
              @click="triggerInput(slot)"
            >
              <RefreshCw v-if="uploadErrors[slot.id]" class="me-2 h-4 w-4" />
              <Upload v-else class="me-2 h-4 w-4" />
              {{ uploadErrors[slot.id] ? 'إعادة المحاولة' : 'رفع ملف PDF' }}
            </Button>
          </template>

          <p v-if="uploadErrors[slot.id]" class="text-xs text-[var(--severity-red)]">
            {{ uploadErrors[slot.id] }}
          </p>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
