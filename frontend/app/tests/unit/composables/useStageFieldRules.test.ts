import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { StageFieldRule } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useStageFieldRules } = await import('../../../composables/useStageFieldRules')

function makeRule(overrides: Partial<StageFieldRule> = {}): StageFieldRule {
  return {
    id: 1,
    stage_id: 5,
    field_id: 10,
    is_visible: true,
    is_editable: true,
    is_required: false,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

describe('useStageFieldRules', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches rules for a stage', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeRule()] })
    const { rules, fetchRules } = useStageFieldRules()
    await fetchRules(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-stages/5/field-rules')
    expect(rules.value).toHaveLength(1)
  })

  it('adds a new rule when none exists for the field', async () => {
    mockGet.mockResolvedValueOnce({ data: [] })
    mockPost.mockResolvedValueOnce({ data: makeRule({ field_id: 10 }) })

    const { rules, fetchRules, setRule } = useStageFieldRules()
    await fetchRules(5)
    await setRule(5, { field_id: 10, is_required: true })

    expect(rules.value).toHaveLength(1)
  })

  it('replaces an existing rule for the same field', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeRule({ field_id: 10, version: 1 })] })
    mockPost.mockResolvedValueOnce({
      data: makeRule({ field_id: 10, is_required: true, version: 2 }),
    })

    const { rules, fetchRules, setRule } = useStageFieldRules()
    await fetchRules(5)
    await setRule(5, { field_id: 10, is_required: true })

    expect(rules.value).toHaveLength(1)
    expect(rules.value[0]?.is_required).toBe(true)
    expect(rules.value[0]?.version).toBe(2)
  })

  it('records an error on fetch failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { error, rules, fetchRules } = useStageFieldRules()
    await fetchRules(5)

    expect(error.value).toBeTruthy()
    expect(rules.value).toHaveLength(0)
  })
})
