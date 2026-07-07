import { describe, expect, it } from 'vitest'
import { resolveSubmitTransition } from '@/utils/resolveSubmitTransition'
import type { WorkflowGraphEdge } from '@/types/models'

describe('resolveSubmitTransition', () => {
  it('prefers is_default_submit over first edge', () => {
    const edges = [
      { id: 1, from_stage_id: 10, is_default_submit: false },
      { id: 2, from_stage_id: 10, is_default_submit: true },
    ] as WorkflowGraphEdge[]
    expect(resolveSubmitTransition(edges, 10)?.id).toBe(2)
  })

  it('falls back to sole outgoing edge', () => {
    const edges = [{ id: 3, from_stage_id: 10, is_default_submit: false }] as WorkflowGraphEdge[]
    expect(resolveSubmitTransition(edges, 10)?.id).toBe(3)
  })

  it('returns null when multiple outgoing edges and none flagged', () => {
    const edges = [
      { id: 1, from_stage_id: 10, is_default_submit: false },
      { id: 2, from_stage_id: 10, is_default_submit: false },
    ] as WorkflowGraphEdge[]
    expect(resolveSubmitTransition(edges, 10)).toBeNull()
  })

  it('ignores edges from other stages', () => {
    const edges = [
      { id: 1, from_stage_id: 20, is_default_submit: true },
      { id: 2, from_stage_id: 10, is_default_submit: true },
    ] as WorkflowGraphEdge[]
    expect(resolveSubmitTransition(edges, 10)?.id).toBe(2)
  })
})
