import { UserRole } from '../types/enums'

export type RoleSurfaceKey =
  | 'nav.dashboard'
  | 'nav.requests'
  | 'nav.new_request'
  | 'nav.merchants'
  | 'nav.staff'
  | 'nav.reports'
  | 'nav.audit'
  | 'nav.notifications'
  | 'nav.admin.entities'
  | 'nav.admin.cby_staff'
  | 'nav.admin.workflow_docs'
  | 'nav.admin.roles'
  | 'nav.settings'
  | 'nav.external_fx_confirmation'
  | 'action.support_claim'
  | 'action.swift_upload'
  | 'action.fx_request_upload'
  | 'action.voting.cast'
  | 'action.voting.close_finalize'
  | 'action.external_fx_confirmation.complete'
  | 'action.external_fx_confirmation.download'

interface RoleSurfaceContract {
  allowed: RoleSurfaceKey[]
  forbidden: RoleSurfaceKey[]
}

const ALL_ROLES: UserRole[] = Object.values(UserRole)

export const ROLE_SURFACE_MATRIX: Record<UserRole, RoleSurfaceContract> = {
  [UserRole.DATA_ENTRY]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.new_request',
      'nav.notifications',
      'nav.settings',
    ],
    forbidden: [
      'action.support_claim',
      'action.swift_upload',
      'action.fx_request_upload',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
      'action.external_fx_confirmation.download',
      'nav.external_fx_confirmation',
      'nav.audit',
      'nav.reports',
      'nav.merchants',
      'nav.staff',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
    ],
  },
  [UserRole.BANK_REVIEWER]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.notifications',
      'nav.settings',
      'action.external_fx_confirmation.download',
    ],
    forbidden: [
      'nav.new_request',
      'nav.external_fx_confirmation',
      'nav.merchants',
      'nav.staff',
      'nav.audit',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'action.support_claim',
      'action.swift_upload',
      'action.fx_request_upload',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
    ],
  },
  [UserRole.BANK_ADMIN]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.merchants',
      'nav.staff',
      'nav.reports',
      'nav.notifications',
      'nav.settings',
    ],
    forbidden: [
      'nav.new_request',
      'nav.external_fx_confirmation',
      'nav.audit',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'action.support_claim',
      'action.swift_upload',
      'action.fx_request_upload',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
      'action.external_fx_confirmation.download',
    ],
  },
  [UserRole.SWIFT_OFFICER]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.notifications',
      'nav.settings',
      'action.swift_upload',
      'action.fx_request_upload',
    ],
    forbidden: [
      'nav.new_request',
      'nav.external_fx_confirmation',
      'nav.merchants',
      'nav.staff',
      'nav.reports',
      'nav.audit',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'action.support_claim',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
      'action.external_fx_confirmation.download',
    ],
  },
  [UserRole.SUPPORT_COMMITTEE]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.notifications',
      'nav.settings',
      'action.support_claim',
    ],
    forbidden: [
      'nav.new_request',
      'nav.external_fx_confirmation',
      'nav.merchants',
      'nav.staff',
      'nav.audit',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'action.swift_upload',
      'action.fx_request_upload',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
      'action.external_fx_confirmation.download',
    ],
  },
  [UserRole.EXECUTIVE_MEMBER]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.reports',
      'nav.notifications',
      'nav.settings',
      'action.voting.cast',
      'action.external_fx_confirmation.download',
    ],
    forbidden: [
      'nav.new_request',
      'nav.external_fx_confirmation',
      'nav.merchants',
      'nav.staff',
      'nav.audit',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'action.support_claim',
      'action.swift_upload',
      'action.fx_request_upload',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
    ],
  },
  [UserRole.COMMITTEE_DIRECTOR]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.external_fx_confirmation',
      'nav.reports',
      'nav.audit',
      'nav.notifications',
      'nav.settings',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.download',
      'action.external_fx_confirmation.complete',
    ],
    forbidden: [
      'nav.new_request',
      'nav.merchants',
      'nav.staff',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'action.support_claim',
      'action.swift_upload',
      'action.fx_request_upload',
    ],
  },
  [UserRole.CBY_ADMIN]: {
    allowed: [
      'nav.dashboard',
      'nav.requests',
      'nav.merchants',
      'nav.reports',
      'nav.audit',
      'nav.notifications',
      'nav.admin.entities',
      'nav.admin.cby_staff',
      'nav.admin.workflow_docs',
      'nav.admin.roles',
      'nav.settings',
      'action.external_fx_confirmation.download',
    ],
    forbidden: [
      'nav.new_request',
      'nav.staff',
      'nav.external_fx_confirmation',
      'action.support_claim',
      'action.swift_upload',
      'action.fx_request_upload',
      'action.voting.cast',
      'action.voting.close_finalize',
      'action.external_fx_confirmation.complete',
    ],
  },
}

export const NAV_SURFACE_ROUTES: Record<
  | 'nav.dashboard'
  | 'nav.requests'
  | 'nav.new_request'
  | 'nav.merchants'
  | 'nav.staff'
  | 'nav.reports'
  | 'nav.audit'
  | 'nav.notifications'
  | 'nav.admin.entities'
  | 'nav.admin.cby_staff'
  | 'nav.admin.workflow_docs'
  | 'nav.admin.roles'
  | 'nav.settings'
  | 'nav.external_fx_confirmation',
  string
> = {
  'nav.dashboard': '/dashboard',
  'nav.requests': '/requests',
  'nav.new_request': '/requests/new',
  'nav.merchants': '/merchants',
  'nav.staff': '/staff',
  'nav.reports': '/reports',
  'nav.audit': '/audit',
  'nav.notifications': '/notifications',
  'nav.admin.entities': '/admin/entities',
  'nav.admin.cby_staff': '/admin/cby-staff',
  'nav.admin.workflow_docs': '/admin/workflow-docs',
  'nav.admin.roles': '/admin/roles',
  'nav.settings': '/settings',
  'nav.external_fx_confirmation': '/customs',
}

export function rolesForSurface(surface: RoleSurfaceKey): UserRole[] {
  return ALL_ROLES.filter(role => ROLE_SURFACE_MATRIX[role].allowed.includes(surface))
}

export function roleHasSurface(role: UserRole, surface: RoleSurfaceKey): boolean {
  return ROLE_SURFACE_MATRIX[role].allowed.includes(surface)
}
