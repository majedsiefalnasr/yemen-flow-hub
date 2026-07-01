import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'
import {
  NOT_ELIGIBLE_LABEL,
  NOT_ELIGIBLE_SUPPORT_LABEL,
  NOT_ELIGIBLE_EXECUTIVE_LABEL,
  STATUS_COLORS,
  STATUS_ICONS,
  STATUS_LABELS,
  SWIFT_DISPLAY_GROUP,
  getBusinessStatus,
  DATA_ENTRY_ROLES,
  BANK_ADMIN_MANAGED_ROLES,
  CBY_OPERATIONAL_ROLES,
  NAV_ITEMS,
  OPERATIONAL_FILTER_ROLES,
  ROUTE_ROLE_MAP,
  ROLE_FILTER_STATUSES,
} from '../../../constants/workflow'

describe('STATUS_COLORS', () => {
  it('covers all RequestStatus values', () => {
    const statuses = Object.values(RequestStatus)
    expect(Object.keys(STATUS_COLORS)).toHaveLength(statuses.length)
    for (const s of statuses) {
      expect(STATUS_COLORS[s], `missing color for ${s}`).toBeDefined()
    }
  })

  it('maps DRAFT to locked gray', () => {
    expect(STATUS_COLORS[RequestStatus.DRAFT]).toBe('#8e8e93')
  })

  it('maps SUBMITTED and BANK_REVIEW to pending amber', () => {
    expect(STATUS_COLORS[RequestStatus.SUBMITTED]).toBe('#ff9f0a')
    expect(STATUS_COLORS[RequestStatus.BANK_REVIEW]).toBe('#ff9f0a')
  })

  it('maps approval/completion statuses to green', () => {
    expect(STATUS_COLORS[RequestStatus.EXECUTIVE_APPROVED]).toBe('#34c759')
    expect(STATUS_COLORS[RequestStatus.CUSTOMS_DECLARATION_ISSUED]).toBe('#34c759')
    expect(STATUS_COLORS[RequestStatus.COMPLETED]).toBe('#34c759')
  })

  it('maps rejection statuses to red', () => {
    expect(STATUS_COLORS[RequestStatus.EXECUTIVE_REJECTED]).toBe('#ff3b30')
    expect(STATUS_COLORS[RequestStatus.SUPPORT_REJECTED]).toBe('#ff3b30')
  })

  it('maps SWIFT-related statuses to SWIFT cyan', () => {
    expect(STATUS_COLORS[RequestStatus.WAITING_FOR_SWIFT]).toBe('#32ade6')
    expect(STATUS_COLORS[RequestStatus.SWIFT_UPLOADED]).toBe('#32ade6')
  })

  it('maps executive voting statuses to voting indigo', () => {
    expect(STATUS_COLORS[RequestStatus.EXECUTIVE_VOTING_OPEN]).toBe('#5856d6')
    expect(STATUS_COLORS[RequestStatus.EXECUTIVE_VOTING_CLOSED]).toBe('#5856d6')
    expect(STATUS_COLORS[RequestStatus.WAITING_FOR_VOTING_OPEN]).toBe('#5856d6')
  })

  it('maps support-review approval stages to voting indigo (not SWIFT cyan)', () => {
    expect(STATUS_COLORS[RequestStatus.BANK_APPROVED]).toBe('#5856d6')
    expect(STATUS_COLORS[RequestStatus.SUPPORT_REVIEW_PENDING]).toBe('#5856d6')
    expect(STATUS_COLORS[RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]).toBe('#5856d6')
    expect(STATUS_COLORS[RequestStatus.SUPPORT_APPROVED]).toBe('#5856d6')
  })
})

describe('STATUS_ICONS', () => {
  it('covers all RequestStatus values', () => {
    const statuses = Object.values(RequestStatus)
    expect(Object.keys(STATUS_ICONS)).toHaveLength(statuses.length)
    for (const s of statuses) {
      expect(STATUS_ICONS[s], `missing icon for ${s}`).toBeDefined()
    }
  })

  it('assigns non-empty icon strings', () => {
    for (const icon of Object.values(STATUS_ICONS)) {
      expect(typeof icon).toBe('string')
      expect(icon.length).toBeGreaterThan(0)
    }
  })
})

