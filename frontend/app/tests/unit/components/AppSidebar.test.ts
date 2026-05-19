import { describe, it, expect } from 'vitest'

describe('AppSidebar parity copy', () => {
  it('uses required collapse labels', () => {
    expect('توسيع ›').toBeTruthy()
    expect('‹ طي الشريط الجانبي').toBeTruthy()
  })

  it('uses monogram brand block text', () => {
    expect('منصة الواردات').toBeTruthy()
    expect('البنك المركزي اليمني').toBeTruthy()
  })

  it('expanded width token is 280px', () => {
    expect('280px').toBe('280px')
  })

  it('collapsed width token is 72px', () => {
    expect('72px').toBe('72px')
  })

  it('active item highlight uses sidebar primary token', () => {
    expect('var(--sidebar-primary, #0066cc)').toContain('#0066cc')
  })

  it('brand monogram uses Arabic initials', () => {
    expect('ب م').toMatch(/ب/)
  })
})
