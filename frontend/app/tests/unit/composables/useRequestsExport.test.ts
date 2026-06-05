import { describe, expect, it } from 'vitest'
import { buildRequestsExportColumns } from '../../../composables/useRequestsExport'
import { UserRole } from '../../../types/enums'

function labelsFor(role: UserRole): string[] {
  return buildRequestsExportColumns(role).map((column) => column.label)
}

describe('buildRequestsExportColumns', () => {
  it('keeps core columns for all roles', () => {
    const labels = labelsFor(UserRole.DATA_ENTRY)
    expect(labels).toContain('المرجع')
    expect(labels).toContain('المستورد')
    expect(labels).toContain('المورد')
    expect(labels).toContain('المبلغ')
    expect(labels).toContain('العملة')
    expect(labels).toContain('الحالة')
  })

  it('hides bank/owner columns for bank-scoped roles', () => {
    const labels = labelsFor(UserRole.BANK_REVIEWER)
    expect(labels).not.toContain('البنك')
    expect(labels).not.toContain('المالك الحالي')
  })

  it('shows bank-level governance columns for CBY roles', () => {
    const labels = labelsFor(UserRole.CBY_ADMIN)
    expect(labels).toContain('البنك')
    expect(labels).toContain('المالك الحالي')
  })

  it('shows claim-state only for SUPPORT_COMMITTEE', () => {
    expect(labelsFor(UserRole.SUPPORT_COMMITTEE)).toContain('حالة الحجز')
    expect(labelsFor(UserRole.DATA_ENTRY)).not.toContain('حالة الحجز')
    expect(labelsFor(UserRole.CBY_ADMIN)).not.toContain('حالة الحجز')
  })

  it('shows voting-state for executive/director/cby only', () => {
    expect(labelsFor(UserRole.EXECUTIVE_MEMBER)).toContain('حالة التصويت')
    expect(labelsFor(UserRole.COMMITTEE_DIRECTOR)).toContain('حالة التصويت')
    expect(labelsFor(UserRole.CBY_ADMIN)).toContain('حالة التصويت')
    expect(labelsFor(UserRole.BANK_ADMIN)).not.toContain('حالة التصويت')
  })
})
