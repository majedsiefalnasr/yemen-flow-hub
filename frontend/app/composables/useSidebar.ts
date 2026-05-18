import { ref } from 'vue'

const STORAGE_KEY = 'sidebar_collapsed'

function readStorage(): boolean {
  if (typeof localStorage === 'undefined') return false
  return localStorage.getItem(STORAGE_KEY) === 'true'
}

const isCollapsed = ref<boolean>(readStorage())

function persist(value: boolean) {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, String(value))
  }
}

export function useSidebar() {
  function toggle() {
    isCollapsed.value = !isCollapsed.value
    persist(isCollapsed.value)
  }

  function collapse() {
    isCollapsed.value = true
    persist(true)
  }

  function expand() {
    isCollapsed.value = false
    persist(false)
  }

  return { isCollapsed, toggle, collapse, expand }
}
