import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { UserPreferences } from '@/types/models'

interface SettingSection {
  [key: string]: any
}

interface SectionDirtyState {
  workflow: boolean
  email: boolean
  notif: boolean
  security: boolean
  general: boolean
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
    workflow: false,
    email: false,
    notif: false,
    security: false,
    general: false,
    theming: {
      appearance: false,
      branding: false,
      accessibility: false,
    },
  })

  const originalValues = ref<Record<string, any>>({})
  const currentValues = ref<Record<string, any>>({})

  const isSectionDirty = (section: string, subsection?: string) => {
    if (subsection) {
      return dirtyState.value[section as keyof SectionDirtyState]?.[subsection] ?? false
    }
    return dirtyState.value[section as keyof SectionDirtyState] ?? false
  }

  const markSectionDirty = (section: string, subsection?: string) => {
    if (subsection) {
      if (typeof dirtyState.value[section as keyof SectionDirtyState] === 'object') {
        (dirtyState.value[section as keyof SectionDirtyState] as any)[subsection] = true
      }
    } else {
      dirtyState.value[section as keyof SectionDirtyState] = true
    }
  }

  const markSectionClean = (section: string, subsection?: string) => {
    if (subsection) {
      if (typeof dirtyState.value[section as keyof SectionDirtyState] === 'object') {
        (dirtyState.value[section as keyof SectionDirtyState] as any)[subsection] = false
      }
    } else {
      dirtyState.value[section as keyof SectionDirtyState] = false
    }
  }

  const saveSection = async (section: string, data: SettingSection, subsection?: string) => {
    saving.value = true
    error.value = null

    try {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      const payload = {
        section,
        subsection: subsection || null,
        data,
      }

      const response = await $fetch('/api/settings/save-section', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: payload,
      })

      markSectionClean(section, subsection)
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
    if (subsection) {
      if (typeof dirtyState.value[section as keyof SectionDirtyState] === 'object') {
        (dirtyState.value[section as keyof SectionDirtyState] as any)[subsection] = false
      }
    } else {
      dirtyState.value[section as keyof SectionDirtyState] = false
    }
  }

  return {
    loading,
    error,
    saving,
    dirtyState,
    isSectionDirty,
    markSectionDirty,
    markSectionClean,
    saveSection,
    setSectionValue,
    resetSection,
  }
})
