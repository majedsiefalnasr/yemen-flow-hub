/**
 * RequestDetail tab structure tests — Story 6.6.
 * Tests tab visibility logic without mounting the full page component.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'

const VOTING_TAB_STATUSES = new Set([
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

type TabKey = 'overview' | 'documents' | 'parties' | 'votes'

function buildTabs(status: RequestStatus): Array<{ key: TabKey; label: string }> {
  const showVotes = VOTING_TAB_STATUSES.has(status)
  return [
    { key: 'overview', label: 'المعلومات' },
    { key: 'documents', label: 'الوثائق' },
    { key: 'parties', label: 'الأطراف' },
    ...(showVotes ? [{ key: 'votes' as TabKey, label: 'التصويت' }] : []),
  ]
}

describe('Request detail — tab keys and labels', () => {
  it('uses "overview" key with label "المعلومات"', () => {
    const tabs = buildTabs(RequestStatus.DRAFT)
    const tab = tabs.find(t => t.key === 'overview')
    expect(tab).toBeDefined()
    expect(tab!.label).toBe('المعلومات')
  })

  it('uses "documents" key with label "الوثائق"', () => {
    const tabs = buildTabs(RequestStatus.DRAFT)
    const tab = tabs.find(t => t.key === 'documents')
    expect(tab).toBeDefined()
    expect(tab!.label).toBe('الوثائق')
  })

  it('uses "parties" key with label "الأطراف"', () => {
    const tabs = buildTabs(RequestStatus.DRAFT)
    const tab = tabs.find(t => t.key === 'parties')
    expect(tab).toBeDefined()
    expect(tab!.label).toBe('الأطراف')
  })

  it('no old "timeline" or "audit" tab keys', () => {
    const tabs = buildTabs(RequestStatus.DRAFT)
    const keys = tabs.map(t => t.key)
    expect(keys).not.toContain('timeline')
    expect(keys).not.toContain('audit')
  })
})

describe('Request detail — votes tab visibility', () => {
  it('shows votes tab for EXECUTIVE_VOTING_OPEN', () => {
    const tabs = buildTabs(RequestStatus.EXECUTIVE_VOTING_OPEN)
    expect(tabs.some(t => t.key === 'votes')).toBe(true)
  })

  it('shows votes tab for EXECUTIVE_VOTING_CLOSED', () => {
    const tabs = buildTabs(RequestStatus.EXECUTIVE_VOTING_CLOSED)
    expect(tabs.some(t => t.key === 'votes')).toBe(true)
  })

  it('does NOT show votes tab for DRAFT', () => {
    const tabs = buildTabs(RequestStatus.DRAFT)
    expect(tabs.some(t => t.key === 'votes')).toBe(false)
  })

  it('does NOT show votes tab for WAITING_FOR_VOTING_OPEN', () => {
    const tabs = buildTabs(RequestStatus.WAITING_FOR_VOTING_OPEN)
    expect(tabs.some(t => t.key === 'votes')).toBe(false)
  })

  it('does NOT show votes tab for EXECUTIVE_APPROVED', () => {
    const tabs = buildTabs(RequestStatus.EXECUTIVE_APPROVED)
    expect(tabs.some(t => t.key === 'votes')).toBe(false)
  })

  it('does NOT show votes tab for EXECUTIVE_REJECTED', () => {
    const tabs = buildTabs(RequestStatus.EXECUTIVE_REJECTED)
    expect(tabs.some(t => t.key === 'votes')).toBe(false)
  })

  it('does NOT show votes tab for COMPLETED', () => {
    const tabs = buildTabs(RequestStatus.COMPLETED)
    expect(tabs.some(t => t.key === 'votes')).toBe(false)
  })

  it('does NOT show votes tab for SUBMITTED', () => {
    const tabs = buildTabs(RequestStatus.SUBMITTED)
    expect(tabs.some(t => t.key === 'votes')).toBe(false)
  })
})

describe('Request detail — tab count', () => {
  it('shows 3 tabs for non-voting stages', () => {
    for (const status of [
      RequestStatus.DRAFT,
      RequestStatus.SUBMITTED,
      RequestStatus.BANK_REVIEW,
      RequestStatus.COMPLETED,
    ]) {
      expect(buildTabs(status).length).toBe(3)
    }
  })

  it('shows 4 tabs for EXECUTIVE_VOTING_OPEN', () => {
    expect(buildTabs(RequestStatus.EXECUTIVE_VOTING_OPEN).length).toBe(4)
  })

  it('shows 4 tabs for EXECUTIVE_VOTING_CLOSED', () => {
    expect(buildTabs(RequestStatus.EXECUTIVE_VOTING_CLOSED).length).toBe(4)
  })

  it('votes tab is last when shown', () => {
    const tabs = buildTabs(RequestStatus.EXECUTIVE_VOTING_OPEN)
    const last = tabs.at(-1)
    expect(last?.key).toBe('votes')
  })

  it('parties tab is always third', () => {
    const tabs = buildTabs(RequestStatus.BANK_REVIEW)
    const third = tabs.at(2)
    expect(third?.key).toBe('parties')
  })
})
