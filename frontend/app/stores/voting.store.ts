import { defineStore } from 'pinia'
import type { ImportRequest, VotingDetail } from '../types/models'
import type { VoteType } from '../types/enums'
import { useVoting } from '../composables/useVoting'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useVotingStore = defineStore('voting', {
  state: () => ({
    queue: [] as ImportRequest[],
    queueMeta: null as PaginationMeta | null,
    votingDetail: null as VotingDetail | null,
    loading: false,
    loadingDetail: false,
    performingVote: false,
    performingDirectorAction: false,
    error: null as string | null,
    voteError: null as string | null,
  }),

  actions: {
    async loadQueue(): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const { fetchVotingQueue } = useVoting()
        const result = await fetchVotingQueue()
        this.queue = result.data
        this.queueMeta = result.meta
      } catch (err) {
        if (import.meta.dev) {
          console.error('[voting.store] loadQueue failed:', err)
        }
        this.error = 'تعذّر تحميل قائمة التصويت.'
        this.queue = []
      } finally {
        this.loading = false
      }
    },

    async loadVotingDetail(id: number): Promise<void> {
      this.loadingDetail = true
      this.voteError = null

      try {
        const { fetchVotingDetail } = useVoting()
        this.votingDetail = await fetchVotingDetail(id)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[voting.store] loadVotingDetail failed:', err)
        }
        this.voteError = 'تعذّر تحميل بيانات التصويت.'
      } finally {
        this.loadingDetail = false
      }
    },

    async castVote(id: number, vote: VoteType, justification?: string): Promise<void> {
      if (this.performingVote) throw new Error('تصويت قيد التنفيذ بالفعل')
      this.performingVote = true
      this.voteError = null

      try {
        const { castVote } = useVoting()
        this.votingDetail = await castVote(id, vote, justification)
      } catch (err: any) {
        if (import.meta.dev) {
          console.error('[voting.store] castVote failed:', err)
        }
        const msg = err instanceof Error ? err.message : ''
        this.voteError = msg || 'تعذّر تسجيل الصوت. يرجى المحاولة مرة أخرى.'
        throw err
      } finally {
        this.performingVote = false
      }
    },

    async openSession(id: number): Promise<ImportRequest> {
      if (this.performingDirectorAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingDirectorAction = true
      this.error = null

      try {
        const { openSession } = useVoting()
        const updated = await openSession(id)
        this.votingDetail = null
        return updated
      } catch (err: any) {
        if (import.meta.dev) {
          console.error('[voting.store] openSession failed:', err)
        }
        const msg = err instanceof Error ? err.message : ''
        this.error = msg || 'تعذّر فتح جلسة التصويت.'
        throw err
      } finally {
        this.performingDirectorAction = false
      }
    },

    async closeSession(id: number): Promise<ImportRequest> {
      if (this.performingDirectorAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingDirectorAction = true
      this.error = null

      try {
        const { closeSession } = useVoting()
        const updated = await closeSession(id)
        this.votingDetail = null
        return updated
      } catch (err: any) {
        if (import.meta.dev) {
          console.error('[voting.store] closeSession failed:', err)
        }
        const msg = err instanceof Error ? err.message : ''
        this.error = msg || 'تعذّر إغلاق جلسة التصويت.'
        throw err
      } finally {
        this.performingDirectorAction = false
      }
    },

    async finalizeDecision(id: number): Promise<ImportRequest> {
      if (this.performingDirectorAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingDirectorAction = true
      this.error = null

      try {
        const { finalizeDecision } = useVoting()
        return await finalizeDecision(id)
      } catch (err: any) {
        if (import.meta.dev) {
          console.error('[voting.store] finalizeDecision failed:', err)
        }
        const msg = err instanceof Error ? err.message : ''
        this.error = msg || 'تعذّر إصدار القرار النهائي.'
        throw err
      } finally {
        this.performingDirectorAction = false
      }
    },

    async directorOverride(
      id: number,
      decision: 'APPROVE' | 'REJECT',
      justification: string,
    ): Promise<ImportRequest> {
      if (this.performingDirectorAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingDirectorAction = true
      this.error = null

      try {
        const { directorOverride } = useVoting()
        const updated = await directorOverride(id, decision, justification)
        this.votingDetail = null
        return updated
      } catch (err: any) {
        if (import.meta.dev) {
          console.error('[voting.store] directorOverride failed:', err)
        }
        const msg = err instanceof Error ? err.message : ''
        this.error = msg || 'تعذّر تنفيذ قرار التجاوز.'
        throw err
      } finally {
        this.performingDirectorAction = false
      }
    },
  },
})
