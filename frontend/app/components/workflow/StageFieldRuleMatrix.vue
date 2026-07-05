<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import type {
  FieldDefinition,
  StageFieldRule,
  WorkflowStage,
  WorkflowVersion,
} from '@/types/models'
import { SlidersHorizontal } from 'lucide-vue-next'
import { Checkbox } from '@/components/ui/checkbox'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useStageFieldRules } from '@/composables/useStageFieldRules'
import { useWorkflowFields } from '@/composables/useWorkflowFields'

const props = defineProps<{ stage: WorkflowStage; version: WorkflowVersion }>()

const { rules, error, fetchRules, setRule } = useStageFieldRules()
const { groups, fetchGroups } = useWorkflowFields()

const editable = props.version.state === 'DRAFT'
const pendingFieldIds = ref<Set<number>>(new Set())

const fields = computed<FieldDefinition[]>(() => groups.value.flatMap((group) => group.fields))

function ruleFor(fieldId: number): StageFieldRule | undefined {
  return rules.value.find((r) => r.field_id === fieldId)
}

function flag(fieldId: number, key: 'is_visible' | 'is_editable' | 'is_required'): boolean {
  const rule = ruleFor(fieldId)
  if (rule) return rule[key]
  // Defaults when no rule exists yet: visible + editable on, required off.
  return key !== 'is_required'
}

function isPending(fieldId: number): boolean {
  return pendingFieldIds.value.has(fieldId)
}

async function toggle(
  field: FieldDefinition,
  key: 'is_visible' | 'is_editable' | 'is_required',
  value: boolean,
) {
  if (!editable || isPending(field.id)) return
  pendingFieldIds.value.add(field.id)
  const current = ruleFor(field.id)
  try {
    await setRule(props.stage.id, {
      field_id: field.id,
      is_visible: key === 'is_visible' ? value : (current?.is_visible ?? true),
      is_editable: key === 'is_editable' ? value : (current?.is_editable ?? true),
      is_required: key === 'is_required' ? value : (current?.is_required ?? false),
    })
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ القاعدة'))
  } finally {
    pendingFieldIds.value.delete(field.id)
  }
}

onMounted(() => {
  fetchRules(props.stage.id)
  fetchGroups(props.version.id)
})
</script>

<template>
  <div class="space-y-3">
    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <Empty v-else-if="fields.length === 0" class="py-6">
      <EmptyMedia variant="icon">
        <SlidersHorizontal />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد حقول</EmptyTitle>
        <EmptyDescription>عرّف الحقول أولاً لضبط قواعدها لكل مرحلة.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="border-border overflow-hidden rounded-md border">
      <Table
        class="[&_td]:py-3.5 [&_td:first-child]:ps-4 [&_td:last-child]:pe-4 [&_th:first-child]:ps-4 [&_th:last-child]:pe-4"
      >
        <TableHeader>
          <TableRow class="bg-muted/50 hover:bg-muted/50">
            <TableHead class="text-right">الحقل</TableHead>
            <TableHead class="text-center">ظاهر</TableHead>
            <TableHead class="text-center">قابل للتعديل</TableHead>
            <TableHead class="text-center">مطلوب</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="field in fields" :key="field.id" class="even:bg-muted/30">
            <TableCell>
              <div class="flex flex-col">
                <span class="font-medium">{{ field.label }}</span>
                <span class="text-muted-foreground font-mono text-xs">{{ field.key }}</span>
              </div>
            </TableCell>
            <TableCell>
              <div class="flex justify-center">
                <Checkbox
                  :model-value="flag(field.id, 'is_visible')"
                  :disabled="!editable || isPending(field.id)"
                  :aria-label="`ظاهر ${field.key}`"
                  @update:model-value="
                    (v: boolean | 'indeterminate') => toggle(field, 'is_visible', v === true)
                  "
                />
              </div>
            </TableCell>
            <TableCell>
              <div class="flex justify-center">
                <Checkbox
                  :model-value="flag(field.id, 'is_editable')"
                  :disabled="!editable || isPending(field.id)"
                  :aria-label="`قابل للتعديل ${field.key}`"
                  @update:model-value="
                    (v: boolean | 'indeterminate') => toggle(field, 'is_editable', v === true)
                  "
                />
              </div>
            </TableCell>
            <TableCell>
              <div class="flex justify-center">
                <Checkbox
                  :model-value="flag(field.id, 'is_required')"
                  :disabled="!editable || isPending(field.id)"
                  :aria-label="`مطلوب ${field.key}`"
                  @update:model-value="
                    (v: boolean | 'indeterminate') => toggle(field, 'is_required', v === true)
                  "
                />
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
