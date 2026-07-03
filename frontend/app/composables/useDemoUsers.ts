import { ref } from 'vue'
import type { ApiResponse, DemoUser } from '../types/models'

interface DemoUsersResponseData {
  users: DemoUser[]
}

export function useDemoUsers() {
  const users = ref<DemoUser[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchDemoUsers(): Promise<void> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string

    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<DemoUsersResponseData>>('/api/auth/demo-users', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      users.value = response.data.users
    } catch {
      error.value = 'تعذّر تحميل قائمة المستخدمين. حاول مجدداً.'
    } finally {
      loading.value = false
    }
  }

  return { users, loading, error, fetchDemoUsers }
}
