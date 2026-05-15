import { describe, it, expect } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'
import {
  STATUS_COLORS,
  STATUS_ICONS,
  getBusinessStatus,
} from '../../../constants/workflow'

describe('STATUS_COLORS', () => {
  it('covers all 18 RequestStatus values', () => {
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
})

describe('STATUS_ICONS', () => {
  it('covers all 18 RequestStatus values', () => {
    const statuses = Object.values(RequestStatus)
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
      expect(result.label).toBe('مُقدَّم')
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
      expect(result.label).toBe('انتظار لجنة الدعم')
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
      const result = getBusinessStatus(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS, UserRole.DATA_ENTRY)
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

    it('returns "مرفوض" for SUPPORT_REJECTED', () => {
      const result = getBusinessStatus(RequestStatus.SUPPORT_REJECTED, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مرفوض')
      expect(result.color).toBe('#ff3b30')
    })

    it('returns "مرفوض نهائياً" for EXECUTIVE_REJECTED', () => {
      const result = getBusinessStatus(RequestStatus.EXECUTIVE_REJECTED, UserRole.DATA_ENTRY)
      expect(result.label).toBe('مرفوض نهائياً')
      expect(result.color).toBe('#ff3b30')
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
      expect(result.label).toBe('قيد مراجعة الدعم')
    })
  })
})
