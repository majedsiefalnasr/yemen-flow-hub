import { computed, ref, type ComputedRef, type Ref } from 'vue'
import type { ResolvedFieldGroup } from '@/types/models'

export interface WizardCallbacks {
  saveDraft: (data: Record<string, unknown>) => Promise<void>
  submit: (data: Record<string, unknown>) => Promise<void>
  /**
   * Extra non-group steps that follow the field-group steps (e.g. a review step).
   * The step index may advance this many positions past the last group.
   */
  extraSteps?: number
}

export interface EngineWizard {
  stepIndex: Ref<number>
  totalSteps: ComputedRef<number>
  currentGroup: ComputedRef<ResolvedFieldGroup | null>
  isFirst: ComputedRef<boolean>
  isLast: ComputedRef<boolean>
  busy: Ref<boolean>
  next: (data: Record<string, unknown>) => Promise<void>
  back: () => void
  finish: (data: Record<string, unknown>) => Promise<void>
}

export function useEngineWizard(
  groups: Ref<ResolvedFieldGroup[]>,
  cb: WizardCallbacks,
): EngineWizard {
  const stepIndex = ref(0)
  const busy = ref(false)
  const extraSteps = cb.extraSteps ?? 0

  const ordered = computed(() => [...groups.value].sort((a, b) => a.sort_order - b.sort_order))
  const totalSteps = computed(() => ordered.value.length)
  const lastIndex = computed(() => totalSteps.value - 1 + extraSteps)
  const currentGroup = computed(() => ordered.value[stepIndex.value] ?? null)
  const isFirst = computed(() => stepIndex.value === 0)
  const isLast = computed(() => stepIndex.value >= lastIndex.value)

  async function next(data: Record<string, unknown>) {
    busy.value = true
    try {
      await cb.saveDraft(data)
      if (stepIndex.value < lastIndex.value) stepIndex.value += 1
    } finally {
      busy.value = false
    }
  }

  function back() {
    if (stepIndex.value > 0) stepIndex.value -= 1
  }

  async function finish(data: Record<string, unknown>) {
    busy.value = true
    try {
      await cb.submit(data)
    } finally {
      busy.value = false
    }
  }

  return { stepIndex, totalSteps, currentGroup, isFirst, isLast, busy, next, back, finish }
}
