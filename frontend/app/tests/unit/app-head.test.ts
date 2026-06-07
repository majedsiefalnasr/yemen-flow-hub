import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const APP_SOURCE = readFileSync(new URL('../../app.vue', import.meta.url), 'utf8')
const NATIONAL_COMMITTEE_AR = 'اللجنة الوطنية لتنظيم وتمويل الواردات'

describe('app.vue head metadata defaults', () => {
  it('uses the National Committee identity for platform and authority fallbacks', () => {
    expect(APP_SOURCE).toContain(`orgStore.platformName.trim() || '${NATIONAL_COMMITTEE_AR}'`)
    expect(APP_SOURCE).toContain(`orgStore.authority.trim() || '${NATIONAL_COMMITTEE_AR}'`)
    expect(APP_SOURCE).toContain('apple-mobile-web-app-title')
    expect(APP_SOURCE).toContain('og:title')
    expect(APP_SOURCE).toContain('twitter:title')
    expect(APP_SOURCE).not.toContain("'البنك المركزي اليمني'")
    expect(APP_SOURCE).not.toContain("'منصة إدارة وتمويل الواردات'")
  })
})
