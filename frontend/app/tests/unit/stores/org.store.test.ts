// @vitest-environment jsdom
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useOrgStore } from '../../../stores/org.store'

const NATIONAL_COMMITTEE_AR = 'اللجنة الوطنية لتنظيم وتمويل الواردات'

describe('org.store defaults', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('uses the National Committee identity as the default platform and authority', () => {
    const store = useOrgStore()

    expect(store.platformName).toBe(NATIONAL_COMMITTEE_AR)
    expect(store.authority).toBe(NATIONAL_COMMITTEE_AR)
  })
})
