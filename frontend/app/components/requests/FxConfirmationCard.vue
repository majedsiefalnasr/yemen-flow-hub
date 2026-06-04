<script setup lang="ts">
import { computed, ref } from 'vue'
import { AlertTriangle, CheckCircle2, FileDown, Loader2, Upload, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import type { ImportRequest } from '../../types/models'
import { RequestStatus } from '../../types/enums'
import { useRequests } from '../../composables/useRequests'
import { useRequestsStore } from '../../stores/requests.store'
import { Alert, AlertDescription } from '../ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '../ui/alert-dialog'
import { Button } from '../ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card'

const props = defineProps<{ request: ImportRequest }>()
const emit = defineEmits<{ 'action-completed': [] }>()

const requestsStore = useRequestsStore()
const { downloadFxConfirmationTemplate } = useRequests()

const signedFile = ref<File | null>(null)
const dragOver = ref(false)
const fileError = ref('')
const downloadError = ref('')
const uploadError = ref('')

const MAX_MB = 10

const canIssue = computed(
  () =>
    props.request.status === RequestStatus.FX_CONFIRMATION_PENDING ||
    (signedFile.value !== null && requestsStore.signedFxUploaded),
)

function validateFile(file: File): string | null {
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
  if (!isPdf) return 'يجب أن يكون الملف بصيغة PDF فقط'
  if (file.size > MAX_MB * 1024 * 1024) return `حجم الملف يتجاوز ${MAX_MB}MB`
  return null
}

function handleFile(file: File | null): void {
  fileError.value = ''
  uploadError.value = ''
  if (!file) return
  const error = validateFile(file)
  if (error) {
    fileError.value = error
    return
  }
  signedFile.value = file
}

function onInputChange(event: Event): void {
  handleFile((event.target as HTMLInputElement).files?.[0] ?? null)
}

function onDrop(event: DragEvent): void {
  dragOver.value = false
  event.preventDefault()
  handleFile(event.dataTransfer?.files?.[0] ?? null)
}

async function handleDownloadTemplate(): Promise<void> {
  downloadError.value = ''
  try {
    const blob = await downloadFxConfirmationTemplate(props.request.id)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `fx-confirmation-template-${props.request.reference_number}.pdf`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  } catch {
    downloadError.value = 'تعذر تحميل النموذج. أعد المحاولة.'
  }
}

async function handleUpload(): Promise<void> {
  if (!signedFile.value) return
  uploadError.value = ''
  try {
    await requestsStore.uploadSignedFxDoc(props.request.id, signedFile.value)
    toast.success('تم رفع الوثيقة الموقعة بنجاح. يمكنك الآن إصدار التأكيد.')
    emit('action-completed')
  } catch (error: any) {
    uploadError.value = error instanceof Error ? error.message : 'تعذر رفع الوثيقة.'
  }
}

async function handleIssue(): Promise<void> {
  try {
    await requestsStore.issueCustomsDeclaration(props.request.id)
    emit('action-completed')
  } catch (error: any) {
    toast.error(error instanceof Error ? error.message : 'تعذر إصدار التأكيد.')
  }
}
</script>

<template>
  <Card
    class="border-border border shadow-sm"
    role="region"
    aria-label="إصدار وثيقة تأكيد المصارفة الخارجية"
  >
    <CardHeader class="pb-2">
      <CardTitle class="font-heading text-base leading-snug font-semibold">
        إصدار وثيقة تأكيد مصارفة / تغطية خارجية
      </CardTitle>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="space-y-2">
        <div class="max-w-[65ch] space-y-1">
          <p class="font-section text-muted-foreground text-xs leading-5 font-medium">الخطوة 1</p>
          <p class="text-foreground text-sm leading-6 font-semibold">تحميل النموذج النظامي</p>
          <p class="text-muted-foreground text-sm leading-6">
            حمّل النموذج المعبأ بالبيانات، اطبعه، اختمه ووقعه، ثم امسحه ضوئيا بصيغة PDF.
          </p>
        </div>
        <Button variant="outline" size="sm" @click="handleDownloadTemplate">
          <FileDown class="me-1 h-4 w-4" />
          تحميل النموذج المعبأ
        </Button>
        <p v-if="downloadError" class="text-xs leading-5 text-[var(--severity-red)]">
          {{ downloadError }}
        </p>
      </div>

      <div class="bg-border h-px" />

      <div class="space-y-2">
        <div class="max-w-[65ch] space-y-1">
          <p class="font-section text-muted-foreground text-xs leading-5 font-medium">الخطوة 2</p>
          <p class="text-foreground text-sm leading-6 font-semibold">رفع الوثيقة الموقعة</p>
          <p class="text-muted-foreground text-sm leading-6">
            ارفع الوثيقة بعد الختم والتوقيع، PDF بحجم لا يتجاوز
            <span class="tabular-nums">{{ MAX_MB }}MB</span>.
          </p>
        </div>

        <div
          v-if="!signedFile"
          class="relative min-h-28 cursor-pointer rounded-lg border-2 border-dashed p-4 transition-colors"
          :class="{
            'border-primary bg-primary/10': dragOver,
            'border-[var(--severity-red)] bg-[var(--severity-red)]/10': fileError,
            'border-border bg-muted/40': !dragOver && !fileError,
          }"
          @dragover.prevent="dragOver = true"
          @dragleave="dragOver = false"
          @drop="onDrop"
        >
          <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <Upload class="text-muted-foreground h-6 w-6" />
            <p class="text-muted-foreground text-sm leading-6">اسحب الملف هنا أو</p>
            <label>
              <Button type="button" variant="outline" size="sm" as-child>
                <span>اضغط للاختيار</span>
              </Button>
              <input type="file" accept=".pdf" class="sr-only" @change="onInputChange" />
            </label>
          </div>
          <p v-if="fileError" class="mt-2 text-center text-xs leading-5 text-[var(--severity-red)]">
            {{ fileError }}
          </p>
        </div>

        <div
          v-else
          class="flex items-center justify-between rounded-lg border bg-[var(--severity-green)]/10 p-3"
        >
          <div class="flex min-w-0 items-center gap-2">
            <CheckCircle2 class="h-4 w-4 text-[var(--severity-green)]" />
            <span class="min-w-0 text-sm leading-6 font-medium break-all text-[var(--success)]">{{
              signedFile.name
            }}</span>
          </div>
          <Button
            variant="ghost"
            size="icon"
            class="text-muted-foreground h-6 w-6"
            @click="signedFile = null"
          >
            <X class="h-4 w-4" />
          </Button>
        </div>

        <Button
          v-if="signedFile && !requestsStore.signedFxUploaded"
          size="sm"
          :disabled="requestsStore.uploadingSignedFx"
          @click="handleUpload"
        >
          <Loader2 v-if="requestsStore.uploadingSignedFx" class="me-1 h-4 w-4 animate-spin" />
          {{ requestsStore.uploadingSignedFx ? 'جار الرفع...' : 'رفع الوثيقة' }}
        </Button>

        <Alert v-if="uploadError" class="border-[var(--severity-red)] bg-[var(--severity-red)]/10">
          <AlertTriangle class="h-4 w-4 text-[var(--severity-red)]" />
          <AlertDescription class="text-sm leading-6 text-[var(--severity-red)]">{{
            uploadError
          }}</AlertDescription>
        </Alert>

        <div
          v-if="request.status === RequestStatus.FX_CONFIRMATION_PENDING && !signedFile"
          class="flex items-center gap-2 text-sm leading-6 text-[var(--success)]"
        >
          <CheckCircle2 class="h-4 w-4" />
          تم رفع الوثيقة الموقعة في جلسة سابقة. يمكنك الآن الإصدار.
        </div>
      </div>

      <div class="bg-border h-px" />

      <div class="space-y-2">
        <div class="max-w-[65ch] space-y-1">
          <p class="font-section text-muted-foreground text-xs leading-5 font-medium">الخطوة 3</p>
          <p class="text-foreground text-sm leading-6 font-semibold">إصدار التأكيد النهائي</p>
          <p class="text-muted-foreground text-sm leading-6">
            بعد رفع الوثيقة الموقعة، أصدر التأكيد النهائي. هذا الإجراء لا يمكن التراجع عنه.
          </p>
        </div>
        <AlertDialog>
          <AlertDialogTrigger as-child>
            <Button :disabled="!canIssue || requestsStore.issuingCustoms">
              <Loader2 v-if="requestsStore.issuingCustoms" class="me-1 h-4 w-4 animate-spin" />
              {{
                requestsStore.issuingCustoms
                  ? 'جار الإصدار...'
                  : 'إصدار وثيقة تأكيد المصارفة الخارجية'
              }}
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>تأكيد إصدار وثيقة المصارفة الخارجية</AlertDialogTitle>
              <AlertDialogDescription>
                سيتم إصدار وثيقة تأكيد المصارفة الخارجية وإتمام معالجة الطلب نهائيا. هذا الإجراء لا
                يمكن التراجع عنه.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>إلغاء</AlertDialogCancel>
              <AlertDialogAction :disabled="requestsStore.issuingCustoms" @click="handleIssue">
                <Loader2 v-if="requestsStore.issuingCustoms" class="me-1 h-4 w-4 animate-spin" />
                {{ requestsStore.issuingCustoms ? 'جار الإصدار...' : 'تأكيد الإصدار' }}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </CardContent>
  </Card>
</template>
