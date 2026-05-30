import { defineStore } from 'pinia'
import { ref } from 'vue'

interface SettingSection {
  [key: string]: any
}

interface SectionDirtyState {
  userProfile: boolean
  workflow: boolean
  email: boolean
  notif: boolean
  security: boolean
  general: boolean
  userAppearance: boolean
  userNotifications: boolean
  bankProfile: boolean
  bankSwift: boolean
  bankNotifications: boolean
  bankSecurity: boolean
  theming: {
    appearance: boolean
    branding: boolean
    accessibility: boolean
  }
}

export const useSettingsStore = defineStore('settings', () => {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const saving = ref(false)

  const dirtyState = ref<SectionDirtyState>({
    userProfile: false,
    workflow: false,
    email: false,
    notif: false,
    security: false,
    general: false,
    userAppearance: false,
    userNotifications: false,
    bankProfile: false,
    bankSwift: false,
    bankNotifications: false,
    bankSecurity: false,
    theming: {
      appearance: false,
      branding: false,
      accessibility: false,
    },
  })

  const originalValues = ref<Record<string, string>>({})
  const currentValues = ref<Record<string, string>>({})

  const sectionKey = (section: string, subsection?: string) => subsection ? `${section}.${subsection}` : section
  const serialize = (data: SettingSection) => JSON.stringify(data)

  const setDirtyFlag = (section: string, dirty: boolean, subsection?: string) => {
    const target = dirtyState.value[section as keyof SectionDirtyState]
    if (subsection) {
      if (typeof target === 'object') {
        (target as Record<string, boolean>)[subsection] = dirty
      }
      return
    }
    if (typeof target === 'boolean') {
      dirtyState.value[section as keyof SectionDirtyState] = dirty as never
    }
  }

  const isSectionDirty = (section: string, subsection?: string) => {
    const target = dirtyState.value[section as keyof SectionDirtyState]
    if (subsection) {
      return typeof target === 'object' ? (target as Record<string, boolean>)[subsection] ?? false : false
    }
    return typeof target === 'boolean' ? target : false
  }

  const markSectionDirty = (section: string, subsection?: string) => {
    setDirtyFlag(section, true, subsection)
  }

  const markSectionClean = (section: string, subsection?: string, data?: SettingSection) => {
    if (data) {
      const key = sectionKey(section, subsection)
      const snapshot = serialize(data)
      originalValues.value[key] = snapshot
      currentValues.value[key] = snapshot
    }
    setDirtyFlag(section, false, subsection)
  }

  const trackSectionState = (section: string, data: SettingSection, subsection?: string) => {
    const key = sectionKey(section, subsection)
    const serialized = serialize(data)
    currentValues.value[key] = serialized

    if (!(key in originalValues.value)) {
      originalValues.value[key] = serialized
      setDirtyFlag(section, false, subsection)
      return
    }

    setDirtyFlag(section, originalValues.value[key] !== serialized, subsection)
  }

  const saveSection = async (section: string, data: SettingSection, subsection?: string) => {
    saving.value = true
    error.value = null

    try {
      const { post } = useApi()

      const payload = {
        section,
        subsection: subsection || null,
        data,
      }

      await post('/api/settings/save-section', payload)

      markSectionClean(section, subsection, data)
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to save settings'
      return false
    } finally {
      saving.value = false
    }
  }

  const setSectionValue = (section: string, key: string, value: any, subsection?: string) => {
    const fullKey = subsection ? `${section}.${subsection}.${key}` : `${section}.${key}`
    currentValues.value[fullKey] = value
    markSectionDirty(section, subsection)
  }

  const resetSection = (section: string, subsection?: string) => {
    const key = sectionKey(section, subsection)
    const current = currentValues.value[key]
    if (current !== undefined) {
      originalValues.value[key] = current
    }
    setDirtyFlag(section, false, subsection)
  }

  return {
    loading,
    error,
    saving,
    dirtyState,
    isSectionDirty,
    markSectionDirty,
    markSectionClean,
    trackSectionState,
    saveSection,
    setSectionValue,
    resetSection,
  }
})
