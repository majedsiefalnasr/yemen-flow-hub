<script setup lang="ts">
import { computed, ref } from 'vue'
import { CheckCircle2, Download, Eye, FileText, Lock, Upload, X } from 'lucide-vue-next'
import type { ImportRequest } from '@/types/models'

interface UploadedDocMeta {
  file: File
  checksum: string
}

const props = defineProps<{
  request: ImportRequest
  uploading?: boolean
}>()

const emit = defineEmits<{
  upload: [payload: { swiftReference: string; swiftFile: File; fxRequestFile: File }]
}>()

const swiftReference = ref('')
const swiftDoc = ref<UploadedDocMeta | null>(null)
const fxRequestDoc = ref<UploadedDocMeta | null>(null)
const errorMessage = ref('')

const swiftInput = ref<HTMLInputElement | null>(null)
const fxInput = ref<HTMLInputElement | null>(null)

const referencePatternWarning = computed(() => {
  if (!swiftReference.value.trim()) return ''
  const looksLikeSwiftRef = /^[A-Za-z0-9\-_/]{8,64}$/.test(swiftReference.value.trim())
  return looksLikeSwiftRef ? '' : 'تنسيق مرجع السويفت غير معتاد، لكن يمكن المتابعة.'
})

const disabledReason = computed(() => {
  if (!swiftReference.value.trim()) return 'أدخل رقم مرجع السويفت أولاً'
  if (!swiftDoc.value) return 'أكمل رفع وثيقة السويفت قبل التسليم'
  if (!fxRequestDoc.value) return 'أكمل رفع طلب تأكيد المصارفة قبل التسليم'
  return ''
})

const canSubmit = computed(() => disabledReason.value.length === 0)

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

function isPdf(file: File): boolean {
  return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
}

async function computeChecksum(file: File): Promise<string> {
  const data = await file.arrayBuffer()
  const digest = await crypto.subtle.digest('SHA-256', data)
  return Array.from(new Uint8Array(digest))
    .map(b => b.toString(16).padStart(2, '0'))
    .join('')
}

async function assignFile(kind: 'swift' | 'fx', event: Event): Promise<void> {
  errorMessage.value = ''
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  if (!isPdf(file)) {
    errorMessage.value = 'صيغة الملف غير مدعومة. يُسمح بملفات PDF فقط.'
    input.value = ''
    return
  }

  if (file.size > 10 * 1024 * 1024) {
    errorMessage.value = 'حجم الملف يتجاوز الحد الأقصى (10MB).'
    input.value = ''
    return
  }

  const checksum = await computeChecksum(file)
  const payload = { file, checksum }
  if (kind === 'swift') swiftDoc.value = payload
  else fxRequestDoc.value = payload
}

function removeFile(kind: 'swift' | 'fx'): void {
  if (kind === 'swift') {
    swiftDoc.value = null
    if (swiftInput.value) swiftInput.value.value = ''
    return
  }

  fxRequestDoc.value = null
  if (fxInput.value) fxInput.value.value = ''
}

