import type { ApiResponse, ImportRequest, PaginatedResponse, VotingDetail } from '../types/models'
import type { VoteType } from '../types/enums'
import { useApi } from './useApi'

export function useVoting() {
  const { get, post } = useApi()

  async function fetchVotingQueue(): Promise<PaginatedResponse<ImportRequest>> {
    const response = await get<ApiResponse<PaginatedResponse<ImportRequest>>>('/api/voting')
    return response.data
  }

  async function fetchVotingDetail(id: number): Promise<VotingDetail> {
    const response = await get<ApiResponse<VotingDetail>>(`/api/voting/${id}`)
    return response.data
  }

  async function castVote(id: number, vote: VoteType, justification?: string): Promise<VotingDetail> {
    const body: Record<string, string> = { vote }
    if (justification !== undefined && justification.trim()) {
      body.justification = justification.trim()
    }
    const response = await post<ApiResponse<VotingDetail>>(`/api/voting/${id}/vote`, body)
    return response.data
  }

  async function openSession(id: number): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/voting/${id}/open`, {})
    return response.data
  }

  async function closeSession(id: number): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/voting/${id}/close`, {})
    return response.data
  }

  async function finalizeDecision(id: number): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/workflow/${id}/finalize-decision`, {})
    return response.data
  }

  async function directorOverride(id: number, decision: 'APPROVE' | 'REJECT', justification: string): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/voting/${id}/override`, { decision, justification })
    return response.data
  }

  return {
    fetchVotingQueue,
    fetchVotingDetail,
    castVote,
    openSession,
    closeSession,
    finalizeDecision,
    directorOverride,
  }
}
