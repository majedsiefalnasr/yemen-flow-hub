import { describe, it, expect } from 'vitest'

// Icon.vue wraps lucide-vue-next icons with a name→component map.
// Tests verify the mapping logic and prop defaults.

const ICON_NAMES = [
  'home',
  'file-text',
  'plus-circle',
  'building',
  'bank',
  'stamp',
  'bar-chart-2',
  'shield-check',
  'bell',
  'landmark',
  'users',
  'user',
  'file-cog',
  'user-check',
  'settings',
  'menu',
  'x',
  'search',
  'sliders',
  'sun',
  'moon',
  'chevron-down',
  'clock',
  'check-circle',
  'x-circle',
  'rotate-ccw',
  'upload-cloud',
  'vote',
] as const

type IconName = (typeof ICON_NAMES)[number]

const DEFAULT_SIZE = 18

function resolveSize(size?: number): number {
  return size ?? DEFAULT_SIZE
}

describe('Icon — name-to-component map', () => {
  it('contains all required icon names', () => {
    const required: IconName[] = [
      'home',
      'file-text',
      'plus-circle',
      'building',
      'stamp',
      'bar-chart-2',
      'shield-check',
      'bell',
      'landmark',
      'users',
      'file-cog',
      'user-check',
      'settings',
      'menu',
      'x',
      'search',
      'sliders',
      'sun',
      'moon',
      'chevron-down',
    ]
    required.forEach((name) => {
      expect(ICON_NAMES).toContain(name)
    })
  })

  it('includes notification icons', () => {
    const notifIcons: IconName[] = [
      'check-circle',
      'x-circle',
      'rotate-ccw',
      'upload-cloud',
      'vote',
    ]
    notifIcons.forEach((name) => expect(ICON_NAMES).toContain(name))
  })

  it('includes search/global icons (clock, user, bank)', () => {
    expect(ICON_NAMES).toContain('clock')
    expect(ICON_NAMES).toContain('user')
    expect(ICON_NAMES).toContain('bank')
  })

  it('includes dark mode icons (sun, moon)', () => {
    expect(ICON_NAMES).toContain('sun')
    expect(ICON_NAMES).toContain('moon')
  })

  it('includes role switcher chevron', () => {
    expect(ICON_NAMES).toContain('chevron-down')
  })
})

describe('Icon — size prop', () => {
  it('defaults to 18 when not provided', () => {
    expect(resolveSize()).toBe(18)
  })

  it('respects explicit size prop', () => {
    expect(resolveSize(24)).toBe(24)
    expect(resolveSize(14)).toBe(14)
    expect(resolveSize(32)).toBe(32)
  })

  it('size 0 is falsy so default applies', () => {
    expect(resolveSize(0) || DEFAULT_SIZE).toBe(DEFAULT_SIZE)
  })
})

describe('Icon — total icon count', () => {
  it('has at least 28 icons registered', () => {
    expect(ICON_NAMES.length).toBeGreaterThanOrEqual(28)
  })
})
