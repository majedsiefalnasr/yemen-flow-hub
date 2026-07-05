// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { StagePermission, WorkflowStage, WorkflowVersion } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const StagePermissionEditor = (
  await import('../../../components/workflow/StagePermissionEditor.vue')
).default

const ORGS = [{ id: 1, code: 'CBY', name: 'البنك المركزي', is_active: true }]
const ROLES = [{ id: 2, code: 'REVIEWER', name: 'مراجع', organization_id: 1 }]
const TEAMS = [{ id: 3, code: 'REVIEW_TEAM', name: 'فريق المراجعة', organization_id: 1 }]

function makePermission(overrides: Partial<StagePermission> = {}): StagePermission {
  return {
    id: 1,
    stage_id: 5,
    organization_id: 1,
    team_id: 3,
    role_id: 2,
    user_id: null,
    access_level: 'EXECUTE',
    display_label: 'مراجعو البنك',
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

function makeStage(): WorkflowStage {
  return {
    id: 5,
    workflow_version_id: 7,
    code: 'intake',
    name: 'الاستلام',
    description: null,
    sort_order: 1,
    is_initial: true,
    is_final: false,
    requires_claim: false,
    sla_duration_minutes: null,
    status: 'ACTIVE',
    created_at: null,
    updated_at: null,
    version: 1,
  }
}

function makeVersion(state: 'DRAFT' | 'PUBLISHED' = 'DRAFT'): WorkflowVersion {
  return {
    id: 7,
    workflow_definition_id: 1,
    version_number: 1,
    state,
    is_editable: state === 'DRAFT',
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 1,
  }
}

async function mountEditor(
  capabilities: Array<'VIEW' | 'MANAGE'>,
  state: 'DRAFT' | 'PUBLISHED' = 'DRAFT',
  permissions = [makePermission()],
) {
  mockGet.mockImplementation((url: string) => {
    if (url.includes('/permissions')) return Promise.resolve({ data: permissions })
    if (url.includes('workflow-stages')) return Promise.resolve({ data: permissions })
    if (url.includes('organizations')) return Promise.resolve({ data: ORGS, meta: {} })
    if (url.includes('roles')) return Promise.resolve({ data: ROLES })
    if (url.includes('teams')) return Promise.resolve({ data: TEAMS })
    return Promise.resolve({ data: [] })
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(StagePermissionEditor, {
    props: { stage: makeStage(), version: makeVersion(state) },
    global: {
      plugins: [pinia],
      stubs: {
        Teleport: true,
        NuxtLink: true,
        // Tooltip needs a TooltipProvider ancestor (supplied at app root); render
        // the trigger slot transparently in isolated mounts.
        Tooltip: { template: '<div><slot /></div>' },
        TooltipTrigger: { template: '<div><slot /></div>' },
        TooltipContent: { template: '<div><slot /></div>' },
      },
    },
  })
  await flushPromises()

  return wrapper
}

function buttonByLabel(wrapper: VueWrapper, label: string) {
  return wrapper.findAll('button').find((b) => b.attributes('aria-label') === label)
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((b) => b.text().trim().includes(text))
}

describe('StagePermissionEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders permission rows with label and access level', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).toContain('مراجعو البنك')
    expect(wrapper.text()).toContain('تنفيذ')
  })

  it('shows add affordance for MANAGE users on a DRAFT version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByText(wrapper, 'إضافة صلاحية')).toBeDefined()
  })

  it('hides mutation affordances on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByText(wrapper, 'إضافة صلاحية')).toBeUndefined()
    expect(buttonByLabel(wrapper, 'حذف الصلاحية')).toBeUndefined()
    expect(wrapper.text()).toContain('مقفلة')
  })

  it('hides add for VIEW-only users', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(buttonByText(wrapper, 'إضافة صلاحية')).toBeUndefined()
  })

  it('shows an empty state when there are no permissions', async () => {
    const wrapper = await mountEditor(['VIEW'], 'DRAFT', [])

    expect(wrapper.text()).toContain('لا توجد صلاحيات')
  })

  it('shows the team label in the table', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).toContain('فريق المراجعة')
  })

  it('shows an edit affordance for MANAGE users on a DRAFT version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByLabel(wrapper, 'تعديل الصلاحية')).toBeDefined()
  })

  it('hides edit affordance on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByLabel(wrapper, 'تعديل الصلاحية')).toBeUndefined()
  })

  it('preserves team and role selections when opening the edit dialog', async () => {
    const permission = makePermission({ organization_id: 1, team_id: 3, role_id: 2 })
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'DRAFT', [permission])

    const editButton = buttonByLabel(wrapper, 'تعديل الصلاحية')
    expect(editButton).toBeDefined()
    await editButton?.trigger('click')
    await flushPromises()
    await nextTick()

    expect(wrapper.vm.teamId).toBe(String(permission.team_id))
    expect(wrapper.vm.roleId).toBe(String(permission.role_id))
  })
})
