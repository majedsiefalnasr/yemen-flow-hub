<script setup lang="ts">
import { AlertCircle, Check, FileText } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import type { DocumentType } from '@/types/models'

const props = defineProps<{
  docTypes: DocumentType[]
  uploadedSlugs: string[]
  stageLabel?: string
}>()

const missingRequired = computed(() =>
  props.docTypes.filter(d => d.is_required && !props.uploadedSlugs.includes(d.slug)).length,
)
</script>

<template>
  <div
    v-if="docTypes.length === 0"
    class="rounded-md border p-3 text-xs text-muted-foreground"
  >
    لا توجد قواعد مستندات معرّفة{{ stageLabel ? ` لمرحلة ${stageLabel}` : '' }}.
  </div>

  <div
    v-else
    class="space-y-2"
  >
    <div class="flex items-center justify-between text-xs">
      <span class="text-muted-foreground">
        قواعد المستندات{{ stageLabel ? ` لمرحلة ${stageLabel}` : '' }}
      </span>
      <Badge
        v-if="missingRequired > 0"
        class="border-0 bg-destructive/15 text-destructive"
      >
        <AlertCircle class="ms-1 h-3 w-3" />
        ينقص {{ missingRequired }} مستند مطلوب
      </Badge>
      <Badge
        v-else
        class="border-0 bg-success/15 text-success"
      >
        <Check class="ms-1 h-3 w-3" />
        مكتمل
      </Badge>
    </div>

    <div class="space-y-1.5">
      <div
        v-for="doc in docTypes"
        :key="doc.id"
        :class="cn(
          'flex items-center gap-2 rounded-md border p-2.5 text-sm',
          uploadedSlugs.includes(doc.slug) && 'border-success/30 bg-success/5',
          !uploadedSlugs.includes(doc.slug) && doc.is_required && 'border-destructive/30 bg-destructive/5',
          !uploadedSlugs.includes(doc.slug) && !doc.is_required && 'border-border bg-muted/20',
        )"
      >
        <div
          :class="cn(
            'grid h-7 w-7 place-items-center rounded',
            uploadedSlugs.includes(doc.slug) ? 'bg-success/15 text-success' : 'bg-muted text-muted-foreground',
          )"
        >
          <Check v-if="uploadedSlugs.includes(doc.slug)" class="h-3.5 w-3.5" />
          <FileText v-else class="h-3.5 w-3.5" />
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-xs font-medium">
            {{ doc.name_ar }}
          </div>
        </div>
        <Badge variant="outline" class="text-[10px]">
          {{ doc.is_required ? 'مطلوب' : 'اختياري' }}
        </Badge>
      </div>
    </div>
  </div>
</template>
