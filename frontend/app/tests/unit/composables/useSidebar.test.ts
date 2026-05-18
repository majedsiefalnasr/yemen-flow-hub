import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {}
  return {
    getItem: (key: string) => store[key] ?? null,
    setItem: (key: string, value: string) => { store[key] = value },
    removeItem: (key: string) => { delete store[key] },
    clear: () => { store = {} },
  }
})()

vi.stubGlobal('localStorage', localStorageMock)
vi.stubGlobal('import', { meta: { client: true } })

// Reset module registry so each test gets a fresh composable instance
beforeEach(() => {
  localStorageMock.clear()
  vi.resetModules()
})

describe('useSidebar — toggle', () => {
  it('starts expanded by default (no localStorage value)', async () => {
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { isCollapsed } = useSidebar()
    expect(isCollapsed.value).toBe(false)
  })

  it('toggle() switches collapsed state', async () => {
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { isCollapsed, toggle } = useSidebar()
    expect(isCollapsed.value).toBe(false)
    toggle()
    expect(isCollapsed.value).toBe(true)
    toggle()
    expect(isCollapsed.value).toBe(false)
  })

  it('collapse() always sets to collapsed', async () => {
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { isCollapsed, collapse } = useSidebar()
    collapse()
    expect(isCollapsed.value).toBe(true)
    collapse()
    expect(isCollapsed.value).toBe(true)
  })

  it('expand() always sets to expanded', async () => {
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { isCollapsed, expand, collapse } = useSidebar()
    collapse()
    expect(isCollapsed.value).toBe(true)
    expand()
    expect(isCollapsed.value).toBe(false)
  })
})

describe('useSidebar — localStorage persistence', () => {
  it('persists collapsed state to localStorage on toggle', async () => {
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { toggle } = useSidebar()
    toggle()
    expect(localStorageMock.getItem('sidebar_collapsed')).toBe('true')
    toggle()
    expect(localStorageMock.getItem('sidebar_collapsed')).toBe('false')
  })

  it('reads collapsed=true from localStorage on init', async () => {
    localStorageMock.setItem('sidebar_collapsed', 'true')
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { isCollapsed } = useSidebar()
    expect(isCollapsed.value).toBe(true)
  })

  it('reads collapsed=false from localStorage on init', async () => {
    localStorageMock.setItem('sidebar_collapsed', 'false')
    const { useSidebar } = await import('../../../composables/useSidebar')
    const { isCollapsed } = useSidebar()
    expect(isCollapsed.value).toBe(false)
  })
})
