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
    const w = useEngineWizard(groups, { submit: vi.fn() })
    expect(w.totalSteps.value).toBe(2)
    expect(w.currentGroup.value?.id).toBe(1)
    expect(w.isFirst.value).toBe(true)
    expect(w.isLast.value).toBe(false)
  })

  it('next advances to the next step (no server draft save between steps)', async () => {
    const groups = ref([group(1, 10), group(2, 20)])
    const w = useEngineWizard(groups, { submit: vi.fn() })
    await w.next({ a: 1 })
    expect(w.stepIndex.value).toBe(1)
    expect(w.isLast.value).toBe(true)
  })

  it('back does not go below zero', () => {
    const groups = ref([group(1, 10)])
    const w = useEngineWizard(groups, { submit: vi.fn() })
    w.back()
    expect(w.stepIndex.value).toBe(0)
  })

  it('finish calls submit with the data', async () => {
    const submit = vi.fn().mockResolvedValue(undefined)
    const groups = ref([group(1, 10)])
    const w = useEngineWizard(groups, { submit })
    await w.finish({ b: 2 })
    expect(submit).toHaveBeenCalledWith({ b: 2 })
  })

  it('extraSteps lets the index advance past the last group into a review step', async () => {
    const groups = ref([group(1, 10), group(2, 20)])
    const w = useEngineWizard(groups, { submit: vi.fn(), extraSteps: 1 })
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
