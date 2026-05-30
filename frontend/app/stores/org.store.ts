import { defineStore } from 'pinia'
import { ref } from 'vue'

const STORAGE_KEY = 'org-settings-cache'

export const useOrgStore = defineStore('org', () => {
  const platformName = ref('منصة إدارة وتمويل الواردات')
  const authority = ref('البنك المركزي اليمني')
  const brandLogoDataUrl = ref<string>('')
  const brandLogoName = ref<string>('')

  function loadSettings() {
    if (typeof localStorage === 'undefined') return
    try {
      const cached = localStorage.getItem(STORAGE_KEY)
      if (!cached) return
      const parsed = JSON.parse(cached)
      platformName.value = parsed.platformName || 'منصة إدارة وتمويل الواردات'
      authority.value = parsed.authority || 'البنك المركزي اليمني'
      brandLogoDataUrl.value = parsed.brandLogoDataUrl || ''
      brandLogoName.value = parsed.brandLogoName || ''
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
    }))
  }

  function setPlatformName(name: string) {
    platformName.value = name || 'منصة إدارة وتمويل الواردات'
    persistToCache()
  }

  function setAuthority(auth: string) {
    authority.value = auth || 'البنك المركزي اليمني'
    persistToCache()
  }

  function setBrandLogo(name: string, dataUrl: string) {
    brandLogoName.value = name
    brandLogoDataUrl.value = dataUrl
    persistToCache()
  }

  function clearBrandLogo() {
    brandLogoName.value = ''
    brandLogoDataUrl.value = ''
    persistToCache()
  }

  return {
    platformName,
    authority,
    brandLogoDataUrl,
    brandLogoName,
    loadSettings,
    setPlatformName,
    setAuthority,
    setBrandLogo,
    clearBrandLogo,
  }
})
