export const WORKFLOWS_LIST_PATH = '/workflows'
export const WORKFLOWS_NEW_PATH = '/workflows/new'

export function workflowInstancePath(id: number | string, suffix = ''): string {
  return `/workflows/instances/${id}${suffix}`
}

/**
 * Maps legacy `/requests*` routes to canonical workflow navigation.
 */
export function toWorkflowRoute(legacyPath: string): string {
  if (legacyPath === '/workflows/instances/new') {
    return WORKFLOWS_NEW_PATH
  }

  if (legacyPath === '/workflows' || legacyPath.startsWith('/workflows?')) {
    return legacyPath.replace('/workflows', WORKFLOWS_LIST_PATH)
  }

  const match = legacyPath.match(/^\/requests\/(\d+)(\/.*)?$/)
  if (match) {
    return workflowInstancePath(match[1], match[2] ?? '')
  }

  return legacyPath
}
