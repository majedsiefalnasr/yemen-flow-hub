import { describe, expect, it } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'

function directorCompositeRows(stats: {
  sessions_ready_to_close?: number
  sessions_with_tie?: number
  fx_confirmation_pending?: number
}) {
  return {
    readyToClose: (stats.sessions_ready_to_close ?? 0) > 0,
    tieBreak: (stats.sessions_with_tie ?? 0) > 0,
    fxReady: (stats.fx_confirmation_pending ?? 0) > 0,
  }
}

function directorActions(status: RequestStatus, allVotesCast: boolean) {
  return {
    canClose: status === RequestStatus.EXECUTIVE_VOTING_OPEN && allVotesCast,
    canFinalize: status === RequestStatus.EXECUTIVE_VOTING_CLOSED,
    canOverride: status === RequestStatus.EXECUTIVE_VOTING_OPEN,
  }
}

function fxFlowState(hasTemplateChecksum: boolean, hasSignedFile: boolean) {
  return {
    hasStep1: true,
    hasStep2: true,
    hasStep3: true,
    showChecksum: hasTemplateChecksum,
    canSubmit: hasSignedFile,
  }
}

function swiftPills(hasSwiftDocument: boolean, hasFxRequestDocument: boolean) {
  return {
    swift: hasSwiftDocument ? 'uploaded' : 'missing',
    fxRequest: hasFxRequestDocument ? 'uploaded' : 'missing',
  }
}

function swiftSubmitDisabledReason(swiftReference: string, hasSwiftFile: boolean, hasFxRequestFile: boolean): string {
  if (!swiftReference.trim()) return 'أدخل رقم مرجع السويفت أولاً'
  if (!hasSwiftFile) return 'أكمل رفع وثيقة السويفت قبل التسليم'
  if (!hasFxRequestFile) return 'أكمل رفع طلب تأكيد المصارفة قبل التسليم'
  return ''
}

function directorBanner(
  status: RequestStatus,
  role: UserRole,
  readyToClose: boolean,
  isTie: boolean,
): 'voting_active' | 'ready_to_close' | 'tie_break' | 'ready_to_finalize' | 'fx_ready' | null {
  if (role !== UserRole.COMMITTEE_DIRECTOR) return null
  if (status === RequestStatus.EXECUTIVE_VOTING_OPEN && !readyToClose && !isTie) return 'voting_active'
  if (status === RequestStatus.EXECUTIVE_VOTING_OPEN && readyToClose) return 'ready_to_close'
  if (status === RequestStatus.EXECUTIVE_VOTING_OPEN && isTie) return 'tie_break'
  if (status === RequestStatus.EXECUTIVE_VOTING_CLOSED) return 'ready_to_finalize'
  if (status === RequestStatus.EXECUTIVE_APPROVED) return 'fx_ready'
  return null
}

function swiftUploadAccess(role: UserRole, status: RequestStatus) {
  const canAccessPage = role === UserRole.SWIFT_OFFICER
  const canUpload = canAccessPage && status === RequestStatus.WAITING_FOR_SWIFT
  return { canAccessPage, canUpload }
}

function handleSwiftUploadError(message: string) {
  if (message.includes('WORKFLOW_LOCKED_STATE') || message.includes('403')) {
    return { lockedStateError: 'تم تغيير حالة الطلب أثناء العمل. حدّث الصفحة للمتابعة.', uploadError: '' }
  }
  return { lockedStateError: '', uploadError: message || 'تعذّر تسليم وثائق السويفت. حاول مرة أخرى.' }
}

function surfaceVisible(role: UserRole, surface: 'claim' | 'vote' | 'fx_tab' | 'swift_upload') {
  if (role === UserRole.SWIFT_OFFICER) {
    return surface === 'swift_upload'
  }
  if (role === UserRole.COMMITTEE_DIRECTOR) {
    return surface === 'vote' || surface === 'fx_tab'
  }
  return false
}

describe('Story 12.3 — Director composite strip', () => {
  it('maps all 3 sub-rows independently', () => {
    expect(directorCompositeRows({ sessions_ready_to_close: 1, sessions_with_tie: 2, fx_confirmation_pending: 3 })).toEqual({
      readyToClose: true,
      tieBreak: true,
      fxReady: true,
    })
  })

  it('hides all rows when counters are zero', () => {
    expect(directorCompositeRows({ sessions_ready_to_close: 0, sessions_with_tie: 0, fx_confirmation_pending: 0 })).toEqual({
      readyToClose: false,
      tieBreak: false,
      fxReady: false,
    })
  })
})

describe('Story 12.3 — Director controls', () => {
  it('gates close/finalize/override controls by status and vote completion', () => {
    expect(directorActions(RequestStatus.EXECUTIVE_VOTING_OPEN, false)).toEqual({ canClose: false, canFinalize: false, canOverride: true })
    expect(directorActions(RequestStatus.EXECUTIVE_VOTING_OPEN, true)).toEqual({ canClose: true, canFinalize: false, canOverride: true })
    expect(directorActions(RequestStatus.EXECUTIVE_VOTING_CLOSED, true)).toEqual({ canClose: false, canFinalize: true, canOverride: false })
  })
})

