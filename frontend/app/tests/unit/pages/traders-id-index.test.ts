import { describe, expect, it } from 'vitest'
import TraderDetailPage from '../../../pages/traders/[id]/index.vue'
import { isMajorOwner } from '../../../types/trader'

describe('/traders/[id] page', () => {
  it('loads the read-only trader detail page module', () => {
    expect(TraderDetailPage).toBeTruthy()
  })

  it('marks owners at or above 25% as major owners', () => {
    expect(isMajorOwner({ ownership_percentage: 25 })).toBe(true)
    expect(isMajorOwner({ ownership_percentage: 5 })).toBe(false)
  })
})
