import { describe, expect, it } from 'vitest'
import { buildStatusFilterOptions } from '../../../composables/useRequestsColumns'
import { RequestStatus } from '../../../types/enums'

describe('buildStatusFilterOptions', () => {
  it('does not expose per-page client counts when backend totals are absent', () => {
    const options = buildStatusFilterOptions()
    expect(options.every((option) => option.count === undefined)).toBe(true)
  })

  it('uses backend-provided totals when available', () => {
    const options = buildStatusFilterOptions({
      [RequestStatus.BANK_REVIEW]: 12,
      [RequestStatus.SUPPORT_APPROVED]: 3,
    })

    const bankReview = options.find((option) => option.value === RequestStatus.BANK_REVIEW)
    const supportApproved = options.find(
      (option) => option.value === RequestStatus.SUPPORT_APPROVED,
    )
    const draft = options.find((option) => option.value === RequestStatus.DRAFT)

    expect(bankReview?.count).toBe(12)
    expect(supportApproved?.count).toBe(3)
    expect(draft?.count).toBeUndefined()
  })
})
