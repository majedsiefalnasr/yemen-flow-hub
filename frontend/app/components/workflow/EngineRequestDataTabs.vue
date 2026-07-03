<!-- app/components/workflow/EngineRequestDataTabs.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { ResolvedFieldGroup, EngineRequestDocument } from '@/types/models'
import { formatFieldValue } from '@/composables/useEngineFieldDisplay'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import EngineDocumentsPanel from '@/components/workflow/EngineDocumentsPanel.vue'

// Read-only "بيانات الطلب" view: one tab per field group, values rendered in a
// two-column definition list. Groups made entirely of FILE fields render the
// (read-only) documents panel instead of a value list.
const props = defineProps<{
  fieldGroups: ResolvedFieldGroup[]
  data: Record<string, unknown>
  documents: EngineRequestDocument[]
  requestId: number
}>()

const orderedGroups = computed(() =>
  [...(props.fieldGroups ?? [])].sort((a, b) => a.sort_order - b.sort_order),
)

function isDocumentGroup(group: ResolvedFieldGroup): boolean {
  const visible = group.fields.filter((f) => f.is_visible)
  return visible.length > 0 && visible.every((f) => f.type === 'FILE')
}

function visibleValueFields(group: ResolvedFieldGroup) {
  return group.fields.filter((f) => f.is_visible && f.type !== 'FILE')
}

const defaultTab = computed(() => orderedGroups.value[0]?.name ?? '')
</script>

<template>
  <Tabs v-if="orderedGroups.length" :default-value="defaultTab" dir="rtl">
    <TabsList class="flex-wrap">
      <TabsTrigger v-for="group in orderedGroups" :key="group.id" :value="group.name">
        {{ group.label }}
      </TabsTrigger>
    </TabsList>

    <TabsContent v-for="group in orderedGroups" :key="group.id" :value="group.name" class="mt-4">
      <EngineDocumentsPanel
        v-if="isDocumentGroup(group)"
        :documents="documents"
        :request-id="requestId"
        :can-manage="false"
      />
      <dl v-else class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
        <div
          v-for="field in visibleValueFields(group)"
          :key="field.id"
          class="flex flex-col gap-0.5"
        >
          <dt class="text-muted-foreground text-xs">{{ field.label }}</dt>
          <dd class="text-foreground text-sm font-medium break-words">
            {{ formatFieldValue(field, data[field.key]) }}
          </dd>
        </div>
      </dl>
    </TabsContent>
  </Tabs>
  <p v-else class="text-muted-foreground text-sm">لا توجد بيانات لعرضها.</p>
</template>
