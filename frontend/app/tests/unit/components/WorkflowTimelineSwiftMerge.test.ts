// @vitest-environment jsdom
/**
 * Story 17-E.4 (AC4): SWIFT stage DISPLAY merge in WorkflowTimeline.
 * WAITING_FOR_SWIFT + SWIFT_UPLOADED collapse into a single display node
 * labeled "تم رفع السويفت" — in-progress while WAITING_FOR_SWIFT, completed at
 * SWIFT_UPLOADED. Display-only: the granular statuses still exist everywhere
 * else (asserted in workflow-status.test.ts).
 */
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WorkflowTimeline from '../../../components/workflow/WorkflowTimeline.vue'
import { RequestStatus } from '../../../types/enums'
import type { RequestStageHistory } from '../../../types/models'

const SWIFT_NODE_LABEL = 'تم رفع السويفت'
const RING_ACTIVE_CLASS = 'ring-ring'

function makeEntry(overrides: Partial<RequestStageHistory> = {}): RequestStageHistory {
  return {
    id: 1,
    request_id: 5,
    from_status: 'SUPPORT_APPROVED',
    to_status: 'WAITING_FOR_SWIFT',
    from_owner_role: 'SUPPORT_COMMITTEE',
    to_owner_role: 'SWIFT_OFFICER',
    actor_id: 3,
    actor_role: 'SUPPORT_COMMITTEE',
    performed_by: { id: 3, name: 'Sara', role: 'SUPPORT_COMMITTEE' },
    action: 'support_approve',
    notes: null,
    metadata: null,
    created_at: '2026-05-08T08:00:00.000Z',
    ...overrides,
  }
}

function countOccurrences(haystack: string, needle: string): number {
  return haystack.split(needle).length - 1
}

describe('WorkflowTimeline — SWIFT stage display merge (Story 17-E.4)', () => {
  it('renders a single merged SWIFT node (not two) and drops the granular labels', () => {
    const wrapper = mount(WorkflowTimeline, {
      props: { currentStatus: RequestStatus.WAITING_FOR_SWIFT, history: [makeEntry()] },
    })

    const text = wrapper.text()
    expect(countOccurrences(text, SWIFT_NODE_LABEL)).toBe(1)
    // The two granular per-status labels must NOT appear as separate nodes.
    expect(text).not.toContain('انتظار رفع SWIFT')
    expect(text).not.toContain('تم رفع SWIFT')
    // One Stepper trigger button per display node: 22 canonical statuses − 1 merge = 21.
    expect(wrapper.findAll('button')).toHaveLength(21)
  })

  it('shows the merged node as in-progress (active ring) when WAITING_FOR_SWIFT', () => {
    const wrapper = mount(WorkflowTimeline, {
      props: { currentStatus: RequestStatus.WAITING_FOR_SWIFT, history: [makeEntry()] },
    })

    const current = wrapper.get('[aria-current="true"]')
    expect(current.text()).toContain(SWIFT_NODE_LABEL)
    expect(current.get('button').classes()).toContain(RING_ACTIVE_CLASS)
  })

  it('shows the merged node as completed (no active ring) when SWIFT_UPLOADED', () => {
    const wrapper = mount(WorkflowTimeline, {
      props: {
        currentStatus: RequestStatus.SWIFT_UPLOADED,
        history: [
          makeEntry(),
          makeEntry({
            id: 2,
            from_status: 'WAITING_FOR_SWIFT',
            to_status: 'SWIFT_UPLOADED',
            action: 'swift_upload',
            created_at: '2026-05-09T08:00:00.000Z',
          }),
        ],
      },
    })

    const current = wrapper.get('[aria-current="true"]')
    expect(current.text()).toContain(SWIFT_NODE_LABEL)
    // Completed-at-current state: merged node is NOT marked in-progress.
    expect(current.get('button').classes()).not.toContain(RING_ACTIVE_CLASS)
  })

  it('keeps the SWIFT node as a single completed node once past it (EXECUTIVE_VOTING_OPEN)', () => {
    const wrapper = mount(WorkflowTimeline, {
      props: {
        currentStatus: RequestStatus.EXECUTIVE_VOTING_OPEN,
        history: [makeEntry()],
      },
    })

    const text = wrapper.text()
    expect(countOccurrences(text, SWIFT_NODE_LABEL)).toBe(1)
    expect(wrapper.findAll('button')).toHaveLength(21)
  })
})
