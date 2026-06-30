import { describe, expect, it } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import { getRequestProgress } from '../../../utils/requestProgress'

describe('getRequestProgress', () => {
  it('covers the early workflow stages in ascending order', () => {
    expect(getRequestProgress(RequestStatus.DRAFT)).toBeLessThan(
      getRequestProgress(RequestStatus.SUBMITTED),
    )
    expect(getRequestProgress(RequestStatus.SUBMITTED)).toBeLessThan(
      getRequestProgress(RequestStatus.BANK_APPROVED),
    )
  })

  it('marks terminal rejected states as complete progress', () => {
    expect(getRequestProgress(RequestStatus.SUPPORT_REJECTED)).toBe(100)
    expect(getRequestProgress(RequestStatus.EXECUTIVE_REJECTED)).toBe(100)
  })

  it('marks completed lifecycle states as 100%', () => {
    expect(getRequestProgress(RequestStatus.CUSTOMS_DECLARATION_ISSUED)).toBe(100)
    expect(getRequestProgress(RequestStatus.FX_CONFIRMATION_PENDING)).toBe(98)
    expect(getRequestProgress(RequestStatus.COMPLETED)).toBe(100)
  })
})
