import { RequestStatus } from '../types/enums'

const REQUEST_PROGRESS: Record<RequestStatus, number> = {
  [RequestStatus.DRAFT]: 10,
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 20,
  [RequestStatus.SUBMITTED]: 25,
  [RequestStatus.BANK_REVIEW]: 35,
  [RequestStatus.BANK_APPROVED]: 50,
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 60,
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 70,
  [RequestStatus.SUPPORT_APPROVED]: 75,
  [RequestStatus.SUPPORT_REJECTED]: 100,
  [RequestStatus.WAITING_FOR_SWIFT]: 80,
  [RequestStatus.SWIFT_UPLOADED]: 85,
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 90,
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 95,
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 96,
  [RequestStatus.EXECUTIVE_APPROVED]: 97,
  [RequestStatus.EXECUTIVE_REJECTED]: 100,
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 100,
  [RequestStatus.COMPLETED]: 100,
  [RequestStatus.BANK_RETURNED]: 20,
  [RequestStatus.SUPPORT_RETURNED]: 60,
  [RequestStatus.BANK_REJECTED]: 100,
}

export function getRequestProgress(status: RequestStatus): number {
  return REQUEST_PROGRESS[status]
}
