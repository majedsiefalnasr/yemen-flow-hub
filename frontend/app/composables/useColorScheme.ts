import { ref, watch } from 'vue'

const STORAGE_KEY = 'color-scheme'
const isDark = ref(false)

function applyScheme(dark: boolean) {
  if (typeof document !== 'undefined') {
    document.documentElement.classList.toggle('dark', dark)
  }
}

function toggle() {
  isDark.value = !isDark.value
}

function hydrate() {
  if (typeof localStorage === 'undefined') return
  const stored = localStorage.getItem(STORAGE_KEY)
  isDark.value = stored === 'dark'
  applyScheme(isDark.value)
}

watch(isDark, (dark) => {
  applyScheme(dark)
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light')
  }
})

export function useColorScheme() {
  return { isDark, toggle, hydrate }
}
