import { UserRole } from '../types/enums'

export interface DeviceInfo {
  browser: string
  os: string
  deviceType: 'desktop' | 'mobile' | 'tablet'
  lastLoginAt: string
}

export interface SavedAccount {
  id: string
  name: string
  email: string
  role: UserRole
  bankName: string
  department?: string
  trustedAt: string
  deviceInfo?: DeviceInfo
  /**
   * boring-avatars variant chosen by the user. Stored locally so the saved-
   * account card can render the same avatar the user will see post-login,
   * without an extra network round-trip.
   */
  avatarVariant?: string | null
}

const STORAGE_KEY = 'yfh-saved-accounts'
const PIN_STATUS_KEY = 'yfh-pin-status'
/** Stores the actual PIN per email locally (mock until backend PIN endpoint exists) */
const PIN_DATA_KEY = 'yfh-pin-data'

function normalizeEmailKey(email: string): string {
  return email.trim().toLowerCase()
}

function normalizePin(pin: string): string {
  return pin
    .replace(/[٠-٩]/g, ch => String(ch.charCodeAt(0) - 0x660))
    .replace(/[۰-۹]/g, ch => String(ch.charCodeAt(0) - 0x6F0))
    .replace(/\D/g, '')
    .slice(0, 6)
}

function parseStoredPinValue(value: unknown): string | null {
  if (typeof value === 'string' || typeof value === 'number') {
    const normalized = normalizePin(String(value))
    return normalized.length > 0 ? normalized : null
  }
  if (!value || typeof value !== 'object') return null
  const record = value as Record<string, unknown>
  const candidate = record.pin ?? record.value ?? record.code
  if (typeof candidate === 'string' || typeof candidate === 'number') {
    const normalized = normalizePin(String(candidate))
    return normalized.length > 0 ? normalized : null
  }
  return null
}

function findLegacyValueByNormalizedEmail<T>(map: Record<string, T>, normalizedEmail: string): T | undefined {
  const legacyKey = Object.keys(map).find(key => normalizeEmailKey(key) === normalizedEmail)
  return legacyKey ? map[legacyKey] : undefined
}

function isClient(): boolean {
  return typeof window !== 'undefined' && typeof localStorage !== 'undefined'
}

const AVATAR_COLORS = [
  '#0066cc', '#5856d6', '#32ade6', '#34c759', '#ff9f0a', '#ff3b30',
]

export function getInitials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(w => w[0])
    .join('')
    .toUpperCase()
}

export function getAvatarColor(id: string): string {
  let hash = 0
  for (let i = 0; i < id.length; i++) {
    hash = id.charCodeAt(i) + ((hash << 5) - hash)
  }
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length]!
}

