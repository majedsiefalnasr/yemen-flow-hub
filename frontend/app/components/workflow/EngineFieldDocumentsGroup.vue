<!-- app/components/workflow/EngineFieldDocumentsGroup.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type {
  ResolvedFieldGroup,
  ResolvedFieldDefinition,
  EngineRequestDocument,
} from '@/types/models'
import EngineDocumentsPanel from '@/components/workflow/EngineDocumentsPanel.vue'

// Renders one EngineDocumentsPanel per FILE field in `group`, so a group with
// multiple file fields (e.g. "فاتورة" + "عقد") never mixes their documents —
// each panel is filtered strictly by EngineRequestDocument.field_id. Any
// request document whose field_id references a field this group used to have
// but no longer does (a deleted/renamed field, or data from an older schema
// version) still renders, read-only, under a separate "orphan" panel instead
// of silently disappearing.
//
// field_id === null is NOT treated as orphaned here: it's a first-class,
// intentional "general document, not tied to any field" state supported by
// the upload API (useEngineRequestDocuments.upload's fieldId param is
// nullable), not stale data — a document like that belongs wherever the app
// already surfaces general/untagged documents, which is outside this
// component's scope (it only knows about `group`'s own fields).
const props = defineProps<{
  group: ResolvedFieldGroup
  documents: EngineRequestDocument[]
  requestId: number
  canManage: boolean
}>()

const emit = defineEmits<{
  upload: [fieldId: number, file: File]
  remove: [documentId: number]
}>()

const visibleFields = computed(() => props.group.fields.filter((f) => f.is_visible))

function docsForField(field: ResolvedFieldDefinition): EngineRequestDocument[] {
  return props.documents.filter((d) => d.field_id === field.id)
}

function canManageField(field: ResolvedFieldDefinition): boolean {
  return props.canManage && field.is_editable
}

const orphanedDocuments = computed(() => {
  // All of the group's fields, not just visible ones — a document tied to a
  // field that's merely hidden (is_visible: false) belongs to that field,
  // not to "orphaned," even though no panel currently renders it.
  const knownFieldIds = new Set(props.group.fields.map((f) => f.id))
  return props.documents.filter((d) => d.field_id !== null && !knownFieldIds.has(d.field_id))
})

function onUpload(fieldId: number, file: File) {
  emit('upload', fieldId, file)
}

function onRemove(documentId: number) {
  emit('remove', documentId)
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <div v-for="field in visibleFields" :key="field.id" class="flex flex-col gap-2">
      <h4 class="text-foreground text-sm font-semibold">{{ field.label }}</h4>
      <EngineDocumentsPanel
        :documents="docsForField(field)"
        :request-id="requestId"
        :can-manage="canManageField(field)"
        @upload="(file) => onUpload(field.id, file)"
        @remove="onRemove"
      />
    </div>

    <div v-if="orphanedDocuments.length" class="flex flex-col gap-2 border-t pt-4">
      <h4 class="text-muted-foreground text-sm font-semibold">مرفقات أخرى</h4>
      <EngineDocumentsPanel
        :documents="orphanedDocuments"
        :request-id="requestId"
        :can-manage="false"
      />
    </div>
  </div>
</template>
