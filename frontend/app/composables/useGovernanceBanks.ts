import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { Bank } from '@/types/models'

export function useGovernanceBanks() {
  const api = useApi()
  const banks = ref<Bank[]>([])

  const fetchBanks = async () => {
    const response = await api.get<{ data: Bank[] }>('/api/v1/banks')
    banks.value = response.data
  }

  const createBank = async (payload: {
    code: string
    name: string
    license_number?: string
    swift_code?: string
    status: 'ACTIVE' | 'SUSPENDED'
  }) => {
    const response = await api.post<{ data: Bank }>('/api/v1/banks', payload)
    banks.value = [response.data, ...banks.value]
  }

  return { banks, fetchBanks, createBank }
}
