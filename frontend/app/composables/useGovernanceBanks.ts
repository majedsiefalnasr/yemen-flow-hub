import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { Bank } from '@/types/models'

export function useGovernanceBanks() {
  const api = useApi()
  const banks = ref<Bank[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchBanks = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: Bank[] }>('/api/v1/banks')
      banks.value = response.data
    } catch (cause: unknown) {
      banks.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل البنوك.')
    } finally {
      loading.value = false
    }
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
    return response.data
  }

  const updateBank = async (
    bank: Bank,
    payload: { name: string; license_number?: string; swift_code?: string },
  ) => {
    const response = await api.put<{ data: Bank }>(`/api/v1/banks/${bank.id}`, {
      ...payload,
      version: bank.version,
    })
    banks.value = banks.value.map((b) => (b.id === response.data.id ? response.data : b))
    return response.data
  }

  const setBankActive = async (bank: Bank, active: boolean) => {
    const response = await api.post<{ data: Bank }>(
      `/api/v1/banks/${bank.id}/${active ? 'activate' : 'deactivate'}`,
    )
    banks.value = banks.value.map((b) => (b.id === response.data.id ? response.data : b))
    return response.data
  }

  const deleteBank = async (bank: Bank) => {
    await api.del(`/api/v1/banks/${bank.id}`)
    banks.value = banks.value.filter((b) => b.id !== bank.id)
  }

  return { banks, loading, error, fetchBanks, createBank, updateBank, setBankActive, deleteBank }
}
