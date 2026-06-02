import { defineStore } from 'pinia'
import { ref } from 'vue'

const STORAGE_KEY = 'org-settings-cache'
const DEFAULT_PLATFORM_NAME = 'منصة إدارة وتمويل الواردات'
const DEFAULT_AUTHORITY = 'البنك المركزي اليمني'
export const DEFAULT_BRAND_LOGO_URL = '/brand/yemen-emblem.svg'
export const DEFAULT_BRAND_LOGO_NAME = 'yemen-emblem.svg'

interface SystemGeneralSettings {
  platformName?: string
  authority?: string
}

interface SystemBrandingSettings {
  brandLogoName?: string | null
  brandLogoDataUrl?: string | null
}

interface OrgCache {
  platformName?: string
  authority?: string
  brandLogoDataUrl?: string
  brandLogoName?: string
  systemVersion?: string
}

export const useOrgStore = defineStore('org', () => {
  const platformName = ref(DEFAULT_PLATFORM_NAME)
  const authority = ref(DEFAULT_AUTHORITY)
  const brandLogoDataUrl = ref<string>(DEFAULT_BRAND_LOGO_URL)
  const brandLogoName = ref<string>(DEFAULT_BRAND_LOGO_NAME)
  const systemVersion = ref<string>('defaults-v1')

  function loadSettings() {
    if (typeof localStorage === 'undefined') return
    try {
      const cached = localStorage.getItem(STORAGE_KEY)
      if (!cached) return
      const parsed = JSON.parse(cached) as OrgCache
      platformName.value = parsed.platformName || DEFAULT_PLATFORM_NAME
      authority.value = parsed.authority || DEFAULT_AUTHORITY
      brandLogoDataUrl.value = parsed.brandLogoDataUrl || DEFAULT_BRAND_LOGO_URL
      brandLogoName.value = parsed.brandLogoName || DEFAULT_BRAND_LOGO_NAME
      systemVersion.value = parsed.systemVersion || 'defaults-v1'
    }
    catch {}
  }

  function persistToCache() {
    if (typeof localStorage === 'undefined') return
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      platformName: platformName.value,
      authority: authority.value,
      brandLogoDataUrl: brandLogoDataUrl.value,
      brandLogoName: brandLogoName.value,
      systemVersion: systemVersion.value,
    }))
  }

  function setPlatformName(name: string) {
    platformName.value = name || DEFAULT_PLATFORM_NAME
    persistToCache()
  }

  function setAuthority(auth: string) {
    authority.value = auth || DEFAULT_AUTHORITY
    persistToCache()
  }

  function setBrandLogo(name: string, dataUrl: string) {
    brandLogoName.value = name
    brandLogoDataUrl.value = dataUrl
    persistToCache()
  }

  function clearBrandLogo() {
    brandLogoName.value = DEFAULT_BRAND_LOGO_NAME
    brandLogoDataUrl.value = DEFAULT_BRAND_LOGO_URL
    persistToCache()
  }

  function applySystemSettings(
    general?: SystemGeneralSettings,
    branding?: SystemBrandingSettings,
    version?: string,
  ) {
    if (version && version === systemVersion.value) return

    platformName.value = general?.platformName || DEFAULT_PLATFORM_NAME
    authority.value = general?.authority || DEFAULT_AUTHORITY
    brandLogoName.value = branding?.brandLogoName || DEFAULT_BRAND_LOGO_NAME
    brandLogoDataUrl.value = branding?.brandLogoDataUrl || DEFAULT_BRAND_LOGO_URL
    systemVersion.value = version || 'defaults-v1'
    persistToCache()
  }

  return {
    platformName,
    authority,
    brandLogoDataUrl,
    brandLogoName,
    systemVersion,
    loadSettings,
    setPlatformName,
    setAuthority,
    setBrandLogo,
    clearBrandLogo,
    applySystemSettings,
  }
})