export function getDeviceInfo(): DeviceInfo {
  if (!isClient() || typeof navigator === 'undefined') {
    return { browser: 'Unknown', os: 'Unknown', deviceType: 'desktop', lastLoginAt: new Date().toISOString() }
  }
  const ua = navigator.userAgent
  let browser = 'Unknown'
  if (/Edg\//.test(ua)) browser = 'Microsoft Edge'
  else if (/OPR\//.test(ua)) browser = 'Opera'
  else if (/Chrome\//.test(ua)) browser = 'Google Chrome'
  else if (/Firefox\//.test(ua)) browser = 'Firefox'
  else if (/Safari\//.test(ua)) browser = 'Safari'

  let os = 'Unknown'
  if (/Windows NT 10/.test(ua)) os = 'Windows 11'
  else if (/Windows NT/.test(ua)) os = 'Windows'
  else if (/Mac OS X/.test(ua)) os = 'macOS'
  else if (/Android/.test(ua)) os = 'Android'
  else if (/iPhone/.test(ua)) os = 'iOS'
  else if (/iPad/.test(ua)) os = 'iPadOS'
  else if (/Linux/.test(ua)) os = 'Linux'

  let deviceType: DeviceInfo['deviceType'] = 'desktop'
  if (/iPad/.test(ua)) deviceType = 'tablet'
  else if (/Mobi|Android|iPhone/.test(ua)) deviceType = 'mobile'

  return { browser, os, deviceType, lastLoginAt: new Date().toISOString() }
}

// TODO: Remove mock accounts once the trusted-device backend endpoint is connected
function getMockAccounts(): SavedAccount[] {
  return []
}

export function useSavedAccounts() {
  const accounts = ref<SavedAccount[]>([])

  function load() {
    if (!isClient()) return
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      // Fall back to mock data if no real saved accounts exist
      accounts.value = raw ? (JSON.parse(raw) as SavedAccount[]) : getMockAccounts()
    }
    catch {
      accounts.value = []
    }
  }

  function persist() {
    if (!isClient()) return
    localStorage.setItem(STORAGE_KEY, JSON.stringify(accounts.value))
  }

  function addAccount(account: SavedAccount) {
    const enriched: SavedAccount = {
      ...account,
      deviceInfo: account.deviceInfo ?? (isClient() ? getDeviceInfo() : undefined),
    }
    const idx = accounts.value.findIndex(a => a.email === enriched.email)
    if (idx >= 0) {
      accounts.value[idx] = { ...accounts.value[idx]!, ...enriched }
    }
    else {
      accounts.value.unshift(enriched)
    }
    persist()
  }

  function removeAccount(id: string) {
    accounts.value = accounts.value.filter(a => a.id !== id)
    persist()
  }

  /** PIN is account-level — stored separately from device trust records */
  function getPINStatus(email: string): boolean {
    if (!isClient()) return false
    try {
      const raw = localStorage.getItem(PIN_STATUS_KEY)
      if (!raw) return false
      const map = JSON.parse(raw) as Record<string, boolean>
      const key = normalizeEmailKey(email)
      return map[key] ?? findLegacyValueByNormalizedEmail(map, key) ?? false
    }
    catch { return false }
  }

  function setPINStatus(email: string, hasPIN: boolean) {
    if (!isClient()) return
    try {
      const raw = localStorage.getItem(PIN_STATUS_KEY)
      const map: Record<string, boolean> = raw ? (JSON.parse(raw) as Record<string, boolean>) : {}
      const key = normalizeEmailKey(email)
      if (hasPIN) map[key] = true
      else {
        delete map[key]
        delete map[email]
      }
      localStorage.setItem(PIN_STATUS_KEY, JSON.stringify(map))
    }
    catch {}
  }

  /**
   * Store the actual PIN value for an account locally.
   * TODO: Replace with POST /api/auth/create-pin once backend endpoint exists.
   */
  function setPIN(email: string, pin: string) {
    if (!isClient()) return
    try {
      const raw = localStorage.getItem(PIN_DATA_KEY)
      const map: Record<string, string> = raw ? (JSON.parse(raw) as Record<string, string>) : {}
      map[normalizeEmailKey(email)] = normalizePin(pin)
      localStorage.setItem(PIN_DATA_KEY, JSON.stringify(map))
    }
    catch {}
    setPINStatus(email, true)
  }

  /**
   * Verify a PIN against the stored value.
   * TODO: Replace with POST /api/auth/verify-pin once backend endpoint exists.
   */
  function hasStoredPIN(email: string): boolean {
    if (!isClient()) return false
    try {
      const raw = localStorage.getItem(PIN_DATA_KEY)
      if (!raw) return false
      const map = JSON.parse(raw) as Record<string, unknown>
      const key = normalizeEmailKey(email)
      const storedPin = map[key] ?? findLegacyValueByNormalizedEmail(map, key)
      return parseStoredPinValue(storedPin) != null
    }
    catch { return false }
  }

  /**
   * Verify a PIN against the stored value.
   * TODO: Replace with POST /api/auth/verify-pin once backend endpoint exists.
   */
  function verifyPIN(email: string, pin: string): boolean {
    if (!isClient()) return false
    try {
      const raw = localStorage.getItem(PIN_DATA_KEY)
      if (!raw) return false
      const map = JSON.parse(raw) as Record<string, unknown>
      const key = normalizeEmailKey(email)
      const normalizedPin = normalizePin(pin)
      const storedPin = parseStoredPinValue(map[key] ?? findLegacyValueByNormalizedEmail(map, key))
      if (!storedPin) return false
      return storedPin === normalizedPin
    }
    catch { return false }
  }

  /** Clear all saved accounts, PIN status, and PIN data from this device. */
  function clearAllData() {
    if (!isClient()) return
    localStorage.removeItem(STORAGE_KEY)
    localStorage.removeItem(PIN_STATUS_KEY)
    localStorage.removeItem(PIN_DATA_KEY)
    accounts.value = []
  }

  load()

  return { accounts, addAccount, removeAccount, getPINStatus, setPINStatus, setPIN, hasStoredPIN, verifyPIN, clearAllData }
}