function previewFile(file: File): void {
  const url = URL.createObjectURL(file)
  window.open(url, '_blank', 'noopener,noreferrer')
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function submit(): void {
  errorMessage.value = ''
  if (!canSubmit.value || !swiftDoc.value || !fxRequestDoc.value) return

  emit('upload', {
    swiftReference: swiftReference.value.trim(),
    swiftFile: swiftDoc.value.file,
    fxRequestFile: fxRequestDoc.value.file,
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-xl border border-border bg-muted/20 p-3 text-xs text-muted-foreground">
      <div class="flex items-center gap-2">
        <Lock class="h-3.5 w-3.5" />
        <span>يجب إكمال البيانات الثلاثة قبل التسليم</span>
      </div>
    </div>

    <section class="space-y-2">
      <label class="text-sm font-medium">
        رقم مرجع السويفت (UETR / Message Reference)
        <span class="text-destructive">*</span>
      </label>
      <Input
        v-model="swiftReference"
        placeholder="مثال: UETR-2026-ABC123456789"
      />
      <p v-if="referencePatternWarning" class="text-xs text-amber-600">{{ referencePatternWarning }}</p>
      <p class="text-xs text-muted-foreground">أدخل المرجع الصادر من نظام SWIFT بالبنك.</p>
    </section>

    <section class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold">وثيقة السويفت</h3>
      </div>
      <p class="text-xs text-muted-foreground">نسخة PDF من رسالة MT103 / MT202.</p>
      <div class="rounded-xl border-2 border-dashed border-border p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium">{{ swiftDoc?.file.name ?? 'اسحب الملف هنا أو اضغط للاختيار' }}</p>
            <p v-if="swiftDoc" class="mt-1 text-xs text-muted-foreground">
              {{ formatFileSize(swiftDoc.file.size) }} • SHA-256: {{ swiftDoc.checksum.slice(0, 12) }}...
            </p>
            <p v-else class="mt-1 text-xs text-muted-foreground">PDF فقط، الحد الأقصى 10MB</p>
          </div>
          <div class="flex items-center gap-2">
            <Button type="button" variant="outline" size="sm" @click="swiftInput?.click()">
              <Upload class="ms-1 h-4 w-4" />
              اختر ملف
            </Button>
            <Button v-if="swiftDoc" type="button" variant="outline" size="icon" @click="previewFile(swiftDoc.file)">
              <Eye class="h-4 w-4" />
            </Button>
            <Button v-if="swiftDoc" type="button" variant="outline" size="icon" @click="removeFile('swift')">
              <X class="h-4 w-4" />
            </Button>
          </div>
        </div>
        <input
          ref="swiftInput"
          type="file"
          accept=".pdf,application/pdf"
          class="hidden"
          @change="assignFile('swift', $event)"
        >
      </div>
    </section>

    <section class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold">طلب تأكيد المصارفة الخارجية</h3>
        <Button type="button" variant="outline" size="sm" disabled>
          <Download class="ms-1 h-4 w-4" />
          تحميل النموذج الفارغ
        </Button>
      </div>
      <p class="text-xs text-muted-foreground">النموذج الرسمي المعبأ والمختوم من البنك (PDF).</p>
      <div class="rounded-xl border-2 border-dashed border-border p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium">{{ fxRequestDoc?.file.name ?? 'اسحب الملف هنا أو اضغط للاختيار' }}</p>
            <p v-if="fxRequestDoc" class="mt-1 text-xs text-muted-foreground">
              {{ formatFileSize(fxRequestDoc.file.size) }} • SHA-256: {{ fxRequestDoc.checksum.slice(0, 12) }}...
            </p>
            <p v-else class="mt-1 text-xs text-muted-foreground">PDF فقط، الحد الأقصى 10MB</p>
          </div>
          <div class="flex items-center gap-2">
            <Button type="button" variant="outline" size="sm" @click="fxInput?.click()">
              <FileText class="ms-1 h-4 w-4" />
              اختر ملف
            </Button>
            <Button v-if="fxRequestDoc" type="button" variant="outline" size="icon" @click="previewFile(fxRequestDoc.file)">
              <Eye class="h-4 w-4" />
            </Button>
            <Button v-if="fxRequestDoc" type="button" variant="outline" size="icon" @click="removeFile('fx')">
              <X class="h-4 w-4" />
            </Button>
          </div>
        </div>
        <input
          ref="fxInput"
          type="file"
          accept=".pdf,application/pdf"
          class="hidden"
          @change="assignFile('fx', $event)"
        >
      </div>
    </section>

    <div v-if="errorMessage" class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
      {{ errorMessage }}
    </div>

    <div class="space-y-2">
      <Button
        :disabled="!canSubmit || uploading"
        class="w-full bg-info text-white hover:bg-info/90"
        @click="submit"
      >
        <CheckCircle2 class="ms-1 h-4 w-4" />
        {{ uploading ? 'جارٍ التسليم...' : 'تسليم وثائق السويفت' }}
      </Button>
      <p v-if="!canSubmit" class="text-xs text-muted-foreground">{{ disabledReason }}</p>
    </div>
  </div>
</template>