describe('getBusinessStatus()', () => {
  describe('BANK_REVIEWER role — shows internal statuses', () => {
    it('returns the correct internal label for SUBMITTED', () => {
      const result = getBusinessStatus(RequestStatus.SUBMITTED, UserRole.BANK_REVIEWER)
      expect(result.label).toBe('مقدم')
      expect(result.color).toBe('#ff9f0a')
      expect(result.icon).toBe('clock')
      expect(result.canonicalStatus).toBe(RequestStatus.SUBMITTED)
    })

    it('returns the correct label for BANK_REVIEW', () => {
      const result = getBusinessStatus(RequestStatus.BANK_REVIEW, UserRole.BANK_REVIEWER)
      expect(result.label).toBe('قيد مراجعة البنك')
      expect(result.canonicalStatus).toBe(RequestStatus.BANK_REVIEW)
    })

    it('returns the correct label for SUPPORT_REVIEW_PENDING', () => {
      const result = getBusinessStatus(RequestStatus.SUPPORT_REVIEW_PENDING, UserRole.BANK_REVIEWER)
      expect(result.label).toBe('بانتظار المراجعة')
    })
  })

  describe('DATA_ENTRY role — simplified business statuses', () => {
    it('returns "مسودة" for DRAFT', () => {
      const result = getBusinessStatus(RequestStatus.DRAFT, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مسودة')
      expect(result.color).toBe('#8e8e93')
    })

    it('returns "معاد للتعديل" for DRAFT_REJECTED_INTERNAL', () => {
      const result = getBusinessStatus(RequestStatus.DRAFT_REJECTED_INTERNAL, UserRole.DATA_ENTRY)
      expect(result.label).toBe('معاد للتعديل')
      expect(result.color).toBe('#ff9f0a')
    })

    it('returns "مقدّم للمراجعة" for SUBMITTED', () => {
      const result = getBusinessStatus(RequestStatus.SUBMITTED, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مقدّم للمراجعة')
    })

    it('returns "مقدّم للمراجعة" for BANK_REVIEW (same bucket as SUBMITTED)', () => {
      const result = getBusinessStatus(RequestStatus.BANK_REVIEW, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مقدّم للمراجعة')
    })

    it('returns "قيد معالجة CBY" for BANK_APPROVED (hides internal CBY stage)', () => {
      const result = getBusinessStatus(RequestStatus.BANK_APPROVED, UserRole.DATA_ENTRY)
      expect(result.label).toBe('قيد معالجة CBY')
    })

    it('returns "قيد معالجة CBY" for SUPPORT_REVIEW_IN_PROGRESS (hidden CBY internal)', () => {
      const result = getBusinessStatus(
        RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
        UserRole.DATA_ENTRY,
      )
      expect(result.label).toBe('قيد معالجة CBY')
    })

    it('returns "قيد معالجة CBY" for WAITING_FOR_SWIFT (hidden CBY internal)', () => {
      const result = getBusinessStatus(RequestStatus.WAITING_FOR_SWIFT, UserRole.DATA_ENTRY)
      expect(result.label).toBe('قيد معالجة CBY')
    })

    it('returns "قيد معالجة CBY" for EXECUTIVE_VOTING_OPEN (hidden CBY internal)', () => {
      const result = getBusinessStatus(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.DATA_ENTRY)
      expect(result.label).toBe('قيد معالجة CBY')
    })

    it('returns Not Eligible for SUPPORT_REJECTED', () => {
      const result = getBusinessStatus(RequestStatus.SUPPORT_REJECTED, UserRole.DATA_ENTRY)
      expect(result.label).toBe(NOT_ELIGIBLE_SUPPORT_LABEL)
      expect(result.color).toBe('#ff3b30')
    })

    it('returns Not Eligible for EXECUTIVE_REJECTED', () => {
      const result = getBusinessStatus(RequestStatus.EXECUTIVE_REJECTED, UserRole.DATA_ENTRY)
      expect(result.label).toBe(NOT_ELIGIBLE_EXECUTIVE_LABEL)
      expect(result.color).toBe('#ff3b30')
    })

    it('does not expose old rejection copy for any Data Entry status label', () => {
      for (const status of Object.values(RequestStatus)) {
        const { label } = getBusinessStatus(status, UserRole.DATA_ENTRY)
        expect(label).not.toMatch(/مرفوض|رفض|Rejected|Declined|Disapproved|Not Approved/)
      }
    })

    it('returns "مكتمل" for EXECUTIVE_APPROVED', () => {
      const result = getBusinessStatus(RequestStatus.EXECUTIVE_APPROVED, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مكتمل')
      expect(result.color).toBe('#34c759')
    })

    it('returns "مكتمل" for COMPLETED', () => {
      const result = getBusinessStatus(RequestStatus.COMPLETED, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مكتمل')
      expect(result.color).toBe('#34c759')
    })

    it('always returns a non-empty label and a valid color/icon for every status', () => {
      for (const status of Object.values(RequestStatus)) {
        const result = getBusinessStatus(status, UserRole.DATA_ENTRY)
        expect(result.label.length, `empty label for ${status}`).toBeGreaterThan(0)
        expect(result.color.startsWith('#'), `invalid color for ${status}`).toBe(true)
        expect(result.icon.length, `empty icon for ${status}`).toBeGreaterThan(0)
      }
    })
  })

  describe('CBY_ADMIN role — shows internal statuses', () => {
    it('returns internal status labels, not simplified', () => {
      const result = getBusinessStatus(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, UserRole.CBY_ADMIN)
      expect(result.label).toBe('قيد المراجعة')
    })
  })
})

describe('STATUS_LABELS — Story 17-F Not Eligible terminology', () => {
  it('uses the canonical Not Eligible phrase for every terminal not-eligible status', () => {
    for (const status of [
      RequestStatus.BANK_REJECTED,
      RequestStatus.SUPPORT_REJECTED,
      RequestStatus.EXECUTIVE_REJECTED,
    ]) {
      expect(STATUS_LABELS[status]).toContain(NOT_ELIGIBLE_LABEL)
      expect(STATUS_LABELS[status]).not.toMatch(
        /مرفوض|رفض|Rejected|Declined|Disapproved|Not Approved/,
      )
    }
  })
})

describe('Role group constants', () => {
  describe('DATA_ENTRY_ROLES', () => {
    it('contains only DATA_ENTRY', () => {
      expect(DATA_ENTRY_ROLES).toEqual([UserRole.DATA_ENTRY])
    })
  })

  describe('CBY_OPERATIONAL_ROLES', () => {
    it('contains SWIFT_OFFICER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN', () => {
      expect(CBY_OPERATIONAL_ROLES).toContain(UserRole.SWIFT_OFFICER)
      expect(CBY_OPERATIONAL_ROLES).toContain(UserRole.SUPPORT_COMMITTEE)
      expect(CBY_OPERATIONAL_ROLES).toContain(UserRole.EXECUTIVE_MEMBER)
      expect(CBY_OPERATIONAL_ROLES).toContain(UserRole.COMMITTEE_DIRECTOR)
      expect(CBY_OPERATIONAL_ROLES).toContain(UserRole.CBY_ADMIN)
    })

    it('does not contain DATA_ENTRY or BANK_REVIEWER', () => {
      expect(CBY_OPERATIONAL_ROLES).not.toContain(UserRole.DATA_ENTRY)
      expect(CBY_OPERATIONAL_ROLES).not.toContain(UserRole.BANK_REVIEWER)
      expect(CBY_OPERATIONAL_ROLES).not.toContain(UserRole.BANK_ADMIN)
    })
  })

  describe('BANK_ADMIN_MANAGED_ROLES', () => {
    it('allows only DATA_ENTRY and BANK_REVIEWER management', () => {
      expect(BANK_ADMIN_MANAGED_ROLES).toEqual([UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER])
      expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.BANK_ADMIN)
      expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.CBY_ADMIN)
      expect(BANK_ADMIN_MANAGED_ROLES).not.toContain(UserRole.SWIFT_OFFICER)
    })
  })

  describe('OPERATIONAL_FILTER_ROLES', () => {
    it('includes BANK_REVIEWER, BANK_ADMIN, and all CBY_OPERATIONAL_ROLES', () => {
      expect(OPERATIONAL_FILTER_ROLES).toContain(UserRole.BANK_REVIEWER)
      expect(OPERATIONAL_FILTER_ROLES).toContain(UserRole.BANK_ADMIN)
      for (const role of CBY_OPERATIONAL_ROLES) {
        expect(OPERATIONAL_FILTER_ROLES).toContain(role)
      }
    })

    it('does not include DATA_ENTRY', () => {
      expect(OPERATIONAL_FILTER_ROLES).not.toContain(UserRole.DATA_ENTRY)
    })
  })

  describe('ROLE_FILTER_STATUSES', () => {
    it('BANK_REVIEWER filter statuses are bank-workflow-relevant', () => {
      const statuses = ROLE_FILTER_STATUSES[UserRole.BANK_REVIEWER]!
      expect(statuses).toContain(RequestStatus.SUBMITTED)
      expect(statuses).toContain(RequestStatus.BANK_REVIEW)
      expect(statuses).toContain(RequestStatus.BANK_APPROVED)
      expect(statuses).not.toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
      expect(statuses).not.toContain(RequestStatus.WAITING_FOR_SWIFT)
    })

    it('SWIFT_OFFICER filter statuses are SWIFT-workflow-relevant', () => {
      const statuses = ROLE_FILTER_STATUSES[UserRole.SWIFT_OFFICER]!
      expect(statuses).toContain(RequestStatus.WAITING_FOR_SWIFT)
      expect(statuses).toContain(RequestStatus.SWIFT_UPLOADED)
      expect(statuses).not.toContain(RequestStatus.DRAFT)
      expect(statuses).not.toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
    })

    it('EXECUTIVE_MEMBER filter statuses are voting-relevant', () => {
      const statuses = ROLE_FILTER_STATUSES[UserRole.EXECUTIVE_MEMBER]!
      expect(statuses).toContain(RequestStatus.EXECUTIVE_VOTING_OPEN)
      expect(statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
      expect(statuses).not.toContain(RequestStatus.DRAFT)
      expect(statuses).not.toContain(RequestStatus.BANK_REVIEW)
    })

    it('CBY_ADMIN has no entry — shows all statuses in filter', () => {
      expect(ROLE_FILTER_STATUSES[UserRole.CBY_ADMIN]).toBeUndefined()
    })

    it('BANK_ADMIN has full own-bank status filters', () => {
      const statuses = ROLE_FILTER_STATUSES[UserRole.BANK_ADMIN]!
      expect(statuses).toContain(RequestStatus.DRAFT)
      expect(statuses).toContain(RequestStatus.DRAFT_REJECTED_INTERNAL)
      expect(statuses).toContain(RequestStatus.SUBMITTED)
      expect(statuses).toContain(RequestStatus.EXECUTIVE_APPROVED)
    })

    it('all defined filter status lists contain only valid RequestStatus values', () => {
      const validStatuses = new Set(Object.values(RequestStatus))
      for (const [, statuses] of Object.entries(ROLE_FILTER_STATUSES)) {
        if (!statuses) continue
        for (const s of statuses) {
          expect(validStatuses.has(s), `invalid status: ${s}`).toBe(true)
        }
      }
    })
  })

  describe('Navigation and route map', () => {
    it('allows BANK_ADMIN to reach staff, merchants, and reports routes', () => {
      expect(ROUTE_ROLE_MAP['/staff']).toContain(UserRole.BANK_ADMIN)
      expect(ROUTE_ROLE_MAP['/merchants']).toContain(UserRole.BANK_ADMIN)
      expect(ROUTE_ROLE_MAP['/audit']).not.toContain(UserRole.BANK_ADMIN)
      expect(ROUTE_ROLE_MAP['/reports']).toContain(UserRole.BANK_ADMIN)
      expect(ROUTE_ROLE_MAP['/reports']).not.toContain(UserRole.SUPPORT_COMMITTEE)
      expect(ROUTE_ROLE_MAP['/workflows/new']).toEqual([UserRole.DATA_ENTRY])
    })

    it('limits external-FX route to COMMITTEE_DIRECTOR', () => {
      expect(ROUTE_ROLE_MAP['/customs']).toEqual([UserRole.COMMITTEE_DIRECTOR])
      expect(ROUTE_ROLE_MAP['/customs']).not.toContain(UserRole.CBY_ADMIN)
    })

    it('shows scoped administration nav items for BANK_ADMIN including reports', () => {
      const bankAdminRoutes = NAV_ITEMS.filter((item) =>
        item.roles.includes(UserRole.BANK_ADMIN),
      ).map((item) => item.route)

      expect(bankAdminRoutes).toContain('/staff')
      expect(bankAdminRoutes).toContain('/merchants')
      expect(bankAdminRoutes).not.toContain('/audit')
      expect(bankAdminRoutes).toContain('/reports')
    })

    it('keeps reports hidden for DATA_ENTRY and BANK_REVIEWER nav', () => {
      const dataEntryRoutes = NAV_ITEMS.filter((item) =>
        item.roles.includes(UserRole.DATA_ENTRY),
      ).map((item) => item.route)
      const reviewerRoutes = NAV_ITEMS.filter((item) =>
        item.roles.includes(UserRole.BANK_REVIEWER),
      ).map((item) => item.route)
      expect(dataEntryRoutes).not.toContain('/reports')
      expect(reviewerRoutes).not.toContain('/reports')
    })
  })
})

// ─── Story 17-E.4: "Returned to Data Entry" label + SWIFT display merge ──────
describe('STATUS_LABELS — Story 17-E.4 BANK_RETURNED rename', () => {
  it('BANK_RETURNED is the single source for the "Returned to Data Entry" label', () => {
    expect(STATUS_LABELS[RequestStatus.BANK_RETURNED]).toBe('أُعيد إلى مدخل البيانات')
  })

  it('drops the legacy "أُعيد للمدخل من البنك" wording for BANK_RETURNED', () => {
    expect(STATUS_LABELS[RequestStatus.BANK_RETURNED]).not.toContain('من البنك')
    expect(STATUS_LABELS[RequestStatus.BANK_RETURNED]).not.toContain('للمراجعة')
  })

  it('covers every RequestStatus and leaves the other labels unchanged', () => {
    const statuses = Object.values(RequestStatus)
    expect(Object.keys(STATUS_LABELS)).toHaveLength(statuses.length)
    expect(STATUS_LABELS[RequestStatus.SUPPORT_RETURNED]).toBe('إعادة من المساندة')
    expect(STATUS_LABELS[RequestStatus.BANK_REVIEW]).toBe('قيد مراجعة البنك')
  })
})

describe('SWIFT_DISPLAY_GROUP — Story 17-E.4 display merge (D8, display-only)', () => {
  it('groups WAITING_FOR_SWIFT and SWIFT_UPLOADED under one display label', () => {
    expect(SWIFT_DISPLAY_GROUP.label).toBe('تم رفع السويفت')
    expect(SWIFT_DISPLAY_GROUP.statuses).toEqual([
      RequestStatus.WAITING_FOR_SWIFT,
      RequestStatus.SWIFT_UPLOADED,
    ])
  })

  it('does NOT mutate the underlying granular STATUS_LABELS (statuses stay distinct)', () => {
    // AC5: the two enum cases remain distinct in every non-timeline surface.
    expect(STATUS_LABELS[RequestStatus.WAITING_FOR_SWIFT]).toBe('انتظار رفع SWIFT')
    expect(STATUS_LABELS[RequestStatus.SWIFT_UPLOADED]).toBe('تم رفع SWIFT')
    expect(STATUS_LABELS[RequestStatus.WAITING_FOR_SWIFT]).not.toBe(
      STATUS_LABELS[RequestStatus.SWIFT_UPLOADED],
    )
  })
})
