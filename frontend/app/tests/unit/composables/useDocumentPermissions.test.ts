import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import {
  canDownloadDocument,
  canDownloadCustoms,
  canDownloadSignedFxDoc,
  canViewConfirmationRequestPreview,
  canUploadDocument,
  isDocumentModificationLocked,
} from '../../../composables/useDocumentPermissions'

// ─── canDownloadDocument ──────────────────────────────────────────────────────

describe('canDownloadDocument — REQUEST_DOC type', () => {
  const DOC_TYPE = 'REQUEST_DOC'

  it.each(Object.values(UserRole))('returns true for role %s', (role) => {
    expect(canDownloadDocument(role, DOC_TYPE)).toBe(true)
  })
})

describe('canDownloadDocument — SWIFT type', () => {
  const SWIFT_ALLOWED = [
    UserRole.BANK_REVIEWER,
    UserRole.BANK_ADMIN,
    UserRole.SWIFT_OFFICER,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ] as const

  const SWIFT_DENIED = [
    UserRole.DATA_ENTRY,
    UserRole.SUPPORT_COMMITTEE,
  ] as const

  it.each(SWIFT_ALLOWED)('returns true for allowed role %s', (role) => {
    expect(canDownloadDocument(role, 'SWIFT')).toBe(true)
  })

  it.each(SWIFT_DENIED)('returns false for denied role %s', (role) => {
    expect(canDownloadDocument(role, 'SWIFT')).toBe(false)
  })
})

describe('canDownloadDocument — FX_REQUEST type', () => {
  const FX_ALLOWED = [
    UserRole.BANK_REVIEWER,
    UserRole.BANK_ADMIN,
    UserRole.SWIFT_OFFICER,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ] as const

  const FX_DENIED = [
    UserRole.DATA_ENTRY,
    UserRole.SUPPORT_COMMITTEE,
  ] as const

  it.each(FX_ALLOWED)('returns true for allowed role %s', (role) => {
    expect(canDownloadDocument(role, 'FX_REQUEST')).toBe(true)
  })

  it.each(FX_DENIED)('returns false for denied role %s', (role) => {
    expect(canDownloadDocument(role, 'FX_REQUEST')).toBe(false)
  })
})

describe('canDownloadDocument — unknown / null type', () => {
  it('returns true for null type (treated as REQUEST_DOC)', () => {
    expect(canDownloadDocument(UserRole.DATA_ENTRY, null)).toBe(true)
    expect(canDownloadDocument(UserRole.SUPPORT_COMMITTEE, null)).toBe(true)
  })

  it('returns true for unknown string type for all roles', () => {
    for (const role of Object.values(UserRole)) {
      expect(canDownloadDocument(role, 'COMMERCIAL_INVOICE')).toBe(true)
    }
  })
})

// ─── canDownloadCustoms ───────────────────────────────────────────────────────

describe('canDownloadCustoms', () => {
  const CUSTOMS_ALLOWED = [
    UserRole.BANK_REVIEWER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ] as const

  const CUSTOMS_DENIED = [
    UserRole.DATA_ENTRY,
    UserRole.SWIFT_OFFICER,
    UserRole.SUPPORT_COMMITTEE,
    UserRole.EXECUTIVE_MEMBER,
  ] as const

  it.each(CUSTOMS_ALLOWED)('returns true for allowed role %s', (role) => {
    expect(canDownloadCustoms(role)).toBe(true)
  })

  it.each(CUSTOMS_DENIED)('returns false for denied role %s', (role) => {
    expect(canDownloadCustoms(role)).toBe(false)
  })
})

// ─── canDownloadDocument — CONFIRMATION_REQUEST type ──────────────────────────

describe('canDownloadDocument — CONFIRMATION_REQUEST type', () => {
  it('returns true for all roles (same policy as REQUEST_DOC)', () => {
    for (const role of Object.values(UserRole)) {
      expect(canDownloadDocument(role, 'CONFIRMATION_REQUEST')).toBe(true)
    }
  })
})

// ─── canDownloadSignedFxDoc ───────────────────────────────────────────────────

describe('canDownloadSignedFxDoc', () => {
  const SIGNED_FX_ALLOWED = [
    UserRole.DATA_ENTRY,
    UserRole.BANK_REVIEWER,
    UserRole.BANK_ADMIN,
    UserRole.SUPPORT_COMMITTEE,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ] as const

  const SIGNED_FX_DENIED = [
    UserRole.SWIFT_OFFICER,
  ] as const

  it.each(SIGNED_FX_ALLOWED)('returns true for allowed role %s', (role) => {
    expect(canDownloadSignedFxDoc(role)).toBe(true)
  })

  it.each(SIGNED_FX_DENIED)('returns false for denied role %s', (role) => {
    expect(canDownloadSignedFxDoc(role)).toBe(false)
  })
})

