import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { GovernanceUser } from '@/types/models'

export function useIdentityUsers() {
  const api = useApi()
  const users = ref<GovernanceUser[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchUsers = async (filters: Record<string, string | number> = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: GovernanceUser[] }>('/api/v1/users', {
        query: filters,
      })
      users.value = response.data
    } catch (cause: unknown) {
      users.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل المستخدمين.')
    } finally {
      loading.value = false
    }
  }

  const createUser = async (payload: Record<string, unknown>) => {
    const response = await api.post<{ data: GovernanceUser }>('/api/v1/users', payload)
    users.value = [response.data, ...users.value]
  }

  const deactivateUser = async (user: GovernanceUser) => {
    const response = await api.post<{ data: GovernanceUser }>(`/api/v1/users/${user.id}/deactivate`)
    users.value = users.value.map((item) => (item.id === user.id ? response.data : item))
  }

  // Reset to a freshly generated strong random secret (never a hardcoded /
  // predictable literal). The generated password is returned so the caller can
  // surface it once to the admin; the backend forces a change on next login.
  const resetPassword = async (user: GovernanceUser): Promise<string> => {
    const password = generateTempPassword()
    await api.post(`/api/v1/users/${user.id}/reset-password`, {
      password,
      password_confirmation: password,
    })
    return password
  }

  const resetMfa = async (user: GovernanceUser) => api.post(`/api/v1/users/${user.id}/reset-mfa`)

  return { users, loading, error, fetchUsers, createUser, deactivateUser, resetPassword, resetMfa }
}

function generateTempPassword(): string {
  const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
  const lower = 'abcdefghijkmnpqrstuvwxyz'
  const digits = '23456789'
  const symbols = '!@#$%^&*'
  const all = upper + lower + digits + symbols
  const pick = (set: string, n: number): number[] => {
    const out: number[] = []
    const bytes = new Uint32Array(n)
    crypto.getRandomValues(bytes)
    for (let i = 0; i < n; i += 1) out.push(bytes[i]! % set.length)
    return out
  }
  // Guarantee one of each class, then fill to 16 chars, then shuffle.
  const chars = [
    upper[pick(upper, 1)[0]!]!,
    lower[pick(lower, 1)[0]!]!,
    digits[pick(digits, 1)[0]!]!,
    symbols[pick(symbols, 1)[0]!]!,
    ...pick(all, 12).map((i) => all[i]!),
  ]
  for (let i = chars.length - 1; i > 0; i -= 1) {
    const j = pick(all, 1)[0]! % (i + 1)
    ;[chars[i], chars[j]] = [chars[j]!, chars[i]!]
  }
  return chars.join('')
}
