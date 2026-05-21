import { UserRole, RequestStatus } from '../types/enums'

/**
 * Returns true if the given role is permitted to download a document of the
 * specified type. The backend always enforces the real policy; this function
 * is used purely to hide the download button in the UI for roles that would
 * receive a 403, improving UX without removing the security gate.
 *
 * Permission matrix (mirrors RequestDocumentPolicy on the backend):
 *   REQUEST_DOC  → all 8 roles (backend enforces bank scope)
 *   SWIFT        → BANK_REVIEWER, BANK_ADMIN, SWIFT_OFFICER, EXECUTIVE_MEMBER,
 *                  COMMITTEE_DIRECTOR, CBY_ADMIN only
 */
export function canDownloadDocument(role: UserRole, docType: string | null): boolean {
  if (docType === 'SWIFT') {
    return (
      role === UserRole.BANK_REVIEWER
      || role === UserRole.BANK_ADMIN
      || role === UserRole.SWIFT_OFFICER
      || role === UserRole.EXECUTIVE_MEMBER
      || role === UserRole.COMMITTEE_DIRECTOR
      || role === UserRole.CBY_ADMIN
    )
  }
  // REQUEST_DOC or unknown type — all roles may attempt (bank scope enforced by backend)
  return true
}

/**
 * Returns true if the given role is permitted to download a customs declaration PDF.
 * Mirrors CustomsDeclarationPolicy on the backend.
 */
export function canDownloadCustoms(role: UserRole): boolean {
  return (
    role === UserRole.BANK_REVIEWER
    || role === UserRole.COMMITTEE_DIRECTOR
    || role === UserRole.CBY_ADMIN
  )
}

/**
 * Returns true if the given role may upload new documents for the given
 * request status. Only DATA_ENTRY users may upload, and only while the request
 * is in a pre-submission editable state.
 */
export function canUploadDocument(role: UserRole, status: RequestStatus): boolean {
  return (
    role === UserRole.DATA_ENTRY
    && (status === RequestStatus.DRAFT
      || status === RequestStatus.DRAFT_REJECTED_INTERNAL
      || status === RequestStatus.BANK_RETURNED
      || status === RequestStatus.SUPPORT_RETURNED)
  )
}

/**
 * Returns true when the request is in a status where documents can no longer
 * be modified (i.e. any status other than DRAFT, DRAFT_REJECTED_INTERNAL, BANK_RETURNED, and SUPPORT_RETURNED).
 * Used to show the "مقفل — لا يمكن تعديل المستندات" note to DATA_ENTRY users.
 */
export function isDocumentModificationLocked(status: RequestStatus): boolean {
  return (
    status !== RequestStatus.DRAFT
    && status !== RequestStatus.DRAFT_REJECTED_INTERNAL
    && status !== RequestStatus.BANK_RETURNED
    && status !== RequestStatus.SUPPORT_RETURNED
  )
}
