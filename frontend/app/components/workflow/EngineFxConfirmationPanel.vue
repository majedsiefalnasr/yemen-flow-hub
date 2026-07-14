<!-- app/components/workflow/EngineFxConfirmationPanel.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'
import type { CustomsDeclarationSummary, EngineFxPanelCapabilities } from '@/types/models'
import { useEngineFxConfirmation } from '@/composables/useEngineFxConfirmation'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Spinner } from '@/components/ui/spinner'
import { FileCheck2, Download, Upload, Stamp } from 'lucide-vue-next'

const props = defineProps<{
  requestId: number
  capabilities: EngineFxPanelCapabilities
  declaration: CustomsDeclarationSummary | null
}>()

const emit = defineEmits<{ refresh: [] }>()

const { uploading, error, declarationDownloadUrl, signedFxDownloadUrl, uploadSignedFx } =
  useEngineFxConfirmation()

const fileInput = ref<HTMLInputElement | null>(null)

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

const formattedIssuedAt = computed(() =>
  props.declaration?.issued_at ? dateFormatter.format(new Date(props.declaration.issued_at)) : null,
)

const formattedUploadedAt = computed(() =>
  props.declaration?.signed_fx_doc_uploaded_at
    ? dateFormatter.format(new Date(props.declaration.signed_fx_doc_uploaded_at))
    : null,
)

const signedFxStatus = computed(() => {
  if (!props.declaration) return null
  if (props.declaration.has_signed_fx_doc) return 'uploaded'
  return 'pending'
})

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files?.[0]) return
  const file = input.files[0]

  if (file.type !== 'application/pdf') {
    toast.error('يجب أن يكون الملف بصيغة PDF فقط.')
    input.value = ''
    return
  }

  handleUpload(file)
  input.value = ''
}

async function handleUpload(file: File) {
  try {
    await uploadSignedFx(props.requestId, file)
    toast.success('تم رفع الوثيقة الموقعة بنجاح.')
    emit('refresh')
  } catch {
    // Error is already set in the composable ref
  }
}

function downloadDeclaration() {
  window.open(declarationDownloadUrl(props.requestId), '_blank', 'noopener,noreferrer')
}

function downloadSignedFx() {
  window.open(signedFxDownloadUrl(props.requestId), '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <Card class="border-0 shadow" aria-labelledby="fx-panel-heading">
    <CardHeader class="pb-2">
      <CardTitle id="fx-panel-heading" class="flex items-center gap-2 text-sm font-semibold">
        <Stamp class="h-4 w-4" aria-hidden="true" />
        <span>تأكيد المصارفة الخارجية</span>
      </CardTitle>
    </CardHeader>

    <CardContent class="flex flex-col gap-3 pt-0">
      <!-- Declaration info -->
      <template v-if="declaration">
        <div class="flex items-center gap-2.5">
          <FileCheck2 class="text-muted-foreground h-4 w-4 shrink-0" aria-hidden="true" />
          <span class="text-muted-foreground text-xs">رقم الإيصال</span>
          <span class="text-foreground ms-auto truncate text-sm font-medium">
            {{ declaration.declaration_number }}
          </span>
        </div>

        <div v-if="formattedIssuedAt" class="flex items-center gap-2.5">
          <Stamp class="text-muted-foreground h-4 w-4 shrink-0" aria-hidden="true" />
          <span class="text-muted-foreground text-xs">تاريخ الإصدار</span>
          <span class="text-foreground ms-auto text-sm font-medium">{{ formattedIssuedAt }}</span>
        </div>

        <div v-if="declaration.issuer" class="flex items-center gap-2.5">
          <FileCheck2 class="text-muted-foreground h-4 w-4 shrink-0" aria-hidden="true" />
          <span class="text-muted-foreground text-xs">صادر من</span>
          <span class="text-foreground ms-auto truncate text-sm font-medium">
            {{ declaration.issuer.name }}
          </span>
        </div>

        <!-- Signed FX doc status -->
        <div class="flex items-center gap-2.5">
          <FileCheck2 class="text-muted-foreground h-4 w-4 shrink-0" aria-hidden="true" />
          <span class="text-muted-foreground text-xs">الوثيقة الموقعة</span>
          <template v-if="signedFxStatus === 'uploaded'">
            <Badge
              class="ms-auto border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
            >
              مرفوعة
            </Badge>
          </template>
          <template v-else>
            <Badge variant="outline" class="ms-auto">لم تُرفَع بعد</Badge>
          </template>
        </div>

        <p v-if="formattedUploadedAt" class="text-muted-foreground ps-7 text-xs">
          تاريخ الرفع: {{ formattedUploadedAt }}
        </p>
      </template>

      <!-- No declaration yet -->
      <p v-else class="text-muted-foreground text-xs">لم يُصدَر إيصال المصارفة الخارجية بعد.</p>

      <!-- Upload error -->
      <Alert v-if="error" variant="destructive" role="alert">
        <AlertTitle>خطأ في الرفع</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>

      <!-- Actions -->
      <div class="mt-1 flex flex-wrap gap-2">
        <Button
          v-if="capabilities.can_download_declaration"
          variant="outline"
          size="sm"
          @click="downloadDeclaration"
        >
          <Download class="h-4 w-4" aria-hidden="true" />
          <span>تنزيل الإيصال</span>
        </Button>

        <Button
          v-if="capabilities.can_download_signed_fx"
          variant="outline"
          size="sm"
          @click="downloadSignedFx"
        >
          <Download class="h-4 w-4" aria-hidden="true" />
          <span>تنزيل الموقّعة</span>
        </Button>

        <Button
          v-if="capabilities.can_upload_signed_fx"
          variant="outline"
          size="sm"
          :disabled="uploading"
          @click="fileInput?.click()"
        >
          <Spinner v-if="uploading" class="h-4 w-4" aria-hidden="true" />
          <Upload v-else class="h-4 w-4" aria-hidden="true" />
          <span>{{ uploading ? 'جارٍ الرفع…' : 'رفع الوثيقة الموقّعة' }}</span>
        </Button>

        <input
          ref="fileInput"
          type="file"
          accept="application/pdf"
          class="hidden"
          @change="onFileChange"
        />
      </div>
    </CardContent>
  </Card>
</template>
