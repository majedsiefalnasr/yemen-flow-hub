<!-- app/components/workflow/EngineDocumentsPanel.vue -->
<script setup lang="ts">
import type { EngineRequestDocument } from '@/types/models'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { FileText, Download, Trash2 } from 'lucide-vue-next'

defineProps<{
  documents: EngineRequestDocument[]
  requestId?: number | null
  canManage: boolean
}>()

const emit = defineEmits<{ upload: [file: File]; remove: [documentId: number] }>()

const { downloadUrl } = useEngineRequestDocuments()

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

function uploaderName(doc: EngineRequestDocument): string {
  return typeof doc.uploaded_by === 'object' ? doc.uploaded_by.name : '—'
}

function formatDate(value: string | null): string {
  return value ? dateFormatter.format(new Date(value)) : '—'
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files?.[0]) {
    emit('upload', input.files[0])
    input.value = ''
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <ul v-if="documents.length" class="flex flex-col divide-y">
      <li v-for="doc in documents" :key="doc.id" class="flex items-center gap-3 py-3">
        <FileText class="text-muted-foreground h-5 w-5 shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium">{{ doc.original_name }}</p>
          <p class="text-muted-foreground text-xs">
            {{ uploaderName(doc) }} · {{ formatDate(doc.created_at) }}
          </p>
        </div>
        <a
          v-if="requestId != null"
          :href="downloadUrl(requestId, doc.id)"
          target="_blank"
          rel="noopener"
        >
          <Button variant="ghost" size="icon" aria-label="تنزيل"
            ><Download class="h-4 w-4"
          /></Button>
        </a>
        <Button
          v-if="canManage"
          variant="ghost"
          size="icon"
          aria-label="حذف"
          @click="emit('remove', doc.id)"
        >
          <Trash2 class="text-destructive h-4 w-4" />
        </Button>
      </li>
    </ul>

    <Empty v-else>
      <EmptyMedia variant="icon"><FileText /></EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد مرفقات</EmptyTitle>
        <EmptyDescription>لم يُرفَق أي مستند بهذا الطلب بعد.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-if="canManage" class="border-t pt-4">
      <Label class="text-muted-foreground mb-1 block text-xs">إرفاق مستند</Label>
      <Input type="file" accept="application/pdf" @change="onFileChange" />
    </div>
  </div>
</template>