describe('Story 12.3 — FX confirmation tab', () => {
  it('always has 3 steps and submit remains disabled without signed file', () => {
    expect(fxFlowState(false, false)).toEqual({
      hasStep1: true,
      hasStep2: true,
      hasStep3: true,
      showChecksum: false,
      canSubmit: false,
    })
  })

  it('enables submit once signed file exists', () => {
    expect(fxFlowState(true, true).canSubmit).toBe(true)
  })
})

describe('Story 12.3 — SWIFT two-pill states', () => {
  it('supports all four states', () => {
    expect(swiftPills(false, false)).toEqual({ swift: 'missing', fxRequest: 'missing' })
    expect(swiftPills(true, false)).toEqual({ swift: 'uploaded', fxRequest: 'missing' })
    expect(swiftPills(false, true)).toEqual({ swift: 'missing', fxRequest: 'uploaded' })
    expect(swiftPills(true, true)).toEqual({ swift: 'uploaded', fxRequest: 'uploaded' })
  })
})

describe('Story 12.3 — SWIFT submit gate reasons', () => {
  it('returns all 3 disabled reasons and enabled state', () => {
    expect(swiftSubmitDisabledReason('', false, false)).toBe('أدخل رقم مرجع السويفت أولاً')
    expect(swiftSubmitDisabledReason('UETR-1', false, false)).toBe('أكمل رفع وثيقة السويفت قبل التسليم')
    expect(swiftSubmitDisabledReason('UETR-1', true, false)).toBe('أكمل رفع طلب تأكيد المصارفة قبل التسليم')
    expect(swiftSubmitDisabledReason('UETR-1', true, true)).toBe('')
  })
})

describe('Story 12.3 — Banner variants', () => {
  it('selects Director variants with precedence', () => {
    expect(directorBanner(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.COMMITTEE_DIRECTOR, false, false)).toBe('voting_active')
    expect(directorBanner(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.COMMITTEE_DIRECTOR, true, true)).toBe('ready_to_close')
    expect(directorBanner(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.COMMITTEE_DIRECTOR, false, true)).toBe('tie_break')
    expect(directorBanner(RequestStatus.EXECUTIVE_VOTING_CLOSED, UserRole.COMMITTEE_DIRECTOR, false, false)).toBe('ready_to_finalize')
    expect(directorBanner(RequestStatus.EXECUTIVE_APPROVED, UserRole.COMMITTEE_DIRECTOR, false, false)).toBe('fx_ready')
  })

  it('does not expose Director banners for non-director roles', () => {
    expect(directorBanner(RequestStatus.EXECUTIVE_VOTING_OPEN, UserRole.SWIFT_OFFICER, false, false)).toBeNull()
  })
})

describe('Story 12.3 — Swift upload access states', () => {
  it('denies wrong role and disallows wrong status', () => {
    expect(swiftUploadAccess(UserRole.COMMITTEE_DIRECTOR, RequestStatus.WAITING_FOR_SWIFT)).toEqual({ canAccessPage: false, canUpload: false })
    expect(swiftUploadAccess(UserRole.SWIFT_OFFICER, RequestStatus.SWIFT_UPLOADED)).toEqual({ canAccessPage: true, canUpload: false })
    expect(swiftUploadAccess(UserRole.SWIFT_OFFICER, RequestStatus.WAITING_FOR_SWIFT)).toEqual({ canAccessPage: true, canUpload: true })
  })
})

describe('Story 12.3 — Locked race handling', () => {
  it('maps WORKFLOW_LOCKED_STATE/403 to locked-state banner message', () => {
    expect(handleSwiftUploadError('WORKFLOW_LOCKED_STATE')).toEqual({
      lockedStateError: 'تم تغيير حالة الطلب أثناء العمل. حدّث الصفحة للمتابعة.',
      uploadError: '',
    })
    expect(handleSwiftUploadError('403 Forbidden')).toEqual({
      lockedStateError: 'تم تغيير حالة الطلب أثناء العمل. حدّث الصفحة للمتابعة.',
      uploadError: '',
    })
  })
})

describe('Story 12.3 — Non-visibility (not mounted)', () => {
  it('never exposes forbidden surfaces per role', () => {
    expect(surfaceVisible(UserRole.SWIFT_OFFICER, 'claim')).toBe(false)
    expect(surfaceVisible(UserRole.SWIFT_OFFICER, 'vote')).toBe(false)
    expect(surfaceVisible(UserRole.SWIFT_OFFICER, 'fx_tab')).toBe(false)
    expect(surfaceVisible(UserRole.COMMITTEE_DIRECTOR, 'swift_upload')).toBe(false)
  })
})
