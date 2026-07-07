import { describe, expect, it } from 'vitest'
import { resolveNotificationTargetUrl } from '../../../utils/notificationNavigation'

describe('resolveNotificationTargetUrl', () => {
  it('prefers action_url from engine notifications', () => {
    expect(
      resolveNotificationTargetUrl({
        action_url: '/workflows/instances/99',
        entity_type: 'engine_request',
        entity_id: 99,
      }),
    ).toBe('/workflows/instances/99')
  })

  it('maps legacy request_id payloads to workflow instance routes', () => {
    expect(
      resolveNotificationTargetUrl({
        type: 'claim_released',
        request_id: 42,
      }),
    ).toBe('/workflows/instances/42')
  })

  it('maps engine_request entity_id when action_url is absent', () => {
    expect(
      resolveNotificationTargetUrl({
        entity_type: 'engine_request',
        entity_id: 7,
      }),
    ).toBe('/workflows/instances/7')
  })

  it('returns null when no navigable target exists', () => {
    expect(resolveNotificationTargetUrl({ type: 'workflow.published' })).toBeNull()
  })
})
