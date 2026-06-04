import { vi, describe, it, expect, beforeEach } from 'vitest'
import { VoteType, RequestStatus, UserRole, VotingSessionStatus } from '../../../types/enums'
import type { ImportRequest, VotingDetail, VotingTally, RequestVote } from '../../../types/models'
import { makeImportRequest } from '../fixtures/request-data'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost }),
}))

const { useVoting } = await import('../../../composables/useVoting')

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    id: 1,
    reference_number: 'YFH-2026-000001',
    bank_name: 'بنك اليمن المركزي',
    status: RequestStatus.EXECUTIVE_VOTING_OPEN,
    current_owner_role: UserRole.EXECUTIVE_MEMBER,
    amount: 100000,
    supplier_name: 'Global Supplier',
    goods_description: 'Industrial Equipment',
    submitted_by: 2,
    reviewed_by: 3,
    approved_by: 4,
    submitted_at: '2026-05-01T00:00:00.000000Z',
    bank_approved_at: '2026-05-02T00:00:00.000000Z',
    support_approved_at: '2026-05-03T00:00:00.000000Z',
    swift_uploaded_by: 5,
    swift_uploaded_at: '2026-05-04T00:00:00.000000Z',
    voting_opened_by: 6,
    voting_opened_at: '2026-05-05T00:00:00.000000Z',
    voting_session_status: VotingSessionStatus.OPEN,
    created_at: '2026-05-01T00:00:00.000000Z',
    updated_at: '2026-05-05T00:00:00.000000Z',
    ...overrides,
  })
}

function makeTally(overrides: Partial<VotingTally> = {}): VotingTally {
  return {
    approve_count: 2,
    reject_count: 1,
    abstain_count: 0,
    auto_abstain_count: 0,
    total_cast: 3,
    is_decided: false,
    result: 'PENDING',
    ...overrides,
  }
}

function makeVote(overrides: Partial<RequestVote> = {}): RequestVote {
  return {
    id: 1,
    request_id: 1,
    user_id: 10,
    user_name: 'أحمد العمري',
    vote: VoteType.APPROVE,
    justification: null,
    is_director_override: false,
    voted_at: '2026-05-05T10:00:00.000000Z',
    created_at: '2026-05-05T10:00:00.000000Z',
    ...overrides,
  }
}

function makeVotingDetail(overrides: Partial<VotingDetail> = {}): VotingDetail {
  return {
    request: makeRequest(),
    tally: makeTally(),
    votes: [makeVote()],
    total_members: 5,
    my_vote: null,
    ...overrides,
  }
}

describe('useVoting — fetchVotingQueue', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls GET /api/voting and returns paginated data', async () => {
    const paginatedData = {
      data: [makeRequest()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    }
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: paginatedData })

    const { fetchVotingQueue } = useVoting()
    const result = await fetchVotingQueue()

    expect(mockGet).toHaveBeenCalledWith('/api/voting')
    expect(result.data).toHaveLength(1)
    expect(result.meta.total).toBe(1)
  })

  it('propagates error when GET /api/voting fails', async () => {
    mockGet.mockRejectedValue(new Error('Unauthorized'))

    const { fetchVotingQueue } = useVoting()
    await expect(fetchVotingQueue()).rejects.toThrow('Unauthorized')
  })
})

describe('useVoting — fetchVotingDetail', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls GET /api/voting/{id} and returns VotingDetail', async () => {
    const detail = makeVotingDetail()
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: detail })

    const { fetchVotingDetail } = useVoting()
    const result = await fetchVotingDetail(1)

    expect(mockGet).toHaveBeenCalledWith('/api/voting/1')
    expect(result.total_members).toBe(5)
    expect(result.tally.approve_count).toBe(2)
    expect(result.votes).toHaveLength(1)
    expect(result.my_vote).toBeNull()
  })

  it('propagates error when GET /api/voting/{id} fails', async () => {
    mockGet.mockRejectedValue(new Error('Not found'))

    const { fetchVotingDetail } = useVoting()
    await expect(fetchVotingDetail(99)).rejects.toThrow('Not found')
  })
})

describe('useVoting — castVote', () => {
  beforeEach(() => vi.resetAllMocks())

  it('sends only vote when justification is undefined', async () => {
    const detail = makeVotingDetail({ my_vote: makeVote({ vote: VoteType.APPROVE }) })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: detail })

    const { castVote } = useVoting()
    await castVote(1, VoteType.APPROVE)

    const body = mockPost.mock.calls[0]![1] as Record<string, string>
    expect(body.vote).toBe(VoteType.APPROVE)
    expect(Object.keys(body)).not.toContain('justification')
  })

  it('sends only vote when justification is empty string', async () => {
    const detail = makeVotingDetail({ my_vote: makeVote({ vote: VoteType.REJECT }) })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: detail })

    const { castVote } = useVoting()
    await castVote(1, VoteType.REJECT, '   ')

    const body = mockPost.mock.calls[0]![1] as Record<string, string>
    expect(body.vote).toBe(VoteType.REJECT)
    expect(Object.keys(body)).not.toContain('justification')
  })

  it('sends vote + trimmed justification when justification is provided', async () => {
    const detail = makeVotingDetail({
      my_vote: makeVote({ vote: VoteType.REJECT, justification: 'مستندات غير مكتملة' }),
    })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: detail })

    const { castVote } = useVoting()
    await castVote(1, VoteType.REJECT, '  مستندات غير مكتملة  ')

    expect(mockPost).toHaveBeenCalledWith('/api/voting/1/vote', {
      vote: VoteType.REJECT,
      justification: 'مستندات غير مكتملة',
    })
  })

  it('sends ABSTAIN vote without justification', async () => {
    const detail = makeVotingDetail({ my_vote: makeVote({ vote: VoteType.ABSTAIN }) })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: detail })

    const { castVote } = useVoting()
    await castVote(1, VoteType.ABSTAIN)

    const body = mockPost.mock.calls[0]![1] as Record<string, string>
    expect(body.vote).toBe(VoteType.ABSTAIN)
    expect(Object.keys(body)).not.toContain('justification')
  })

  it('returns updated VotingDetail after successful vote', async () => {
    const myVote = makeVote({ vote: VoteType.APPROVE })
    const detail = makeVotingDetail({
      my_vote: myVote,
      tally: makeTally({ approve_count: 3, total_cast: 4 }),
    })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: detail })

    const { castVote } = useVoting()
    const result = await castVote(1, VoteType.APPROVE)

    expect(result.my_vote?.vote).toBe(VoteType.APPROVE)
    expect(result.tally.approve_count).toBe(3)
  })

  it('propagates error when vote POST fails', async () => {
    mockPost.mockRejectedValue(new Error('VOTING_SESSION_CLOSED'))

    const { castVote } = useVoting()
    await expect(castVote(1, VoteType.APPROVE)).rejects.toThrow('VOTING_SESSION_CLOSED')
  })
})

