<script setup lang="ts">
import { CheckCircle2, FileText, Upload } from 'lucide-vue-next'
import type { ImportRequest } from '@/types/models'

const props = defineProps<{
  request: ImportRequest
  uploading?: boolean
}>()

const emit = defineEmits<{
  upload: [file: File, swiftReference: string]
}>()

const file = ref<File | null>(null)
const swiftReference = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

const hasSwift = computed(() => props.request.status === 'SWIFT_UPLOADED')

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const next = target.files?.[0] ?? null
  if (!next) { file.value = null; return }
  const isPdf = next.type === 'application/pdf' || next.name.toLowerCase().endsWith('.pdf')
  if (!isPdf) {
    alert('يجب اختيار ملف PDF فقط.')
    target.value = ''
    file.value = null
    return
  }
  file.value = next
}

function submit() {
  if (!file.value) return
  emit('upload', file.value, swiftReference.value)
  file.value = null
  swiftReference.value = ''
  if (fileInput.value) fileInput.value.value = ''
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="hasSwift"
      class="flex items-start gap-3 rounded-xl border border-green-200/30 bg-green-50/5 p-4"
    >
      <CheckCircle2 class="mt-0.5 h-5 w-5 text-green-700" />
      <div class="text-sm">
        <div class="font-semibold text-green-700">
          تم رفع وثيقة SWIFT
        </div>
      </div>
    </div>

    <div
      v-else
      class="space-y-4"
    >
      <div
        class="cursor-pointer rounded-xl border-2 border-dashed border-border p-8 text-center hover:border-primary/40 hover:bg-primary/5"
        @click="fileInput?.click()"
      >
        <FileText class="mx-auto mb-3 h-10 w-10 text-muted-foreground/50" />
        <div class="text-sm font-medium">
          {{ file ? file.name : 'اختر ملف PDF للسويفت' }}
        </div>
        <div class="mt-1 text-xs text-muted-foreground">
          PDF · حد أقصى 10MB
        </div>
        <input
          ref="fileInput"
          type="file"
          accept=".pdf,application/pdf"
          class="hidden"
          @change="onFileChange"
        >
      </div>

      <Input
        v-model="swiftReference"
        placeholder="رقم مرجع السويفت (اختياري)"
        dir="ltr"
      />

      <Button
        :disabled="!file || uploading"
        class="w-full"
        @click="submit"
      >
        <Upload class="ms-2 h-4 w-4" />
        {{ uploading ? 'جارٍ الرفع...' : 'رفع وثيقة SWIFT' }}
      </Button>
    </div>
  </div>
</template>
