import { beforeEach, describe, expect, it, vi } from 'vitest'

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
vi.stubGlobal('window', {} as Window & typeof globalThis)

beforeEach(() => {
  localStorageMock.clear()
  vi.resetModules()
})

describe('useSavedAccounts — PIN behavior', () => {
  it('stores and verifies PIN case-insensitively by email', async () => {
    const { useSavedAccounts } = await import('../../../composables/useSavedAccounts')
    const { setPIN, verifyPIN } = useSavedAccounts()

    setPIN('User@Example.COM', '123456')

    expect(verifyPIN('user@example.com', '123456')).toBe(true)
    expect(verifyPIN('USER@EXAMPLE.COM', '123456')).toBe(true)
  })

  it('verifies Arabic-Indic digits against stored ASCII PIN', async () => {
    const { useSavedAccounts } = await import('../../../composables/useSavedAccounts')
    const { setPIN, verifyPIN } = useSavedAccounts()

    setPIN('user@example.com', '123456')

    expect(verifyPIN('user@example.com', '١٢٣٤٥٦')).toBe(true)
    expect(verifyPIN('user@example.com', '۱۲۳۴۵۶')).toBe(true)
  })

  it('reads legacy mixed-case storage keys for PIN status and PIN value', async () => {
    localStorageMock.setItem('yfh-pin-status', JSON.stringify({ 'Legacy@Example.com': true }))
    localStorageMock.setItem('yfh-pin-data', JSON.stringify({ 'Legacy@Example.com': '123456' }))

    const { useSavedAccounts } = await import('../../../composables/useSavedAccounts')
    const { getPINStatus, verifyPIN } = useSavedAccounts()

    expect(getPINStatus('legacy@example.com')).toBe(true)
    expect(verifyPIN('legacy@example.com', '123456')).toBe(true)
  })

  it('accepts legacy numeric and object PIN value formats', async () => {
    localStorageMock.setItem('yfh-pin-data', JSON.stringify({
      'admin@cby.gov.ye': 125812,
      'legacy@cby.gov.ye': { pin: '125812' },
      'legacy2@cby.gov.ye': { code: 125812 },
    }))

    const { useSavedAccounts } = await import('../../../composables/useSavedAccounts')
    const { verifyPIN, hasStoredPIN } = useSavedAccounts()

    expect(hasStoredPIN('admin@cby.gov.ye')).toBe(true)
    expect(verifyPIN('admin@cby.gov.ye', '125812')).toBe(true)
    expect(verifyPIN('legacy@cby.gov.ye', '125812')).toBe(true)
    expect(verifyPIN('legacy2@cby.gov.ye', '125812')).toBe(true)
  })
})
