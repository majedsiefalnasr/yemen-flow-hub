/** RequestProgress — logic-only tests (no DOM mount; env is node per vitest.config.ts) */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import { STATUS_PROGRESS } from '../../../constants/workflow'

describe('RequestProgress logic via STATUS_PROGRESS', () => {
  it('DRAFT progress is 5', () => {
    expect(STATUS_PROGRESS[RequestStatus.DRAFT]).toBe(5)
  })

  it('COMPLETED progress is 100', () => {
    expect(STATUS_PROGRESS[RequestStatus.COMPLETED]).toBe(100)
  })

  it('SUBMITTED progress is greater than DRAFT', () => {
    expect(STATUS_PROGRESS[RequestStatus.SUBMITTED]).toBeGreaterThan(
      STATUS_PROGRESS[RequestStatus.DRAFT],
    )
  })

  it('EXECUTIVE_APPROVED progress is greater than BANK_APPROVED', () => {
    expect(STATUS_PROGRESS[RequestStatus.EXECUTIVE_APPROVED]).toBeGreaterThan(
      STATUS_PROGRESS[RequestStatus.BANK_APPROVED],
    )
  })

  it('all values are numbers in [0, 100]', () => {
    for (const [status, pct] of Object.entries(STATUS_PROGRESS)) {
      expect(typeof pct, `${status} not a number`).toBe('number')
      expect(pct, `${status} out of range`).toBeGreaterThanOrEqual(0)
      expect(pct, `${status} out of range`).toBeLessThanOrEqual(100)
    }
  })

  it('unknown status defaults to 0 via nullish coalescing', () => {
    const unknown = 'UNKNOWN_STATUS' as RequestStatus
    const val = STATUS_PROGRESS[unknown] ?? 0
    expect(val).toBe(0)
  })
})
