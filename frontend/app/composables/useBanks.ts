import type { ApiResponse, Bank } from '../types/models'
import { useApi } from './useApi'

export interface CreateBankPayload {
  name?: string
  name_ar: string
  name_en: string
  code: string
  license_number?: string | null
  entity_type?: string | null
  is_active: boolean
}

export interface UpdateBankPayload {
  name?: string
  name_ar?: string
  name_en?: string
  code?: string
  license_number?: string | null
  entity_type?: string | null
  is_active?: boolean
}

export function useBanks() {
  const { get, post, put } = useApi()

  async function fetchBanks(): Promise<Bank[]> {
    const response = await get<ApiResponse<Bank[]>>('/api/banks')
    return response.data
  }

  async function createBank(payload: CreateBankPayload): Promise<Bank> {
    const response = await post<ApiResponse<Bank>>('/api/banks', payload)
    return response.data
  }

  async function updateBank(id: number, payload: UpdateBankPayload): Promise<Bank> {
    const response = await put<ApiResponse<Bank>>(`/api/banks/${id}`, payload)
    return response.data
  }

  return { fetchBanks, createBank, updateBank }
}
