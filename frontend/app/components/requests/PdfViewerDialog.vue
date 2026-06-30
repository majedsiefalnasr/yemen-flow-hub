<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { Download, Printer, Loader2, AlertCircle } from 'lucide-vue-next'
import { Button } from '../ui/button'
import { Alert, AlertDescription } from '../ui/alert'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '../ui/dialog'

const props = defineProps<{
  open: boolean
  title: string
  description?: string
  // Lazily fetch the PDF blob when the dialog opens (policy-guarded backend call)
  fetchPdf: () => Promise<Blob>
  downloadFilename: string
}>()

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const loading = ref(false)
const error = ref('')
const objectUrl = ref<string | null>(null)
const currentBlob = ref<Blob | null>(null)

function revoke() {
  if (objectUrl.value) {
    URL.revokeObjectURL(objectUrl.value)
    objectUrl.value = null
  }
  currentBlob.value = null
}

async function load() {
  loading.value = true
  error.value = ''
  revoke()
  try {
    const blob = await props.fetchPdf()
    const pdfBlob =
      blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
    currentBlob.value = pdfBlob
    objectUrl.value = URL.createObjectURL(pdfBlob)
  } catch {
    error.value = 'تعذّر تحميل الوثيقة. أعد المحاولة.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) load()
    else revoke()
  },
)

onBeforeUnmount(revoke)

function handleDownload() {
  if (!currentBlob.value) return
  const url = URL.createObjectURL(currentBlob.value)
  const a = document.createElement('a')
  a.href = url
  a.download = props.downloadFilename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 5_000)
}

function handlePrint() {
  if (!objectUrl.value) return
  // Print via a hidden iframe so the embedded PDF is sent to the printer
  const frame = document.createElement('iframe')
  frame.style.position = 'fixed'
  frame.style.right = '0'
  frame.style.bottom = '0'
  frame.style.width = '0'
  frame.style.height = '0'
  frame.style.border = '0'
  frame.src = objectUrl.value
  frame.onload = () => {
    try {
      frame.contentWindow?.focus()
      frame.contentWindow?.print()
    } catch {
      // Fallback: open in a new tab if the embedded print is blocked
      if (objectUrl.value) window.open(objectUrl.value, '_blank', 'noopener')
    }
    // Leave the frame mounted long enough for the print dialog to grab it
    setTimeout(() => frame.remove(), 60_000)
  }
  document.body.appendChild(frame)
}

function setOpen(value: boolean) {
  emit('update:open', value)
}
</script>

<template>
  <Dialog :open="open" @update:open="setOpen">
    <!-- Wide A4 viewer — override shadcn's default max-w-lg with explicit style -->
    <DialogContent
      class="gap-0 overflow-hidden p-0"
      style="width: min(95vw, 1100px); max-width: min(95vw, 1100px)"
    >
      <DialogHeader class="border-border border-b px-5 pt-5 pb-3">
        <DialogTitle class="text-base font-semibold">{{ title }}</DialogTitle>
        <DialogDescription :class="description ? 'text-xs' : 'sr-only'">
          {{ description || 'عارض PDF للوثيقة مع خيارات الطباعة والتنزيل.' }}
        </DialogDescription>
      </DialogHeader>

      <!-- PDF viewport: tall enough to show a full A4 page -->
      <div class="bg-muted/40 flex items-center justify-center" style="height: min(82vh, 1050px)">
        <div v-if="loading" class="text-muted-foreground flex flex-col items-center gap-2">
          <Loader2 class="h-6 w-6 animate-spin" aria-hidden="true" />
          <span class="text-sm">جارٍ تحميل الوثيقة...</span>
        </div>

        <Alert v-else-if="error" variant="destructive" class="m-5 w-auto" role="alert">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <iframe
          v-else-if="objectUrl"
          :src="objectUrl"
          title="عارض الوثيقة"
          class="h-full w-full border-0"
          style="aspect-ratio: 210 / 297"
        />
      </div>

      <!-- Actions -->
      <div class="border-border flex items-center justify-end gap-2 border-t px-5 py-3">
        <Button
          variant="outline"
          size="sm"
          :disabled="loading || !!error || !objectUrl"
          @click="handlePrint"
        >
          <Printer class="me-1 h-4 w-4" aria-hidden="true" />
          طباعة
        </Button>
        <Button size="sm" :disabled="loading || !!error || !currentBlob" @click="handleDownload">
          <Download class="me-1 h-4 w-4" aria-hidden="true" />
          تنزيل
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
