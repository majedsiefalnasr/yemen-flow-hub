import type { ApiResponse, User } from '../types/models'
import type { UserRole } from '../types/enums'
import { useApi } from './useApi'

export interface FetchUsersParams {
  role?: UserRole
  bank_id?: number
  is_active?: boolean
  per_page?: number
}

export interface CreateUserPayload {
  name: string
  email: string
  password: string
  role: UserRole
  bank_id: number | null
  is_active: boolean
}

export interface UpdateUserPayload {
  name: string
  email: string
  password?: string
  role: UserRole
  bank_id: number | null
  is_active: boolean
}

export function useUsers() {
  const { get, post, put } = useApi()

  async function fetchUsers(params: FetchUsersParams = {}): Promise<User[]> {
    const query = new URLSearchParams()
    if (params.role) query.set('role', params.role)
    if (params.bank_id !== undefined) query.set('bank_id', String(params.bank_id))
    if (params.is_active !== undefined) query.set('is_active', String(params.is_active))
    if (params.per_page !== undefined) query.set('per_page', String(params.per_page))

    const path = query.size > 0 ? `/api/users?${query.toString()}` : '/api/users'
    const response = await get<ApiResponse<User[]>>(path)
    return response.data
  }

  async function createUser(payload: CreateUserPayload): Promise<User> {
    const response = await post<ApiResponse<User>>('/api/users', payload)
    return response.data
  }

  async function updateUser(id: number, payload: UpdateUserPayload): Promise<User> {
    const response = await put<ApiResponse<User>>(`/api/users/${id}`, payload)
    return response.data
  }

  async function getUser(id: number): Promise<User> {
    const response = await get<ApiResponse<User>>(`/api/users/${id}`)
    return response.data
  }

  return { fetchUsers, createUser, updateUser, getUser }
}
