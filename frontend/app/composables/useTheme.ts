import { computed, ref } from 'vue'

type Theme = 'light' | 'dark' | 'system'

const THEME_STORAGE_KEY = 'app-theme'

export const useTheme = () => {
  const theme = ref<Theme>('light')
  const mounted = ref(false)

  const isDark = computed(() => {
    if (theme.value === 'system') {
      if (typeof window === 'undefined') return false
      return window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    return theme.value === 'dark'
  })

  const initTheme = () => {
    if (typeof window === 'undefined') return

    // Get saved theme from localStorage
    const saved = localStorage.getItem(THEME_STORAGE_KEY) as Theme | null
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches

    // Determine initial theme
    let initialTheme: Theme = saved || 'system'

    // Apply theme to DOM
    applyTheme(initialTheme)
    theme.value = initialTheme
    mounted.value = true

    // Listen to system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (theme.value === 'system') {
        applyTheme('system')
      }
    })
  }

  const applyTheme = (newTheme: Theme) => {
    const html = document.documentElement
    const shouldBeDark = newTheme === 'dark' ||
      (newTheme === 'system' && typeof window !== 'undefined' &&
       window.matchMedia('(prefers-color-scheme: dark)').matches)

    if (shouldBeDark) {
      html.classList.add('dark')
    } else {
      html.classList.remove('dark')
    }
  }

  const setTheme = (newTheme: Theme) => {
    theme.value = newTheme
    applyTheme(newTheme)
    if (typeof window !== 'undefined') {
      localStorage.setItem(THEME_STORAGE_KEY, newTheme)
    }
  }

  return {
    theme: computed(() => theme.value),
    isDark,
    mounted,
    initTheme,
    setTheme,
  }
}
