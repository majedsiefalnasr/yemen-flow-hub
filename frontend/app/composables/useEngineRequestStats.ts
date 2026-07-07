import { ref } from 'vue'
import type { EngineRequestStats } from '@/types/models'
import type { ListOptions } from '@/composables/useEngineRequests'
import { useApi } from '@/composables/useApi'

export function useEngineRequestStats() {
  const api = useApi()
  const stats = ref<EngineRequestStats | null>(null)

  async function fetchStats(options: ListOptions & { scope: 'all' | 'queue' }) {
    const { scope, ...filters } = options
    const response = await api.get<{ data: EngineRequestStats }>('/api/v1/engine-requests/stats', {
      query: { scope, ...filters },
    })
    stats.value = response.data
  }

  return { stats, fetchStats }
}
