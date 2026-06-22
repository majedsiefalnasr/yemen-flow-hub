import type { ScreenCapability } from '@/types/models'
import { useAuthStore } from '@/stores/auth.store'

export function useScreenPermissions() {
  const auth = useAuthStore()

  const can = (screen: string, capability: ScreenCapability = 'VIEW'): boolean =>
    auth.screenPermissions[screen]?.includes(capability) ?? false

  return { can }
}
