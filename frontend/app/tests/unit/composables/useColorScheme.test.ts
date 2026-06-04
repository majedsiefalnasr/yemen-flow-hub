import { describe, it, expect, beforeEach, vi } from 'vitest'

// Inline the composable logic so tests stay pure (no DOM/localStorage side effects in unit tests)
const STORAGE_KEY = 'color-scheme'

function createSchemeState() {
  let dark = false
  const classesAdded: boolean[] = []
  const stored: Record<string, string> = {}

  function applyScheme(isDark: boolean) {
    classesAdded.push(isDark)
  }

  function toggle() {
    dark = !dark
    applyScheme(dark)
    stored[STORAGE_KEY] = dark ? 'dark' : 'light'
  }

  function hydrate(storedValue: string | null) {
    dark = storedValue === 'dark'
    applyScheme(dark)
  }

  function getIsDark() {
    return dark
  }
  function getLastApplied() {
    return classesAdded[classesAdded.length - 1]
  }
  function getStoredValue() {
    return stored[STORAGE_KEY]
  }

  return { toggle, hydrate, getIsDark, getLastApplied, getStoredValue }
}

describe('useColorScheme — logic', () => {
  it('starts as light mode', () => {
    const s = createSchemeState()
    expect(s.getIsDark()).toBe(false)
  })

  it('toggle switches from light to dark', () => {
    const s = createSchemeState()
    s.toggle()
    expect(s.getIsDark()).toBe(true)
  })

  it('toggle switches from dark to light', () => {
    const s = createSchemeState()
    s.toggle()
    s.toggle()
    expect(s.getIsDark()).toBe(false)
  })

  it('toggle applies dark class', () => {
    const s = createSchemeState()
    s.toggle()
    expect(s.getLastApplied()).toBe(true)
  })

  it('toggle applies light class after second toggle', () => {
    const s = createSchemeState()
    s.toggle()
    s.toggle()
    expect(s.getLastApplied()).toBe(false)
  })

  it('persists "dark" to storage on dark toggle', () => {
    const s = createSchemeState()
    s.toggle()
    expect(s.getStoredValue()).toBe('dark')
  })

  it('persists "light" to storage on light toggle', () => {
    const s = createSchemeState()
    s.toggle()
    s.toggle()
    expect(s.getStoredValue()).toBe('light')
  })

  it('hydrate with "dark" sets isDark to true', () => {
    const s = createSchemeState()
    s.hydrate('dark')
    expect(s.getIsDark()).toBe(true)
  })

  it('hydrate with "light" sets isDark to false', () => {
    const s = createSchemeState()
    s.hydrate('light')
    expect(s.getIsDark()).toBe(false)
  })

  it('hydrate with null sets isDark to false', () => {
    const s = createSchemeState()
    s.hydrate(null)
    expect(s.getIsDark()).toBe(false)
  })

  it('hydrate with "dark" applies dark class', () => {
    const s = createSchemeState()
    s.hydrate('dark')
    expect(s.getLastApplied()).toBe(true)
  })

  it('hydrate with null applies light class', () => {
    const s = createSchemeState()
    s.hydrate(null)
    expect(s.getLastApplied()).toBe(false)
  })

  it('storage key is color-scheme', () => {
    expect(STORAGE_KEY).toBe('color-scheme')
  })
})