// ─── canViewConfirmationRequestPreview ───────────────────────────────────────

describe('canViewConfirmationRequestPreview', () => {
  const PREVIEW_ALLOWED = [
    UserRole.BANK_REVIEWER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ] as const

  const PREVIEW_DENIED = [
    UserRole.DATA_ENTRY,
    UserRole.BANK_ADMIN,
    UserRole.SWIFT_OFFICER,
    UserRole.SUPPORT_COMMITTEE,
    UserRole.EXECUTIVE_MEMBER,
  ] as const

  it.each(PREVIEW_ALLOWED)('returns true for allowed role %s', (role) => {
    expect(canViewConfirmationRequestPreview(role)).toBe(true)
  })

  it.each(PREVIEW_DENIED)('returns false for denied role %s', (role) => {
    expect(canViewConfirmationRequestPreview(role)).toBe(false)
  })
})

// ─── canUploadDocument ────────────────────────────────────────────────────────

describe('canUploadDocument — only DATA_ENTRY on DRAFT, DRAFT_REJECTED_INTERNAL, BANK_RETURNED, or SUPPORT_RETURNED', () => {
  it('returns true for DATA_ENTRY + DRAFT', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBe(true)
  })

  it('returns true for DATA_ENTRY + DRAFT_REJECTED_INTERNAL', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(true)
  })

  it('returns true for DATA_ENTRY + BANK_RETURNED', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.BANK_RETURNED)).toBe(true)
  })

  it('returns true for DATA_ENTRY + SUPPORT_RETURNED', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.SUPPORT_RETURNED)).toBe(true)
  })

  it('returns false for DATA_ENTRY on any other status', () => {
    const lockedStatuses = Object.values(RequestStatus).filter(
      s => s !== RequestStatus.DRAFT
        && s !== RequestStatus.DRAFT_REJECTED_INTERNAL
        && s !== RequestStatus.BANK_RETURNED
        && s !== RequestStatus.SUPPORT_RETURNED,
    )
    for (const status of lockedStatuses) {
      expect(canUploadDocument(UserRole.DATA_ENTRY, status)).toBe(false)
    }
  })

  it('returns false for non-DATA_ENTRY roles even on DRAFT', () => {
    const nonDataEntry = Object.values(UserRole).filter(r => r !== UserRole.DATA_ENTRY)
    for (const role of nonDataEntry) {
      expect(canUploadDocument(role, RequestStatus.DRAFT)).toBe(false)
    }
  })

  it('returns false for non-DATA_ENTRY roles on DRAFT_REJECTED_INTERNAL', () => {
    const nonDataEntry = Object.values(UserRole).filter(r => r !== UserRole.DATA_ENTRY)
    for (const role of nonDataEntry) {
      expect(canUploadDocument(role, RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(false)
    }
  })
})

// ─── isDocumentModificationLocked ────────────────────────────────────────────

describe('isDocumentModificationLocked', () => {
  it('returns false for DRAFT (editable)', () => {
    expect(isDocumentModificationLocked(RequestStatus.DRAFT)).toBe(false)
  })

  it('returns false for DRAFT_REJECTED_INTERNAL (editable)', () => {
    expect(isDocumentModificationLocked(RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(false)
  })

  it('returns false for BANK_RETURNED (editable)', () => {
    expect(isDocumentModificationLocked(RequestStatus.BANK_RETURNED)).toBe(false)
  })

  it('returns false for SUPPORT_RETURNED (editable)', () => {
    expect(isDocumentModificationLocked(RequestStatus.SUPPORT_RETURNED)).toBe(false)
  })

  it('returns true for all other statuses', () => {
    const lockedStatuses = Object.values(RequestStatus).filter(
      s => s !== RequestStatus.DRAFT
        && s !== RequestStatus.DRAFT_REJECTED_INTERNAL
        && s !== RequestStatus.BANK_RETURNED
        && s !== RequestStatus.SUPPORT_RETURNED,
    )
    for (const status of lockedStatuses) {
      expect(isDocumentModificationLocked(status)).toBe(true)
    }
  })

  it('covers all 22 canonical statuses — exactly 4 are unlocked', () => {
    const allStatuses = Object.values(RequestStatus)
    expect(allStatuses).toHaveLength(22)
    const locked = allStatuses.filter(s => isDocumentModificationLocked(s))
    expect(locked).toHaveLength(18)
  })
})
