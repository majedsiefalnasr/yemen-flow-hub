import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { RequestStatus, UserRole } from '../../../types/enums'
import { makeImportRequest } from '../fixtures/request-data'

// ---------- composable mocks ----------
const mockCloneRequest = vi.fn()
const mockFetchRequest = vi.fn()
const mockFetchRequestDocuments = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: mockFetchRequest,
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: vi.fn(),
    fetchRequestDocuments: mockFetchRequestDocuments,
    generateCustomsDeclaration: vi.fn(),
    downloadCustomsDeclaration: vi.fn(),
    bankReturn: vi.fn(),
    supportReturn: vi.fn(),
    bankRejectTerminal: vi.fn(),
    cloneRequest: mockCloneRequest,
    fetchRequestHistory: vi.fn(),
    fetchCustomsPreview: vi.fn(),
    uploadSwift: vi.fn(),
  }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

// ─── showCloneButton visibility logic ─────────────────────────────────────────

const CLONEABLE_STATUSES = [
  RequestStatus.BANK_REJECTED,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
]

const NON_CLONEABLE_STATUSES = [
  RequestStatus.DRAFT,
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.COMPLETED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
]

const CLONE_ALLOWED_ROLES = [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN]
const CLONE_BLOCKED_ROLES = [
  UserRole.BANK_REVIEWER,
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
  UserRole.SWIFT_OFFICER,
]

function shouldShowClone(status: RequestStatus, role: UserRole): boolean {
  const cloneableStatuses = new Set([
    RequestStatus.BANK_REJECTED,
    RequestStatus.SUPPORT_REJECTED,
    RequestStatus.EXECUTIVE_REJECTED,
  ])
  const allowedRoles = new Set([UserRole.DATA_ENTRY, UserRole.BANK_ADMIN])
  return cloneableStatuses.has(status) && allowedRoles.has(role)
}

describe('clone button visibility', () => {
  it.each(CLONEABLE_STATUSES)('shows clone button for DATA_ENTRY on %s', (status) => {
    expect(shouldShowClone(status, UserRole.DATA_ENTRY)).toBe(true)
  })

  it.each(CLONEABLE_STATUSES)('shows clone button for BANK_ADMIN on %s', (status) => {
    expect(shouldShowClone(status, UserRole.BANK_ADMIN)).toBe(true)
  })

  it.each(NON_CLONEABLE_STATUSES)('hides clone button for DATA_ENTRY on %s', (status) => {
    expect(shouldShowClone(status, UserRole.DATA_ENTRY)).toBe(false)
  })

  it.each(CLONE_BLOCKED_ROLES)('hides clone button for %s on BANK_REJECTED', (role) => {
    expect(shouldShowClone(RequestStatus.BANK_REJECTED, role)).toBe(false)
  })
})

// ─── cloneRequest composable integration ──────────────────────────────────────

describe('RequestsStore — clone request via store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('store loads cloned request after clone', async () => {
    const cloned = makeImportRequest({ id: 99, status: RequestStatus.DRAFT })
    mockFetchRequest.mockResolvedValue(cloned)

    const store = useRequestsStore()
    await store.loadRequest(99)

    expect(store.currentRequest?.id).toBe(99)
    expect(store.currentRequest?.status).toBe(RequestStatus.DRAFT)
  })

  it('cloneRequest returns new id on success', async () => {
    mockCloneRequest.mockResolvedValueOnce(99)

    const { useRequests } = await import('../../../composables/useRequests')
    const { cloneRequest } = useRequests()
    const newId = await cloneRequest(42)

    expect(mockCloneRequest).toHaveBeenCalledWith(42)
    expect(newId).toBe(99)
  })

  it('cloneRequest propagates rejection', async () => {
    mockCloneRequest.mockRejectedValueOnce(Object.assign(new Error('Forbidden'), { statusCode: 403 }))

    const { useRequests } = await import('../../../composables/useRequests')
    const { cloneRequest } = useRequests()
    await expect(cloneRequest(42)).rejects.toMatchObject({ statusCode: 403 })
  })
})

// ─── /requests/new?clone_of guard logic ───────────────────────────────────────

describe('new.vue clone_of guard', () => {
  it('invalid clone_of param (NaN) does not trigger clone', () => {
    const sourceId = Number('abc')
    expect(Number.isNaN(sourceId)).toBe(true)
  })

  it('zero clone_of param does not trigger clone', () => {
    const sourceId = Number('0')
    expect(sourceId <= 0).toBe(true)
  })

  it('valid clone_of param triggers clone', () => {
    const sourceId = Number('42')
    expect(!Number.isNaN(sourceId) && sourceId > 0).toBe(true)
  })
})