describe('useVoting — openSession', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls POST /api/voting/{id}/open with empty body', async () => {
    const req = makeRequest({
      status: RequestStatus.EXECUTIVE_VOTING_OPEN,
      voting_session_status: VotingSessionStatus.OPEN,
    })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { openSession } = useVoting()
    const result = await openSession(1)

    expect(mockPost).toHaveBeenCalledWith('/api/voting/1/open', {})
    expect(result.status).toBe(RequestStatus.EXECUTIVE_VOTING_OPEN)
  })

  it('propagates error when open POST fails', async () => {
    mockPost.mockRejectedValue(new Error('Forbidden'))

    const { openSession } = useVoting()
    await expect(openSession(1)).rejects.toThrow('Forbidden')
  })
})

describe('useVoting — closeSession', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls POST /api/voting/{id}/close with empty body', async () => {
    const req = makeRequest({
      status: RequestStatus.EXECUTIVE_VOTING_CLOSED,
      voting_session_status: VotingSessionStatus.CLOSED,
    })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { closeSession } = useVoting()
    const result = await closeSession(1)

    expect(mockPost).toHaveBeenCalledWith('/api/voting/1/close', {})
    expect(result.status).toBe(RequestStatus.EXECUTIVE_VOTING_CLOSED)
  })

  it('propagates error when close POST fails', async () => {
    mockPost.mockRejectedValue(new Error('Forbidden'))

    const { closeSession } = useVoting()
    await expect(closeSession(1)).rejects.toThrow('Forbidden')
  })
})

describe('useVoting — finalizeDecision', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls POST /api/workflow/{id}/finalize-decision (NOT /api/voting)', async () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { finalizeDecision } = useVoting()
    await finalizeDecision(1)

    expect(mockPost).toHaveBeenCalledWith('/api/workflow/1/finalize-decision', {})
    expect(mockPost).not.toHaveBeenCalledWith(
      expect.stringContaining('/api/voting'),
      expect.anything(),
    )
  })

  it('sends empty body — no decision parameter', async () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { finalizeDecision } = useVoting()
    await finalizeDecision(1)

    const body = mockPost.mock.calls[0]![1] as Record<string, unknown>
    expect(Object.keys(body)).toHaveLength(0)
  })

  it('returns updated ImportRequest', async () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { finalizeDecision } = useVoting()
    const result = await finalizeDecision(1)

    expect(result.status).toBe(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('propagates error when finalize POST fails', async () => {
    mockPost.mockRejectedValue(new Error('VOTING_NOT_DECIDED'))

    const { finalizeDecision } = useVoting()
    await expect(finalizeDecision(1)).rejects.toThrow('VOTING_NOT_DECIDED')
  })
})

describe('useVoting — directorOverride', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls POST /api/voting/{id}/override with decision and justification', async () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_APPROVED })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { directorOverride } = useVoting()
    await directorOverride(1, 'APPROVE', 'قرار المدير النهائي')

    expect(mockPost).toHaveBeenCalledWith('/api/voting/1/override', {
      decision: 'APPROVE',
      justification: 'قرار المدير النهائي',
    })
  })

  it('sends REJECT decision — does NOT send vote key', async () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_REJECTED })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { directorOverride } = useVoting()
    await directorOverride(1, 'REJECT', 'مخالفة للوائح')

    const body = mockPost.mock.calls[0]![1] as Record<string, string>
    expect(body.decision).toBe('REJECT')
    expect(body.justification).toBe('مخالفة للوائح')
    expect(Object.keys(body)).not.toContain('vote')
  })

  it('returns updated ImportRequest', async () => {
    const req = makeRequest({ status: RequestStatus.EXECUTIVE_REJECTED })
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: req })

    const { directorOverride } = useVoting()
    const result = await directorOverride(1, 'REJECT', 'السبب')

    expect(result.status).toBe(RequestStatus.EXECUTIVE_REJECTED)
  })

  it('propagates error when override POST fails', async () => {
    mockPost.mockRejectedValue(new Error('Forbidden'))

    const { directorOverride } = useVoting()
    await expect(directorOverride(1, 'APPROVE', 'السبب')).rejects.toThrow('Forbidden')
  })
})
