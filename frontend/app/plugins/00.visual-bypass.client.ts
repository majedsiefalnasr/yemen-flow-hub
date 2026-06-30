import { UserRole } from '../types/enums'
import { useAuthStore } from '../stores/auth.store'

const BANK_ROLES = new Set<UserRole>([
  UserRole.DATA_ENTRY,
  UserRole.BANK_REVIEWER,
  UserRole.BANK_ADMIN,
  UserRole.SWIFT_OFFICER,
])

function resolveRole(input: string | undefined): UserRole {
  const role = (input ?? '').trim().toUpperCase()
  const match = Object.values(UserRole).find((r) => r === role)
  return match ?? UserRole.CBY_ADMIN
}

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  if (!config.public.visualBypass) return

  const auth = useAuthStore()
  const role = resolveRole(config.public.visualBypassRole as string | undefined)
  const isBankRole = BANK_ROLES.has(role)

  auth.user = {
    id: 999001,
    name: 'Visual Bypass User',
    email: `visual-${role.toLowerCase()}@cby.local`,
    role,
    bank_id: isBankRole ? 11 : null,
    bank_name_ar: isBankRole ? 'البنك اليمني للتجارة والاستثمار' : null,
    bank_name_en: isBankRole ? 'YBTI' : null,
    is_active: true,
  }
  auth.isAuthenticated = true

  localStorage.setItem('yfh-authenticated', '1')
})
