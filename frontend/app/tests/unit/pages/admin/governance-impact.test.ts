import { describe, expect, it } from 'vitest'
import { isGovernanceActionBlocked } from '@/types/governance-impact'

describe('governance impact helpers', () => {
  it('blocks delete when referenced by published workflow', () => {
    expect(
      isGovernanceActionBlocked(
        {
          entity_type: 'role',
          entity_id: 1,
          referenced_by_published: true,
          referenced_by_draft_only: false,
          would_break_executor: false,
          affected: [],
          warnings: [],
        },
        'delete',
      ),
    ).toBe(true)
  })

  it('blocks deactivate when executor would break', () => {
    expect(
      isGovernanceActionBlocked(
        {
          entity_type: 'team',
          entity_id: 2,
          referenced_by_published: false,
          referenced_by_draft_only: false,
          would_break_executor: true,
          affected: [],
          warnings: [],
        },
        'deactivate',
      ),
    ).toBe(true)
  })

  it('allows safe deactivate', () => {
    expect(
      isGovernanceActionBlocked(
        {
          entity_type: 'role',
          entity_id: 3,
          referenced_by_published: false,
          referenced_by_draft_only: true,
          would_break_executor: false,
          affected: [],
          warnings: ['draft only'],
        },
        'deactivate',
      ),
    ).toBe(false)
  })
})
