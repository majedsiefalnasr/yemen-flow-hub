import type { ApiResponse, PaginatedResponse, User } from '../types/models'
import type { UserRole } from '../types/enums'
import { useApi } from './useApi'

export interface FetchUsersParams {
  role?: UserRole
  bank_id?: number
  is_active?: boolean
  per_page?: number
  page?: number
  search?: string
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
    const response = await get<ApiResponse<User[] | PaginatedResponse<User>>>(path)
    const payload = response.data

    if (Array.isArray(payload)) {
      return payload
    }

    return payload.data ?? []
  }

  // Server-side paginated fetch (same shape the requests page consumes).
  async function fetchUsersPaginated(params: FetchUsersParams = {}): Promise<PaginatedResponse<User>> {
    const query = new URLSearchParams()
    if (params.role) query.set('role', params.role)
    if (params.bank_id !== undefined) query.set('bank_id', String(params.bank_id))
    if (params.is_active !== undefined) query.set('is_active', String(params.is_active))
    if (params.per_page !== undefined) query.set('per_page', String(params.per_page))
    if (params.page !== undefined) query.set('page', String(params.page))
    if (params.search) query.set('search', params.search)

    const response = await get<ApiResponse<PaginatedResponse<User>>>(`/api/users?${query.toString()}`)
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

  return { fetchUsers, fetchUsersPaginated, createUser, updateUser, getUser }
}
