<script setup lang="ts">
import { Circle, Clock } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Stepper,
  StepperDescription,
  StepperItem,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from '@/components/ui/stepper'
import type { RequestStageHistory } from '@/types/models'
import { UserRole } from '@/types/enums'
import { STATUS_LABELS, DATA_ENTRY_STATUS_LABELS } from '@/constants/workflow'

const props = withDefaults(
  defineProps<{
    entries: RequestStageHistory[]
    limit?: number
    userRole?: UserRole | null
  }>(),
  {
    limit: 25,
    userRole: null,
  },
)

const visible = computed(() => props.entries.slice(0, props.limit))

const ACTION_LABELS: Record<string, string> = {
  submit: 'تقديم الطلب',
  bank_begin_review: 'بدء مراجعة البنك',
  bank_approve: 'اعتماد البنك',
  bank_reject: 'إعادة الطلب للتعديل',
  bank_return_to_intake: 'إرجاع الطلب للمدخل',
  bank_reject_terminal: 'رفض نهائي من البنك',
  return_to_entry: 'إرجاع الطلب للمدخل',
  bank_return_after_support_reject: 'إرجاع الطلب بعد رفض لجنة المساندة',
  bank_finalize_rejection: 'تثبيت رفض لجنة المساندة',
  support_claim: 'استلام لجنة المساندة للطلب',
  support_release: 'تحرير مطالبة لجنة المساندة',
  support_approve: 'اعتماد لجنة المساندة',
  support_reject: 'رفض لجنة المساندة',
  support_return_to_intake: 'إرجاع الطلب للمدخل من لجنة المساندة',
  move_to_support_queue: 'إحالة الطلب إلى لجنة المساندة',
  move_to_swift_queue: 'إحالة الطلب إلى رفع SWIFT',
  swift_upload: 'رفع وثائق SWIFT',
  open_voting: 'فتح التصويت التنفيذي',
  close_voting: 'إغلاق التصويت التنفيذي',
  finalize_approved: 'اعتماد القرار التنفيذي',
  finalize_rejected: 'رفض القرار التنفيذي',
  issue_customs: 'إصدار تأكيد المصارفة الخارجية',
  complete: 'إكمال معالجة الطلب',
}

function statusLabel(raw: string | null | undefined): string | null {
  if (!raw) return null
  const map = props.userRole === UserRole.DATA_ENTRY ? DATA_ENTRY_STATUS_LABELS : STATUS_LABELS
  return (map as Record<string, string>)[raw] ?? raw
}

function actionLabel(raw: string): string {
  return ACTION_LABELS[raw] ?? 'إجراء مسجل في سير العمل'
}

function entryNotes(entry: RequestStageHistory): string | null {
  const notes = entry.metadata?.notes ?? entry.notes
  return typeof notes === 'string' && notes.trim() ? notes : null
}
</script>

<template>
  <div v-if="visible.length === 0" class="text-muted-foreground p-4 text-sm">
    لا توجد إجراءات مسجلة في سجل سير العمل بعد.
  </div>

  <Stepper
    v-else
    :model-value="visible.length"
    orientation="vertical"
    class="flex w-full flex-col justify-start gap-4"
  >
    <StepperItem
      v-for="(entry, idx) in visible"
      :key="entry.id"
      v-slot="{ state }"
      class="relative flex w-full items-start gap-4"
      :step="idx + 1"
    >
      <StepperSeparator
        v-if="idx < visible.length - 1"
        class="bg-muted group-data-[state=completed]:bg-primary absolute inset-s-[12px] top-[20px] block h-[110%] w-0.5 shrink-0 rounded-full"
      />

      <StepperTrigger as-child>
        <Button
          :variant="state === 'completed' || state === 'active' ? 'default' : 'outline'"
          size="icon"
          class="pointer-events-none z-10 size-6 shrink-0 rounded-full"
        >
          <Circle class="size-3" />
        </Button>
      </StepperTrigger>

      <div class="flex flex-1 flex-col gap-0.5 pt-0.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <StepperTitle class="font-section text-sm leading-5 font-semibold">
            {{ actionLabel(entry.action) }}
          </StepperTitle>
          <div class="text-muted-foreground flex items-center gap-1 text-xs leading-5 tabular-nums">
            <Clock class="h-3 w-3" />
            {{ new Date(entry.created_at).toLocaleString('ar-EG') }}
          </div>
        </div>

        <StepperDescription class="text-muted-foreground text-xs leading-5">
          {{ entry.performed_by?.name ?? 'غير معروف' }}
          <span v-if="entry.from_status && entry.to_status">
            من «{{ statusLabel(entry.from_status) }}» إلى «{{ statusLabel(entry.to_status) }}»
          </span>
        </StepperDescription>

        <div
          v-if="entryNotes(entry)"
          class="bg-muted/40 mt-1.5 rounded px-2 py-1 text-xs leading-5"
        >
          {{ entryNotes(entry) }}
        </div>
      </div>
    </StepperItem>
  </Stepper>
</template>
