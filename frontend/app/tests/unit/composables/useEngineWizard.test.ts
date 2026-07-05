import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useEngineWizard } from '@/composables/useEngineWizard'
import type { ResolvedFieldGroup } from '@/types/models'

const group = (id: number, sort_order: number): ResolvedFieldGroup => ({
  id,
  name: `g${id}`,
  label: `مجموعة ${id}`,
  sort_order,
  fields: [],
})

describe('useEngineWizard', () => {
  it('orders groups by sort_order and exposes the current one', () => {
    const groups = ref([group(2, 20), group(1, 10)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit: vi.fn() })
    expect(w.totalSteps.value).toBe(2)
    expect(w.currentGroup.value?.id).toBe(1)
    expect(w.isFirst.value).toBe(true)
    expect(w.isLast.value).toBe(false)
  })

  it('next saves the draft then advances', async () => {
    const saveDraft = vi.fn().mockResolvedValue(undefined)
    const groups = ref([group(1, 10), group(2, 20)])
    const w = useEngineWizard(groups, { saveDraft, submit: vi.fn() })
    await w.next({ a: 1 })
    expect(saveDraft).toHaveBeenCalledWith({ a: 1 })
    expect(w.stepIndex.value).toBe(1)
    expect(w.isLast.value).toBe(true)
  })

  it('saves the draft before advancing to the next step, not after', async () => {
    const saveDraftCalls: number[] = []
    let stepAtSaveTime = -1

    const groups = ref([group(1, 10), group(2, 20)])

    const wizard = useEngineWizard(groups, {
      saveDraft: async (_data) => {
        stepAtSaveTime = wizard.stepIndex.value
        saveDraftCalls.push(stepAtSaveTime)
      },
      submit: async () => {},
    })

    await wizard.next({})

    expect(saveDraftCalls).toEqual([0])
    expect(stepAtSaveTime).toBe(0) // saveDraft ran while still on step 0, before stepIndex advanced to 1
    expect(wizard.stepIndex.value).toBe(1)
  })

  it('back does not go below zero', () => {
    const groups = ref([group(1, 10)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit: vi.fn() })
    w.back()
    expect(w.stepIndex.value).toBe(0)
  })

  it('finish calls submit with the data', async () => {
    const submit = vi.fn().mockResolvedValue(undefined)
    const groups = ref([group(1, 10)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit })
    await w.finish({ b: 2 })
    expect(submit).toHaveBeenCalledWith({ b: 2 })
  })

  it('extraSteps lets the index advance past the last group into a review step', async () => {
    const groups = ref([group(1, 10), group(2, 20)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit: vi.fn(), extraSteps: 1 })
    // Two groups + one review step: not last until index 2.
    await w.next({})
    expect(w.stepIndex.value).toBe(1)
    expect(w.isLast.value).toBe(false)
    await w.next({})
    expect(w.stepIndex.value).toBe(2)
    expect(w.isLast.value).toBe(true)
    // Cannot advance past the review step.
    await w.next({})
    expect(w.stepIndex.value).toBe(2)
  })
})
