import type { ApiResponse, User } from '../types/models'
import type { UserRole } from '../types/enums'
import { useApi } from './useApi'

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

  async function fetchUsers(): Promise<User[]> {
    const response = await get<ApiResponse<User[]>>('/api/users')
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

  return { fetchUsers, createUser, updateUser }
}
